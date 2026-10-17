<?php

namespace App\Clips;

use App\Ai\Data\GenerationRequest;
use App\Ai\Fal\FalEndpoints;
use App\Ai\Fal\FalQueue;
use App\Ai\GenerationService;
use App\Billing\PriceQuoter;
use App\Billing\Quote;
use App\Billing\UsageRecorder;
use App\Billing\Wallet;
use App\Enums\Capability;
use App\Models\ClipRun;
use App\Models\ClipSource;
use App\Models\Generation;
use App\Models\User;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The clipping steps, one method per stage. Each one is idempotent to a retry: a stage that already
 * happened is skipped, and money is only ever moved through the Wallet.
 */
class ClipService
{
    public const TEXT_MODEL_DEFAULT = 'claude-haiku-5-5';

    public function __construct(
        protected PriceQuoter $quotes,
        protected Wallet $wallet,
        protected GenerationService $generations,
        protected FalQueue $queue,
    ) {}

    public function createUpload(User $user, UploadedFile $file): ClipSource
    {
        $source = ClipSource::create([
            'user_id' => $user->id,
            'kind' => ClipSource::KIND_UPLOAD,
            'status' => ClipSource::STATUS_UPLOADED,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
        ]);

        $extension = strtolower($file->getClientOriginalExtension() ?: 'mp4');
        $path = "clips/sources/{$source->id}/source.{$extension}";
        Storage::disk('local')->putFileAs("clips/sources/{$source->id}", $file, "source.{$extension}");

        $source->forceFill(['storage_path' => $path])->save();

        return $source;
    }

    /**
     * A YouTube link is played in full. It is never downloaded: that breaks YouTube's terms.
     */
    public function createYoutube(User $user, string $url): ClipSource
    {
        $id = self::youtubeId($url);

        if ($id === null) {
            throw new DomainException('That is not a YouTube video link.');
        }

        return ClipSource::create([
            'user_id' => $user->id,
            'kind' => ClipSource::KIND_YOUTUBE,
            'status' => ClipSource::STATUS_READY,
            'youtube_id' => $id,
            'original_name' => 'YouTube video',
        ]);
    }

