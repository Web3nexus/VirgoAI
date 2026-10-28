<?php

namespace App\Productions;

use App\Ai\Fal\FalEndpoints;
use App\Billing\InsufficientCredit;
use App\Billing\PriceQuoter;
use App\Billing\PriceUnavailable;
use App\Billing\Quote;
use App\Billing\Wallet;
use App\Enums\Capability;
use App\Jobs\ProductionTick;
use App\Models\Generation;
use App\Models\Production;
use App\Models\Project;
use App\Models\User;
use App\Skills\SkillLibrary;
use App\Styles\StylePresets;
use App\Tools\Skills\DirectorSkill;
use DomainException;

/**
 * Starts a full video. The whole estimated cost is reserved up front, then settled to what the steps really used.
 */
class ProductionService
{
    public const TEXT_MODEL = 'claude-sonnet-5-5';

    public function __construct(
        protected PriceQuoter $quotes,
        protected Wallet $wallet,
    ) {}

    /**
     * What a full video will cost at most: the script, plus a keyframe and a motion clip for the largest number of scenes.
     *
     * @return array{price_micros: int, cost_micros: int, scenes: int}
     */
    public function estimate(string $prompt, string $format, string $quality, string $model, ?string $skillKey = null, ?string $reference = null, ?string $videoModel = null): array
    {
        $system = trim((DirectorSkill::systemPrompt(DirectorSkill::SKILL_DIRECTOR, 'story', []) ?? '')."\n\n".SkillLibrary::brief($skillKey));
        $scenes = (int) config('virgoai.productions.max_scenes', 6);
        $seconds = (int) config('virgoai.productions.scene_seconds', 5);

        $script = $this->quotes->quote('anthropic', $model, Capability::Text, $prompt, ['system' => $system], []);
        // With a reference image every keyframe is an edit of it, so it is priced as that endpoint.
        $keyframe = $reference === null
            ? $this->quotes->quote('fal', FalEndpoints::IMAGE_TEXT, Capability::Image, $prompt, ['size' => $format], [])
            : $this->quotes->quote('fal', FalEndpoints::IMAGE_TEXT, Capability::Image, $prompt, ['size' => $format], ['image_url' => $reference]);
        $motion = $this->quotes->quote('fal', $videoModel ?? FalEndpoints::VIDEO_IMAGE, Capability::Video, $prompt, ['duration' => $seconds, 'resolution' => $quality], ['image_url' => 'https://example.invalid/k.png']);

        return [
            'price_micros' => $script->priceMicros + $scenes * ($keyframe->priceMicros + $motion->priceMicros),
            'cost_micros' => $script->costMicros + $scenes * ($keyframe->costMicros + $motion->costMicros),
            'scenes' => $scenes,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $styles
     */
    public function start(User $user, string $prompt, string $format, string $quality, ?array $styles = null, string $model = self::TEXT_MODEL, ?string $skillKey = null, ?string $referenceUrl = null, ?string $videoModel = null): Production
    {
        $prompt = trim($prompt);
        if (preg_match('~^(https?://)?(www\.|m\.)?(youtube\.com|youtu\.be)/~i', $prompt)) {
            throw new DomainException('A YouTube link is clipped or watched, not turned into a full video. Describe the video you want, or paste its captions.');
        }

        if (! array_key_exists($format, Project::FORMATS)) {
            throw new DomainException('Choose a video format.');
        }
        if (! in_array($quality, ['720p', '1080p'], true)) {
            throw new DomainException('Choose 720p or 1080p.');
        }

        $styles = array_filter((array) $styles);
        $directive = StylePresets::directive($styles);
        $skillKey = $skillKey !== null && SkillLibrary::exists($skillKey) ? $skillKey : null;
        $videoModel = $videoModel !== null && in_array($videoModel, FalEndpoints::selectableVideoIds(), true) ? $videoModel : FalEndpoints::VIDEO_IMAGE;
        $estimate = $this->estimate($prompt.' '.$directive, $format, $quality, $model, $skillKey, $referenceUrl, $videoModel);
        if ($estimate['price_micros'] > (int) config('virgoai.guardrails.max_production_price_micros')) {
            throw new DomainException('This full video is over the per-production limit. Use a shorter prompt or 720p.');
        }

        $quote = new Quote($estimate['cost_micros'], $estimate['price_micros'], 'production', 1, true);

        $generation = $this->wallet->reserveRun($user, $quote, [
            'user_id' => $user->id,
            'tool' => 'production',
            'capability' => Capability::Video->value,
            'provider_key' => 'fal',
            'model_key' => $videoModel,
            'prompt' => mb_substr($prompt, 0, 4000),
            'options' => ['format' => $format, 'quality' => $quality, 'styles' => $styles, 'skill_key' => $skillKey, 'reserved_scenes' => $estimate['scenes']],
            'output_type' => 'video',
        ]);

        $production = Production::create([
            'user_id' => $user->id,
            'prompt' => $prompt,
            'format' => $format,
            'quality' => $quality,
            'styles' => $styles === [] ? null : $styles,
            'skill_key' => $skillKey,
            'reference_url' => $referenceUrl,
            'model_key' => $model,
            'video_model_key' => $videoModel,
            'status' => 'queued',
            'generation_id' => $generation->id,
        ]);

        ProductionTick::dispatch($production->id);

        return $production;
    }

    /**
     * Stop the production. Work already sent to fal is charged, because fal bills it. Work that was never sent is
     * given back. The accounting is taken before the scenes are marked failed, because their status records what was sent.
     */
    public function fail(Production $production, string $reason): void
    {
        $generation = Generation::query()->find($production->generation_id);

        if ($generation !== null) {
            $this->wallet->settle($generation, $this->submittedQuote($production));
            $generation->forceFill(['status' => Generation::STATUS_FAILED, 'error' => $reason])->save();
        }

        $production->forceFill(['status' => 'failed', 'error' => $reason])->save();
        $production->scenes()->where('status', '!=', 'done')->update(['status' => 'failed']);
    }

    /**
     * What the production has sent to a provider so far: the script, every keyframe that was started, and every
     * motion clip that was started. A step that never started is not counted.
     */
    public function submittedQuote(Production $production): Quote
    {
        $scenes = $production->scenes()->get();
        $keyframed = $scenes->whereIn('status', ['keyframing', 'keyframed', 'animating', 'done']);
        $animated = $scenes->whereIn('status', ['animating', 'done']);

        $price = (int) $production->script_price + (int) $keyframed->sum('keyframe_price') + (int) $animated->sum('motion_price');
        $cost = (int) $production->script_cost + (int) $keyframed->sum('keyframe_cost') + (int) $animated->sum('motion_cost');

        return new Quote($cost, $price, 'production', $scenes->count(), false);
    }

    public static function insufficient(InsufficientCredit|PriceUnavailable $e): string
    {
        return $e->getMessage();
    }
}
