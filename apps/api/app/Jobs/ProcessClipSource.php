<?php

namespace App\Jobs;

use App\Clips\ClipService;
use App\Clips\Ffmpeg;
use App\Clips\LocalTranscriber;
use App\Models\ClipSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Probe an uploaded video, then start its transcription. Runs once per upload; a failure is saved on the source.
 */
class ProcessClipSource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public readonly string $sourceId) {}

    public function handle(ClipService $clips): void
    {
        $source = ClipSource::query()->find($this->sourceId);

        if ($source === null || $source->status !== ClipSource::STATUS_UPLOADED) {
            return;
        }

        $source->forceFill(['status' => ClipSource::STATUS_PROBING])->save();

        try {
            $ffmpeg = Ffmpeg::fromConfig();
            $info = $ffmpeg->probe(Storage::disk('local')->path($source->storage_path));

            $source->forceFill(['width' => $info['width'], 'height' => $info['height'], 'duration_seconds' => $info['duration']])->save();

            $local = LocalTranscriber::fromConfig();

            if (config('virgoai.clips.transcriber') === 'local' && $local->available()) {
                // Our own engine: runs here, costs no API charge, and the transcript is ready when it returns.
                $clips->transcribeLocally($source, $local);

                return;
            }

            $clips->startTranscription($source, $info['duration']);
            PollTranscription::dispatch($source->id)
                ->delay(now()->addSeconds((int) config('virgoai.clips.poll_interval_seconds', 15)));
        } catch (Throwable $e) {
            $source->forceFill(['status' => ClipSource::STATUS_FAILED, 'error' => ClipService::quoteError($e)])->save();
        }
    }
}
