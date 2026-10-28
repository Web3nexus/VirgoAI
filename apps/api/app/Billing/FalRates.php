<?php

namespace App\Billing;

use App\Ai\Fal\FalEndpoints;
use App\Enums\Capability;

/**
 * The provider cost of one fal call, from the rate table in config/pricing.php. Only the call's real options are
 * priced: its size and quality for an image, its resolution and billed seconds for a video.
 */
final class FalRates
{
    /**
     * @param  array<string, mixed>  $options  The options the call is made with (format, quality, resolution, duration).
     * @return array{cost_micros: int, quantity: int, unit: string, verified: bool}|null Null when the table has no price for this call.
     */
    public static function quote(string $endpoint, Capability $capability, array $options): ?array
    {
        $rate = config('pricing.fal_rates', [])[$endpoint] ?? null;

        if (! is_array($rate)) {
            return null;
        }

        if ($rate['unit'] === 'image') {
            if ($capability !== Capability::Image) {
                return null;
            }

            $quality = (string) ($options['quality'] ?? FalEndpoints::DEFAULT_IMAGE_QUALITY);
            $size = FalEndpoints::gptImageSize((string) ($options['size'] ?? ''));
            $usd = $rate['tiers'][$quality][$size] ?? null;

            if ($usd === null) {
                throw new PriceUnavailable("fal does not price [{$endpoint}] at {$quality} quality and {$size}.");
            }

            return self::result($usd, 1, 'image', (bool) $rate['verified']);
        }

        if ($rate['unit'] === 'second') {
            if ($capability !== Capability::Video) {
                return null;
            }

            $resolution = (string) ($options['resolution'] ?? '720p');
            $usd = $rate['tiers'][$resolution] ?? $rate['tiers']['any'] ?? null;

            if ($usd === null) {
                throw new PriceUnavailable("fal does not price [{$endpoint}] at {$resolution}.");
            }

            $seconds = FalEndpoints::billableSeconds($endpoint, $options['duration'] ?? null);

            return self::result($usd, $seconds, 'second', (bool) $rate['verified']);
        }

        throw new PriceUnavailable("The fal rate table has an unknown unit for [{$endpoint}].");
    }

    /**
     * The cheapest and dearest price a user can meet for one unit of this endpoint, for labels and plan entitlements.
     * For video, the range is taken at 720p, the resolution the plans are sold at. Null when the table has no entry.
     *
     * @return array{min_micros: int, max_micros: int, unit: string}|null
     */
    public static function range(string $endpoint): ?array
    {
        $rate = config('pricing.fal_rates', [])[$endpoint] ?? null;

        if (! is_array($rate)) {
            return null;
        }

        $values = $rate['unit'] === 'image'
            ? array_merge(...array_map('array_values', array_values($rate['tiers'])))
            : [$rate['tiers']['720p'] ?? $rate['tiers']['any'] ?? reset($rate['tiers'])];

        $micros = array_map(fn (float $usd) => (int) round($usd * (int) config('pricing.micros_per_usd', 1_000_000)), $values);

        return ['min_micros' => min($micros), 'max_micros' => max($micros), 'unit' => $rate['unit']];
    }

    /**
     * @return array{cost_micros: int, quantity: int, unit: string, verified: bool}
     */
    private static function result(float $usd, int $quantity, string $unit, bool $verified): array
    {
        $micros = (int) round($usd * (int) config('pricing.micros_per_usd', 1_000_000));

        return [
            'cost_micros' => Money::times($micros, $quantity),
            'quantity' => $quantity,
            'unit' => $unit,
            'verified' => $verified,
        ];
    }
}
