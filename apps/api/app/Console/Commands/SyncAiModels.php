<?php

namespace App\Console\Commands;

use App\Ai\AdapterRegistry;
use App\Ai\Anthropic\AnthropicModelSync;
use App\Ai\Fal\FalModelSync;
use App\Billing\PriceSync;
use App\Models\AiProvider;
use Illuminate\Console\Command;
use Throwable;

class SyncAiModels extends Command
{
    protected $signature = 'ai:sync';

    protected $description = 'Read the models your ANTHROPIC_API_KEY and FAL_KEY can use, and register the ones this app supports';

    public function handle(AnthropicModelSync $anthropic, FalModelSync $fal, PriceSync $prices, AdapterRegistry $registry): int
    {
        $failed = false;

        // Remove providers left in the database that no adapter in the code serves, with their models.
        $stale = AiProvider::query()->whereNotIn('key', $registry->keys())->get();
        foreach ($stale as $provider) {
            $provider->models()->delete();
            $provider->delete();
            $this->warn("Removed [{$provider->key}]: no adapter in the code serves it.");
        }

        try {
            $summary = $anthropic->sync();
            $this->info("Anthropic: {$summary['upserted']} Claude models registered.");
        } catch (Throwable $e) {
            $this->error('Anthropic: '.$e->getMessage());
            $failed = true;
        }

        try {
            $summary = $fal->sync();
            $this->info("fal: {$summary['upserted']} endpoints verified and registered.");

            foreach ($summary['errors'] as $error) {
                $this->error('fal: '.$error);
                $failed = true;
            }
        } catch (Throwable $e) {
            $this->error('fal: '.$e->getMessage());
            $failed = true;
        }

        $seeded = $prices->seedAnthropic();
        $this->info("Anthropic prices: {$seeded['upserted']} set from the public pricing page.");

        try {
            $summary = $prices->syncFal();
            $this->info("fal prices: {$summary['upserted']} read from fal's pricing API.");

            foreach ($summary['errors'] as $error) {
                $this->error('fal prices: '.$error);
                $failed = true;
            }
        } catch (Throwable $e) {
            $this->error('fal prices: '.$e->getMessage());
            $failed = true;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
