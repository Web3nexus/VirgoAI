<?php

namespace Database\Factories;

use App\Enums\ProviderStatus;
use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProvider>
 */
class AiProviderFactory extends Factory
{
    protected $model = AiProvider::class;

    public function definition(): array
    {
        $key = $this->faker->unique()->slug(1);

        return [
            'key' => $key,
            'name' => strtoupper($key),
            'status' => ProviderStatus::Active,
            'credentials_key' => null,
            'priority' => 100,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['status' => ProviderStatus::Disabled]);
    }
}
