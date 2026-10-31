<?php

namespace Database\Factories;

use App\Models\ProxyProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProxyProvider>
 */
class ProxyProviderFactory extends Factory
{
    protected $model = ProxyProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'driver' => 'generic',
            'protocol' => 'http',
            'website_url' => fake()->url(),
            'docs_url' => null,
            'description' => fake()->sentence(),
            'countries' => ['us', 'gb', 'de'],
            'config' => [],
            'is_active' => true,
            'is_public' => true,
            'priority' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
