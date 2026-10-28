<?php

namespace App\Billing;

use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Records every step of a production and rolls them up.
 *
 * Users are shown tokens and credits per step and in total. Provider cost and the margin the platform keeps are
 * only returned to the owner, through ownerSummary().
 */
class UsageRecorder
{
    public const TYPE_REMIX = 'remix';

    public const TYPE_CLIP_RUN = 'clip_run';

    public const TYPE_CLIP_SOURCE = 'clip_source';

    public const TYPE_GENERATION = 'generation';

    public const TYPE_PRODUCTION = 'production';

    public const TYPE_FILM = 'film';

    /**
     * @param  array{input?: int, output?: int, units?: float, unit_label?: string}  $measure
     */
    public function record(User $user, string $type, string $productionId, string $step, string $providerKey, string $modelKey, Quote $quote, array $measure = []): UsageEvent
    {
        return UsageEvent::create([
            'user_id' => $user->id,
            'production_type' => $type,
            'production_id' => $productionId,
            'step' => $step,
            'provider_key' => $providerKey,
            'model_key' => $modelKey,
            'input_tokens' => (int) ($measure['input'] ?? 0),
            'output_tokens' => (int) ($measure['output'] ?? 0),
            'units' => (float) ($measure['units'] ?? $quote->quantity),
            'unit_label' => $measure['unit_label'] ?? $quote->unit,
            'cost_micros' => $quote->costMicros,
            'price_micros' => $quote->priceMicros,
        ]);
    }

    /**
     * What the user sees: each step's tokens and credits, and the totals.
     *
     * @return array{steps: list<array<string, mixed>>, total_tokens: int, total_credits: float}
     */
    public function userSummary(string $type, string $productionId): array
    {
        $events = $this->events($type, $productionId);

        return [
            'steps' => $events->map(fn (UsageEvent $event) => [
                'step' => $event->step,
                'model' => $event->model_key,
                'input_tokens' => (int) $event->input_tokens,
                'output_tokens' => (int) $event->output_tokens,
                'tokens' => (int) $event->input_tokens + (int) $event->output_tokens,
                'units' => (float) $event->units,
                'unit_label' => $event->unit_label,
                'platform_tokens' => Money::toTokens((int) $event->price_micros),
            ])->values()->all(),
            'total_tokens' => (int) $events->sum(fn (UsageEvent $e) => (int) $e->input_tokens + (int) $e->output_tokens),
            // The customer's unit: what the production cost, in platform tokens.
            'total_platform_tokens' => Money::toTokens((int) $events->sum('price_micros')),
        ];
    }

    /**
     * Owner view: cost to us, price to the user, and what we keep, per step and in total.
     *
     * @return array<string, mixed>
     */
    public function ownerSummary(string $type, string $productionId): array
    {
        $events = $this->events($type, $productionId);
        $cost = (int) $events->sum('cost_micros');
        $price = (int) $events->sum('price_micros');

        return [
            'steps' => $events->map(fn (UsageEvent $event) => [
                'step' => $event->step,
                'model' => $event->model_key,
                'tokens' => (int) $event->input_tokens + (int) $event->output_tokens,
                'cost_usd' => Money::toUsd((int) $event->cost_micros),
                'price_usd' => Money::toUsd((int) $event->price_micros),
                'kept_usd' => Money::toUsd((int) $event->price_micros - (int) $event->cost_micros),
            ])->values()->all(),
            'total_tokens' => (int) $events->sum(fn (UsageEvent $e) => (int) $e->input_tokens + (int) $e->output_tokens),
            'cost_usd' => Money::toUsd($cost),
            'price_usd' => Money::toUsd($price),
            'kept_usd' => Money::toUsd($price - $cost),
        ];
    }

    /** @return Collection<int, UsageEvent> */
    private function events(string $type, string $productionId): Collection
    {
        return UsageEvent::query()
            ->where('production_type', $type)
            ->where('production_id', $productionId)
            ->orderBy('created_at')
            ->get();
    }
}
