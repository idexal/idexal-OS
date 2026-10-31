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
 * The customer-facing usage page: a workspace sees which gateway features ran
 * on its own requests, so a billed Web Search is visible instead of silently
 * folded into the request cost.
 */
class DashboardUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_usage_page_shows_which_features_ran_on_a_request(): void
    {
        [$user, $organization] = $this->workspace();

        $this->record($organization, $user, ['features' => ['web_search']]);

        $this->actingAs($user)->get('/dashboard/usage')
            ->assertOk()
            ->assertSee(__('ui.usage.table_features'))
            ->assertSee(__('ui.usage.table_subtitle'))
            ->assertSee(__('ui.admin.gateway.feature_badges.web_search'))
            ->assertDontSee(__('ui.admin.gateway.feature_badges.vision'))
            ->assertDontSee(__('ui.developer.no_logs'));
    }

    public function test_an_empty_workspace_sees_the_empty_state_not_a_log_row(): void
    {
        [$user] = $this->workspace();

        $this->actingAs($user)->get('/dashboard/usage')
            ->assertOk()
            ->assertSee(__('ui.usage.no_usage'))
            ->assertSee(__('ui.usage.feature_usage'))
            ->assertSee(__('ui.usage.feature_usage_empty'));
    }

    public function test_the_usage_page_summarises_feature_counts_for_the_period(): void
    {
        [$user, $organization] = $this->workspace();

        $this->record($organization, $user, ['features' => ['web_search']]);
        $this->record($organization, $user, ['features' => ['web_search', 'pii_sanitizer']]);

        $this->actingAs($user)->get('/dashboard/usage')
            ->assertOk()
            ->assertSee(__('ui.usage.feature_usage'))
            ->assertSee(__('ui.admin.gateway.feature_badges.web_search').' 2')
            ->assertSee(__('ui.admin.gateway.feature_badges.pii_sanitizer').' 1')
            ->assertDontSee(__('ui.admin.gateway.feature_badges.vision'))
            ->assertDontSee(__('ui.usage.feature_usage_empty'));
    }

    public function test_a_searched_request_says_which_part_of_its_cost_was_the_search_fee(): void
    {
        [$user, $organization] = $this->workspace();

        $this->record($organization, $user, ['features' => ['web_search'], 'web_search_price' => 0.015]);

        $this->actingAs($user)->get('/dashboard/usage')
            ->assertOk()
            ->assertSee(__('ui.usage.search_fee', ['fee' => money(0.015, $organization->currency)]));
    }

    public function test_a_request_without_a_search_shows_no_fee_note(): void
    {
        [$user, $organization] = $this->workspace();

        $this->record($organization, $user, ['features' => ['vision']]);

        $this->actingAs($user)->get('/dashboard/usage')
            ->assertOk()
            ->assertDontSee(__('ui.usage.search_fee', ['fee' => money(0.015, $organization->currency)]));
    }

    public function test_a_workspace_never_sees_another_workspaces_requests(): void
    {
        [$user, $organization] = $this->workspace();
        [$otherUser, $other] = $this->workspace();

        $this->record($other, $otherUser, ['features' => ['vision']], model: 'foreign-model-zzz');

        $this->actingAs($user)->get('/dashboard/usage')
            ->assertOk()
            ->assertDontSee('foreign-model-zzz')
            ->assertDontSee(__('ui.admin.gateway.feature_badges.vision'));
    }

    public function test_an_unknown_feature_name_never_leaks_a_translation_key(): void
    {
        [$user, $organization] = $this->workspace();

        $this->record($organization, $user, ['features' => ['web_search', 'retired_feature']]);

        $this->actingAs($user)->get('/dashboard/usage')
            ->assertOk()
            ->assertSee(__('ui.admin.gateway.feature_badges.web_search'))
            ->assertDontSee('ui.admin.gateway.feature_badges.retired_feature')
            ->assertDontSee('ui.admin.features.retired_feature_name')
            ->assertDontSee('retired_feature');
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function record(
        Organization $organization,
        User $user,
        ?array $meta,
        string $model = 'text-only-model',
        int $latency = 120,
    ): void {
        GatewayRequest::query()->create([
            'request_id' => 'req_'.Str::random(24),
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'endpoint' => 'chat',
            'model' => $model,
            'status_code' => 200,
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
            'cost' => 0.02,
            'latency_ms' => $latency,
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
