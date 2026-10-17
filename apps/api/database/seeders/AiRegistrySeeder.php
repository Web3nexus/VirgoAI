<?php

namespace Database\Seeders;

use App\Ai\Fal\FalEndpoints;
use App\Enums\Capability;
use App\Enums\ProviderStatus;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ProviderCapability;
use App\Productions\ProductionService;
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

        $this->seedFal();
        $this->seedAnthropic();
    }

    /** Display names for the fal endpoints the studio offers. */
    private const FAL_NAMES = [
        'fal-ai/gpt-image-1.5' => 'GPT Image 1.5',
        'fal-ai/flux/schnell' => 'FLUX schnell',
        'fal-ai/nano-banana-2' => 'Nano Banana 2',
        'bytedance/seedance-2.0/image-to-video' => 'Seedance 2.0',
        'bytedance/seedance-2.0/fast/image-to-video' => 'Seedance 2.0 Fast',
        'fal-ai/minimax/hailuo-02/standard/image-to-video' => 'Hailuo 02 Standard',
        'fal-ai/kling-video/v3/standard/image-to-video' => 'Kling 3.0 Standard',
        'fal-ai/gemini-25-flash-image' => 'Gemini 2.5 Flash Image',
        'fal-ai/veo3.1/fast/image-to-video' => 'Veo 3.1 Fast',
        'fal-ai/veo3/fast/image-to-video' => 'Veo 3 Fast',
    ];

    /**
     * The fal endpoints the studio offers. A model is usable only once FAL_KEY is set; until then the picker shows why.
     */
    private function seedFal(): void
    {
        $provider = AiProvider::updateOrCreate(['key' => 'fal'], ['name' => 'fal.ai', 'status' => ProviderStatus::Active, 'priority' => 10]);

        foreach (FalEndpoints::selectableIds() as $priority => $endpoint) {
            $model = AiModel::updateOrCreate(
                ['ai_provider_id' => $provider->id, 'key' => $endpoint],
                ['name' => self::FAL_NAMES[$endpoint] ?? $endpoint, 'enabled' => true, 'priority' => ($priority + 1) * 10],
            );

            ProviderCapability::updateOrCreate(
                ['ai_model_id' => $model->id, 'capability' => FalEndpoints::all()[$endpoint]['capability']->value],
                [],
            );
        }
    }

    /** The one text model the studio writes scripts with. */
    private function seedAnthropic(): void
    {
        $provider = AiProvider::updateOrCreate(['key' => 'anthropic'], ['name' => 'Anthropic', 'status' => ProviderStatus::Active, 'priority' => 20]);

        $model = AiModel::updateOrCreate(
            ['ai_provider_id' => $provider->id, 'key' => ProductionService::TEXT_MODEL],
            ['name' => 'Claude Sonnet 5.5', 'enabled' => true, 'priority' => 10],
        );

        ProviderCapability::updateOrCreate(['ai_model_id' => $model->id, 'capability' => Capability::Text->value], []);
    }
}
