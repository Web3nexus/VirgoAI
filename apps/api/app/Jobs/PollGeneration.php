<?php

namespace App\Jobs;

use App\Ai\GenerationCompleter;
use App\Models\Generation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Checks a queued generation and re-queues itself until the provider finishes it.
 */
class PollGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public string $generationId) {}

    public function handle(GenerationCompleter $completer): void
    {
        $generation = Generation::query()->find($this->generationId);

        if ($generation === null || $completer->advance($generation)) {
            return;
        }

        self::dispatch($this->generationId)
            ->delay(now()->addSeconds((int) config('virgoai.fal.poll_interval_seconds', 10)));
    }
}
