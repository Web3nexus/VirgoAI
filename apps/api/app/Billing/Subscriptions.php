<?php

namespace App\Billing;

use App\Models\Plan;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Plan lifecycle. Detection rules, applied by SubscriptionCycle every hour:
 *
 *  - Entitled: status active and before current_period_end.
 *  - Each cycle (one month) the plan's monthly credit is granted and the previous cycle's unused
 *    subscription credit expires. The monthly credit is the only credit a plan adds.
 *  - When the period ends without a paid renewal, the plan becomes past_due. It grants nothing and
 *    keeps its window open for GRACE_DAYS. A paid renewal (Fulfilment) restores it at once.
 *  - When the grace window ends, or the user cancelled, the plan ends and its credit expires.
 */
class Subscriptions
{
    public function __construct(protected Wallet $wallet) {}

    /** Start a plan, replacing any current one. The first monthly credit is granted now. */
    public function activate(User $user, Plan $plan, string $interval, ?Purchase $purchase = null): Subscription
    {
        return DB::transaction(function () use ($user, $plan, $interval, $purchase) {
            $account = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            Subscription::query()
                ->where('user_id', $account->id)
                ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
                ->get()
                ->each(function (Subscription $current) {
                    $this->wallet->expireSubscriptionCredit($current->user, 'Replaced by a new plan');
                    $current->forceFill(['status' => Subscription::STATUS_ENDED, 'ended_at' => now()])->save();
                });

            $now = CarbonImmutable::now();

            $subscription = Subscription::create([
                'user_id' => $account->id,
                'plan_key' => $plan->key,
                'interval' => $interval,
                'status' => Subscription::STATUS_ACTIVE,
                'current_period_end' => $this->periodEnd($now, $interval),
                'next_credit_at' => $now->addMonth(),
                'cancel_at_period_end' => false,
                'provider' => $purchase?->provider ?? 'manual',
                // Paddle's subscription id, when the payment created one. Otherwise the purchase reference.
                'provider_ref' => $purchase?->provider_subscription_id ?? $purchase?->provider_ref,
            ]);

            $this->wallet->grantSubscriptionCredit($account, $plan->monthly_credit_micros, "{$plan->name} monthly credit");

            return $subscription;
        });
    }

    /** A paid renewal. The period moves on from its end, or from now if the plan had lapsed. */
    public function renew(Subscription $subscription, Plan $plan, Purchase $purchase): Subscription
    {
        return DB::transaction(function () use ($subscription, $plan, $purchase) {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Subscription::STATUS_ENDED) {
                throw new DomainException('This plan has ended. Start a new one instead.');
            }

            $now = CarbonImmutable::now();
            $from = $locked->current_period_end->isFuture() ? CarbonImmutable::instance($locked->current_period_end) : $now;

            $this->wallet->expireSubscriptionCredit($user, 'Replaced by renewal');
            $this->wallet->grantSubscriptionCredit($user, $plan->monthly_credit_micros, "{$plan->name} monthly credit");

            $locked->forceFill([
                'status' => Subscription::STATUS_ACTIVE,
                'current_period_end' => $this->periodEnd($from, $locked->interval),
                'next_credit_at' => $now->addMonth(),
                'cancel_at_period_end' => false,
                'provider_ref' => $purchase->provider_ref ?? $locked->provider_ref,
            ])->save();

            return $locked;
        });
    }

    /** The user keeps the plan until the period ends, then it ends. No refund. */
    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->forceFill(['cancel_at_period_end' => true])->save();

        return $subscription;
    }

    public function periodEnd(CarbonImmutable $from, string $interval): CarbonImmutable
    {
        return match ($interval) {
            Subscription::INTERVAL_MONTHLY => $from->addMonth(),
            Subscription::INTERVAL_YEARLY => $from->addYear(),
            default => throw new DomainException("Unknown billing interval [{$interval}]."),
        };
    }
}
