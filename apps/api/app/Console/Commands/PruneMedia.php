<?php

namespace App\Console\Commands;

use App\Media\MediaRemover;
use App\Models\Generation;
use App\Models\Production;
use DomainException;
use Illuminate\Console\Command;

/**
 * Removes the files of videos and images older than the retention period. Records stay; their files do not.
 */
class PruneMedia extends Command
{
    protected $signature = 'media:prune';

    protected $description = 'Delete video and image files older than the retention period';

    public function handle(MediaRemover $remover): int
    {
        $days = (int) config('virgoai.media.retention_days', 30);
        $cutoff = now()->subDays($days);
        $removed = 0;

        Production::query()
            ->whereNull('media_deleted_at')
            ->whereNotNull('output_path')
            ->where('created_at', '<', $cutoff)
            ->whereIn('status', Production::TERMINAL)
            ->chunkById(100, function ($productions) use ($remover, &$removed) {
                foreach ($productions as $production) {
                    try {
                        $remover->removeProduction($production);
                        $removed++;
                    } catch (DomainException) {
                        // Still rendering: left for the next run.
                    }
                }
            });

        Generation::query()
            ->whereNull('media_deleted_at')
            ->whereNotNull('output_path')
            ->where('created_at', '<', $cutoff)
            ->where('status', '!=', Generation::STATUS_RUNNING)
            ->chunkById(100, function ($generations) use ($remover, &$removed) {
                foreach ($generations as $generation) {
                    try {
                        $remover->removeGeneration($generation);
                        $removed++;
                    } catch (DomainException) {
                        // Still rendering: left for the next run.
                    }
                }
            });

        $this->info("Removed media from {$removed} item(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
