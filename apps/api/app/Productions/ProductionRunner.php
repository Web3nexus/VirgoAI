<?php

namespace App\Productions;

use App\Ai\Adapters\FalAdapter;
use App\Ai\Data\GenerationRequest;
use App\Ai\Fal\FalEndpoints;
use App\Ai\GenerationService;
use App\Ai\Guardrails;
use App\Ai\MediaStore;
use App\Billing\PriceQuoter;
use App\Billing\Quote;
use App\Billing\UsageRecorder;
use App\Billing\Wallet;
use App\Clips\Ffmpeg;
use App\Enums\Capability;
use App\Models\Generation;
use App\Models\Production;
use App\Models\ProductionScene;
use App\Models\Project;
use App\Skills\SkillLibrary;
use App\Styles\StylePresets;
use App\Tools\Skills\DirectorScript;
use App\Tools\Skills\DirectorSkill;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves one full video through its steps. Each call does what the current step can do now and returns. Nothing
 * blocks: provider jobs are checked on the next call.
 *
 *   queued -> script -> keyframing -> animating -> assembling -> succeeded
 *
 * Any step that fails refunds the whole reservation.
 */
class ProductionRunner
{
    public function __construct(
        protected GenerationService $generations,
        protected PriceQuoter $quotes,
        protected Wallet $wallet,
        protected UsageRecorder $usage,
        protected ProductionService $service,
        protected MediaStore $media,
    ) {}

    public function advance(Production $production): void
    {
        if ($production->isTerminal()) {
            return;
        }

        if ($this->timedOut($production)) {
            $this->service->fail($production, 'The full video took too long and was stopped. Your credit was returned.');

            return;
        }

        try {
            match ($production->status) {
                'queued' => $this->script($production),
                'keyframing' => $this->keyframes($production),
                'animating' => $this->motion($production),
                'assembling' => $this->assemble($production),
                default => null,
            };
        } catch (Throwable $e) {
            $this->service->fail($production, $e->getMessage());
        }
    }

    /** Write the script and create one scene per section, then start every keyframe. */
    private function script(Production $production): void
    {
        $directive = StylePresets::directive($production->styles ?? []);
        $system = Guardrails::withText(trim(implode("\n\n", array_filter([
            DirectorSkill::systemPrompt(DirectorSkill::SKILL_DIRECTOR, 'story', []) ?? '',
            $directive,
            SkillLibrary::brief($production->skill_key),
        ]))));

        $result = $this->generations->generate(new GenerationRequest(
            capability: Capability::Text,
            modelKey: '',
            prompt: $production->prompt,
            options: ['system' => $system],
            requestedModelKey: $production->model_key,
        ));

        if (! $result->ok) {
            throw new DomainException($result->error ?? 'The script could not be written.');
        }

        $actual = $this->quotes->settleText('anthropic', $production->model_key, $result->usage);

        // The script is paid for as soon as the model has replied, even if the reply is not usable.
        $production->forceFill([
            'script_price' => $actual?->priceMicros ?? 0,
            'script_cost' => $actual?->costMicros ?? 0,
        ])->save();

        $parsed = DirectorScript::parse((string) ($result->output['text'] ?? ''), 'story', []);

        if (! $parsed['ok']) {
            throw new DomainException('The script was not usable: '.implode(' ', $parsed['errors']));
        }

        $script = $parsed['script'];
        $production->forceFill(['script' => $script])->save();

        if ($actual !== null) {
            $this->usage->record($production->user, UsageRecorder::TYPE_PRODUCTION, $production->id, 'script', 'anthropic', $production->model_key, $actual, [
                'input' => (int) ($result->usage['input_tokens'] ?? 0),
                'output' => (int) ($result->usage['output_tokens'] ?? 0),
                'units' => 1,
                'unit_label' => 'script',
            ]);
        }

        $max = (int) config('virgoai.productions.max_scenes', 6);
        $sections = array_slice($script['sections'], 0, $max);
        $position = 0;

        foreach ($sections as $section) {
            $position++;
            $environment = collect($script['environments'])->firstWhere('environment_id', $section['environment_id']);
            $people = collect($script['characters'])->whereIn('character_id', $section['character_ids'] ?? [])->pluck('identity_block')->implode(' ');
            $beat = trim((string) ($section['narration'] ?? '')) ?: collect($script['lines'] ?? [])->where('section_id', $section['section_id'])->pluck('text')->implode(' ');

            ProductionScene::create([
                'production_id' => $production->id,
                'position' => $position,
                'title' => mb_substr(ucfirst((string) $section['purpose']).': '.$beat, 0, 200),
                'image_prompt' => Guardrails::withMedia(trim(($environment['prompt'] ?? '')."\n".$people."\n".$beat."\n".$directive."\nA single cinematic frame.")),
                'video_prompt' => Guardrails::withMedia(trim($beat."\nGentle, natural motion. ".$directive)),
                'status' => 'pending',
            ]);
        }

        if ($position === 0) {
            throw new DomainException('The script has no scenes.');
        }

        $production->forceFill(['status' => 'keyframing'])->save();

        foreach ($production->scenes()->get() as $scene) {
            $this->startKeyframe($production, $scene);
        }
    }

