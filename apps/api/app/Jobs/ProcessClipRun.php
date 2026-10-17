<?php

namespace App\Jobs;

use App\Billing\Wallet;
use App\Clips\ClipService;
use App\Clips\Ffmpeg;
use App\Models\Clip;
use App\Models\ClipRun;
use App\Models\ClipSource;
use App\Models\Generation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pick the clips with the model, then cut each one from the source. A failure refunds the run.
 */
class ProcessClipRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly string $runId) {}

    public function handle(ClipService $clips, Wallet $wallet): void
    {
        $run = ClipRun::query()->with('source')->find($this->runId);

        if ($run === null || $run->status !== ClipRun::STATUS_QUEUED) {
            return;
        }

        $run->forceFill(['status' => ClipRun::STATUS_RUNNING])->save();
        $generation = Generation::query()->find($run->generation_id);

        try {
            $source = $run->source;
            $picked = $clips->analyse($run, $source);

            if ($picked['clips'] === []) {
                throw new \DomainException('No moment in the video matched the request. Try a broader request. The charge was refunded.');
            }

            $local = $source->storage_path ? Storage::disk('local')->path($source->storage_path) : null;
            $ffmpeg = Ffmpeg::fromConfig();
            Storage::disk('public')->makeDirectory("clips/runs/{$run->id}");

            foreach ($picked['clips'] as $position => $clip) {
                // A YouTube source is not cut: the moment is played from YouTube's own player, between two times.
                $path = null;
                if ($source->kind === ClipSource::KIND_UPLOAD) {
                    $path = "clips/runs/{$run->id}/".($position + 1).'.mp4';
                    $ffmpeg->cut($local, $clip['start'], $clip['end'], Storage::disk('public')->path($path));
                }

                Clip::create([
                    'clip_run_id' => $run->id,
                    'position' => $position + 1,
                    'start_seconds' => $clip['start'],
                    'end_seconds' => $clip['end'],
                    'title' => $clip['title'],
                    'reason' => $clip['reason'],
                    'storage_path' => $path,
                ]);
            }

            $run->forceFill(['status' => ClipRun::STATUS_SUCCEEDED, 'error' => null])->save();
        } catch (Throwable $e) {
            if ($generation !== null) {
                $wallet->refund($generation, 'Refunded: clip request did not finish');
                $generation->forceFill(['status' => Generation::STATUS_FAILED, 'error' => $e->getMessage()])->save();
            }

            $run->forceFill(['status' => ClipRun::STATUS_FAILED, 'error' => $e->getMessage()])->save();
        }
    }
}
