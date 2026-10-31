<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\ComboModelService;
use App\Services\GatewayService;
use App\Services\GatewayToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComboModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_models_collapse_into_one_combo_and_rotate_members(): void
    {
        [$user, $organization, $apiKey] = $this->workspace();

        $providerA = $this->provider('provider-a', 20);
        $providerB = $this->provider('provider-b', 10);
        $modelA = $this->model($providerA, 'shared-model', 'Provider A model');
        $modelB = $this->model($providerB, 'shared-model', 'Provider B model');

        $count = app(ComboModelService::class)->syncAutomaticCombos();

        $this->assertSame(1, $count);

        $combo = AiModel::query()
            ->whereHas('provider', fn ($query) => $query->where('slug', ComboModelService::PROVIDER_SLUG))
            ->firstOrFail();

        $this->assertTrue($combo->is_active);
        $this->assertTrue((bool) ($combo->hybrid_config['auto'] ?? false));
        $this->assertCount(2, $combo->hybrid_config['members']);

        $catalog = app(GatewayService::class)->desktopGatewayModels($organization);
        $this->assertTrue($catalog->contains(fn (AiModel $model): bool => $model->is($combo)));
        $this->assertFalse($catalog->contains(fn (AiModel $model): bool => in_array($model->getKey(), [$modelA->getKey(), $modelB->getKey()], true)));

        Http::fake([
            'https://provider-a.test/v1/chat/completions' => Http::response($this->completion('A'), 200),
            'https://provider-b.test/v1/chat/completions' => Http::response($this->completion('B'), 200),
        ]);

        $gatewayToken = app(GatewayToken::class)->issue($apiKey);
        $comboId = 'gwm_'.$organization->getKey().'_'.$organization->getKey().'_'.$combo->getKey();

        $this->withToken($gatewayToken)
            ->postJson('/v1/chat/completions', [
                'model' => $comboId,
                'messages' => [['role' => 'user', 'content' => 'Hello combo']],
            ])
            ->assertOk();

        $this->withToken($gatewayToken)
            ->postJson('/v1/chat/completions', [
                'model' => $comboId,
                'messages' => [['role' => 'user', 'content' => 'Hello combo again']],
            ])
            ->assertOk();

        // Both members share one model id, so the requested id proves nothing: the
        // provider that answered is the only evidence that the rotation moved.
        $answeredUrls = Http::recorded()
            ->map(fn (array $pair): string => (string) $pair[0]->url())
            ->values()
            ->all();

        $this->assertCount(2, $answeredUrls);
        $this->assertNotSame($answeredUrls[0], $answeredUrls[1]);
        $this->assertContains('https://provider-a.test/v1/chat/completions', $answeredUrls);
        $this->assertContains('https://provider-b.test/v1/chat/completions', $answeredUrls);
    }

    public function test_naming_differences_do_not_split_one_model_into_two_rows(): void
    {
        [, $organization] = $this->workspace();

        $prefixed = $this->model($this->provider('vendor-a', 20), 'deepseek-ai/DeepSeek-V4-Flash-0731', 'DeepSeek V4 Flash');
        $plain = $this->model($this->provider('vendor-b', 10), 'DeepSeek-V4-Flash', 'DeepSeek V4 Flash');

        $this->assertSame(1, app(ComboModelService::class)->syncAutomaticCombos());

        $combo = AiModel::query()
            ->whereHas('provider', fn ($query) => $query->where('slug', ComboModelService::PROVIDER_SLUG))
            ->firstOrFail();

        $this->assertSame('combo-deepseek-v4-flash', $combo->model_id);
        $this->assertCount(2, $combo->hybrid_config['members']);

        $catalog = app(GatewayService::class)->desktopGatewayModels($organization);
        $this->assertTrue($catalog->contains(fn (AiModel $model): bool => $model->is($combo)));
        $this->assertFalse($catalog->contains(fn (AiModel $model): bool => in_array(
            $model->getKey(),
            [$prefixed->getKey(), $plain->getKey()],
            true,
        )));
    }

    public function test_size_variants_of_one_family_stay_separate_models(): void
    {
        $this->workspace();

        $this->model($this->provider('vendor-a', 20), 'deepseek-ai/DeepSeek-V4', 'DeepSeek V4');
        $this->model($this->provider('vendor-b', 10), 'DeepSeek-V4-Flash', 'DeepSeek V4 Flash');

        $this->assertSame(0, app(ComboModelService::class)->syncAutomaticCombos());
    }

    public function test_organization_scoped_models_stay_out_of_global_combos(): void
    {
        [, $organization] = $this->workspace();
        $other = Organization::factory()->create();

        $global = $this->provider('global-vendor', 20);
        $tenant = $this->provider('tenant-vendor', 10);
        $tenant->forceFill(['organization_id' => $other->getKey()])->save();

        $this->model($global, 'glm-5.3-flash', 'Global');
        $this->model($tenant, 'glm-5.3-flash', 'Tenant');

        $this->assertSame(0, app(ComboModelService::class)->syncAutomaticCombos());
        $this->assertSame(
            ['glm-5.3-flash'],
            app(GatewayService::class)->desktopGatewayModels($organization)->pluck('model_id')->unique()->values()->all(),
        );
    }

    public function test_a_combo_never_stores_plan_rows_of_its_members(): void
    {
        $this->workspace();
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        $providerA = $this->provider('provider-a', 20);
        $providerB = $this->provider('provider-b', 10);
        $modelA = $this->model($providerA, 'shared-model', 'Provider A model');
        $modelB = $this->model($providerB, 'shared-model', 'Provider B model');
        $plan->aiModels()->attach([$modelA->getKey(), $modelB->getKey()]);

        $this->assertSame(1, app(ComboModelService::class)->syncAutomaticCombos());

        $combo = AiModel::query()
            ->whereHas('provider', fn ($query) => $query->where('slug', ComboModelService::PROVIDER_SLUG))
            ->firstOrFail();

        // The combo is offered through its members, never through a copy of
        // their plans: that copy went stale on the next plan edit.
        $this->assertSame([], $combo->plans()->pluck('plans.id')->all());
    }

    public function test_a_manual_combo_stores_no_plan_rows_either(): void
    {
        $this->workspace();
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        $providerA = $this->provider('provider-a', 20);
        $providerB = $this->provider('provider-b', 10);
        $modelA = $this->model($providerA, 'shared-model', 'Provider A model');
        $modelB = $this->model($providerB, 'shared-model', 'Provider B model');
        $plan->aiModels()->attach([$modelA->getKey(), $modelB->getKey()]);

        $combo = app(ComboModelService::class)->createAdvanced(
            'Manual combo',
            'manual-combo',
            '',
            'failover',
            [$modelA->getKey(), $modelB->getKey()],
        );

        $this->assertSame([], $combo->plans()->pluck('plans.id')->all());
    }

    public function test_the_purge_migration_clears_stale_combo_rows_at_both_levels(): void
    {
        $this->workspace();
        $plan = Plan::query()->where('slug', 'pro')->firstOrFail();

        $providerA = $this->provider('provider-a', 20);
        $providerB = $this->provider('provider-b', 10);
        $modelA = $this->model($providerA, 'shared-model', 'Provider A model');
        $modelB = $this->model($providerB, 'shared-model', 'Provider B model');

        $this->assertSame(1, app(ComboModelService::class)->syncAutomaticCombos());

        // Stand in for rows written before the gateway stopped copying members'
        // plans onto the combo, at both levels.
        $comboProvider = AiProvider::query()
            ->where('slug', ComboModelService::PROVIDER_SLUG)
            ->firstOrFail();
        $combo = AiModel::query()->where('ai_provider_id', $comboProvider->getKey())->firstOrFail();

        $plan->aiModels()->attach([$modelA->getKey(), $modelB->getKey(), $combo->getKey()]);
        $plan->aiProviders()->attach([$providerA->getKey(), $providerB->getKey(), $comboProvider->getKey()]);

        $migration = require database_path('migrations/2026_11_06_000000_purge_combo_plan_assignments.php');
        $migration->up();

        $this->assertSame(
            [],
            DB::table('plan_ai_model')->where('ai_model_id', $combo->getKey())->pluck('ai_model_id')->all(),
        );
        $this->assertSame(
            [],
            DB::table('plan_ai_provider')->where('ai_provider_id', $comboProvider->getKey())->pluck('ai_provider_id')->all(),
        );

        // Only the derived rows go: the members keep their real entitlements.
        $this->assertEqualsCanonicalizing(
            [$modelA->getKey(), $modelB->getKey()],
            DB::table('plan_ai_model')->where('plan_id', $plan->getKey())->pluck('ai_model_id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$providerA->getKey(), $providerB->getKey()],
            DB::table('plan_ai_provider')->where('plan_id', $plan->getKey())->pluck('ai_provider_id')->all(),
        );
    }

    public function test_a_combo_still_answers_when_its_connection_carries_a_stale_plan(): void
    {
        [$user, $organization, $apiKey] = $this->workspace();
        $otherPlan = Plan::factory()->create(['slug' => 'enterprise']);

        $providerA = $this->provider('provider-a', 20);
        $providerB = $this->provider('provider-b', 10);
        $this->model($providerA, 'shared-model', 'Provider A model');
        $this->model($providerB, 'shared-model', 'Provider B model');

        $this->assertSame(1, app(ComboModelService::class)->syncAutomaticCombos());

        $comboProvider = AiProvider::query()
            ->where('slug', ComboModelService::PROVIDER_SLUG)
            ->firstOrFail();
        $combo = AiModel::query()->where('ai_provider_id', $comboProvider->getKey())->firstOrFail();

        // A stale row on the synthetic connection must not block an actual call:
        // the combo is resolved from its members, whose own providers are healthy.
        $comboProvider->plans()->sync([$otherPlan->getKey()]);

        Http::fake([
            'https://provider-a.test/v1/chat/completions' => Http::response($this->completion('A'), 200),
            'https://provider-b.test/v1/chat/completions' => Http::response($this->completion('B'), 200),
        ]);

        $gatewayToken = app(GatewayToken::class)->issue($apiKey);

        $this->withToken($gatewayToken)
            ->postJson('/v1/chat/completions', [
                'model' => 'gwm_'.$organization->getKey().'_'.$organization->getKey().'_'.$combo->getKey(),
                'messages' => [['role' => 'user', 'content' => 'Hello combo']],
            ])
            ->assertOk();

        $this->assertCount(1, Http::recorded());
    }

    private function provider(string $slug, int $priority): AiProvider
    {
        return AiProvider::factory()->create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
            'base_url' => "https://{$slug}.test/v1",
            'api_key' => "{$slug}-secret",
            'is_active' => true,
            'priority' => $priority,
        ]);
    }

    private function model(AiProvider $provider, string $modelId, string $displayName): AiModel
    {
        return AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => $modelId,
            'display_name' => $displayName,
            'context_window' => 128000,
            'max_output_tokens' => 8192,
            'capabilities' => ['chat' => true, 'streaming' => true, 'tools' => true],
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function completion(string $text): array
    {
        return [
            'id' => 'chatcmpl-'.$text,
            'object' => 'chat.completion',
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $text],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 4, 'completion_tokens' => 2, 'total_tokens' => 6],
        ];
    }

    /**
     * @return array{0: User, 1: Organization, 2: ApiKey}
     */
    private function workspace(): array
    {
        Plan::factory()->create(['slug' => 'pro']);
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->getKey()]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        [$apiKey] = ApiKey::issue($organization, $user, 'Combo test desktop key');

        return [$user, $organization, $apiKey];
    }
}
