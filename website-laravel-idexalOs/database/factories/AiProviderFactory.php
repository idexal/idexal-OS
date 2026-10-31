<?php

namespace Database\Factories;

use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiProvider>
 */
class AiProviderFactory extends Factory
{
    protected $model = AiProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
            'base_url' => 'https://api.example.com/v1',
            'api_key' => 'test-key',
            'is_active' => true,
            'priority' => 0,
            'supports_streaming' => true,
        ];
    }
}