    private function startKeyframe(Production $production, ProductionScene $scene): void
    {
        $format = $production->format;
        // The reference image, when there is one, is the source every keyframe is edited from.
        $inputs = $production->reference_url ? ['image_url' => $production->reference_url] : [];
        $quote = $this->quotes->quote('fal', FalEndpoints::IMAGE_TEXT, Capability::Image, $scene->image_prompt, ['size' => $format], $inputs);

        $result = $this->generations->generate(new GenerationRequest(
            capability: Capability::Image,
            modelKey: '',
            prompt: $scene->image_prompt,
            inputs: $inputs,
            options: ['size' => $format],
            requestedModelKey: FalEndpoints::IMAGE_TEXT,
        ));

        if (! $result->ok || ! $result->isQueued()) {
            throw new DomainException($result->error ?? 'A keyframe could not be started.');
        }

        $scene->forceFill([
            'status' => 'keyframing',
            'keyframe_job' => $result->job,
            'keyframe_price' => $quote->priceMicros,
            'keyframe_cost' => $quote->costMicros,
        ])->save();
    }

    private function keyframes(Production $production): void
    {
        $adapter = app(FalAdapter::class);

        foreach ($production->scenes()->where('status', 'keyframing')->get() as $scene) {
            $result = $adapter->poll((array) $scene->keyframe_job);

            if ($result->isQueued()) {
                continue;
            }
            if (! $result->ok) {
                // A failed job is not billed by fal, so the scene stops counting as sent before the production is settled.
                $scene->forceFill(['status' => 'failed', 'error' => $result->error])->save();

                throw new DomainException('A keyframe failed: '.($result->error ?? 'unknown error'));
            }

            $url = (string) $result->output['url'];
            $path = "productions/{$production->id}/keyframe-{$scene->position}.png";
            Storage::disk('public')->makeDirectory("productions/{$production->id}");
            $this->media->store($url, $path);

            $scene->forceFill(['status' => 'keyframed', 'keyframe_url' => $url, 'keyframe_path' => $path])->save();
            $this->usage->record($production->user, UsageRecorder::TYPE_PRODUCTION, $production->id, 'keyframe', 'fal', FalEndpoints::IMAGE_TEXT, $this->costOf($scene->keyframe_cost, $scene->keyframe_price), [
                'units' => 1, 'unit_label' => 'keyframe',
            ]);
        }

        if ($production->scenes()->where('status', 'keyframing')->exists()) {
            return;
        }

        foreach ($production->scenes()->where('status', 'keyframed')->get() as $scene) {
            $this->startMotion($production, $scene);
        }

        $production->forceFill(['status' => 'animating'])->save();
    }

