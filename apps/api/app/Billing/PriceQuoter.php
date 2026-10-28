<?php

namespace App\Billing;

use App\Ai\Fal\FalEndpoints;
use App\Enums\Capability;
use App\Models\ModelPrice;

/**
 * Prices a run before it starts, and settles text runs from the tokens the model actually used.
 *
 * Quotes are refused rather than guessed: a model with no price, or a fal unit this app cannot bill,
 * throws PriceUnavailable with the reason.
 */
class PriceQuoter
{
    public function __construct(protected PricingSettings $settings) {}

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $inputs
     */
    public function quote(string $providerKey, string $modelKey, Capability $capability, string $prompt, array $options, array $inputs): Quote
    {
        if (in_array($providerKey, (array) config('pricing.free_providers', ['echo']), true)) {
            return Quote::free();
        }

        if ($providerKey === 'anthropic' && $capability === Capability::Text) {
            return $this->quoteText($modelKey, $prompt, $options);
        }

        if ($providerKey === 'fal') {
            return $this->quoteFal($modelKey, $capability, $options, $inputs);
        }

        throw new PriceUnavailable("No price rules exist for the [{$providerKey}] provider.");
    }

    /**
     * Settle a finished text run from the tokens the provider reported.
     *
     * @param  array<string, mixed>  $usage
     */
    public function settleText(string $providerKey, string $modelKey, array $usage): ?Quote
    {
        if ($providerKey !== 'anthropic') {
            return null;
        }

        $input = (int) ($usage['input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);
        [$inputPrice, $outputPrice] = $this->textPrices($modelKey, $input);

        $cost = Money::tokens($input, $inputPrice) + Money::tokens($output, $outputPrice);

        return new Quote($cost, $this->settings->sell($cost), 'tokens', $input + $output, false);
    }

    /** @param  array<string, mixed>  $options */
    private function quoteText(string $modelKey, string $prompt, array $options): Quote
    {
        // A conservative upper bound: about three characters per token, and the model's full reply budget.
        $system = is_string($options['system'] ?? null) ? $options['system'] : '';
        $estimatedInput = (int) ceil((mb_strlen($prompt) + mb_strlen($system)) / 3);
        [$inputPrice, $outputPrice] = $this->textPrices($modelKey, $estimatedInput);
        $maxOutput = (int) config('virgoai.anthropic.max_tokens', 8192);

        $cost = Money::tokens($estimatedInput, $inputPrice) + Money::tokens($maxOutput, $outputPrice);

        return new Quote($cost, $this->settings->sell($cost), 'tokens', $estimatedInput + $maxOutput, true);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $inputs
     */
    private function quoteFal(string $modelKey, Capability $capability, array $options, array $inputs): Quote
    {
        $endpoint = FalEndpoints::resolve($modelKey, $inputs);

        if ($endpoint === null) {
            throw new PriceUnavailable("The fal model [{$modelKey}] is not supported.");
        }

        // The rate table prices the call as it is sent: its size, quality, resolution and billed seconds.
        $rate = FalRates::quote($endpoint, $capability, $options);

        if ($rate !== null) {
            return new Quote($rate['cost_micros'], $this->settings->sell($rate['cost_micros']), $rate['unit'], $rate['quantity'], true);
        }

        $price = ModelPrice::query()
            ->where('provider_key', 'fal')
            ->where('model_key', $endpoint)
            ->first();

        if ($price === null) {
            throw new PriceUnavailable("No price is set for [{$modelKey}] yet. Run php artisan ai:sync to read fal's price.");
        }

        // A synced price is one number for the model, so it cannot tell resolutions apart. 1080p video is refused
        // until the rate table has its price, rather than charged at the cheaper rate.
        if ($capability === Capability::Video && ($options['resolution'] ?? '720p') !== '720p') {
            throw new PriceUnavailable("[{$modelKey}] has no price at {$options['resolution']} yet.");
        }

        [$quantity, $unit] = $this->fulfilledUnits($price->unit, $capability, $options);

        if ($quantity === null) {
            throw new PriceUnavailable("fal bills [{$modelKey}] per [{$price->unit}], which this app cannot price yet.");
        }

        if ($unit === 'second') {
            $quantity = FalEndpoints::billableSeconds($endpoint, $options['duration'] ?? null);
        }

        $cost = Money::times($price->cost_micros, $quantity);

        return new Quote($cost, $this->settings->sell($cost), $unit, $quantity, true);
    }

    /**
     * How many billable units one output uses, for a fal unit name.
     *
     * @param  array<string, mixed>  $options
     * @return array{0: float|null, 1: string}
     */
    private function fulfilledUnits(string $apiUnit, Capability $capability, array $options): array
    {
        if (! in_array($apiUnit, (array) config('pricing.fal_units', []), true)) {
            return [null, $apiUnit];
        }

        return match ($apiUnit) {
            'second', 'seconds' => [(float) ($options['duration'] ?? 5), 'second'],
            default => [1.0, $capability === Capability::Video ? 'video' : 'image'],
        };
    }

    /**
     * Micro-dollars per million tokens: input and output. A prompt over the long-prompt threshold uses the
     * model's long-prompt rates, when it has them.
     *
     * @return array{0: int, 1: int}
     */
    private function textPrices(string $modelKey, int $inputTokens = 0): array
    {
        $long = $inputTokens > (int) config('pricing.anthropic.long_prompt_tokens', 100000);
        $suffix = $long ? '_long' : '';

        $rows = ModelPrice::query()
            ->where('provider_key', 'anthropic')
            ->where('model_key', $modelKey)
            ->whereIn('unit', ["input_mtok{$suffix}", "output_mtok{$suffix}"])
            ->get()
            ->keyBy('unit');

        // A model with no long-prompt rate keeps its standard rate.
        if ($long && ! isset($rows["input_mtok{$suffix}"], $rows["output_mtok{$suffix}"])) {
            return $this->textPrices($modelKey, 0);
        }

        if (! isset($rows["input_mtok{$suffix}"], $rows["output_mtok{$suffix}"])) {
            throw new PriceUnavailable("No price is set for [{$modelKey}] yet. Set it on the Pricing page.");
        }

        return [$rows["input_mtok{$suffix}"]->cost_micros, $rows["output_mtok{$suffix}"]->cost_micros];
    }
}
