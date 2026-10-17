<?php

namespace App\Http\Controllers\Api;

use App\Billing\Checkout;
use App\Billing\Money;
use App\Billing\Payments\PaddleGateway;
use App\Billing\Payments\PaymentCheckout;
use App\Billing\Payments\PaymentNotConfigured;
use App\Billing\Payments\PaymentRouter;
use App\Billing\Subscriptions;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    /** The signed-in user's wallet, plan, and purchases. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
            ->latest('created_at')
            ->first();

        $suggested = strtoupper((string) ($request->header('CF-IPCountry') ?? $request->header('X-Country') ?? ''));

        return response()->json([
            'balance_tokens' => Money::toTokens((int) $user->balance_micros),
            'subscription_tokens' => Money::toTokens((int) $user->subscription_credit_micros),
            'purchased_tokens' => Money::toTokens(max(0, (int) $user->balance_micros - (int) $user->subscription_credit_micros)),
            'country_code' => $user->country_code,
            'suggested_country' => preg_match('/^[A-Z]{2}$/', $suggested) ? $suggested : null,
            'provider' => $user->country_code ? PaymentRouter::providerFor($user) : null,
            'subscription' => $subscription === null ? null : [
                'plan_key' => $subscription->plan_key,
                'plan_name' => Plan::query()->where('key', $subscription->plan_key)->value('name'),
                'interval' => $subscription->interval,
                'status' => $subscription->status,
                'entitled' => $subscription->isEntitled(),
                'current_period_end' => $subscription->current_period_end->toIso8601String(),
                'next_credit_at' => $subscription->next_credit_at->toIso8601String(),
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
            ],
            'purchases' => Purchase::query()->where('user_id', $user->id)->latest('created_at')->limit(20)->get()->map(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'kind' => $purchase->kind,
                'item_key' => $purchase->item_key,
                'interval' => $purchase->interval,
                'provider' => $purchase->provider,
                'amount_usd' => Money::toUsd($purchase->amount_micros),
                'tokens' => Money::toTokens($purchase->credits_micros),
                'status' => $purchase->status,
                // Only a pending purchase can still be paid, so only its link is returned.
                'checkout_url' => $purchase->status === Purchase::STATUS_PENDING ? $purchase->checkout_url : null,
                'created_at' => $purchase->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function checkoutPlan(Request $request, Checkout $checkout, PaymentCheckout $payments): JsonResponse
    {
        $data = $request->validate([
            'plan_key' => ['required', 'string', Rule::exists('plans', 'key')->where('active', true)],
            'interval' => ['required', 'string', Rule::in([Subscription::INTERVAL_MONTHLY, Subscription::INTERVAL_YEARLY])],
        ]);

        $provider = $this->providerOrFail($request);

        return $this->checkout($request, $checkout->plan($request->user(), $data['plan_key'], $data['interval'], $provider), $payments);
    }

    public function checkoutPack(Request $request, Checkout $checkout, PaymentCheckout $payments): JsonResponse
    {
        $data = $request->validate([
            'pack_key' => ['required', 'string', Rule::exists('credit_packs', 'key')->where('active', true)],
        ]);

        $provider = $this->providerOrFail($request);

        return $this->checkout($request, $checkout->pack($request->user(), $data['pack_key'], $provider), $payments);
    }

    /**
     * Cancel future renewals. Paddle is told too. Flutterwave cancellation has to be done on its side as well,
     * so the response says so.
     */
    public function cancel(Request $request, Subscriptions $subscriptions, PaddleGateway $paddle): JsonResponse
    {
        $subscription = Subscription::query()
            ->where('user_id', $request->user()->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->latest('created_at')
            ->first();

        if ($subscription === null) {
            return response()->json(['message' => 'You have no active plan to cancel.'], 422);
        }

        try {
            if ($subscription->provider === 'paddle' && $subscription->provider_ref) {
                $paddle->cancelSubscription($subscription->provider_ref);
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => 'The payment provider did not confirm the cancellation. Your plan is unchanged. Try again.'], 502);
        }

        $subscriptions->cancel($subscription);

        $message = $subscription->provider === 'flutterwave'
            ? 'Your plan stays active until the end of this period. Also cancel the subscription in your Flutterwave account so it is not charged again.'
            : 'Your plan stays active until the end of this period, then ends.';

        return response()->json(['message' => $message]);
    }

    /** Save the customer's country. The server suggests one from the request's country header. */
    public function country(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
        ]);

        $request->user()->forceFill(['country_code' => strtoupper($data['country_code'])])->save();

        return response()->json([
            'country_code' => $request->user()->country_code,
            'provider' => PaymentRouter::providerFor($request->user()),
        ]);
    }

    private function providerOrFail(Request $request): string
    {
        try {
            return PaymentRouter::providerFor($request->user());
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['country_code' => $e->getMessage()]);
        }
    }

    /** Create the hosted checkout for the purchase. Calling it again returns the same link. */
    private function checkout(Request $request, Purchase $purchase, PaymentCheckout $payments): JsonResponse
    {
        try {
            $url = $payments->start($purchase, $request->user());
        } catch (PaymentNotConfigured $e) {
            $purchase->forceFill(['status' => Purchase::STATUS_FAILED])->save();

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'purchase' => [
                'id' => $purchase->id,
                'kind' => $purchase->kind,
                'status' => $purchase->status,
                'provider' => $purchase->provider,
                'amount_usd' => Money::toUsd($purchase->amount_micros),
                'credits_usd' => Money::toUsd($purchase->credits_micros),
            ],
            'checkout_url' => $url,
            'message' => 'Complete the payment on the checkout page. Credit is added once the payment is confirmed.',
        ], 201);
    }
}
