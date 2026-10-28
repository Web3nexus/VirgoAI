<?php

namespace App\Billing;

use App\Enums\Capability;
use App\Models\ModelPrice;

/**
 * The price a user sees next to a model in the picker: what one unit of that model costs them, after margin.
 * Built from the same prices the wallet charges, so the label and the charge cannot drift apart.
 */
class PriceLabel
{
    public function __construct(protected PricingSettings $settings) {}

    public function for(string $providerKey, string $modelKey, Capability $capability): ?string
    {
        if ($providerKey === 'echo') {
            return 'Free test model';
        }

        if ($providerKey === 'anthropic' && $capability === Capability::Text) {
            $input = $this->cost($providerKey, $modelKey, 'input_mtok');
            $output = $this->cost($providerKey, $modelKey, 'output_mtok');

            if ($input === null || $output === null) {
                return 'Not priced yet';
            }

            return sprintf(
                '%s in · %s out per 1M tokens',
                Money::format($this->settings->sell($input), 2),
                Money::format($this->settings->sell($output), 2),
            );
        }

        if ($providerKey === 'fal' && ($range = FalRates::range($modelKey)) !== null) {
            $noun = $range['unit'] === 'image' ? 'image' : 'second of video';
            $low = Money::format($this->settings->sell($range['min_micros']), 3);
            $high = Money::format($this->settings->sell($range['max_micros']), 3);

            return $range['min_micros'] === $range['max_micros']
                ? "{$low} per {$noun}"
                : "{$low} to {$high} per {$noun}, by size and quality";
        }

        if ($providerKey === 'fal') {
            $unit = $capability === Capability::Video ? 'second' : 'image';
            $cost = $this->cost($providerKey, $modelKey, $unit);

            if ($cost === null) {
                return 'Not priced yet';
            }

            return Money::format($this->settings->sell($cost), 3).' per '.($unit === 'second' ? 'second of video' : 'image');
        }

        return null;
    }

    private function cost(string $providerKey, string $modelKey, string $unit): ?int
    {
        $micros = ModelPrice::query()
            ->where('provider_key', $providerKey)
            ->where('model_key', $modelKey)
            ->where('unit', $unit)
            ->value('cost_micros');

        return $micros === null ? null : (int) $micros;
    }
}
