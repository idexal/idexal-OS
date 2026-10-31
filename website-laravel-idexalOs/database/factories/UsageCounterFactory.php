<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\UsageCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageCounter>
 */
class UsageCounterFactory extends Factory
{
    protected $model = UsageCounter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'period' => now()->format('Y-m'),
            'requests' => 0,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cost' => 0,
        ];
    }
}
