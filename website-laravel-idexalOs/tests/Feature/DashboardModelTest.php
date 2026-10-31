<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workspace models page. The desktop opens "Manage organization models" at
 * `/dashboard/custom-llm-providers`, a den-web address; on the self-hosted
 * platform that URL used to answer with a 404. The page now renders, and its
 * older aliases resolve to it, so no desktop link is dead.
 */
class DashboardModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_lists_the_models_the_workspace_is_entitled_to(): void
    {
        Plan::factory()->create(['slug' => 'free']);
        [$user] = $this->workspace();
        $provider = $this->provider('acme');
        $this->model($provider, 'acme-large', 'Acme Large');

        $this->actingAs($user)
            ->get('/dashboard/models')
            ->assertOk()
            ->assertSee(__('ui.dashboard.models.title'))
            ->assertSee('Acme Large')
            ->assertSee('acme-large')
            // The provider key lives server-side; the page must never print it.
            ->assertDontSee('acme-secret-provider')
            ->assertDontSee(__('ui.dashboard.models.empty_title'));
    }

    public function test_the_page_names_an_empty_workspace_instead_of_showing_nothing(): void
    {
        Plan::factory()->create(['slug' => 'free']);
        [$user] = $this->workspace();

        $this->actingAs($user)
            ->get('/dashboard/models')
            ->assertOk()
            ->assertSee(__('ui.dashboard.models.empty_title'))
            ->assertSee(__('ui.dashboard.models.empty_member'))
            ->assertSee(__('ui.dashboard.models.contact_support'))
            // A member cannot publish a provider, so they are not offered that action.
            ->assertDontSee(__('ui.dashboard.models.manage_providers'));
    }

    public function test_a_platform_admin_is_sent_to_the_provider_page(): void
    {
        Plan::factory()->create(['slug' => 'free']);
        $admin = User::factory()->admin()->create();
        $this->workspace($admin);

        $this->actingAs($admin)
            ->get('/dashboard/models')
            ->assertOk()
            ->assertSee(__('ui.dashboard.models.empty_admin'))
            ->assertSee(__('ui.dashboard.models.manage_providers'))
            ->assertSee(route('admin.providers.index'), false);
    }

    public function test_the_den_addresses_the_desktop_opens_all_resolve(): void
    {
        Plan::factory()->create(['slug' => 'free']);
        [$user] = $this->workspace();
        $this->model($this->provider('acme'), 'acme-large', 'Acme Large');

        // The exact address the desktop opens for "Manage organization models".
        $this->actingAs($user)
            ->get('/dashboard/custom-llm-providers')
            ->assertOk()
            ->assertSee('Acme Large');

        // The two other Den links the desktop builds.
        $this->actingAs($user)
            ->get('/dashboard/library?show=models')
            ->assertRedirect(route('dashboard.models'));

        $this->actingAs($user)
            ->get('/dashboard/ai-gateway?tab=ai-providers')
            ->assertRedirect(route('dashboard.models'));
    }

    public function test_another_workspaces_private_model_never_appears(): void
    {
        Plan::factory()->create(['slug' => 'free']);
        [$user] = $this->workspace();

        $other = Organization::factory()->create();
        $this->model($this->provider('tenant', ['organization_id' => $other->getKey()]), 'tenant-model', 'Tenant model');

        $this->actingAs($user)
            ->get('/dashboard/models')
            ->assertOk()
            ->assertDontSee('Tenant model')
            ->assertSee(__('ui.dashboard.models.empty_title'));
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function workspace(?User $user = null): array
    {
        $user ??= User::factory()->create();
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function provider(string $slug, array $attributes = []): AiProvider
    {
        return AiProvider::factory()->create(array_merge([
            'name' => ucfirst($slug).' provider',
            'slug' => $slug,
            'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
            'base_url' => "https://{$slug}.test/v1",
            'api_key' => "{$slug}-secret-provider",
            'is_active' => true,
            'priority' => 1,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function model(AiProvider $provider, string $modelId, string $displayName, array $attributes = []): AiModel
    {
        return AiModel::factory()->create(array_merge([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => $modelId,
            'display_name' => $displayName,
            'context_window' => 128000,
            'max_output_tokens' => 8192,
            'is_active' => true,
        ], $attributes));
    }
}
