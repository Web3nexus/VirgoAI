<?php

namespace App\Billing;

use App\Models\AppSetting;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A personal discount on the plans, for a new account that has not bought anything yet.
 *
 * The admin sets the percentage and the window. The window starts when the account is created, so the
 * countdown the user sees is always the time they actually have left.
 */
class PersonalPromo
{
    public const SETTING_KEY = 'personal_promo';

    /** @return array{enabled: bool, code: string, percent: int, window_hours: int} */
    public function config(): array
    {
        $stored = AppSetting::read(self::SETTING_KEY, []);

        return [
            'enabled' => (bool) ($stored['enabled'] ?? false),
            'code' => (string) ($stored['code'] ?? 'WELCOME_PROMO'),
            'percent' => (int) ($stored['percent'] ?? 50),
            'window_hours' => (int) ($stored['window_hours'] ?? 3),
        ];
    }

    /**
     * The promo this user can claim right now, with per-plan prices, or null when they cannot.
     *
     * @return array<string, mixed>|null
     */
    public function for(User $user): ?array
    {
        $config = $this->config();

        if (! $config['enabled'] || ! $this->neverBought($user)) {
            return null;
        }

        $endsAt = CarbonImmutable::instance($user->created_at)->addHours($config['window_hours']);

        if (now()->greaterThanOrEqualTo($endsAt)) {
            return null;
        }

        return [
            'code' => $config['code'],
            'percent' => $config['percent'],
            'ends_at' => $endsAt->toIso8601String(),
            'plans' => Plan::query()->where('active', true)->orderBy('sort')->get()
                ->mapWithKeys(fn (Plan $plan) => [$plan->key => [
                    'monthly_usd' => Money::toUsd($this->discounted($plan->monthly_micros, $config['percent'])),
                    'yearly_usd' => Money::toUsd($this->discounted($plan->yearly_micros, $config['percent'])),
                    'yearly_per_month_usd' => Money::toUsd($this->discounted((int) round($plan->yearly_micros / 12), $config['percent'])),
                ]])
                ->all(),
        ];
    }

    /** Price after the discount, rounded to the cent so the figure shown is the figure charged. */
    public function discounted(int $micros, int $percent): int
    {
        $centMicros = (int) config('pricing.micros_per_usd', 1_000_000) / 100;

        return (int) (round($micros * (100 - $percent) / 100 / $centMicros) * $centMicros);
    }

    /** A user who has ever had a paid purchase or a subscription is past the welcome offer. */
    private function neverBought(User $user): bool
    {
        return ! Purchase::query()->where('user_id', $user->id)->where('status', Purchase::STATUS_PAID)->exists()
            && ! Subscription::query()->where('user_id', $user->id)->exists();
    }
}
