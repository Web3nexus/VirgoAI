<?php

namespace App\Billing;

use App\Ai\Fal\FalEndpoints;
use App\Models\CreditPack;
use App\Models\ModelPrice;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * The plans and top-ups, priced from the live model prices and the owner's margin.
 *
 * The public view says what a plan buys (images, video seconds, scripts). It never shows the margin.
 * The admin view shows what the owner keeps from each plan if the credit is fully used.
 */
class Catalog
{
    /** Assumed size of one script run, used for the "scripts" entitlement. */
    private const SCRIPT_INPUT_TOKENS = 3000;

    private const SCRIPT_OUTPUT_TOKENS = 2000;

    public function __construct(protected PricingSettings $settings) {}

    /** @return array<string, mixed> */
    public function public(): array
    {
        $unit = $this->unitPrices();

        return [
            'currency' => 'USD',
            'plans' => Plan::query()->where('active', true)->orderBy('sort')->get()->map(fn (Plan $plan) => $this->planView($plan, $unit))->values(),
            'packs' => CreditPack::query()->where('active', true)->orderBy('sort')->get()->map(fn (CreditPack $pack) => [
                'key' => $pack->key,
                'name' => $pack->name,
                'tokens' => Money::toTokens($pack->credits_micros),
                'price_usd' => Money::toUsd($pack->price_micros),
                'subscriber_price_usd' => Money::toUsd((int) round($pack->price_micros * (100 - $pack->subscriber_discount_percent) / 100)),
                'subscriber_discount_percent' => $pack->subscriber_discount_percent,
            ])->values(),
            'intervals' => [Subscription::INTERVAL_MONTHLY, Subscription::INTERVAL_YEARLY],
        ];
    }

    /**
     * What the owner keeps per plan. Assumes the monthly credit is fully used, which is the worst case.
     *
     * @return list<array<string, mixed>>
     */
    public function economics(): array
    {
        $margin = $this->settings->marginPercent();

        return Plan::query()->orderBy('sort')->get()->map(function (Plan $plan) use ($margin) {
            $budget = fn (int $credit) => (int) round($credit / (100 + $margin) * 100);

            $monthlyBudget = $budget($plan->monthly_credit_micros);
            $yearlyBudget = $budget($plan->monthly_credit_micros * 12);

            return [
                'key' => $plan->key,
                'name' => $plan->name,
                'monthly_price_usd' => Money::toUsd($plan->monthly_micros),
                'yearly_price_usd' => Money::toUsd($plan->yearly_micros),
                'monthly_credit_usd' => Money::toUsd($plan->monthly_credit_micros),
                'monthly' => [
                    'revenue_usd' => Money::toUsd($plan->monthly_micros),
                    'provider_budget_usd' => Money::toUsd($monthlyBudget),
                    'kept_usd' => Money::toUsd($plan->monthly_micros - $monthlyBudget),
                ],
                'yearly' => [
                    'revenue_usd' => Money::toUsd($plan->yearly_micros),
                    'provider_budget_usd' => Money::toUsd($yearlyBudget),
                    'kept_usd' => Money::toUsd($plan->yearly_micros - $yearlyBudget),
                ],
                'active' => (bool) $plan->active,
                'subscribers' => Subscription::query()->where('plan_key', $plan->key)->where('status', Subscription::STATUS_ACTIVE)->count(),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, int|null>  $unit
     * @return array<string, mixed>
     */
    private function planView(Plan $plan, array $unit): array
    {
        $monthly = $plan->monthly_micros;
        $yearlyPerMonth = (int) round($plan->yearly_micros / 12);
        $savings = $monthly > 0 ? (int) round((1 - $plan->yearly_micros / (12 * $monthly)) * 100) : 0;
        $credit = $plan->monthly_credit_micros;

        return [
            'key' => $plan->key,
            'name' => $plan->name,
            'tagline' => $plan->tagline,
            'highlighted' => (bool) $plan->highlighted,
            'monthly_usd' => Money::toUsd($monthly),
            'yearly_usd' => Money::toUsd($plan->yearly_micros),
            'yearly_per_month_usd' => Money::toUsd($yearlyPerMonth),
            'yearly_savings_percent' => $savings,
            'monthly_tokens' => Money::toTokens($credit),
            'yearly_tokens' => Money::toTokens($credit * 12),
            'features' => $plan->features ?? [],
        ];
    }

    /** How many units of credit buys. Null when the price for that unit is not known yet. */
    private function count(int $credit, ?int $sellMicros): ?int
    {
        if ($sellMicros === null || $sellMicros <= 0) {
            return null;
        }

        return intdiv($credit, $sellMicros);
    }

    /**
     * Sell price, in micro-dollars, for one image, one second of video, and one script. Built from the
     * synced provider prices, so it moves when the cost or the margin moves.
     *
     * @return array{image: int|null, second: int|null, script: int|null}
     */
    private function unitPrices(): array
    {
        // Plans promise the dearest image and the 720p second, so a plan never gives more than the cost allows.
        $imageRange = FalRates::range(FalEndpoints::IMAGE_TEXT);
        $secondRange = FalRates::range(FalEndpoints::VIDEO_IMAGE);
        $image = $imageRange['max_micros'] ?? null;
        $second = $secondRange['min_micros'] ?? null;

        $inputPrice = ModelPrice::query()->where('provider_key', 'anthropic')->where('model_key', 'claude-sonnet-5-5')->where('unit', 'input_mtok')->value('cost_micros');
        $outputPrice = ModelPrice::query()->where('provider_key', 'anthropic')->where('model_key', 'claude-sonnet-5-5')->where('unit', 'output_mtok')->value('cost_micros');

        $scriptCost = $inputPrice !== null && $outputPrice !== null
            ? Money::tokens(self::SCRIPT_INPUT_TOKENS, (int) $inputPrice) + Money::tokens(self::SCRIPT_OUTPUT_TOKENS, (int) $outputPrice)
            : null;

        return [
            'image' => $image !== null ? $this->settings->sell((int) $image) : null,
            'second' => $second !== null ? $this->settings->sell((int) $second) : null,
            'script' => $scriptCost !== null ? $this->settings->sell($scriptCost) : null,
        ];
    }
}
