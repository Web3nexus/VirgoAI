<?php

namespace App\Billing\Payments;

use App\Models\CreditPack;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Creates the hosted checkout link for a pending purchase, once.
 *
 * Idempotent: the purchase row is locked, and a link that already exists is returned as it was. A double
 * click, a retry, or two tabs cannot open two checkouts for one purchase.
 */
class PaymentCheckout
{
    public function __construct(
        protected FlutterwaveGateway $flutterwave,
        protected PaddleGateway $paddle,
    ) {}

    public function start(Purchase $purchase, User $user): string
    {
        return DB::transaction(function () use ($purchase, $user) {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Purchase::STATUS_PENDING) {
                throw new DomainException('This purchase is no longer pending.');
            }

            if (is_string($locked->checkout_url) && $locked->checkout_url !== '') {
                return $locked->checkout_url;
            }

            $this->refuseUnchargeableOffer($locked);

            [$url, $reference] = match ($locked->provider) {
                'flutterwave' => $this->flutterwaveCheckout($locked, $user),
                'paddle' => $this->paddleCheckout($locked, $user),
                default => throw new DomainException('Choose a payment provider.'),
            };

            $locked->forceFill(['checkout_url' => $url, 'provider_ref' => $reference])->save();

            return $url;
        });
    }

    /**
     * A welcome-offer price is recorded on the purchase, but the providers still charge their fixed list
     * price (see the plan price ids). Refuse rather than quote a price the customer would not be charged.
     */
    private function refuseUnchargeableOffer(Purchase $purchase): void
    {
        if ($purchase->kind === Purchase::KIND_PACK) {
            return;
        }

        $plan = Plan::query()->where('key', $purchase->item_key)->firstOrFail();
        $list = $purchase->interval === Subscription::INTERVAL_YEARLY ? $plan->yearly_micros : $plan->monthly_micros;

        if ($purchase->amount_micros < $list) {
            throw new PaymentNotConfigured('Your welcome offer cannot be charged yet. Please try again later.');
        }
    }

    /** @return array{0: string, 1: string} */
    private function flutterwaveCheckout(Purchase $purchase, User $user): array
    {
        $planId = null;

        if ($purchase->kind !== Purchase::KIND_PACK) {
            $plan = Plan::query()->where('key', $purchase->item_key)->firstOrFail();
            $planId = $purchase->interval === Subscription::INTERVAL_YEARLY
                ? $plan->flutterwave_yearly_plan_id
                : $plan->flutterwave_monthly_plan_id;

            if ($planId === null) {
                throw new PaymentNotConfigured("{$plan->name} is not set up on Flutterwave yet. Create its payment plans on the plan editor.");
            }
        }

        return $this->flutterwave->createCheckout($purchase, $user, $planId);
    }

    /** @return array{0: string, 1: string} */
    private function paddleCheckout(Purchase $purchase, User $user): array
    {
        $priceId = $purchase->kind === Purchase::KIND_PACK
            ? $this->packPrice($purchase)
            : $this->planPrice($purchase);

        return $this->paddle->createCheckout($purchase, $user, $priceId);
    }

    private function planPrice(Purchase $purchase): string
    {
        $plan = Plan::query()->where('key', $purchase->item_key)->firstOrFail();
        $price = $purchase->interval === Subscription::INTERVAL_YEARLY ? $plan->paddle_yearly_price_id : $plan->paddle_monthly_price_id;

        if ($price === null) {
            throw new PaymentNotConfigured("{$plan->name} has no Paddle price yet. Add its price id on the plan editor.");
        }

        return $price;
    }

    private function packPrice(Purchase $purchase): string
    {
        $pack = CreditPack::query()->where('key', $purchase->item_key)->firstOrFail();

        // A subscriber's discounted purchase uses the discounted price when one is set up.
        if ($pack->paddle_subscriber_price_id !== null && $purchase->amount_micros < $pack->price_micros) {
            return $pack->paddle_subscriber_price_id;
        }

        if ($pack->paddle_price_id === null) {
            throw new PaymentNotConfigured("{$pack->name} has no Paddle price yet. Add its price id on the top-up editor.");
        }

        return $pack->paddle_price_id;
    }
}
