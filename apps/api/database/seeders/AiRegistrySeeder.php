<?php

namespace Database\Seeders;

use App\Enums\Capability;
use App\Enums\ProviderStatus;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ProviderCapability;
use Illuminate\Database\Seeder;

/**
 * Seeds the DB-backed model registry.
 *
 * In local/testing it registers the offline `echo` adapter so the router and workflow stages can be
 * exercised without credentials. Real providers are seeded here as they are integrated one at a
 * time (their credentials live in env, never in the DB).
 */
class AiRegistrySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $provider = AiProvider::updateOrCreate(
            ['key' => 'echo'],
            [
                'name' => 'Echo (dev)',
                'status' => ProviderStatus::Active,
                'priority' => 999,
            ],
        );

        $model = AiModel::updateOrCreate(
            ['ai_provider_id' => $provider->id, 'key' => 'echo-1'],
            [
                'name' => 'Echo 1',
                'enabled' => true,
                'priority' => 999,
            ],
        );

        // Only what the echo adapter can actually serve.
        foreach ([Capability::Text] as $capability) {
            ProviderCapability::updateOrCreate(
                ['ai_model_id' => $model->id, 'capability' => $capability->value],
                [],
            );
        }
    }
}
