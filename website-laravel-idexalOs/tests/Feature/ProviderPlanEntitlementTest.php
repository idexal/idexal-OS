<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderPlanEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_provider_restricted_to_a_plan_is_hidden_from_other_plans(): void
    {
        $free = Plan::factory()->create(['slug' => 'free']);
        $pro = Plan::factory()->create(['slug' => 'pro']);
        [$user, $organization] = $this->workspace($free);

        $everyone = AiProvider::factory()->create(['slug' => 'everyone', 'is_active' => true]);
        AiModel::factory()->create([
            'ai_provider_id' => $everyone->getKey(),
            'model_id' => 'shared-model',
            'is_active' => true,
        ]);

        $proOnly = AiProvider::factory()->create(['slug' => 'pro-only', 'is_active' => true]);
        $proOnly->plans()->attach($pro);
        AiModel::factory()->create([
            'ai_provider_id' => $proOnly->getKey(),
            'model_id' => 'pro-model',
            'is_active' => true,
        ]);

        [, $token] = ApiKey::issue($organization, $user, 'Desktop');

        $freeIds = collect($this->withToken($token)->getJson('/v1/models')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($freeIds->contains('shared-model'));
        $this->assertFalse($freeIds->contains('pro-model'));

        $organization->subscriptions()->update(['status' => Subscription::STATUS_CANCELED]);
        Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $pro->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_end' => now()->addMonth(),
        ]);

        $proIds = collect($this->withToken($token)->getJson('/v1/models')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($proIds->contains('shared-model'));
        $this->assertTrue($proIds->contains('pro-model'));
    }

    public function test_a_model_restricted_to_a_plan_is_hidden_from_other_plans(): void
    {
        $free = Plan::factory()->create(['slug' => 'free']);
        $pro = Plan::factory()->create(['slug' => 'pro']);
        [$user, $organization] = $this->workspace($free);

        $provider = AiProvider::factory()->create(['slug' => 'shared', 'is_active' => true]);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'basic-model',
            'is_active' => true,
        ]);
        $premium = AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'premium-model',
            'is_active' => true,
        ]);
        $premium->plans()->attach($pro);

        [, $token] = ApiKey::issue($organization, $user, 'Desktop');

        $freeIds = collect($this->withToken($token)->getJson('/v1/models')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($freeIds->contains('basic-model'));
        $this->assertFalse($freeIds->contains('premium-model'));

        $organization->subscriptions()->update(['status' => Subscription::STATUS_CANCELED]);
        Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $pro->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_end' => now()->addMonth(),
        ]);

        $proIds = collect($this->withToken($token)->getJson('/v1/models')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($proIds->contains('basic-model'));
        $this->assertTrue($proIds->contains('premium-model'));
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function workspace(Plan $plan): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->getKey()]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_end' => now()->addMonth(),
        ]);

        return [$user, $organization];
    }
}
