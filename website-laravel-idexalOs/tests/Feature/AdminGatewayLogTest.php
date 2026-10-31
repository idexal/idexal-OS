<?php

namespace Tests\Feature;

use App\Models\GatewayRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The admin gateway log: filtering by the feature the pipeline applied, and the
 * feature-usage summary the operator scans to see which transforms are active.
 *
 * The latency column is the discriminator in these assertions: unlike the model
 * name it is rendered only inside the (filterable) request log, never in the
 * top-models or top-workspaces panels.
 */
class AdminGatewayLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_log_can_be_filtered_by_the_feature_that_ran(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->create();

        $this->record($organization, 'searched-model', ['features' => ['web_search']], 111);
        $this->record($organization, 'visioned-model', ['features' => ['vision']], 222);

        $this->actingAs($admin)->get('/admin/gateway?feature=web_search')
            ->assertOk()
            ->assertSee('111ms')
            ->assertDontSee('222ms');
    }

    public function test_an_unknown_feature_filter_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->create();

        $this->record($organization, 'searched-model', ['features' => ['web_search']], 111);
        $this->record($organization, 'visioned-model', ['features' => ['vision']], 222);

        $this->actingAs($admin)->get('/admin/gateway?feature=not-a-real-feature')
            ->assertOk()
            ->assertSee('111ms')
            ->assertSee('222ms');
    }

    public function test_the_page_summarises_how_often_each_feature_ran(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->create();

        $this->record($organization, 'searched-model', ['features' => ['web_search']], 111);
        $this->record($organization, 'also-searched', ['features' => ['web_search']], 112);

        $this->actingAs($admin)->get('/admin/gateway')
            ->assertOk()
            ->assertSee(__('ui.admin.gateway.feature_usage'))
            ->assertSee(route('admin.gateway', ['feature' => 'web_search']), false)
            ->assertSee(route('admin.gateway', ['feature' => 'repetition_guard']), false);
    }

    public function test_a_request_without_meta_never_matches_a_feature_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->create();

        $this->record($organization, 'plain-model', null, 333);
        $this->record($organization, 'searched-model', ['features' => ['web_search']], 111);

        $this->actingAs($admin)->get('/admin/gateway?feature=web_search')
            ->assertOk()
            ->assertSee('111ms')
            ->assertDontSee('333ms');
    }

    public function test_an_unknown_feature_name_never_leaks_a_translation_key(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->create();

        $this->record($organization, 'searched-model', ['features' => ['web_search', 'retired_feature']], 111);

        $this->actingAs($admin)->get('/admin/gateway')
            ->assertOk()
            ->assertSee(__('ui.admin.gateway.feature_badges.web_search'))
            ->assertDontSee('ui.admin.gateway.feature_badges.retired_feature')
            ->assertDontSee('retired_feature');
    }

    public function test_the_log_shows_the_search_fee_inside_a_flat_cost(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->create();

        $this->record($organization, 'searched-model', ['features' => ['web_search'], 'web_search_price' => 0.015], 111);

        $this->actingAs($admin)->get('/admin/gateway')
            ->assertOk()
            ->assertSee(__('ui.admin.gateway.search_fee', ['fee' => money(0.015)]));
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function record(Organization $organization, string $model, ?array $meta, int $latency): void
    {
        GatewayRequest::query()->create([
            'request_id' => 'req_'.Str::random(24),
            'organization_id' => $organization->getKey(),
            'endpoint' => 'chat',
            'model' => $model,
            'status_code' => 200,
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
            'cost' => 0.0001,
            'latency_ms' => $latency,
            'is_stream' => false,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
