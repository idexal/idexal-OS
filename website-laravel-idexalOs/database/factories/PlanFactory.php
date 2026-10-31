<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'price_monthly' => 19,
            'price_yearly' => 190,
            'currency' => 'USD',
            'features' => ['api_access' => true, 'team_members' => true],
            'limits' => [
                'requests_per_month' => 10000,
                'tokens_per_month' => 1000000,
                'seats' => 5,
                'api_keys' => 10,
            ],
            'is_public' => true,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_monthly' => 0,
            'price_yearly' => 0,
            'limits' => [
                'requests_per_month' => 1000,
                'tokens_per_month' => 250000,
                'seats' => 1,
                'api_keys' => 2,
            ],
        ]);
    }
}