    private function startMotion(Production $production, ProductionScene $scene): void
    {
        $seconds = (int) config('virgoai.productions.scene_seconds', 5);
        $quote = $this->quotes->quote('fal', $production->video_model_key ?? FalEndpoints::VIDEO_IMAGE, Capability::Video, $scene->video_prompt, ['duration' => $seconds, 'resolution' => $production->quality, 'size' => $production->format], ['image_url' => $scene->keyframe_url]);

        $result = $this->generations->generate(new GenerationRequest(
            capability: Capability::Video,
            modelKey: '',
            prompt: $scene->video_prompt,
            inputs: ['image_url' => $scene->keyframe_url],
            options: ['duration' => $seconds, 'resolution' => $production->quality, 'size' => $production->format],
            requestedModelKey: $production->video_model_key ?? FalEndpoints::VIDEO_IMAGE,
        ));

        if (! $result->ok || ! $result->isQueued()) {
            throw new DomainException($result->error ?? 'A motion clip could not be started.');
        }

        $scene->forceFill([
            'status' => 'animating',
            'motion_job' => $result->job,
            'motion_price' => $quote->priceMicros,
            'motion_cost' => $quote->costMicros,
        ])->save();
    }

    private function motion(Production $production): void
    {
        $adapter = app(FalAdapter::class);

        foreach ($production->scenes()->where('status', 'animating')->get() as $scene) {
            $result = $adapter->poll((array) $scene->motion_job);

            if ($result->isQueued()) {
                continue;
            }
            if (! $result->ok) {
                $scene->forceFill(['status' => 'failed', 'error' => $result->error])->save();

                throw new DomainException('A motion clip failed: '.($result->error ?? 'unknown error'));
            }

            $path = "productions/{$production->id}/scene-{$scene->position}.mp4";
            if (! $this->media->store((string) $result->output['url'], $path)) {
                throw new DomainException('A motion clip could not be saved.');
            }

            $scene->forceFill(['status' => 'done', 'clip_path' => $path])->save();
            $this->usage->record($production->user, UsageRecorder::TYPE_PRODUCTION, $production->id, 'motion', 'fal', $production->video_model_key ?? FalEndpoints::VIDEO_IMAGE, $this->costOf($scene->motion_cost, $scene->motion_price), [
                'units' => $seconds = (int) config('virgoai.productions.scene_seconds', 5), 'unit_label' => 'seconds of motion',
            ]);
        }

        if ($production->scenes()->where('status', 'animating')->exists()) {
            return;
        }

        $production->forceFill(['status' => 'assembling'])->save();
        $this->assemble($production);
    }

    /** Fit every clip to the format, join them in order, and settle the reservation to what was used. */
    private function assemble(Production $production): void
    {
        $format = Project::FORMATS[$production->format] ?? Project::FORMATS[Project::DEFAULT_FORMAT];
        $ffmpeg = Ffmpeg::fromConfig();
        $parts = [];

        foreach ($production->scenes()->where('status', 'done')->get() as $scene) {
            $source = Storage::disk('public')->path($scene->clip_path);
            $fitted = Storage::disk('public')->path("productions/{$production->id}/part-{$scene->position}.mp4");
            $ffmpeg->normalize($source, $fitted, $format['width'], $format['height']);
            $parts[] = $fitted;
        }

        if ($parts === []) {
            throw new DomainException('No scenes were finished.');
        }

        $final = "productions/{$production->id}/final.mp4";
        $ffmpeg->concat($parts, Storage::disk('public')->path($final));

        $generation = Generation::query()->find($production->generation_id);
        $actual = $this->actualPrice($production);

        if ($generation !== null) {
            $this->wallet->settle($generation, $actual);
            $generation->forceFill(['status' => Generation::STATUS_SUCCEEDED, 'output_path' => $final, 'output_text' => null])->save();
        }

        $production->forceFill(['status' => 'succeeded', 'output_path' => $final, 'error' => null])->save();
    }

    /** What the finished steps cost the user. The reservation covers this; the rest is refunded by settle(). */
    private function actualPrice(Production $production): Quote
    {
        $scenes = $production->scenes()->get();
        $price = (int) $production->script_price + (int) $scenes->sum('keyframe_price') + (int) $scenes->sum('motion_price');
        $cost = (int) $production->script_cost + (int) $scenes->sum('keyframe_cost') + (int) $scenes->sum('motion_cost');

        return new Quote($cost, $price, 'production', $scenes->count(), false);
    }

    private function costOf(int $cost, int $price): Quote
    {
        return new Quote($cost, $price, 'step', 1, false);
    }

    private function timedOut(Production $production): bool
    {
        $max = (int) config('virgoai.productions.max_wait_seconds', 3600);

        return CarbonImmutable::instance($production->created_at)->diffInSeconds(now()) >= $max;
    }
}
