<?php

namespace App\Billing;

use App\Models\Generation;
use App\Models\LedgerEntry;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The only code that changes a balance. Every change writes a ledger row, inside a transaction that
 * locks the user first, so two runs at once cannot spend the same credit.
 *
 * Credit comes in two buckets. Subscription credit expires at the end of its cycle and is spent first.
 * Purchased credit (top-ups, grants, refunds) never expires.
 */
class Wallet
{
    /**
     * Charge the quoted price and create the run in one transaction. Fails before any provider call
     * when the balance cannot cover the price.
     *
     * @param  array<string, mixed>  $attributes  Generation attributes; status is set here.
     */
    public function reserveRun(User $user, Quote $quote, array $attributes): Generation
    {
        return DB::transaction(function () use ($user, $quote, $attributes) {
            $account = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($quote->priceMicros > $account->balance_micros) {
                throw new InsufficientCredit($quote->priceMicros, $account->balance_micros);
            }

            $generation = Generation::create([
                ...$attributes,
                'status' => Generation::STATUS_PENDING,
                'price_micros' => $quote->priceMicros,
                'cost_micros' => $quote->costMicros,
                'cost_usd' => Money::toUsd($quote->costMicros),
            ]);

            if ($quote->priceMicros > 0) {
                $this->post($account, -$quote->priceMicros, LedgerEntry::TYPE_CHARGE, 'Run: '.($attributes['tool'] ?? 'tool'), $generation->id);
            }

            return $generation;
        });
    }

    /** A text run finished. Replace the reserved price with the price of the tokens actually used. */
    public function settle(Generation $generation, Quote $actual): void
    {
        DB::transaction(function () use ($generation, $actual) {
            $run = Generation::query()->whereKey($generation->id)->lockForUpdate()->firstOrFail();
            $account = User::query()->whereKey($run->user_id)->lockForUpdate()->firstOrFail();

            $difference = $run->price_micros - $actual->priceMicros;

            if ($difference !== 0) {
                $this->post($account, $difference, LedgerEntry::TYPE_SETTLEMENT, 'Settled to tokens used', $run->id);
            }

            $run->forceFill([
                'price_micros' => $actual->priceMicros,
                'cost_micros' => $actual->costMicros,
                'cost_usd' => Money::toUsd($actual->costMicros),
            ])->save();
        });
    }

    /** Return the full charge of a run that did not produce its output. Refunds are purchased credit. Safe to call twice. */
    public function refund(Generation $generation, string $note = 'Run did not finish'): void
    {
        DB::transaction(function () use ($generation, $note) {
            $run = Generation::query()->whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if ($run->price_micros <= 0) {
                return;
            }

            $account = User::query()->whereKey($run->user_id)->lockForUpdate()->firstOrFail();
            $this->post($account, $run->price_micros, LedgerEntry::TYPE_REFUND, $note, $run->id);

            // A refunded run earns nothing and costs nothing on our books.
            $run->forceFill(['price_micros' => 0, 'cost_micros' => 0, 'cost_usd' => 0])->save();
        });
    }

    /** The owner adds or removes purchased credit. A removal cannot take the balance below zero. */
    public function adjust(User $user, int $amountMicros, string $note, ?User $actor): LedgerEntry
    {
        if ($amountMicros === 0) {
            throw new DomainException('The amount must not be zero.');
        }

        return DB::transaction(function () use ($user, $amountMicros, $note, $actor) {
            $account = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($amountMicros < 0 && -$amountMicros > $account->balance_micros) {
                throw new DomainException('That removes more than the balance. Lower the amount.');
            }

            $type = $amountMicros > 0 ? LedgerEntry::TYPE_GRANT : LedgerEntry::TYPE_ADJUSTMENT;

            return $this->post($account, $amountMicros, $type, $note, null, $actor);
        });
    }

    /** Monthly credit from a subscription. It expires at the end of the cycle. */
    public function grantSubscriptionCredit(User $user, int $micros, string $note): LedgerEntry
    {
        return DB::transaction(function () use ($user, $micros, $note) {
            $account = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            return $this->post($account, $micros, LedgerEntry::TYPE_SUBSCRIPTION, $note, null, null, subscription: true);
        });
    }

    /** Purchased credit from a top-up. It does not expire. */
    public function grantPurchasedCredit(User $user, int $micros, string $note, ?User $actor = null): LedgerEntry
    {
        return DB::transaction(function () use ($user, $micros, $note, $actor) {
            $account = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            return $this->post($account, $micros, LedgerEntry::TYPE_PURCHASE, $note, null, $actor);
        });
    }

    /** Remove whatever subscription credit is left, at the end of a cycle or when a plan ends. */
    public function expireSubscriptionCredit(User $user, string $note = 'Monthly credit expired'): ?LedgerEntry
    {
        return DB::transaction(function () use ($user, $note) {
            $account = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($account->subscription_credit_micros <= 0) {
                return null;
            }

            return $this->post($account, -$account->subscription_credit_micros, LedgerEntry::TYPE_EXPIRY, $note, null, null, expiring: true);
        });
    }

    /**
     * Must run inside a transaction with $account locked.
     *
     * A charge takes from the subscription bucket first. Subscription credit is only added by
     * grantSubscriptionCredit, and only the expiry removes it outside a charge.
     */
    private function post(User $account, int $amountMicros, string $type, ?string $note, ?string $generationId, ?User $actor = null, bool $subscription = false, bool $expiring = false): LedgerEntry
    {
        $subscriptionCredit = (int) $account->subscription_credit_micros;

        if ($subscription) {
            $subscriptionCredit += $amountMicros;
        } elseif ($expiring) {
            $subscriptionCredit = 0;
        } elseif ($amountMicros < 0) {
            $subscriptionCredit = max(0, $subscriptionCredit + $amountMicros);
        }

        $balance = $account->balance_micros + $amountMicros;

        $account->forceFill([
            'balance_micros' => $balance,
            'subscription_credit_micros' => $subscriptionCredit,
        ])->saveQuietly();

        return LedgerEntry::create([
            'user_id' => $account->id,
            'generation_id' => $generationId,
            'type' => $type,
            'amount_micros' => $amountMicros,
            'balance_after_micros' => $balance,
            'note' => $note !== null ? mb_substr($note, 0, 255) : null,
            'actor_user_id' => $actor?->id,
        ]);
    }
}
