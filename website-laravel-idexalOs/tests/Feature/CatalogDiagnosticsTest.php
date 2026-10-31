<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\ComboModelService;
use App\Services\GatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_names_the_exact_gate_that_keeps_a_model_from_the_desktop(): void
    {
        $free = Plan::factory()->create(['slug' => 'free', 'name' => 'Free']);
        $starter = Plan::factory()->create(['slug' => 'starter', 'name' => 'Starter']);
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $gateway = app(GatewayService::class);

        $healthy = $this->provider('healthy', ['priority' => 1]);
        $this->model($healthy, 'shown-model', 'Shown model');
        $this->model($healthy, 'off-model', 'Off model', ['is_active' => false]);

        $this->model($this->provider('paused', ['is_active' => false]), 'paused-model', 'Paused model');
        $this->model($this->provider('claude', ['driver' => AiProvider::DRIVER_ANTHROPIC]), 'claude-model', 'Claude model');
        $this->model($this->provider('keyless', ['api_key' => null]), 'keyless-model', 'Keyless model');
        $this->model($this->provider('tenant', ['organization_id' => $otherOrganization->getKey()]), 'tenant-model', 'Tenant model');

        $starterProvider = $this->provider('starter-only');
        $starterProvider->plans()->attach($starter->getKey());
        $this->model($starterProvider, 'starter-provider-model', 'Starter provider model');

        $starterModelProvider = $this->provider('model-plan');
        $this->model($starterModelProvider, 'starter-model-model', 'Starter model model')
            ->plans()->attach($starter->getKey());

        $winning = $this->provider('winner', ['priority' => 50]);
        $this->model($winning, 'shared-duplicate', 'Winner copy');
        $this->model($healthy, 'shared-duplicate', 'Loser copy');

        $this->assertSame($free->getKey(), $organization->currentPlan()?->getKey());

        // Keyed by provider and model id: two providers may share one model id,
        // and that pair is exactly the case under test.
        $reasons = collect($gateway->diagnoseDesktopCatalog($organization))
            ->mapWithKeys(fn (array $row): array => [
                $row['provider'].'|'.$row['model_id'] => $row['reason'] ?? 'visible',
            ])
            ->all();

        $this->assertSame('visible', $reasons['healthy|shown-model']);
        $this->assertSame('model_inactive', $reasons['healthy|off-model']);
        $this->assertSame('provider_inactive', $reasons['paused|paused-model']);
        $this->assertSame('driver_not_desktop', $reasons['claude|claude-model']);
        $this->assertSame('provider_without_credential', $reasons['keyless|keyless-model']);
        $this->assertSame('provider_other_organization', $reasons['tenant|tenant-model']);
        $this->assertSame('provider_plan_mismatch', $reasons['starter-only|starter-provider-model']);
        $this->assertSame('model_plan_mismatch', $reasons['model-plan|starter-model-model']);
        $this->assertSame('visible', $reasons['winner|shared-duplicate']);
        $this->assertSame('duplicate_model_id', $reasons['healthy|shared-duplicate']);

        $this->assertCatalogMatchesDiagnoses($organization);
    }

    public function test_a_model_hidden_only_by_a_missing_plan_is_not_reported_as_a_duplicate(): void
    {
        Plan::factory()->create(['slug' => 'starter', 'name' => 'Starter']);
        $organization = Organization::factory()->create();

        // No subscription and no free plan: currentPlan() cannot resolve, so plan
        // assignments hide everything they touch.
        $this->assertNull($organization->currentPlan());

        $provider = $this->provider('assigned');
        $provider->plans()->attach(Plan::query()->firstOrFail()->getKey());
        $this->model($provider, 'assigned-model', 'Assigned model');

        $reasons = collect(app(GatewayService::class)->diagnoseDesktopCatalog($organization))
            ->mapWithKeys(fn (array $row): array => [
                $row['provider'].'|'.$row['model_id'] => $row['reason'] ?? 'visible',
            ])
            ->all();

        $this->assertSame('provider_plan_mismatch', $reasons['assigned|assigned-model']);
        $this->assertCatalogMatchesDiagnoses($organization);
    }

    public function test_combo_members_are_reported_as_hidden_inside_a_combo(): void
    {
        $organization = Organization::factory()->create();

        $first = $this->provider('first', ['priority' => 20]);
        $second = $this->provider('second', ['priority' => 10]);
        $this->model($first, 'routed-model', 'First copy');
        $this->model($second, 'routed-model', 'Second copy');

        $this->assertSame(1, app(ComboModelService::class)->syncAutomaticCombos());

        $reasons = collect(app(GatewayService::class)->diagnoseDesktopCatalog($organization))
            ->mapWithKeys(fn (array $row): array => [
                $row['provider'].'|'.$row['model_id'] => $row['reason'] ?? 'visible',
            ])
            ->all();

        $this->assertSame('combo_member_hidden', $reasons['first|routed-model']);
        $this->assertSame('combo_member_hidden', $reasons['second|routed-model']);
        $this->assertSame('visible', $reasons[ComboModelService::PROVIDER_SLUG.'|combo-routed-model']);

        $this->assertCatalogMatchesDiagnoses($organization);
    }

    public function test_a_new_workspace_keeps_its_models_when_a_combo_carries_a_stale_plan(): void
    {
        $free = Plan::factory()->create(['slug' => 'free', 'name' => 'Free']);
        $pro = Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro']);
        $organization = Organization::factory()->create();

        $this->assertSame($free->getKey(), $organization->currentPlan()?->getKey());

        $modelA = $this->model($this->provider('alpha', ['priority' => 20]), 'twin-model', 'Alpha twin');
        $modelB = $this->model($this->provider('beta', ['priority' => 10]), 'twin-model', 'Beta twin');

        $this->assertSame(1, app(ComboModelService::class)->syncAutomaticCombos());

        $combo = AiModel::query()
            ->whereHas('provider', fn ($query) => $query->where('slug', ComboModelService::PROVIDER_SLUG))
            ->firstOrFail();

        // The combo's stored plan rows are a stale copy: they say Pro, while both
        // members serve every plan. A combo carries no entitlement of its own, so
        // this must not empty the workspace — that was the reported bug.
        $combo->plans()->sync([$pro->getKey()]);

        $catalog = app(GatewayService::class)->desktopGatewayModels($organization);

        $this->assertTrue($catalog->contains(fn (AiModel $model): bool => $model->is($combo)));
        $this->assertFalse($catalog->contains(fn (AiModel $model): bool => in_array(
            $model->getKey(),
            [$modelA->getKey(), $modelB->getKey()],
            true,
        )));

        $reasons = collect(app(GatewayService::class)->diagnoseDesktopCatalog($organization))
            ->mapWithKeys(fn (array $row): array => [
                $row['provider'].'|'.$row['model_id'] => $row['reason'] ?? 'visible',
            ])
            ->all();

        $this->assertSame('visible', $reasons[ComboModelService::PROVIDER_SLUG.'|combo-twin-model']);
        $this->assertSame('combo_member_hidden', $reasons['alpha|twin-model']);
        $this->assertSame('combo_member_hidden', $reasons['beta|twin-model']);

        $this->assertCatalogMatchesDiagnoses($organization);
    }

    public function test_a_new_workspace_keeps_its_models_when_the_combo_connection_carries_a_stale_plan(): void
    {
        $free = Plan::factory()->create(['slug' => 'free', 'name' => 'Free']);
        $pro = Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro']);
        $organization = Organization::factory()->create();

        $this->assertSame($free->getKey(), $organization->currentPlan()?->getKey());

        $modelA = $this->model($this->provider('alpha', ['priority' => 20]), 'twin-model', 'Alpha twin');
        $modelB = $this->model($this->provider('beta', ['priority' => 10]), 'twin-model', 'Beta twin');

        $this->assertSame(1, app(ComboModelService::class)->syncAutomaticCombos());

        $comboProvider = AiProvider::query()
            ->where('slug', ComboModelService::PROVIDER_SLUG)
            ->firstOrFail();
        $combo = AiModel::query()->where('ai_provider_id', $comboProvider->getKey())->firstOrFail();

        // The combo connection is synthetic, so a stale provider plan row must no
        // more hide the combo than a stale model row did. Before the fix this
        // emptied the catalog for every workspace not on Pro — the same failure
        // as the model-level copy, one level up.
        $comboProvider->plans()->sync([$pro->getKey()]);

        $catalog = app(GatewayService::class)->desktopGatewayModels($organization);

        $this->assertTrue($catalog->contains(fn (AiModel $model): bool => $model->is($combo)));
        $this->assertFalse($catalog->contains(fn (AiModel $model): bool => in_array(
            $model->getKey(),
            [$modelA->getKey(), $modelB->getKey()],
            true,
        )));

        $reasons = collect(app(GatewayService::class)->diagnoseDesktopCatalog($organization))
            ->mapWithKeys(fn (array $row): array => [
                $row['provider'].'|'.$row['model_id'] => $row['reason'] ?? 'visible',
            ])
            ->all();

        $this->assertSame('visible', $reasons[ComboModelService::PROVIDER_SLUG.'|combo-twin-model']);
        $this->assertSame('combo_member_hidden', $reasons['alpha|twin-model']);
        $this->assertSame('combo_member_hidden', $reasons['beta|twin-model']);

        $this->assertCatalogMatchesDiagnoses($organization);
    }

    public function test_a_combo_without_an_available_member_is_not_offered_and_hides_nothing(): void
    {
        Plan::factory()->create(['slug' => 'free', 'name' => 'Free']);
        $organization = Organization::factory()->create();

        // Keyless providers reach the combo builder but never the desktop, so the
        // combo has no member to stand for. It must stay out of the catalog and,
        // crucially, must not report its members as hidden — the members are
        // missing for their own reason.
        $this->model($this->provider('keyless-a', ['api_key' => null]), 'twin-model', 'Alpha twin');
        $this->model($this->provider('keyless-b', ['api_key' => null]), 'twin-model', 'Beta twin');

        $this->assertSame(1, app(ComboModelService::class)->syncAutomaticCombos());

        $reasons = collect(app(GatewayService::class)->diagnoseDesktopCatalog($organization))
            ->mapWithKeys(fn (array $row): array => [
                $row['provider'].'|'.$row['model_id'] => $row['reason'] ?? 'visible',
            ])
            ->all();

        $this->assertSame('provider_without_credential', $reasons['keyless-a|twin-model']);
        $this->assertSame('provider_without_credential', $reasons['keyless-b|twin-model']);
        $this->assertSame('combo_members_unavailable', $reasons[ComboModelService::PROVIDER_SLUG.'|combo-twin-model']);

        $this->assertCatalogMatchesDiagnoses($organization);
    }

    public function test_the_admin_page_renders_the_reason_an_operator_can_act_on(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->create(['owner_id' => $admin->getKey()]);
        Plan::factory()->create(['slug' => 'free', 'name' => 'Free']);

        $keyless = $this->provider('keyless', ['api_key' => null]);
        $this->model($keyless, 'keyless-model', 'Keyless model');

        $page = $this->actingAs($admin)->get('/admin/catalog?organization='.$organization->getKey());

        // The platform renders in its configured locale, so assert the resolved
        // strings rather than literals in either language file.
        $page->assertOk()
            ->assertSee(__('ui.admin.catalog.title'))
            ->assertSee(__('ui.admin.catalog.reasons.provider_without_credential.0'))
            ->assertSee(__('ui.admin.catalog.reasons.provider_without_credential.1'))
            ->assertSee('keyless-model')
            // A workspace would see nothing: the page says so before an operator
            // has to reason it out from the reason column.
            ->assertSee(__('ui.admin.catalog.empty_visible_title'))
            ->assertSee(__('ui.admin.catalog.empty_visible_hint'))
            ->assertDontSee('ui.admin.catalog', false);

        $this->assertCatalogMatchesDiagnoses($organization);
    }

    public function test_the_admin_page_stays_quiet_when_the_workspace_can_see_a_model(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->create(['owner_id' => $admin->getKey()]);
        Plan::factory()->create(['slug' => 'free', 'name' => 'Free']);
        $this->model($this->provider('healthy'), 'shown-model', 'Shown model');

        $this->actingAs($admin)
            ->get('/admin/catalog?organization='.$organization->getKey())
            ->assertOk()
            ->assertSee('shown-model')
            ->assertDontSee(__('ui.admin.catalog.empty_visible_title'));
    }

    /**
     * A gate that names itself in Arabic but not in English leaves the other
     * locale printing the raw key, which is the failure this page exists to fix.
     */
    private function assertReasonTranslated(string $reason): void
    {
        $original = app()->getLocale();

        foreach (['ar', 'en'] as $locale) {
            app()->setLocale($locale);
            $entry = __('ui.admin.catalog.reasons.'.$reason);

            $this->assertIsArray($entry, 'Reason '.$reason.' has no structured entry in '.$locale);
            $this->assertNotEmpty($entry[0] ?? null, 'Reason '.$reason.' has no label in '.$locale);
            $this->assertNotEmpty($entry[1] ?? null, 'Reason '.$reason.' has no fix in '.$locale);
        }

        app()->setLocale($original);
    }

    private function assertCatalogMatchesDiagnoses(Organization $organization): void
    {
        $gateway = app(GatewayService::class);

        $catalogIds = $gateway->desktopGatewayModels($organization)
            ->pluck('model_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $reportedVisible = collect($gateway->diagnoseDesktopCatalog($organization))
            ->filter(fn (array $row): bool => $row['reason'] === null)
            ->map(fn (array $row): string => $row['model_id'])
            ->unique()
            ->sort()
            ->values()
            ->all();

        // The diagnostic is only worth anything if it agrees with the query it
        // describes: one visible row per catalog row, and no reason on it.
        $this->assertSame($catalogIds, $reportedVisible);

        foreach ($gateway->diagnoseDesktopCatalog($organization) as $row) {
            $this->assertSame($row['in_catalog'], $row['reason'] === null, 'Diagnosis for '.$row['model_id'].' contradicts the catalog.');

            if ($row['reason'] !== null) {
                $this->assertReasonTranslated($row['reason']);
            }
        }
    }

    private function provider(string $slug, array $attributes = []): AiProvider
    {
        return AiProvider::factory()->create(array_merge([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
            'base_url' => "https://{$slug}.test/v1",
            'api_key' => "{$slug}-secret",
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
            'capabilities' => ['chat' => true, 'streaming' => true, 'tools' => true],
            'is_active' => true,
        ], $attributes));
    }
}
