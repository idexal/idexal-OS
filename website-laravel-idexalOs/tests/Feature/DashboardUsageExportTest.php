<?php

namespace Tests\Feature;

use App\Models\GatewayRequest;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The customer usage export: a workspace must be able to reconcile a billed
 * feature (Web Search) from its own CSV, not only from the on-screen log.
 */
class DashboardUsageExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_export_names_the_features_that_ran_each_day(): void
    {
        [$user, $organization] = $this->workspace();

        $this->record($organization, $user, ['features' => ['web_search']]);
        $this->record($organization, $user, ['features' => ['web_search', 'vision']]);
        $this->record($organization, $user, ['features' => ['retired_feature']]);

        $response = $this->actingAs($user)->get('/dashboard/usage/export');
        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('date,requests,tokens,cost,features', $csv);
        $this->assertStringContainsString('web_search=2; vision=1', $csv);
        $this->assertStringNotContainsString('retired_feature', $csv);
    }

    public function test_the_export_keeps_its_header_for_a_workspace_without_requests(): void
    {
        [$user] = $this->workspace();

        $response = $this->actingAs($user)->get('/dashboard/usage/export');
        $response->assertOk();

        $this->assertStringContainsString('date,requests,tokens,cost,features', $response->streamedContent());
    }

    public function test_the_export_never_includes_another_workspaces_features(): void
    {
        [$user] = $this->workspace();
        [$otherUser, $other] = $this->workspace();

        $this->record($other, $otherUser, ['features' => ['web_search']]);

        $response = $this->actingAs($user)->get('/dashboard/usage/export');

        $this->assertStringNotContainsString('web_search=1', $response->streamedContent());
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function record(Organization $organization, User $user, ?array $meta): void
    {
        GatewayRequest::query()->create([
            'request_id' => 'req_'.Str::random(24),
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'endpoint' => 'chat',
            'model' => 'text-only-model',
            'status_code' => 200,
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
            'cost' => 0.02,
            'latency_ms' => 120,
            'is_stream' => false,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function workspace(): array
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

        return [$user, $organization];
    }
}
