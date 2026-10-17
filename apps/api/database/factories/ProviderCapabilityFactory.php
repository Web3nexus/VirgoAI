<?php

namespace Database\Factories;

use App\Enums\Capability;
use App\Models\AiModel;
use App\Models\ProviderCapability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderCapability>
 */
class ProviderCapabilityFactory extends Factory
{
    protected $model = ProviderCapability::class;

    public function definition(): array
    {
        return [
            'ai_model_id' => AiModel::factory(),
            'capability' => Capability::Text,
        ];
    }
}
