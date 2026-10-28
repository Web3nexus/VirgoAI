<?php

namespace App\Billing;

use App\Ai\Fal\FalEndpoints;
use App\Films\FilmEstimator;
use App\Models\ModelPrice;
use DomainException;

/**
 * The rate card: what each unit of each model costs the user in credits, and what the owner keeps.
 *
 * One credit is $0.001 of sell price. Every sell price is the provider's cost plus the owner's margin, rounded up.
 * A director package (a film) is the sum of its parts, the director's calls, the board's references and every take,
 * each priced the same way, so the package price can never be lower than the parts it is made of.
 */
class RateCard
{
    /** Lengths shown in the director table, in seconds. */
    public const PACKAGE_LENGTHS = [30, 60, 180, 600, 1800, 3600];

    /** Display names for the fal endpoints the app calls. Anything not listed shows its endpoint id. */
    private const LABELS = [
        FalEndpoints::IMAGE_TEXT => 'GPT Image 1.5 · text to image',
        FalEndpoints::IMAGE_FLUX_SCHNELL => 'FLUX schnell · text to image',
        FalEndpoints::IMAGE_NANO_BANANA => 'Nano Banana 2 · text to image',
        FalEndpoints::IMAGE_GEMINI => 'Gemini 2.5 Flash Image · text to image',
        FalEndpoints::VIDEO_SEEDANCE_REF => 'Seedance 2.0 · reference to video (director)',
        FalEndpoints::VIDEO_IMAGE => 'Seedance 2.0 · image to video',
        FalEndpoints::VIDEO_SEEDANCE_FAST => 'Seedance 2.0 fast · image to video',
        FalEndpoints::VIDEO_KLING => 'Kling v3 standard · image to video',
        FalEndpoints::VIDEO_HAILUO => 'Hailuo 02 · image to video',
        FalEndpoints::VIDEO_VEO_31_FAST => 'Veo 3.1 fast · image to video',
        FalEndpoints::VIDEO_VEO_3_FAST => 'Veo 3 fast · image to video',
        FalEndpoints::TRANSCRIBE => 'Whisper · transcription',
    ];

    /** Display names for the language models. */
    private const TEXT_LABELS = [
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5 · text',
        'claude-opus-5-5' => 'Claude Opus 5.5 · text',
        'claude-fable-5-1' => 'Claude Fable 5.1 · text',
        'claude-haiku-5-5' => 'Claude Haiku 5.5 · text',
    ];

    public function __construct(
        protected PricingSettings $settings,
        protected PriceQuoter $quotes,
        protected FilmEstimator $films,
    ) {}

    /**
     * The public rate card: credits and sell prices only. The margin and the provider's cost are not shown.
     *
     * @return array<string, mixed>
     */
    public function public(): array
    {
        return $this->build(owner: false);
    }

    /**
     * The owner's rate card: the same prices, with the provider's cost, what the owner keeps, and what is left after fees.
     *
     * @return array<string, mixed>
     */
    public function owner(): array
    {
        return $this->build(owner: true);
    }

    /**
     * Every price the app would charge, checked against its cost. Returns the problems found, empty when none.
     *
     * @return list<string>
     */
    public function verify(): array
    {
        $problems = [];

        foreach (ModelPrice::query()->get() as $price) {
            $sell = $this->settings->sell((int) $price->cost_micros);

            if ($sell < (int) ceil($price->cost_micros * (100 + $this->settings->marginPercent()) / 100)) {
                $problems[] = "{$price->provider_key} {$price->model_key} per {$price->unit} sells below cost plus margin.";
            }

            if ($this->settings->marginPercent() < $this->settings->floorPercent()) {
                $problems[] = 'The margin is below its floor.';
            }
        }

        foreach ($this->packageRows(owner: true) as $package) {
            if (! $package['available']) {
                continue;
            }

            $parts = $package['parts'];
            $sum = $parts['director']['price_micros'] + $parts['board']['price_micros'] + $parts['takes']['price_micros'];

            if ($sum !== $package['total_micros']) {
                $problems[] = "Director package of {$package['length_seconds']} s does not add up to its parts.";
            }
            if ($package['total_cost_micros'] > $package['total_micros']) {
                $problems[] = "Director package of {$package['length_seconds']} s costs more than it sells for.";
            }
        }

        return array_values(array_unique($problems));
    }

    /** @return array<string, mixed> */
    private function build(bool $owner): array
    {
        return [
            'currency' => 'USD',
            'credit' => [
                'name' => 'credit',
                'usd' => Money::toUsd(Money::MICROS_PER_TOKEN),
                'note' => '1 credit = $0.001 of sell price. $1 = 1,000 credits.',
            ],
            'models' => $this->modelRows($owner),
            'director' => $this->packageRows($owner),
        ];
    }

