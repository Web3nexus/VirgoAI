<?php

namespace App\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Applies the plan rules to every live subscription. Safe to run repeatedly: each step only fires once,
 * because it moves a timestamp forward.
 */
class SubscriptionCycle
{
    public function __construct(protected Wallet $wallet) {}

    /**
     * @return array{granted: int, past_due: int, ended: int}
     */
    public function run(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $counts = ['granted' => 0, 'past_due' => 0, 'ended' => 0];

        Subscription::query()
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($now, &$counts) {
                foreach ($subscriptions as $subscription) {
                    $outcome = $this->step($subscription, $now);
                    $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
                }
            }, 'id');

        return $counts;
    }

    /** One subscription, one step. Returns what happened. */
    private function step(Subscription $subscription, CarbonImmutable $now): string
    {
        return DB::transaction(function () use ($subscription, $now) {
            $locked = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();
            $periodEnd = CarbonImmutable::instance($locked->current_period_end);

            if ($locked->status === Subscription::STATUS_ACTIVE && $periodEnd->lte($now)) {
                if ($locked->cancel_at_period_end) {
                    return $this->end($locked, 'ended');
                }

                $locked->forceFill(['status' => Subscription::STATUS_PAST_DUE])->save();
                $this->wallet->expireSubscriptionCredit($locked->user, 'Plan period ended without renewal');

                return 'past_due';
            }

            if ($locked->status === Subscription::STATUS_PAST_DUE && $periodEnd->addDays(Subscription::GRACE_DAYS)->lte($now)) {
                return $this->end($locked, 'ended');
            }

            if ($locked->status === Subscription::STATUS_ACTIVE && CarbonImmutable::instance($locked->next_credit_at)->lte($now)) {
                $plan = Plan::query()->where('key', $locked->plan_key)->firstOrFail();

                $this->wallet->expireSubscriptionCredit($locked->user, 'Monthly credit expired');
                $this->wallet->grantSubscriptionCredit($locked->user, $plan->monthly_credit_micros, "{$plan->name} monthly credit");
                $locked->forceFill(['next_credit_at' => CarbonImmutable::instance($locked->next_credit_at)->addMonth()])->save();

                return 'granted';
            }

            return 'unchanged';
        });
    }

    private function end(Subscription $subscription, string $outcome): string
    {
        $this->wallet->expireSubscriptionCredit($subscription->user, 'Plan ended');
        $subscription->forceFill(['status' => Subscription::STATUS_ENDED, 'ended_at' => now()])->save();

        return $outcome;
    }
}
