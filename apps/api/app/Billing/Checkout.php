<?php

namespace App\Billing;

use App\Models\CreditPack;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use DomainException;

/**
 * Prices what a user is about to buy and records it as a pending purchase. A purchase grants nothing
 * until it is paid. Payment itself is handled by the provider (see the webhook); until one is connected,
 * an admin marks purchases paid.
 */
class Checkout
{
    public function __construct(protected PersonalPromo $promo) {}

    public function plan(User $user, string $planKey, string $interval, string $provider): Purchase
    {
        if (! in_array($interval, [Subscription::INTERVAL_MONTHLY, Subscription::INTERVAL_YEARLY], true)) {
            throw new DomainException('Choose monthly or yearly.');
        }

        $plan = Plan::query()->where('key', $planKey)->where('active', true)->firstOrFail();
        $renewing = Subscription::query()
            ->where('user_id', $user->id)
            ->where('plan_key', $plan->key)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
            ->exists();

        $yearly = $interval === Subscription::INTERVAL_YEARLY;
        $list = $yearly ? $plan->yearly_micros : $plan->monthly_micros;
        $offer = $this->promo->for($user);

        return Purchase::create([
            'user_id' => $user->id,
            'kind' => $renewing ? Purchase::KIND_RENEWAL : Purchase::KIND_SUBSCRIPTION,
            'item_key' => $plan->key,
            'interval' => $interval,
            // The welcome offer, when it applies, is recorded as the price the user is quoted.
            'amount_micros' => $offer ? $this->promo->discounted($list, $offer['percent']) : $list,
            // A yearly plan adds its monthly credit each month, so the year is worth twelve months of credit.
            'credits_micros' => $yearly ? $plan->monthly_credit_micros * 12 : $plan->monthly_credit_micros,
            'status' => Purchase::STATUS_PENDING,
            'provider' => $provider,
        ]);
    }

    public function pack(User $user, string $packKey, string $provider): Purchase
    {
        $pack = CreditPack::query()->where('key', $packKey)->where('active', true)->firstOrFail();

        return Purchase::create([
            'user_id' => $user->id,
            'kind' => Purchase::KIND_PACK,
            'item_key' => $pack->key,
            'interval' => null,
            'amount_micros' => self::packPriceFor($user, $pack),
            'credits_micros' => $pack->credits_micros,
            'status' => Purchase::STATUS_PENDING,
            'provider' => $provider,
        ]);
    }

    /** Subscribers pay the discounted price for a top-up. Everyone else pays list. */
    public static function packPriceFor(User $user, CreditPack $pack): int
    {
        $entitled = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('current_period_end', '>', now())
            ->exists();

        if (! $entitled) {
            return $pack->price_micros;
        }

        return (int) round($pack->price_micros * (100 - $pack->subscriber_discount_percent) / 100);
    }
}
