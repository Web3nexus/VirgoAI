<?php

namespace App\Billing;

use App\Models\CreditPack;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Turns a paid purchase into what it bought. Runs once per purchase: a second payment event for the
 * same purchase changes nothing.
 */
class Fulfilment
{
    public function __construct(
        protected Subscriptions $subscriptions,
        protected Wallet $wallet,
    ) {}

    public function markPaid(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Purchase::STATUS_PENDING) {
                return $locked;
            }

            $locked->forceFill(['status' => Purchase::STATUS_PAID, 'paid_at' => now()])->save();
            $user = User::query()->whereKey($locked->user_id)->firstOrFail();

            match ($locked->kind) {
                Purchase::KIND_SUBSCRIPTION => $this->subscriptions->activate($user, $this->plan($locked), $locked->interval, $locked),
                Purchase::KIND_RENEWAL => $this->renewOrActivate($user, $locked),
                Purchase::KIND_PACK => $this->wallet->grantPurchasedCredit($user, $locked->credits_micros, 'Top-up: '.$this->pack($locked)->name),
                default => throw new DomainException("Unknown purchase kind [{$locked->kind}]."),
            };

            return $locked;
        });
    }

    public function markFailed(Purchase $purchase): Purchase
    {
        if ($purchase->status === Purchase::STATUS_PENDING) {
            $purchase->forceFill(['status' => Purchase::STATUS_FAILED])->save();
        }

        return $purchase;
    }

    private function renewOrActivate(User $user, Purchase $purchase): void
    {
        $plan = $this->plan($purchase);
        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_PAST_DUE])
            ->latest('created_at')
            ->first();

        if ($subscription === null) {
            $this->subscriptions->activate($user, $plan, $purchase->interval, $purchase);

            return;
        }

        $this->subscriptions->renew($subscription, $plan, $purchase);
    }

    private function plan(Purchase $purchase): Plan
    {
        return Plan::query()->where('key', $purchase->item_key)->firstOrFail();
    }

    private function pack(Purchase $purchase): CreditPack
    {
        return CreditPack::query()->where('key', $purchase->item_key)->firstOrFail();
    }
}