    public static function youtubeId(string $url): ?string
    {
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * Charge for transcription, extract the audio, and send it to the transcriber. Returns the generation that
     * holds the charge, so the poller can settle or refund it.
     */
    public function startTranscription(ClipSource $source, float $duration): Generation
    {
        $user = $source->user;
        $label = 'Transcribe '.($source->original_name ?? 'video');

        $quote = $this->quotes->quote('fal', FalEndpoints::TRANSCRIBE, Capability::Audio, $label, ['duration' => $duration], []);

        $generation = $this->wallet->reserveRun($user, $quote, [
            'user_id' => $user->id,
            'tool' => 'transcribe',
            'capability' => Capability::Audio->value,
            'provider_key' => 'fal',
            'model_key' => FalEndpoints::TRANSCRIBE,
            'prompt' => $label,
            'options' => ['duration' => $duration],
            'output_type' => 'transcript',
        ]);

        $audioPath = "clips/audio/{$source->id}.mp3";
        Storage::disk('public')->makeDirectory('clips/audio');
        $ffmpeg = Ffmpeg::fromConfig();
        $ffmpeg->extractAudio(Storage::disk('local')->path($source->storage_path), Storage::disk('public')->path($audioPath));

        $job = $this->queue->submit(FalEndpoints::TRANSCRIBE, [
            'audio_url' => Storage::disk('public')->url($audioPath),
            'task' => 'transcribe',
            'chunk_level' => 'segment',
        ]);

        $generation->forceFill(['status' => Generation::STATUS_RUNNING, 'provider_job' => $job])->save();
        app(UsageRecorder::class)->record($user, UsageRecorder::TYPE_CLIP_SOURCE, $source->id, 'transcribe', 'fal', FalEndpoints::TRANSCRIBE, $quote, [
            'units' => $duration,
            'unit_label' => 'seconds of audio',
        ]);
        $source->forceFill([
            'status' => ClipSource::STATUS_TRANSCRIBING,
            'duration_seconds' => $duration,
            'transcript_generation_id' => $generation->id,
        ])->save();

        return $generation;
    }

    /**
     * Transcribe on our own engine. The transcript is saved and the source is ready when this returns.
     */
    public function transcribeLocally(ClipSource $source, LocalTranscriber $engine): void
    {
        $wav = Storage::disk('local')->path("clips/audio/{$source->id}.wav");
        Storage::disk('local')->makeDirectory('clips/audio');

        try {
            Ffmpeg::fromConfig()->extractWav(Storage::disk('local')->path($source->storage_path), $wav);
            $segments = $engine->transcribe($wav);

            // Our own engine: the step is recorded with zero provider cost, so the total shows the work done.
            app(UsageRecorder::class)->record($source->user, UsageRecorder::TYPE_CLIP_SOURCE, $source->id, 'transcribe', 'local', 'whisper.cpp', Quote::free(), [
                'units' => (float) $source->duration_seconds,
                'unit_label' => 'seconds of audio',
            ]);

            if ($segments === []) {
                throw new DomainException('The video has no speech that could be transcribed. Add a transcript file instead.');
            }

            $source->forceFill(['status' => ClipSource::STATUS_READY, 'transcript' => $segments, 'error' => null])->save();
        } finally {
            @unlink($wav);
        }
    }

    /**
     * Replace a source's transcript with captions the owner supplied (SRT or VTT). No download is involved.
     */
    public function importTranscript(ClipSource $source, string $content): ClipSource
    {
        $segments = SubtitleParser::parse($content);
        $source->forceFill(['status' => ClipSource::STATUS_READY, 'transcript' => $segments, 'error' => null])->save();

        return $source;
    }

    /** The source's length: its probed duration, or for a YouTube source the end of its last caption. */
    public static function lengthOf(ClipSource $source): float
    {
        if ($source->duration_seconds) {
            return (float) $source->duration_seconds;
        }

        return (float) collect($source->transcript ?? [])->max('end');
    }

    /**
     * Start a clip request. The prompt includes the whole transcript, so the quote reflects the real size of
     * the job: a long video costs more to analyse than a short one.
     */
    public function requestRun(User $user, ClipSource $source, string $request, string $modelKey, int $count, int $minSeconds, int $maxSeconds): ClipRun
    {
        $prompt = $this->analysisPrompt($source, $request);
        $system = ClipPicker::systemPrompt($count, $minSeconds, $maxSeconds);

        $quote = $this->quotes->quote('anthropic', $modelKey, Capability::Text, $prompt, ['system' => $system], []);

        $generation = $this->wallet->reserveRun($user, $quote, [
            'user_id' => $user->id,
            'tool' => 'clips',
            'capability' => Capability::Text->value,
            'provider_key' => 'anthropic',
            'model_key' => $modelKey,
            'prompt' => mb_substr($request, 0, 4000),
            'options' => ['count' => $count, 'min_seconds' => $minSeconds, 'max_seconds' => $maxSeconds],
            'output_type' => 'clips',
        ]);

        return ClipRun::create([
            'user_id' => $user->id,
            'clip_source_id' => $source->id,
            'generation_id' => $generation->id,
            'request' => $request,
            'model_key' => $modelKey,
            'count' => $count,
            'min_seconds' => $minSeconds,
            'max_seconds' => $maxSeconds,
            'status' => ClipRun::STATUS_QUEUED,
        ]);
    }

    /** The prompt the analysis is quoted and sent with: the request, then the transcript. */
    public function analysisPrompt(ClipSource $source, string $request): string
    {
        return "Request: {$request}\n\nTranscript:\n".ClipPicker::transcriptBlock($source->transcript ?? []);
    }

    /**
     * Ask the model for clips and settle the charge to the tokens it used.
     *
     * @return array{clips: list<array<string, mixed>>, dropped: int, usage: array<string, mixed>, provider: string}
     */
    public function analyse(ClipRun $run, ClipSource $source): array
    {
        $prompt = $this->analysisPrompt($source, $run->request);
        $system = ClipPicker::systemPrompt($run->count, $run->min_seconds, $run->max_seconds);

        $result = $this->generations->generate(new GenerationRequest(
            capability: Capability::Text,
            modelKey: '',
            prompt: $prompt,
            options: ['system' => $system],
            requestedModelKey: $run->model_key,
        ));

        if (! $result->ok) {
            throw new DomainException($result->error ?? 'The model did not answer.');
        }

        $generation = Generation::query()->findOrFail($run->generation_id);
        $actual = $this->quotes->settleText('anthropic', $run->model_key, $result->usage);

        if ($actual !== null) {
            $this->wallet->settle($generation, $actual);
            app(UsageRecorder::class)->record($run->user, UsageRecorder::TYPE_CLIP_RUN, $run->id, 'clip_analysis', 'anthropic', $run->model_key, $actual, [
                'input' => (int) ($result->usage['input_tokens'] ?? 0),
                'output' => (int) ($result->usage['output_tokens'] ?? 0),
                'units' => 1,
                'unit_label' => 'analysis',
            ]);
        }

        $candidates = ClipPicker::parseReply((string) ($result->output['text'] ?? ''));

        if ($candidates === null) {
            throw new DomainException('The model did not return clips in the expected form. Try the request again.');
        }

        $validated = ClipPicker::validate($candidates, self::lengthOf($source), $run->min_seconds, $run->max_seconds, $run->count);

        return ['clips' => $validated['clips'], 'dropped' => $validated['dropped'], 'usage' => $result->usage, 'provider' => 'anthropic'];
    }

    public static function quoteError(\Throwable $e): string
    {
        return $e->getMessage();
    }

    public static function newStoragePath(string $prefix): string
    {
        return $prefix.'/'.Str::ulid().'.mp4';
    }
}
