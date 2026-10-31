<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Desktop delivery" panel: the operator's answer to "why do my users see
 * no models?". The desktop is served one gateway provider built from the
 * models the plan entitles and the gateway can actually reach, so this screen
 * has to name every gate that dropped a model.
 */
class AdminGatewayDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['locale' => 'en']);
        $this->organization = Organization::factory()->create([
            'name' => 'Delivery Workspace',
            'owner_id' => $this->admin->getKey(),
        ]);
        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $this->admin->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
    }

    public function test_a_provider_without_a_key_is_reported_as_the_blocker(): void
    {
        $free = $this->plan('free');
        $provider = AiProvider::factory()->create([
            'slug' => 'keyless',
            'name' => 'Keyless',
            'driver' => AiProvider::DRIVER_OPENAI,
            'is_active' => true,
            'api_key' => null,
            'base_url' => 'https://api.example.test',
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'keyless-model',
            'display_name' => 'Keyless model',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/gateway?delivery='.$this->organization->getKey());

        $response->assertOk()
            ->assertSeeText(__('ui.admin.gateway.delivery_title'))
            ->assertSeeText('provider_without_credential')
            ->assertSeeText(__('ui.admin.gateway.delivery_reason_credential'));
    }

    public function test_a_provider_assigned_to_another_plan_is_reported(): void
    {
        $this->plan('free');
        $pro = $this->plan('pro');

        $provider = AiProvider::factory()->create([
            'slug' => 'pro-only',
            'name' => 'Pro only',
            'driver' => AiProvider::DRIVER_OPENAI,
            'is_active' => true,
            'api_key' => 'sk-test',
        ]);
        $provider->plans()->attach($pro);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'pro-model',
            'display_name' => 'Pro model',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->get('/admin/gateway?delivery='.$this->organization->getKey())
            ->assertOk()
            ->assertSeeText('provider_plan_mismatch')
            ->assertSeeText(__('ui.admin.gateway.delivery_reason_provider_plan'));
    }

    public function test_an_anthropic_provider_is_reported_as_not_desktop_capable(): void
    {
        $this->plan('free');

        $provider = AiProvider::factory()->create([
            'slug' => 'anthropic',
            'name' => 'Anthropic',
            'driver' => AiProvider::DRIVER_ANTHROPIC,
            'is_active' => true,
            'api_key' => 'sk-ant-test',
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'claude-sonnet-4',
            'display_name' => 'Claude Sonnet 4',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->get('/admin/gateway?delivery='.$this->organization->getKey())
            ->assertOk()
            ->assertSeeText('driver_not_desktop')
            ->assertSeeText(__('ui.admin.gateway.delivery_reason_driver'));
    }

    public function test_a_correctly_configured_provider_is_reported_as_shown(): void
    {
        $this->plan('free');

        $provider = AiProvider::factory()->create([
            'slug' => 'ready',
            'name' => 'Ready',
            'driver' => AiProvider::DRIVER_OPENAI,
            'is_active' => true,
            'api_key' => 'sk-test',
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'ready-model',
            'display_name' => 'Ready model',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/gateway?delivery='.$this->organization->getKey());

        $response->assertOk()
            ->assertSeeText('ready-model')
            ->assertSeeText(__('ui.admin.gateway.delivery_shown'));
    }

    public function test_a_platform_without_a_free_plan_is_warned_about(): void
    {
        // No `free` plan at all: a new signup gets a workspace with no plan.
        Plan::query()->delete();

        $this->actingAs($this->admin)->get('/admin/gateway?delivery='.$this->organization->getKey())
            ->assertOk()
            ->assertSeeText(__('ui.admin.gateway.delivery_no_free_plan'));
    }

    private function plan(string $slug): Plan
    {
        $plan = Plan::factory()->create(['slug' => $slug, 'name' => ucfirst($slug), 'is_active' => true, 'is_public' => true]);

        Subscription::factory()->create([
            'organization_id' => $this->organization->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        return $plan;
    }
}
