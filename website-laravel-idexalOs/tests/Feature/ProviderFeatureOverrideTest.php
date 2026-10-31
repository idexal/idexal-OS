<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Per-provider feature overrides: an operator pins one gateway feature for a
 * single provider, and that choice outranks the platform-wide toggle.
 */
class ProviderFeatureOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_pin_features_for_one_provider(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create();

        $this->actingAs($admin)->patch('/admin/providers/'.$provider->getKey(), [
            'name' => $provider->name,
            'slug' => $provider->slug,
            'driver' => $provider->driver,
            'base_url' => $provider->base_url,
            'feature_overrides' => [
                'pii_sanitizer' => 'off',
                'web_search' => 'on',
                'vision' => 'inherit',
            ],
        ])->assertRedirect();

        $provider->refresh();

        $this->assertSame(['web_search' => true, 'pii_sanitizer' => false], $provider->feature_overrides);
        $this->assertFalse($provider->featureOverride('pii_sanitizer'));
        $this->assertTrue($provider->featureOverride('web_search'));
        $this->assertNull($provider->featureOverride('vision'));
    }

    public function test_an_unknown_override_value_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create();

        $this->actingAs($admin)->patch('/admin/providers/'.$provider->getKey(), [
            'name' => $provider->name,
            'slug' => $provider->slug,
            'driver' => $provider->driver,
            'base_url' => $provider->base_url,
            'feature_overrides' => ['pii_sanitizer' => 'maybe'],
        ])->assertSessionHasErrors('feature_overrides.pii_sanitizer');
    }

    public function test_the_provider_form_offers_a_three_way_choice_per_feature(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create();

        $this->actingAs($admin)->get('/admin/providers/'.$provider->getKey().'/edit')
            ->assertOk()
            ->assertSee('feature_overrides[pii_sanitizer]', false)
            ->assertSee('feature_overrides[json_schema]', false)
            ->assertSee(__('ui.admin.providers.feature_overrides'))
            ->assertSee(__('ui.admin.providers.feature_inherit'));
    }

    public function test_a_pin_change_is_recorded_with_the_state_it_moved_from(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create();

        $this->actingAs($admin)->patch('/admin/providers/'.$provider->getKey(), [
            'name' => $provider->name,
            'slug' => $provider->slug,
            'driver' => $provider->driver,
            'base_url' => $provider->base_url,
            'feature_overrides' => ['pii_sanitizer' => 'off', 'vision' => 'inherit'],
        ])->assertRedirect();

        $entry = AuditLog::query()->where('action', 'admin.provider.updated')->sole();

        $this->assertSame(['pii_sanitizer' => ['from' => 'inherit', 'to' => 'off']], $entry->meta['features']);
        $this->assertArrayNotHasKey('vision', $entry->meta['features'], 'Leaving a feature on inherit is not a pin change.');
    }

    public function test_a_provider_save_that_moves_no_pin_records_no_pin_diff(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create(['feature_overrides' => ['web_search' => true]]);

        $this->actingAs($admin)->patch('/admin/providers/'.$provider->getKey(), [
            'name' => $provider->name,
            'slug' => $provider->slug,
            'driver' => $provider->driver,
            'base_url' => $provider->base_url,
            'feature_overrides' => ['web_search' => 'on'],
        ])->assertRedirect();

        $entry = AuditLog::query()->where('action', 'admin.provider.updated')->sole();

        $this->assertSame([], $entry->meta);
    }

    public function test_the_inherit_choice_names_the_platform_default_it_falls_back_to(): void
    {
        Setting::put('feature_web_search_enabled', true, false, 'features');

        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create();

        $this->actingAs($admin)->get('/admin/providers/'.$provider->getKey().'/edit')
            ->assertOk()
            ->assertSee(__('ui.admin.providers.feature_inherit').' ('.__('ui.admin.providers.feature_on').')', false)
            ->assertSee(__('ui.admin.providers.feature_inherit').' ('.__('ui.admin.providers.feature_off').')', false);
    }

    public function test_a_provider_can_turn_off_a_feature_the_platform_turned_on(): void
    {
        Setting::put('feature_pii_sanitizer_enabled', true, false, 'features');

        [, $token] = $this->workspaceWithProvider('pinned-model', ['pii_sanitizer' => false]);

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => function ($request) {
                return Http::response($this->completion('Saved '.$request['messages'][0]['content']), 200);
            },
        ]);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'pinned-model',
            'messages' => [['role' => 'user', 'content' => 'Write to ada@example.com']],
        ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', 'Saved Write to ada@example.com');

        Http::assertSent(fn ($request): bool => str_contains((string) $request['messages'][0]['content'], 'ada@example.com'));
    }

    public function test_a_provider_override_only_applies_to_that_provider(): void
    {
        Setting::put('feature_pii_sanitizer_enabled', true, false, 'features');

        [$user, $organization] = $this->workspace();

        $pinned = AiProvider::factory()->create([
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => 'https://pinned.test/v1',
            'api_key' => 'pinned-secret',
            'is_active' => true,
            'feature_overrides' => ['pii_sanitizer' => false],
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $pinned->getKey(),
            'model_id' => 'pinned-model',
            'is_active' => true,
        ]);

        $inherited = AiProvider::factory()->create([
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => 'https://inherited.test/v1',
            'api_key' => 'inherited-secret',
            'is_active' => true,
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $inherited->getKey(),
            'model_id' => 'inherited-model',
            'is_active' => true,
        ]);

        [, $token] = ApiKey::issue($organization, $user, 'Override key');

        Http::fake([
            'https://pinned.test/v1/chat/completions' => Http::response($this->completion('ok'), 200),
            'https://inherited.test/v1/chat/completions' => Http::response($this->completion('ok'), 200),
        ]);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'pinned-model',
            'messages' => [['role' => 'user', 'content' => 'mail ada@example.com']],
        ])->assertOk();

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'inherited-model',
            'messages' => [['role' => 'user', 'content' => 'mail ada@example.com']],
        ])->assertOk();

        $sent = Http::recorded();

        $pinnedContent = (string) $sent->first(fn (array $pair): bool => $pair[0]->url() === 'https://pinned.test/v1/chat/completions')[0]['messages'][0]['content'];
        $inheritedContent = (string) $sent->first(fn (array $pair): bool => $pair[0]->url() === 'https://inherited.test/v1/chat/completions')[0]['messages'][0]['content'];

        $this->assertStringContainsString('ada@example.com', $pinnedContent);
        $this->assertStringNotContainsString('ada@example.com', $inheritedContent);
        $this->assertStringContainsString('[[PII:', $inheritedContent);
    }

    public function test_a_provider_can_force_a_feature_the_platform_leaves_off(): void
    {
        [, $token] = $this->workspaceWithProvider('forced-model', ['repetition_guard' => true]);

        $unit = 'the same sentence keeps coming back ';
        $looping = 'Here is the answer. '.str_repeat($unit, 6);

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response($this->completion($looping), 200),
        ]);

        $response = $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'forced-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])->assertOk();

        $content = (string) $response->json('choices.0.message.content');

        $this->assertLessThan(strlen($looping), strlen($content));
        $this->assertSame('stop', $response->json('choices.0.finish_reason'));
    }

    /**
     * @return array<string, mixed>
     */
    private function completion(string $content): array
    {
        return [
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'choices' => [
                ['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop'],
            ],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
        ];
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

    /**
     * @param  array<string, bool>  $overrides
     * @return array{0: Organization, 1: string}
     */
    private function workspaceWithProvider(string $modelId, array $overrides): array
    {
        [$user, $organization] = $this->workspace();

        $provider = AiProvider::factory()->create([
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => 'https://api.provider.test/v1',
            'api_key' => 'provider-secret',
            'is_active' => true,
            'feature_overrides' => $overrides,
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => $modelId,
            'is_active' => true,
        ]);

        [, $token] = ApiKey::issue($organization, $user, 'Override key');

        return [$organization, $token];
    }
}
