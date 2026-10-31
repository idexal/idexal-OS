<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Plan;
use App\Models\User;
use App\Services\ComboModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPlanProviderAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_assign_ai_providers_and_models_when_creating_a_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $pro = AiProvider::factory()->create(['slug' => 'pro-provider']);
        $basic = AiProvider::factory()->create(['slug' => 'basic-provider']);
        $model = AiModel::factory()->create([
            'ai_provider_id' => $pro->getKey(),
            'model_id' => 'premium-model',
        ]);
        $otherModel = AiModel::factory()->create([
            'ai_provider_id' => $pro->getKey(),
            'model_id' => 'other-model',
        ]);

        $this->actingAs($admin)
            ->post('/admin/plans', $this->planPayload([
                'ai_providers_present' => '1',
                'ai_provider_ids' => [$pro->getKey()],
                'ai_models_present' => '1',
                'ai_model_ids' => [$model->getKey()],
            ]))
            ->assertRedirect('/admin/plans');

        $plan = Plan::query()->where('slug', 'guarded-plan')->firstOrFail();

        $this->assertSame([$pro->getKey()], $plan->aiProviders()->pluck('ai_providers.id')->all());
        $this->assertSame([$model->getKey()], $plan->aiModels()->pluck('ai_models.id')->all());
        $this->assertNotContains($basic->getKey(), $plan->aiProviders()->pluck('ai_providers.id'));
        $this->assertNotContains($otherModel->getKey(), $plan->aiModels()->pluck('ai_models.id'));
    }

    public function test_updating_a_plan_without_the_entitlement_fields_keeps_its_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create();
        $model = AiModel::factory()->create(['ai_provider_id' => $provider->getKey()]);
        $plan = Plan::factory()->create(['slug' => 'guarded-plan']);
        $plan->aiProviders()->attach($provider);
        $plan->aiModels()->attach($model);

        $this->actingAs($admin)
            ->patch("/admin/plans/{$plan->getKey()}", $this->planPayload(['name' => 'Renamed plan']))
            ->assertRedirect('/admin/plans');

        $this->assertSame([$provider->getKey()], $plan->fresh()->aiProviders()->pluck('ai_providers.id')->all());
        $this->assertSame([$model->getKey()], $plan->fresh()->aiModels()->pluck('ai_models.id')->all());
    }

    public function test_an_empty_selection_returns_the_plan_to_every_provider_and_model(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create();
        $model = AiModel::factory()->create(['ai_provider_id' => $provider->getKey()]);
        $plan = Plan::factory()->create(['slug' => 'guarded-plan']);
        $plan->aiProviders()->attach($provider);
        $plan->aiModels()->attach($model);

        $this->actingAs($admin)
            ->patch("/admin/plans/{$plan->getKey()}", $this->planPayload([
                'ai_providers_present' => '1',
                'ai_models_present' => '1',
            ]))
            ->assertRedirect('/admin/plans');

        $this->assertSame([], $plan->fresh()->aiProviders()->pluck('ai_providers.id')->all());
        $this->assertSame([], $plan->fresh()->aiModels()->pluck('ai_models.id')->all());
    }

    public function test_the_plan_form_lists_providers_and_their_models(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create(['name' => 'Gateway AI', 'slug' => 'gateway-ai']);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'display_name' => 'Flux Model',
            'model_id' => 'flux-model',
            'is_active' => true,
        ]);
        $plan = Plan::factory()->create();

        $this->actingAs($admin)
            ->get("/admin/plans/{$plan->getKey()}/edit")
            ->assertOk()
            ->assertSee('Gateway AI')
            ->assertSee('Flux Model')
            ->assertSee('ai_providers_present', false)
            ->assertSee('ai_models_present', false);
    }

    public function test_a_combo_model_posted_to_a_plan_is_dropped(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create();
        $model = AiModel::factory()->create(['ai_provider_id' => $provider->getKey()]);

        $comboProvider = app(ComboModelService::class)->systemProvider();
        $combo = AiModel::factory()->create([
            'ai_provider_id' => $comboProvider->getKey(),
            'model_id' => 'combo-shared',
            'hybrid_config' => [
                'strategy' => 'round_robin',
                'auto' => false,
                'members' => [['ai_model_id' => $model->getKey()]],
            ],
        ]);

        $this->actingAs($admin)
            ->post('/admin/plans', $this->planPayload([
                'ai_providers_present' => '1',
                'ai_provider_ids' => [$provider->getKey(), $comboProvider->getKey()],
                'ai_models_present' => '1',
                'ai_model_ids' => [$model->getKey(), $combo->getKey()],
            ]))
            ->assertRedirect('/admin/plans');

        $plan = Plan::query()->where('slug', 'guarded-plan')->firstOrFail();

        // A combo carries no entitlement of its own, so neither its provider
        // nor its model id survives the write.
        $this->assertSame([$provider->getKey()], $plan->aiProviders()->pluck('ai_providers.id')->all());
        $this->assertSame([$model->getKey()], $plan->aiModels()->pluck('ai_models.id')->all());
    }

    public function test_the_plan_form_omits_combos_and_their_provider(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create(['name' => 'Gateway AI', 'slug' => 'gateway-ai']);
        $model = AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'display_name' => 'Flux Model',
            'model_id' => 'flux-model',
        ]);

        $comboProvider = app(ComboModelService::class)->systemProvider();
        AiModel::factory()->create([
            'ai_provider_id' => $comboProvider->getKey(),
            'display_name' => 'Flux Combo',
            'model_id' => 'combo-flux',
            'hybrid_config' => ['members' => [['ai_model_id' => $model->getKey()]]],
        ]);
        $plan = Plan::factory()->create();

        $this->actingAs($admin)
            ->get("/admin/plans/{$plan->getKey()}/edit")
            ->assertOk()
            ->assertSee('Gateway AI')
            ->assertSee('Flux Model')
            ->assertDontSee('Flux Combo')
            ->assertDontSee(ComboModelService::PROVIDER_NAME);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Guarded plan',
            'slug' => 'guarded-plan',
            'price_monthly' => '29',
            'price_yearly' => '290',
            'currency' => 'usd',
            'is_active' => '1',
            'is_public' => '1',
        ], $overrides);
    }
}
