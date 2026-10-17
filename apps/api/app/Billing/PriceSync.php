<?php

namespace App\Billing;

use App\Ai\Fal\FalEndpoints;
use App\Models\ModelPrice;
use App\Settings\ProviderCredentials;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fills the model_prices table from the two places prices come from:
 *
 *  - fal.ai's pricing API, read with the owner's FAL_KEY for each endpoint this app calls.
 *  - Anthropic's public pricing page, copied into config/pricing.php because Anthropic has no pricing API.
 *
 * A price the owner typed in (source `admin`) is never overwritten by a sync.
 */
class PriceSync
{
    public const FAL_PRICING_URL = 'https://api.fal.ai/v1/models/pricing';

    /**
     * @return array{upserted: int, skipped_admin: int, errors: list<string>}
     */
    public function syncFal(): array
    {
        $apiKey = ProviderCredentials::get('FAL_KEY');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('FAL_KEY is not configured; cannot read fal prices.');
        }

        $query = collect(array_keys(FalEndpoints::all()))
            ->map(fn (string $id) => 'endpoint_id='.rawurlencode($id))
            ->implode('&');

        $response = Http::withHeaders(['Authorization' => 'Key '.$apiKey])
            ->acceptJson()
            ->timeout(30)
            ->get(self::FAL_PRICING_URL.'?'.$query);

        if ($response->failed()) {
            throw new RuntimeException('fal pricing request failed: HTTP '.$response->status());
        }

        $upserted = 0;
        $skippedAdmin = 0;
        $errors = [];
        $seen = [];

        foreach ($response->json('prices') ?? [] as $price) {
            $endpoint = (string) ($price['endpoint_id'] ?? '');
            $unit = (string) ($price['unit'] ?? '');
            $currency = strtoupper((string) ($price['currency'] ?? ''));

            if (! array_key_exists($endpoint, FalEndpoints::all())) {
                continue;
            }

            if ($currency !== 'USD' || $unit === '' || ! is_numeric($price['unit_price'] ?? null)) {
                $errors[] = "fal returned an unusable price for [{$endpoint}].";

                continue;
            }

            $seen[$endpoint] = true;

            $existing = ModelPrice::query()
                ->where(['provider_key' => 'fal', 'model_key' => $endpoint, 'unit' => $unit])
                ->first();

            if ($existing?->source === ModelPrice::SOURCE_ADMIN) {
                $skippedAdmin++;

                continue;
            }

            ModelPrice::query()->updateOrCreate(
                ['provider_key' => 'fal', 'model_key' => $endpoint, 'unit' => $unit],
                [
                    'cost_micros' => Money::fromUsd((float) $price['unit_price']),
                    'source' => ModelPrice::SOURCE_FAL_API,
                    'verified_at' => now(),
                ],
            );

            // Drop an older unit for the same endpoint when fal changed how it bills.
            ModelPrice::query()
                ->where('provider_key', 'fal')
                ->where('model_key', $endpoint)
                ->where('unit', '!=', $unit)
                ->where('source', ModelPrice::SOURCE_FAL_API)
                ->delete();

            $upserted++;
        }

        foreach (array_keys(FalEndpoints::all()) as $endpoint) {
            if (! isset($seen[$endpoint])) {
                $errors[] = "fal returned no price for [{$endpoint}].";
            }
        }

        return ['upserted' => $upserted, 'skipped_admin' => $skippedAdmin, 'errors' => $errors];
    }

    /**
     * @return array{upserted: int, skipped_admin: int}
     */
    public function seedAnthropic(): array
    {
        $upserted = 0;
        $skippedAdmin = 0;

        foreach ((array) config('pricing.anthropic.models', []) as $model => $units) {
            foreach ($units as $unit => $usdPerMillion) {
                $existing = ModelPrice::query()
                    ->where(['provider_key' => 'anthropic', 'model_key' => $model, 'unit' => $unit])
                    ->first();

                if ($existing?->source === ModelPrice::SOURCE_ADMIN) {
                    $skippedAdmin++;

                    continue;
                }

                ModelPrice::query()->updateOrCreate(
                    ['provider_key' => 'anthropic', 'model_key' => $model, 'unit' => $unit],
                    [
                        'cost_micros' => Money::fromUsd((float) $usdPerMillion),
                        'source' => ModelPrice::SOURCE_ANTHROPIC_DOCS,
                        'verified_at' => config('pricing.anthropic.verified_at'),
                    ],
                );
                $upserted++;
            }
        }

        return ['upserted' => $upserted, 'skipped_admin' => $skippedAdmin];
    }
}
