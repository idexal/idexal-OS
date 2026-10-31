<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plain = 'ixo_test_'.Str::random(40);

        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'key_prefix' => mb_substr($plain, 0, 16),
            'key_hash' => hash('sha256', $plain),
            'abilities' => [ApiKey::ABILITY_GATEWAY_CHAT, ApiKey::ABILITY_GATEWAY_MODELS],
        ];
    }
}
