<?php

namespace App\Ai;

use App\Ai\Contracts\AsyncProviderAdapter;
use App\Billing\Wallet;
use App\Clips\Ffmpeg;
use App\Models\AiUsageRecord;
use App\Models\Generation;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Advances one queued generation: asks its provider for the job state, then either records a
 * timeout, saves the finished file, or records the failure. Every path ends the usage record too.
 */
class GenerationCompleter
{
    public function __construct(
        protected AdapterRegistry $adapters,
        protected MediaStore $media,
        protected Wallet $wallet,
    ) {}

    /**
     * Check the job once. Returns true once the generation has finished, whether it succeeded or failed.
     */
    public function advance(Generation $generation): bool
    {
        if ($generation->status !== Generation::STATUS_RUNNING) {
            return true;
        }

        $job = $generation->provider_job ?? [];

        if (! $this->adapters->has((string) $generation->provider_key)) {
            return $this->fail($generation, 'The provider for this run is no longer configured.');
        }

        $adapter = $this->adapters->resolve((string) $generation->provider_key);

        if (! $adapter instanceof AsyncProviderAdapter) {
            return $this->fail($generation, 'The provider for this run cannot be polled.');
        }

        $result = $adapter->poll($job);

        if ($result->isQueued()) {
            $maxWait = (int) config('virgoai.generation.max_wait_seconds', 1200);

            if ($generation->created_at->diffInSeconds(now()) >= $maxWait) {
                return $this->fail($generation, 'The render did not finish in time. Try the run again.');
            }

            return false;
        }

        if (! $result->ok) {
            return $this->fail($generation, $result->error ?? 'The provider failed.');
        }

        $url = $result->output['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return $this->fail($generation, 'The provider finished without a file.');
        }

        $extension = $generation->output_type === 'video' ? 'mp4' : 'png';
        $path = "generations/{$generation->id}.{$extension}";

        if (! $this->media->store($url, $path)) {
            return $this->fail($generation, 'The finished file could not be saved. Try the run again.');
        }

        if ($generation->output_type === 'video') {
            $this->fitToFormat($path, (string) ($generation->options['size'] ?? ''));
        }

        $generation->forceFill([
            'status' => Generation::STATUS_SUCCEEDED,
            'output_path' => $path,
            'provider_job' => [...$job, 'output_url' => $url],
            'error' => null,
        ])->save();

        $this->finishUsage($job, 'succeeded', null);

        return true;
    }

    /**
     * Make the video exactly the chosen format's size. If the resize fails, the provider's file is kept as it is and
     * the problem is logged, so a finished render is never lost to a conversion error.
     */
    private function fitToFormat(string $path, string $formatKey): void
    {
        $format = Project::FORMATS[$formatKey] ?? null;

        if ($format === null) {
            return;
        }

        $disk = Storage::disk('public');
        $source = $disk->path($path);
        $fitted = $source.'.fit.mp4';

        try {
            Ffmpeg::fromConfig()->normalize($source, $fitted, $format['width'], $format['height']);
            rename($fitted, $source);
        } catch (\Throwable $e) {
            @unlink($fitted);
            Log::warning('Could not fit video to format', ['format' => $formatKey, 'error' => $e->getMessage()]);
        }
    }

    private function fail(Generation $generation, string $error): bool
    {
        $job = $generation->provider_job ?? [];

        $this->wallet->refund($generation, 'Refunded: render did not finish');

        $generation->forceFill([
            'status' => Generation::STATUS_FAILED,
            'error' => $error,
        ])->save();

        $this->finishUsage($job, 'failed', $error);

        return true;
    }

    /** @param  array<string, mixed>  $job */
    private function finishUsage(array $job, string $status, ?string $error): void
    {
        $recordId = $job['usage_record_id'] ?? null;

        if (is_string($recordId) && $recordId !== '') {
            AiUsageRecord::whereKey($recordId)->update(['status' => $status, 'error' => $error]);
        }
    }
}
