<?php

namespace App\Billing\Payments;

use App\Billing\Fulfilment;
use App\Billing\Subscriptions;
use App\Billing\Wallet;
use App\Models\PaymentEvent;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns verified provider events into purchases and subscription changes.
 *
 * Idempotency, in three layers:
 *  1. Each accepted event is recorded in payment_events under a unique (provider, event id). The record and
 *     the change it causes are written in one transaction, so a redelivery finds the record and does nothing.
 *     Two deliveries racing each other are settled by the unique index: the loser rolls back.
 *  2. Each renewal is a purchase with a unique provider reference, so the same payment cannot create two.
 *  3. Fulfilment only moves a pending purchase to paid, under a row lock, so credit is granted once.
 *
 * Amounts are checked against the purchase before anything is granted. A mismatch fails the purchase.
 */
class PaymentEvents
{
    public function __construct(
        protected FlutterwaveGateway $flutterwave,
        protected PaddleGateway $paddle,
        protected Fulfilment $fulfilment,
        protected Subscriptions $subscriptions,
        protected Wallet $wallet,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function flutterwave(array $payload, string $rawBody): string
    {
        $event = (string) ($payload['event'] ?? '');
        $data = (array) ($payload['data'] ?? []);

        if ($event === 'charge.completed') {
            return $this->flutterwaveCharge($data);
        }

        if ($event === 'subscription.cancelled') {
            return $this->guarded('flutterwave', 'cancel:'.hash('sha256', $rawBody), 'subscription.cancelled', function () use ($data) {
                $subscription = $this->flutterwaveSubscription($data);

                if ($subscription === null) {
                    return 'unmatched';
                }

                // Flutterwave has stopped billing. Access continues to the end of the paid period.
                $this->subscriptions->cancel($subscription);

                return 'cancel_scheduled';
            });
        }

        return $this->ignore('flutterwave', 'ignored:'.hash('sha256', $rawBody), $event);
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    public function paddle(array $envelope): string
    {
        $eventId = (string) ($envelope['event_id'] ?? '');
        $type = (string) ($envelope['event_type'] ?? '');
        $data = (array) ($envelope['data'] ?? []);

        if ($eventId === '') {
            return 'rejected';
        }

        return match ($type) {
            'transaction.completed' => $this->guarded('paddle', $eventId, $type, fn () => $this->paddleCompleted($data)),
            'transaction.payment_failed' => $this->guarded('paddle', $eventId, $type, fn () => $this->paddleFailed($data)),
            'subscription.past_due' => $this->guarded('paddle', $eventId, $type, fn () => $this->paddlePastDue($data)),
            'subscription.canceled' => $this->guarded('paddle', $eventId, $type, fn () => $this->paddleCanceled($data)),
            default => $this->ignore('paddle', $eventId, $type),
        };
    }

    /** @param  array<string, mixed>  $data */
    private function flutterwaveCharge(array $data): string
    {
        $transactionId = (string) ($data['id'] ?? '');

        if ($transactionId === '') {
            return 'rejected';
        }

        // The webhook says a charge happened. The verify call says what it really was.
        $verified = $this->flutterwave->verifyTransaction($transactionId);

        if (($verified['status'] ?? '') !== 'successful') {
            return $this->ignore('flutterwave', 'charge:'.$transactionId, 'charge.'.($verified['status'] ?? 'unknown'));
        }

        return $this->guarded('flutterwave', 'charge:'.$transactionId, 'charge.completed', function () use ($verified, $transactionId, $data) {
            $purchase = Purchase::query()->where('provider_ref', (string) ($verified['tx_ref'] ?? ''))->first();

            if ($purchase !== null) {
                return $this->settle($purchase, (string) $verified['amount'], (string) $verified['currency'], 'flutterwave_decimal');
            }

            // No purchase for this reference, so it is a renewal of a Flutterwave plan. The plan id may sit in
            // the webhook or the verify response, in either a plain or an object form.
            $planId = $this->planIdFrom($data) ?: $this->planIdFrom($verified);
            $plan = $this->flutterwavePlan($planId);
            $user = User::query()->where('email', (string) ($verified['customer']['email'] ?? ''))->first();

            if ($plan === null || $user === null) {
                return 'unmatched';
            }

            $interval = $planId === $plan->flutterwave_yearly_plan_id ? Subscription::INTERVAL_YEARLY : Subscription::INTERVAL_MONTHLY;
            $renewal = $this->renewal($user, $plan, $interval, 'flutterwave', 'flw:'.$transactionId);

            return $this->settle($renewal, (string) $verified['amount'], (string) $verified['currency'], 'flutterwave_decimal');
        });
    }

    /** @param  array<string, mixed>  $data */
    private function planIdFrom(array $data): int
    {
        $plan = $data['payment_plan'] ?? $data['plan'] ?? null;

        return (int) (is_array($plan) ? ($plan['id'] ?? 0) : ($plan ?? 0));
    }

    /** @param  array<string, mixed>  $data */
    private function paddleCompleted(array $data): string
    {
        if (($data['status'] ?? '') !== 'completed') {
            return 'ignored';
        }

        $transactionId = (string) ($data['id'] ?? '');
        $totals = (array) ($data['details']['totals'] ?? []);
        $amount = (string) ($totals['subtotal'] ?? $totals['total'] ?? '');
        $currency = (string) ($data['currency_code'] ?? '');

        $purchaseId = $data['custom_data']['purchase_id'] ?? null;
        $purchase = is_string($purchaseId)
            ? Purchase::query()->whereKey($purchaseId)->first()
            : Purchase::query()->where('provider_ref', $transactionId)->first();

        if ($purchase !== null) {
            $purchase->forceFill([
                'provider_subscription_id' => $data['subscription_id'] ?? $purchase->provider_subscription_id,
            ])->save();

            return $this->settle($purchase, $amount, $currency, 'paddle_minor');
        }

        // A recurring charge on an existing Paddle subscription.
        $subscriptionId = (string) ($data['subscription_id'] ?? '');
        $subscription = $subscriptionId !== '' ? Subscription::query()->where('provider_ref', $subscriptionId)->first() : null;
        $priceId = (string) ($data['items'][0]['price']['id'] ?? $data['items'][0]['price_id'] ?? '');
        $plan = $this->paddlePlan($priceId);

        if ($subscription === null || $plan === null) {
            return 'unmatched';
        }

        $interval = $priceId === $plan->paddle_yearly_price_id ? Subscription::INTERVAL_YEARLY : Subscription::INTERVAL_MONTHLY;
        $renewal = $this->renewal($subscription->user, $plan, $interval, 'paddle', $transactionId);

        return $this->settle($renewal, $amount, $currency, 'paddle_minor');
    }

    /** @param  array<string, mixed>  $data */
    private function paddleFailed(array $data): string
    {
        $purchase = $this->paddlePurchase($data);

        if ($purchase !== null) {
            $this->fulfilment->markFailed($purchase);
        }

        return $purchase !== null ? 'failed' : 'unmatched';
    }

    /** @param  array<string, mixed>  $data */
    private function paddlePastDue(array $data): string
    {
        $subscription = Subscription::query()->where('provider_ref', (string) ($data['id'] ?? ''))->first();

        if ($subscription === null) {
            return 'unmatched';
        }

        // The payment failed. No credit is granted until a renewal is paid.
        $subscription->forceFill(['status' => Subscription::STATUS_PAST_DUE])->save();

        return 'past_due';
    }

    /** @param  array<string, mixed>  $data */
    private function paddleCanceled(array $data): string
    {
        $subscription = Subscription::query()->where('provider_ref', (string) ($data['id'] ?? ''))->first();

        if ($subscription === null) {
            return 'unmatched';
        }

        $this->wallet->expireSubscriptionCredit($subscription->user, 'Plan cancelled');
        $subscription->forceFill(['status' => Subscription::STATUS_ENDED, 'ended_at' => now()])->save();

        return 'ended';
    }

    /**
     * Move a pending purchase to paid, if the amount and currency match what was sold.
     */
    private function settle(Purchase $purchase, string $amount, string $currency, string $format): string
    {
        if ($purchase->status === Purchase::STATUS_PAID) {
            return 'duplicate';
        }

        if ($purchase->status !== Purchase::STATUS_PENDING) {
            return 'ignored';
        }

        $paidCents = $format === 'paddle_minor' ? (int) $amount : (int) round((float) $amount * 100);
        $expectedCents = intdiv($purchase->amount_micros, 10_000);

        if (strtoupper($currency) !== 'USD' || $paidCents !== $expectedCents) {
            Log::warning('Payment amount mismatch', ['purchase' => $purchase->id, 'paid_cents' => $paidCents, 'expected_cents' => $expectedCents, 'currency' => $currency]);
            $this->fulfilment->markFailed($purchase);

            return 'amount_mismatch';
        }

        $this->fulfilment->markPaid($purchase);

        return 'fulfilled';
    }

    /** A pending renewal purchase for a plan the user already holds. Its reference makes it unique. */
    private function renewal(User $user, Plan $plan, string $interval, string $provider, string $reference): Purchase
    {
        $existing = Purchase::query()->where('provider_ref', $reference)->first();
        if ($existing !== null) {
            return $existing;
        }

        return Purchase::create([
            'user_id' => $user->id,
            'kind' => Purchase::KIND_RENEWAL,
            'item_key' => $plan->key,
            'interval' => $interval,
            'amount_micros' => $interval === Subscription::INTERVAL_YEARLY ? $plan->yearly_micros : $plan->monthly_micros,
            'credits_micros' => $interval === Subscription::INTERVAL_YEARLY ? $plan->monthly_credit_micros * 12 : $plan->monthly_credit_micros,
            'status' => Purchase::STATUS_PENDING,
            'provider' => $provider,
            'provider_ref' => $reference,
        ]);
    }

    private function paddlePurchase(array $data): ?Purchase
    {
        $purchaseId = $data['custom_data']['purchase_id'] ?? null;

        if (is_string($purchaseId)) {
            return Purchase::query()->whereKey($purchaseId)->first();
        }

        return Purchase::query()->where('provider_ref', (string) ($data['id'] ?? ''))->first();
    }

    private function flutterwavePlan(int $planId): ?Plan
    {
        if ($planId === 0) {
            return null;
        }

        return Plan::query()->where('flutterwave_monthly_plan_id', $planId)
            ->orWhere('flutterwave_yearly_plan_id', $planId)
            ->first();
    }

    private function paddlePlan(string $priceId): ?Plan
    {
        if ($priceId === '') {
            return null;
        }

        return Plan::query()->where('paddle_monthly_price_id', $priceId)
            ->orWhere('paddle_yearly_price_id', $priceId)
            ->first();
    }

    /** @param  array<string, mixed>  $data */
    private function flutterwaveSubscription(array $data): ?Subscription
    {
        $email = (string) ($data['customer']['email'] ?? '');
        $user = User::query()->where('email', $email)->first();
        $plan = $this->flutterwavePlan((int) ($data['plan']['id'] ?? 0));

        if ($user === null || $plan === null) {
            return null;
        }

        return Subscription::query()
            ->where('user_id', $user->id)
            ->where('plan_key', $plan->key)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
            ->latest('created_at')
            ->first();
    }

    /**
     * Run the handler once per provider event. A repeat returns 'duplicate' and changes nothing.
     *
     * @param  callable(): string  $handler
     */
    private function guarded(string $provider, string $eventId, string $type, callable $handler): string
    {
        try {
            return DB::transaction(function () use ($provider, $eventId, $type, $handler) {
                PaymentEvent::query()->create([
                    'provider' => $provider,
                    'event_id' => mb_substr($eventId, 0, 191),
                    'type' => $type,
                    'outcome' => 'processing',
                ]);

                $outcome = $handler();

                PaymentEvent::query()->where('provider', $provider)->where('event_id', mb_substr($eventId, 0, 191))
                    ->update(['outcome' => $outcome]);

                return $outcome;
            });
        } catch (QueryException $e) {
            // The unique index caught a repeat, most likely a concurrent delivery of the same event.
            if ((string) $e->getCode() === '23505' || str_contains($e->getMessage(), 'UNIQUE')) {
                return 'duplicate';
            }

            throw $e;
        } catch (DomainException $e) {
            Log::warning('Payment event refused', ['provider' => $provider, 'type' => $type, 'error' => $e->getMessage()]);

            return 'refused';
        }
    }

    private function ignore(string $provider, string $eventId, string $type): string
    {
        return $this->guarded($provider, $eventId, $type, fn () => 'ignored');
    }
}
