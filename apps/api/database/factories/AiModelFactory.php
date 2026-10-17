<?php

namespace Database\Factories;

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiModel>
 */
class AiModelFactory extends Factory
{
    protected $model = AiModel::class;

    public function definition(): array
    {
        return [
            'ai_provider_id' => AiProvider::factory(),
            'key' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'enabled' => true,
            'priority' => 100,
        ];
    }
}
