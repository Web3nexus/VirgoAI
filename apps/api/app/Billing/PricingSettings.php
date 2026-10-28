<?php

namespace App\Billing;

use App\Models\AppSetting;

/**
 * The margin the owner keeps on every provider cost.
 */
class PricingSettings
{
    public const MARGIN_KEY = 'margin_percent';

    /** The margin in use. Never below the floor, even if a lower value was stored before the floor existed. */
    public function marginPercent(): int
    {
        $stored = (int) AppSetting::read(self::MARGIN_KEY, config('pricing.default_margin_percent', 40));

        return max($this->floorPercent(), $stored);
    }

    public function floorPercent(): int
    {
        return (int) config('pricing.min_margin_percent', 40);
    }

    /** Sell price for a provider cost. Rounded up so the margin is never short. */
    public function sell(int $costMicros): int
    {
        return (int) ceil($costMicros * (100 + $this->marginPercent()) / 100);
    }

    /** Share of each sell price the owner keeps, as a percentage. */
    public function keptShare(): float
    {
        $margin = $this->marginPercent();

        return round($margin / (100 + $margin) * 100, 2);
    }
}
