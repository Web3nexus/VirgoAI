<?php

namespace App\Jobs;

use App\Ai\Fal\FalQueue;
use App\Billing\Wallet;
use App\Clips\ClipPicker;
use App\Models\ClipSource;
use App\Models\Generation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Check a transcription job until fal finishes it. Saves the transcript, or refunds the charge if it cannot.
 */
class PollTranscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly string $sourceId) {}

    public function handle(FalQueue $queue, Wallet $wallet): void
    {
        $source = ClipSource::query()->find($this->sourceId);

        if ($source === null || $source->status !== ClipSource::STATUS_TRANSCRIBING) {
            return;
        }

        $generation = Generation::query()->find($source->transcript_generation_id);

        if ($generation === null) {
            return;
        }

        $maxWait = (int) config('virgoai.clips.max_wait_seconds', 3600);

        try {
            $check = $queue->check((array) $generation->provider_job);

            // fal reports a job that will never finish as FAILED or ERROR. Stop polling and refund.
            if (in_array($check['state'], ['FAILED', 'ERROR'], true)) {
                $this->fail($source, $generation, $wallet, 'Transcription failed at the transcriber. The charge was refunded.');

                return;
            }

            if ($check['state'] !== 'COMPLETED') {
                if (CarbonImmutable::instance($generation->created_at)->diffInSeconds(now()) >= $maxWait) {
                    $this->fail($source, $generation, $wallet, 'Transcription did not finish in time. Try again.');

                    return;
                }

                $this->again();

                return;
            }

            $segments = ClipPicker::segmentsFromChunks((array) ($check['body']['chunks'] ?? []), (float) $source->duration_seconds);

            if ($segments === []) {
                $this->fail($source, $generation, $wallet, 'The video has no speech that could be transcribed.');

                return;
            }

            $generation->forceFill(['status' => Generation::STATUS_SUCCEEDED, 'output_text' => count($segments).' transcript segments'])->save();
            $source->forceFill(['status' => ClipSource::STATUS_READY, 'transcript' => $segments, 'error' => null])->save();

            Storage::disk('public')->delete("clips/audio/{$source->id}.mp3");
        } catch (Throwable $e) {
            $this->fail($source, $generation, $wallet, 'Transcription failed: '.$e->getMessage());
        }
    }

    private function again(): void
    {
        self::dispatch($this->sourceId)->delay(now()->addSeconds((int) config('virgoai.clips.poll_interval_seconds', 15)));
    }

    private function fail(ClipSource $source, Generation $generation, Wallet $wallet, string $reason): void
    {
        $wallet->refund($generation, 'Refunded: transcription did not finish');
        $generation->forceFill(['status' => Generation::STATUS_FAILED, 'error' => $reason])->save();
        $source->forceFill(['status' => ClipSource::STATUS_FAILED, 'error' => $reason])->save();
        Storage::disk('public')->delete("clips/audio/{$source->id}.mp3");
    }
}
