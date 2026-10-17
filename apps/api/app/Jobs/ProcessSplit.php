<?php

namespace App\Jobs;

use App\Clips\Ffmpeg;
use App\Clips\Split;
use App\Models\Clip;
use App\Models\ClipRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Cut an uploaded video into consecutive parts. Costs nothing from the wallet: it is local work.
 */
class ProcessSplit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public readonly string $runId) {}

    public function handle(): void
    {
        $run = ClipRun::query()->with('source')->find($this->runId);

        if ($run === null || $run->status !== ClipRun::STATUS_QUEUED) {
            return;
        }

        $run->forceFill(['status' => ClipRun::STATUS_RUNNING])->save();
        $source = $run->source;

        try {
            $local = Storage::disk('local')->path($source->storage_path);
            Storage::disk('public')->makeDirectory("clips/runs/{$run->id}");
            $ffmpeg = Ffmpeg::fromConfig();

            $parts = Split::plan(
                (float) $source->duration_seconds,
                $run->max_seconds,
                is_array($source->transcript) ? $source->transcript : [],
            );

            foreach ($parts as $index => $part) {
                $position = $index + 1;
                $path = "clips/runs/{$run->id}/{$position}.mp4";
                $ffmpeg->cut($local, $part['start'], $part['end'], Storage::disk('public')->path($path));

                Clip::create([
                    'clip_run_id' => $run->id,
                    'position' => $position,
                    'start_seconds' => $part['start'],
                    'end_seconds' => $part['end'],
                    'title' => 'Part '.$position,
                    'reason' => 'Automatic split',
                    'storage_path' => $path,
                ]);
            }

            $run->forceFill(['status' => ClipRun::STATUS_SUCCEEDED, 'count' => count($parts), 'error' => null])->save();
        } catch (Throwable $e) {
            $run->forceFill(['status' => ClipRun::STATUS_FAILED, 'error' => $e->getMessage()])->save();
        }
    }
}
