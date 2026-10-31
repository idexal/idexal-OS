<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewayUsageContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_limits_use_the_desktop_utc_timestamp_contract(): void
    {
        $plan = Plan::factory()->create([
            'slug' => 'free',
            'limits' => [
                'requests_per_month' => 1000,
                'tokens_per_month' => 250000,
                'seats' => 1,
                'api_keys' => 2,
            ],
        ]);
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

        [, $token] = ApiKey::issue($organization, $user, 'Desktop');

        $response = $this->withToken($token)->getJson('/v1/gateway/usage-limits/me')->assertOk();

        $this->assertMatchesRegularExpression('/Z$/', $response->json('serverTime'));
        $this->assertMatchesRegularExpression('/Z$/', $response->json('coverage.trackingStartedAt'));
        $this->assertContains($response->json('state'), [
            'unlimited',
            'within_limit',
            'over_limit',
            'blocked',
        ]);
        $this->assertIsArray($response->json('buckets'));
        $this->assertIsArray($response->json('providers'));
        $this->assertSame('free', $response->json('planUsage.plan.slug'));
        $this->assertSame(1000, $response->json('planUsage.requests.limit'));
        $this->assertSame(250000, $response->json('planUsage.tokens.limit'));
    }
}
