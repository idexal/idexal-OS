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
 * Per-model feature overrides, the finest grain of control: a model's choice
 * outranks its provider's, which outranks the platform toggle.
 */
class ModelFeatureOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_pin_features_for_one_model(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create();
        $model = AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'gpt-test',
        ]);

        $this->actingAs($admin)->patch('/admin/providers/'.$provider->getKey().'/models/'.$model->getKey(), [
            'model_id' => 'gpt-test',
            'display_name' => $model->display_name,
            'input_price' => '1',
            'output_price' => '2',
            'feature_overrides' => ['pii_sanitizer' => 'off'],
        ])->assertRedirect();

        $model->refresh();

        $this->assertSame(['pii_sanitizer' => false], $model->feature_overrides);
        $this->assertFalse($model->featureOverride('pii_sanitizer'));
        $this->assertNull($model->featureOverride('vision'));
    }

    public function test_a_model_pin_change_is_recorded_with_the_state_it_moved_from(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create();
        $model = AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'gpt-audit',
            'feature_overrides' => ['vision' => true],
        ]);

        $this->actingAs($admin)->patch('/admin/providers/'.$provider->getKey().'/models/'.$model->getKey(), [
            'model_id' => 'gpt-audit',
            'display_name' => $model->display_name,
            'input_price' => '1',
            'output_price' => '2',
            'feature_overrides' => ['vision' => 'inherit', 'json_schema' => 'on'],
        ])->assertRedirect();

        $entry = AuditLog::query()->where('action', 'admin.provider_model.updated')->sole();

        $this->assertSame([
            'vision' => ['from' => 'on', 'to' => 'inherit'],
            'json_schema' => ['from' => 'inherit', 'to' => 'on'],
        ], $entry->meta['features']);
    }

    public function test_a_model_can_turn_off_a_feature_the_platform_turned_on(): void
    {
        Setting::put('feature_pii_sanitizer_enabled', true, false, 'features');

        [, $token] = $this->workspaceWithModel('pinned-model', ['pii_sanitizer' => false]);

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => function ($request) {
                return Http::response($this->completion('Saved '.$request['messages'][0]['content']), 200);
            },
        ]);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'pinned-model',
            'messages' => [['role' => 'user', 'content' => 'Write to ada@example.com']],
        ])->assertOk();

        Http::assertSent(fn ($request): bool => str_contains((string) $request['messages'][0]['content'], 'ada@example.com'));
    }

    public function test_a_model_override_outranks_its_provider_override(): void
    {
        [, $token] = $this->workspaceWithModel(
            'decisive-model',
            ['repetition_guard' => false],
            ['provider_overrides' => ['repetition_guard' => true]],
        );

        $unit = 'the same sentence keeps coming back ';
        $looping = 'Here is the answer. '.str_repeat($unit, 6);

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response($this->completion($looping), 200),
        ]);

        // The provider says "on", the model says "off": the model wins, so the
        // loop is left untouched.
        $response = $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'decisive-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])->assertOk();

        $this->assertSame($looping, $response->json('choices.0.message.content'));
    }

    public function test_the_provider_form_shows_a_control_inside_every_model_row(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = AiProvider::factory()->create();
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'listed-model',
            'display_name' => 'Listed model',
        ]);

        $this->actingAs($admin)->get('/admin/providers/'.$provider->getKey().'/edit')
            ->assertOk()
            ->assertSee('feature_overrides[repetition_guard]', false)
            ->assertSee('feature_overrides[web_search]', false);
    }

    public function test_a_model_can_force_a_feature_its_provider_leaves_alone(): void
    {
        [, $token] = $this->workspaceWithModel('forced-model', ['repetition_guard' => true]);

        $unit = 'the same sentence keeps coming back ';
        $looping = 'Here is the answer. '.str_repeat($unit, 6);

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response($this->completion($looping), 200),
        ]);

        $response = $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'forced-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])->assertOk();

        $this->assertLessThan(strlen($looping), strlen((string) $response->json('choices.0.message.content')));
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
     * @param  array<string, bool>  $modelOverrides
     * @param  array<string, mixed>  $providerExtra
     * @return array{0: Organization, 1: string}
     */
    private function workspaceWithModel(string $modelId, array $modelOverrides, array $providerExtra = []): array
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

        $provider = AiProvider::factory()->create([
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => 'https://api.provider.test/v1',
            'api_key' => 'provider-secret',
            'is_active' => true,
            'feature_overrides' => $providerExtra['provider_overrides'] ?? null,
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => $modelId,
            'is_active' => true,
            'feature_overrides' => $modelOverrides,
        ]);

        [, $token] = ApiKey::issue($organization, $user, 'Model override key');

        return [$organization, $token];
    }
}
