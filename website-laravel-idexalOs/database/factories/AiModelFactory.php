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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_provider_id' => AiProvider::factory(),
            'model_id' => 'test-model-'.fake()->unique()->numerify('###'),
            'display_name' => fake()->words(2, true),
            'context_window' => 128000,
            'max_output_tokens' => 4096,
            'input_price' => 1,
            'output_price' => 2,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
