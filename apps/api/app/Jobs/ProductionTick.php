<?php

namespace App\Jobs;

use App\Models\Production;
use App\Productions\ProductionRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Advance a full video one step, then check again later until it finishes. A lock stops two checks from
 * running at once for the same production.
 */
class ProductionTick implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly string $productionId) {}

    public function handle(ProductionRunner $runner): void
    {
        $lock = Cache::lock("production:{$this->productionId}", 1800);

        if (! $lock->get()) {
            return;
        }

        try {
            $production = Production::query()->find($this->productionId);

            if ($production === null || $production->isTerminal()) {
                return;
            }

            $runner->advance($production->refresh());

            if (! $production->refresh()->isTerminal()) {
                self::dispatch($this->productionId)->delay(now()->addSeconds((int) config('virgoai.productions.tick_seconds', 15)));
            }
        } finally {
            $lock->release();
        }
    }
}
