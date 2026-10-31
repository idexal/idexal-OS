<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AuditLog;
use App\Models\GatewayRequest;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use App\Services\GatewayService;
use App\Support\GatewayFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_features_page_renders_every_catalogue_card_for_an_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $page = $this->actingAs($admin)->get('/admin/features')->assertOk();

        $page->assertSee(__('ui.admin.features.title'));

        foreach (GatewayFeature::cases() as $feature) {
            $page->assertSee(__($feature->labelKey()));
            $page->assertSee(__($feature->bodyKey()));
            $page->assertSee($feature->fieldKey(), false);
        }
    }

    public function test_an_admin_can_turn_every_feature_on_and_configure_the_pipeline(): void
    {
        $admin = User::factory()->admin()->create();
        AiModel::factory()->create(['model_id' => 'vision-model']);
        AiModel::factory()->create(['model_id' => 'zai-org/GLM-5.3-Flash']);

        $this->actingAs($admin)
            ->patch('/admin/features', [
                'web_search_enabled' => '1',
                'web_search_price' => '0.015',
                'vision_enabled' => '1',
                'vision_model_id' => 'vision-model',
                'param_sanitizer_enabled' => '1',
                'model_substitute_enabled' => '1',
                'default_model_id' => 'zai-org/GLM-5.3-Flash',
                'pii_sanitizer_enabled' => '1',
                'patient_retry_enabled' => '1',
                'patient_retry_attempts' => '6',
                'patient_retry_budget' => '60',
                'json_schema_enabled' => '1',
                'repetition_guard_enabled' => '1',
            ])
            ->assertRedirect('/admin/features');

        $this->assertTrue(Setting::get('feature_web_search_enabled'));
        $this->assertTrue(Setting::get('feature_vision_enabled'));
        $this->assertSame('vision-model', Setting::get('feature_vision_model_id'));
        $this->assertTrue(Setting::get('feature_param_sanitizer_enabled'));
        $this->assertTrue(Setting::get('feature_model_substitute_enabled'));
        $this->assertSame('zai-org/GLM-5.3-Flash', Setting::get('feature_default_model_id'));
        $this->assertTrue(Setting::get('feature_pii_sanitizer_enabled'));
        $this->assertTrue(Setting::get('feature_patient_retry_enabled'));
        $this->assertSame(6, Setting::get('feature_patient_retry_attempts'));
        $this->assertSame(60, Setting::get('feature_patient_retry_budget'));
        $this->assertTrue(Setting::get('feature_json_schema_enabled'));
        $this->assertTrue(Setting::get('feature_repetition_guard_enabled'));
    }

    public function test_unticked_features_are_turned_back_off(): void
    {
        Setting::put('feature_pii_sanitizer_enabled', true, false, 'features');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/features', [])
            ->assertRedirect('/admin/features');

        $this->assertFalse(Setting::get('feature_pii_sanitizer_enabled'));
        $this->assertFalse(Setting::get('feature_web_search_enabled'));
    }

    public function test_the_features_page_shows_where_the_default_is_overridden(): void
    {
        $admin = User::factory()->admin()->create();

        $provider = AiProvider::factory()->create([
            'feature_overrides' => ['pii_sanitizer' => false],
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'feature_overrides' => ['pii_sanitizer' => true],
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'feature_overrides' => ['web_search' => true],
        ]);

        $this->actingAs($admin)->get('/admin/features')
            ->assertOk()
            ->assertSee(__('ui.admin.features.overrides_count', ['count' => 2]))
            ->assertSee(__('ui.admin.features.overrides_count', ['count' => 1]));
    }

    public function test_turning_a_billed_feature_on_is_recorded_with_what_changed(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/features', ['web_search_enabled' => '1', 'pii_sanitizer_enabled' => '1'])
            ->assertRedirect('/admin/features');

        $entry = AuditLog::query()->where('action', 'admin.features.updated')->sole();

        $this->assertSame(['web_search' => ['from' => false, 'to' => true], 'pii_sanitizer' => ['from' => false, 'to' => true]], $entry->meta['features']);
        $this->assertArrayNotHasKey('vision', $entry->meta['features'], 'A feature nobody touched must stay out of the audit entry.');
        $this->assertStringContainsString(__('ui.admin.features.web_search_name'), (string) $entry->description);
        $this->assertStringContainsString(__('ui.admin.providers.feature_on'), (string) $entry->description);
    }

    public function test_a_change_to_a_configured_value_is_recorded_too(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/features', ['web_search_price' => '0.05', 'patient_retry_attempts' => '9'])
            ->assertRedirect('/admin/features');

        $entry = AuditLog::query()->where('action', 'admin.features.updated')->sole();

        $this->assertSame(['from' => 0.015, 'to' => 0.05], $entry->meta['settings']['feature_web_search_price']);
        $this->assertSame(['from' => 6, 'to' => 9], $entry->meta['settings']['feature_patient_retry_attempts']);
        $this->assertArrayNotHasKey('features', $entry->meta, 'No toggle moved, so no toggle diff belongs in the entry.');
    }

    public function test_a_save_that_changes_nothing_writes_no_audit_entry(): void
    {
        Setting::put('feature_web_search_enabled', true, false, 'features');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/features', [
                'web_search_enabled' => '1',
                'web_search_price' => '0.015',
                'patient_retry_attempts' => '6',
                'patient_retry_budget' => '60',
            ])
            ->assertRedirect('/admin/features');

        $this->assertSame(0, AuditLog::query()->where('action', 'admin.features.updated')->count());
    }

    public function test_the_panel_shows_how_often_each_feature_ran_this_week(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->create();

        foreach ([1, 2] as $index) {
            GatewayRequest::query()->create([
                'request_id' => 'req_'.$index.'_'.Str::random(20),
                'organization_id' => $organization->getKey(),
                'endpoint' => 'chat',
                'model' => 'searched-model',
                'status_code' => 200,
                'prompt_tokens' => 1,
                'completion_tokens' => 1,
                'total_tokens' => 2,
                'cost' => 0.01,
                'latency_ms' => 100,
                'is_stream' => false,
                'meta' => ['features' => ['repetition_guard']],
                'created_at' => now(),
            ]);
        }

        $this->actingAs($admin)->get('/admin/features')
            ->assertOk()
            ->assertSee(__('ui.admin.features.usage_this_week', ['count' => '2']))
            ->assertDontSee(__('ui.admin.features.usage_this_week', ['count' => '0']))
            ->assertDontSee(__('ui.admin.features.usage_this_week', ['count' => '1']));
    }

    public function test_an_enabled_feature_with_no_traffic_reports_zero_instead_of_hiding(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/features', ['web_search_enabled' => '1'])->assertRedirect();

        $this->actingAs($admin)->get('/admin/features')
            ->assertOk()
            ->assertSee(__('ui.admin.features.usage_this_week', ['count' => '0']))
            ->assertDontSee(__('ui.admin.features.changes_empty'));
    }

    public function test_the_features_page_says_nothing_was_switched_when_no_entry_is_recent(): void
    {
        $admin = User::factory()->admin()->create();

        AuditLog::record('admin.features.updated', null, null, __('ui.admin.features.title'), [
            'features' => ['web_search' => ['from' => false, 'to' => true]],
        ]);

        AuditLog::query()->update(['created_at' => now()->subDays(8)]);

        $this->actingAs($admin)->get('/admin/features')
            ->assertOk()
            ->assertSee(__('ui.admin.features.changes_empty'));
    }

    public function test_the_features_page_reports_the_week_before_for_comparison(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->create();

        foreach (range(1, 3) as $index) {
            GatewayRequest::query()->create([
                'request_id' => 'req_old_'.$index.'_'.Str::random(20),
                'organization_id' => $organization->getKey(),
                'endpoint' => 'chat',
                'model' => 'old-model',
                'status_code' => 200,
                'prompt_tokens' => 1,
                'completion_tokens' => 1,
                'total_tokens' => 2,
                'cost' => 0.01,
                'latency_ms' => 100,
                'is_stream' => false,
                'meta' => ['features' => ['vision']],
                'created_at' => now()->subDays(9),
            ]);
        }

        $this->actingAs($admin)->get('/admin/features')
            ->assertOk()
            ->assertSee(__('ui.admin.features.usage_last_week', ['count' => '3']));
    }

    public function test_the_audit_page_names_a_toggled_feature_instead_of_its_key(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch('/admin/features', ['web_search_enabled' => '1'])->assertRedirect();

        $this->actingAs($admin)->get('/admin/audit?action=admin.features.updated')
            ->assertOk()
            ->assertSee(__('ui.admin.features.web_search_name'))
            ->assertSee(__('ui.admin.audit.from'))
            ->assertSee(__('ui.common.yes'));
    }

    public function test_the_retry_budget_is_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/features', [
                'patient_retry_attempts' => '0',
                'patient_retry_budget' => '1',
            ])
            ->assertSessionHasErrors(['patient_retry_attempts', 'patient_retry_budget']);
    }

    public function test_a_model_that_cannot_be_routed_to_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        AiModel::factory()->create(['model_id' => 'real-model']);

        $this->actingAs($admin)
            ->patch('/admin/features', [
                'vision_model_id' => 'ghost-model',
                'default_model_id' => 'ghost-model',
            ])
            ->assertSessionHasErrors(['vision_model_id', 'default_model_id']);

        $this->assertNotSame('ghost-model', Setting::get('feature_vision_model_id'));
        $this->assertNotSame('ghost-model', Setting::get('feature_default_model_id'));
    }

    public function test_the_panel_warns_when_a_configured_model_no_longer_resolves(): void
    {
        Setting::put('feature_default_model_id', 'ghost-model', false, 'features');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/features')
            ->assertOk()
            ->assertSee(__('ui.admin.features.model_missing', ['model' => 'ghost-model']));
    }

    public function test_every_recordable_feature_has_a_name_and_a_badge_in_both_locales(): void
    {
        $features = GatewayFeature::keys();
        $this->assertGreaterThanOrEqual(8, count($features), 'The feature catalogue lost features.');
        sort($features);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            $badges = trans('ui.admin.gateway.feature_badges');
            $this->assertIsArray($badges, "Badge labels must be an array in {$locale}.");

            $keys = array_keys($badges);
            sort($keys);
            $this->assertSame($features, $keys, "Badge labels drifted from the recordable features in {$locale}.");

            foreach (GatewayFeature::cases() as $feature) {
                $name = trans($feature->labelKey());
                $this->assertNotSame(
                    $feature->labelKey(),
                    $name,
                    "Feature {$feature->value} has no operator-facing name in {$locale}.",
                );

                $body = trans($feature->bodyKey());
                $this->assertNotSame(
                    $feature->bodyKey(),
                    $body,
                    "Feature {$feature->value} has no operator-facing body in {$locale}.",
                );

                $badge = trans($feature->badgeKey());
                $this->assertNotSame(
                    $feature->badgeKey(),
                    $badge,
                    "Feature {$feature->value} has no log badge label in {$locale}.",
                );
            }
        }
    }

    public function test_the_catalogue_order_matches_the_pipeline_feature_flags(): void
    {
        $flags = (new \ReflectionClass(GatewayService::class))->getConstant('FEATURE_FLAGS');
        $this->assertIsArray($flags, 'GatewayService::FEATURE_FLAGS must stay a readable map.');
        $this->assertNotEmpty($flags, 'GatewayService::FEATURE_FLAGS lost every prepared-input flag.');

        foreach ($flags as $preparedInput => $feature) {
            $this->assertContains(
                $feature,
                GatewayFeature::keys(),
                "Prepared-input flag {$preparedInput} points at {$feature}, which is not a catalogue feature.",
            );
        }
    }
}