    /**
     * One row per priced unit: the model, what the unit is, and what it costs in credits.
     *
     * @return list<array<string, mixed>>
     */
    private function modelRows(bool $owner): array
    {
        $rows = $this->rateTableRows($owner);
        $rated = array_keys((array) config('pricing.fal_rates', []));

        foreach (ModelPrice::query()->orderBy('provider_key')->orderBy('model_key')->orderBy('unit')->get() as $price) {
            // A fal model with a rate table is priced there, so its synced number is not shown.
            if ($price->provider_key === 'fal' && in_array($price->model_key, $rated, true)) {
                continue;
            }

            $cost = (int) $price->cost_micros;
            $sell = $this->settings->sell($cost);

            $row = [
                'provider' => $price->provider_key,
                'model' => $price->model_key,
                'label' => $this->labelFor($price->provider_key, $price->model_key),
                'unit' => $this->unitLabel($price->unit),
                // A per-million-token price is already per million, so it is not divided again.
                'credits_per_unit' => $this->credits($sell),
                'price_usd' => Money::toUsd($sell),
            ];

            if ($owner) {
                $row['cost_usd'] = Money::toUsd($cost);
                $row['kept_usd'] = Money::toUsd($sell - $cost);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * One row per tier of the fal rate table: each quality and size for images, each resolution for video.
     *
     * @return list<array<string, mixed>>
     */
    private function rateTableRows(bool $owner): array
    {
        $rows = [];

        foreach ((array) config('pricing.fal_rates', []) as $endpoint => $rate) {
            $micros = fn (float $usd) => (int) round($usd * (int) config('pricing.micros_per_usd', 1_000_000));

            $units = [];
            if ($rate['unit'] === 'image') {
                foreach ($rate['tiers'] as $quality => $sizes) {
                    foreach ($sizes as $size => $usd) {
                        $units[] = [$micros($usd), "credits per image · {$quality} quality · {$size}"];
                    }
                }
            } else {
                foreach ($rate['tiers'] as $resolution => $usd) {
                    $units[] = [$micros($usd), "credits per second · {$resolution}"];
                }
            }

            foreach ($units as [$cost, $unit]) {
                $sell = $this->settings->sell($cost);

                $row = [
                    'provider' => 'fal',
                    'model' => $endpoint,
                    'label' => $this->labelFor('fal', $endpoint),
                    'unit' => $unit,
                    'credits_per_unit' => $this->credits($sell),
                    'price_usd' => Money::toUsd($sell),
                    'verified' => (bool) $rate['verified'],
                ];

                if ($owner) {
                    $row['cost_usd'] = Money::toUsd($cost);
                    $row['kept_usd'] = Money::toUsd($sell - $cost);
                }

                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * What a director package costs, at each length the rate card lists. Each package is priced from its parts.
     *
     * @return list<array<string, mixed>>
     */
    private function packageRows(bool $owner): array
    {
        $rows = [];

        foreach (self::PACKAGE_LENGTHS as $length) {
            foreach (['720p', '1080p'] as $quality) {
                $row = $this->package($length, $quality);
                $rows[] = $owner ? $row : $this->publicPackage($row);
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function package(int $length, string $quality): array
    {
        try {
            $estimate = $this->films->estimate($length, 'landscape_16_9', $quality);
        } catch (DomainException|PriceUnavailable $e) {
            return ['length_seconds' => $length, 'quality' => $quality, 'available' => false, 'reason' => $e->getMessage()];
        }

        $parts = $estimate['parts'];
        $total = $estimate['price_micros'];
        $cost = $estimate['cost_micros'];

        return [
            'length_seconds' => $length,
            'quality' => $quality,
            'available' => true,
            'takes' => $estimate['takes'],
            'chapters' => $estimate['chapters'],
            'total_micros' => $total,
            'total_cost_micros' => $cost,
            'parts' => $parts,
            'total_credits' => $this->credits($total),
            'part_credits' => [
                'director' => $this->credits($parts['director']['price_micros']),
                'board' => $this->credits($parts['board']['price_micros']),
                'takes' => $this->credits($parts['takes']['price_micros']),
            ],
            'kept_usd' => Money::toUsd($total - $cost),
            'kept_after_fees_usd' => Money::toUsd($total - $cost - $this->fees($total)),
        ];
    }

    /** @param  array<string, mixed>  $row */
    private function publicPackage(array $row): array
    {
        if (! $row['available']) {
            return ['length_seconds' => $row['length_seconds'], 'quality' => $row['quality'], 'available' => false];
        }

        return [
            'length_seconds' => $row['length_seconds'],
            'quality' => $row['quality'],
            'available' => true,
            'takes' => $row['takes'],
            'chapters' => $row['chapters'],
            'total_credits' => $row['total_credits'],
            'total_usd' => Money::toUsd($row['total_micros']),
            'part_credits' => $row['part_credits'],
        ];
    }

    /** Credits for a micro-dollar amount, rounded up to a tenth so a displayed price is never short. */
    private function credits(int $micros): float
    {
        return ceil($micros / Money::MICROS_PER_TOKEN * 10) / 10;
    }

    /** The payment fee on a purchase of this size, from the planning assumption in config. */
    private function fees(int $micros): int
    {
        $percent = (float) config('pricing.payment_fees.percent', 0);

        return (int) ceil($micros * $percent / 100);
    }

    private function labelFor(string $provider, string $model): string
    {
        return match ($provider) {
            'anthropic' => self::TEXT_LABELS[$model] ?? $model,
            'fal' => self::LABELS[$model] ?? $model,
            default => $model,
        };
    }

    private function unitLabel(string $unit): string
    {
        return match ($unit) {
            'input_mtok' => 'credits per 1M input tokens',
            'output_mtok' => 'credits per 1M output tokens',
            'input_mtok_long' => 'credits per 1M input tokens, prompts over 100k tokens',
            'output_mtok_long' => 'credits per 1M output tokens, prompts over 100k tokens',
            'second', 'seconds' => 'credits per second of video',
            'image' => 'credits per image',
            'video' => 'credits per video',
            default => "credits per {$unit}",
        };
    }
}
