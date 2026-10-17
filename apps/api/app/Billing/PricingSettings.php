<?php

namespace App\Billing;

use App\Models\AppSetting;

/**
 * The margin the owner keeps on every provider cost.
 */
class PricingSettings
{
    public const MARGIN_KEY = 'margin_percent';

    public function marginPercent(): int
    {
        return (int) AppSetting::read(self::MARGIN_KEY, config('pricing.default_margin_percent', 40));
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
