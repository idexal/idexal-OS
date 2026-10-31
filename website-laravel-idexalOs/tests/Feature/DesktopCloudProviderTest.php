<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\ComboModelService;
use App\Services\GatewayToken;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DesktopCloudProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_llm_providers_stay_empty_and_gateway_provider_has_the_strict_contract(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $this->providerWithModel('deepseek-v4-flash');

        $this->withToken($token)
            ->getJson('/v1/llm-providers')
            ->assertOk()
            ->assertExactJson(['llmProviders' => []]);

        $response = $this->withToken($token)
            ->withHeader('x-idexal-os-org-id', $organization->getKey())
            ->getJson('/v1/inference-providers?scope=usable')
            ->assertOk();

        $provider = $response->json('inferenceProviders.0');

        $this->assertCount(1, $response->json('inferenceProviders'));
        $this->assertStringStartsWith('ipr_', $provider['id']);
        $this->assertSame('idexal_os_gateway', $provider['source']);
        $this->assertSame('ready', $provider['credentialStatus']);
        $this->assertStringStartsWith(strtoupper($provider['id']).'_', $provider['providerConfig']['env'][0]);
        $this->assertMatchesRegularExpression('~^http://localhost(:\d+)?/v1$~', $provider['providerConfig']['options']['baseURL']);

        $this->assertNotSame([], $provider['models'], 'the workspace must receive at least one model');

        foreach ($provider['models'] as $model) {
            $this->assertCanonicalGatewayAlias($model, $organization);
        }

        $model = $provider['models'][0];
        $this->assertSame($model['id'], $model['config']['id']);
        $this->assertSame('deepseek-v4-flash', $model['upstreamModelId']);
        $this->assertSame('gmg_'.$organization->getKey(), $model['modelGroupId']);
        $this->assertSame('gcs_'.$organization->getKey(), $model['credentialSetId']);

        $connection = $this->withToken($token)
            ->getJson('/v1/inference-providers/'.$provider['id'].'/connect')
            ->assertOk()
            ->json('inferenceProvider');

        $this->assertStringStartsWith('ow_gw_', $connection['apiKey']);
        $this->assertSame($connection['apiKey'], $connection['apiKeys'][$provider['providerConfig']['env'][0]]);
    }

    public function test_the_managed_gateway_token_chats_through_the_real_model(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $this->providerWithModel('deepseek-v4-flash');

        $provider = $this->withToken($token)
            ->getJson('/v1/inference-providers?scope=usable')
            ->json('inferenceProviders.0');
        $gatewayToken = $this->withToken($token)
            ->getJson('/v1/inference-providers/'.$provider['id'].'/connect')
            ->json('inferenceProvider.apiKey');
        $modelId = $provider['models'][0]['id'];

        Http::fake([
            'https://upstream.test/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'choices' => [
                    ['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Hello from the gateway.'], 'finish_reason' => 'stop'],
                ],
                'usage' => ['prompt_tokens' => 8, 'completion_tokens' => 4, 'total_tokens' => 12],
            ], 200),
        ]);

        $this->withToken($gatewayToken)
            ->postJson('/v1/chat/completions', [
                'model' => $modelId,
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', 'Hello from the gateway.')
            ->assertJsonPath('model', $modelId);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://upstream.test/v1/chat/completions'
            && $request['model'] === 'deepseek-v4-flash'
            && $request->hasHeader('Authorization', 'Bearer upstream-secret'));

        $this->assertDatabaseHas('gateway_requests', [
            'organization_id' => $organization->getKey(),
            'status_code' => 200,
        ]);
    }

    public function test_managed_catalog_omits_remote_providers_without_credentials(): void
    {
        [$user, $organization, $token] = $this->workspace();

        $keyless = AiProvider::factory()->create([
            'slug' => 'keyless-remote',
            'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
            'base_url' => 'https://keyless.test/v1',
            'api_key' => null,
            'is_active' => true,
            'priority' => 10,
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $keyless->getKey(),
            'model_id' => 'keyless-model',
            'is_active' => true,
        ]);

        $loopback = AiProvider::factory()->create([
            'slug' => 'local-ollama',
            'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
            'base_url' => 'http://127.0.0.1:11434/v1',
            'api_key' => null,
            'is_active' => true,
            'priority' => 5,
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $loopback->getKey(),
            'model_id' => 'local-model',
            'is_active' => true,
        ]);

        $models = $this->withToken($token)
            ->getJson('/v1/inference-providers?scope=usable')
            ->assertOk()
            ->json('inferenceProviders.0.models');

        $upstreamIds = array_column($models, 'upstreamModelId');

        $this->assertNotContains('keyless-model', $upstreamIds);
        $this->assertContains('local-model', $upstreamIds);
    }

    public function test_a_model_restricted_to_another_plan_is_hidden_and_rejected(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $shared = $this->providerWithModel('shared-model');

        $otherPlan = Plan::factory()->create(['slug' => 'business']);
        $restricted = AiModel::factory()->create([
            'ai_provider_id' => $shared->ai_provider_id,
            'model_id' => 'business-only-model',
            'is_active' => true,
        ]);
        $restricted->plans()->attach($otherPlan);

        $provider = $this->withToken($token)
            ->getJson('/v1/inference-providers?scope=usable')
            ->assertOk()
            ->json('inferenceProviders.0');
        $gatewayToken = $this->withToken($token)
            ->getJson('/v1/inference-providers/'.$provider['id'].'/connect')
            ->json('inferenceProvider.apiKey');

        $upstreamIds = array_column($provider['models'], 'upstreamModelId');
        $this->assertContains('shared-model', $upstreamIds);
        $this->assertNotContains('business-only-model', $upstreamIds);

        Http::fake();
        $restrictedId = 'gwm_'.$organization->getKey().'_'.$organization->getKey().'_'.$restricted->getKey();

        $this->withToken($gatewayToken)
            ->postJson('/v1/chat/completions', [
                'model' => $restrictedId,
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_a_managed_stream_rewrites_the_upstream_model_to_the_requested_id(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $this->providerWithModel('deepseek-v4-flash');

        $provider = $this->withToken($token)
            ->getJson('/v1/inference-providers?scope=usable')
            ->json('inferenceProviders.0');
        $gatewayToken = $this->withToken($token)
            ->getJson('/v1/inference-providers/'.$provider['id'].'/connect')
            ->json('inferenceProvider.apiKey');
        $modelId = $provider['models'][0]['id'];

        $stream = 'data: {"choices":[{"delta":{"content":"OK"}}],"model":"upstream-alias"}

data: {"choices":[{"delta":[],"finish_reason":"stop"}],"usage":{"prompt_tokens":5,"completion_tokens":1}}

data: [DONE]

';
        Http::fake([
            'https://upstream.test/v1/chat/completions' => Http::response($stream, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $response = $this->withToken($gatewayToken)
            ->postJson('/v1/chat/completions', [
                'model' => $modelId,
                'stream' => true,
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('"model":"'.$modelId.'"', $content);
        $this->assertStringNotContainsString('upstream-alias', $content);
        $this->assertStringContainsString('OK', $content);
        $this->assertStringContainsString('"delta":{}', $content);
        $this->assertStringNotContainsString('"delta":[]', $content);
    }

    public function test_a_foreign_or_tampered_model_id_is_rejected_before_any_upstream_call(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $this->providerWithModel('deepseek-v4-flash');
        $other = Organization::factory()->create(['owner_id' => $user->getKey()]);

        $provider = $this->withToken($token)
            ->getJson('/v1/inference-providers?scope=usable')
            ->json('inferenceProviders.0');
        $gatewayToken = $this->withToken($token)
            ->getJson('/v1/inference-providers/'.$provider['id'].'/connect')
            ->json('inferenceProvider.apiKey');
        $modelKey = explode('_', $provider['models'][0]['id'])[3];
        $foreignModelId = 'gwm_'.$other->getKey().'_'.$other->getKey().'_'.$modelKey;

        Http::fake();

        $this->withToken($gatewayToken)
            ->postJson('/v1/chat/completions', [
                'model' => $foreignModelId,
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertNotFound();

        $this->withToken($gatewayToken.'tampered')
            ->postJson('/v1/chat/completions', [
                'model' => $provider['models'][0]['id'],
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_revoking_the_desktop_key_revokes_its_gateway_token(): void
    {
        [$user, $organization] = $this->workspace();
        [$apiKey, $token] = ApiKey::issue($organization, $user, 'Revocable desktop');
        $gatewayToken = app(GatewayToken::class)->issue($apiKey);

        $apiKey->revoke();

        $this->withToken($gatewayToken)
            ->getJson('/v1/models')
            ->assertUnauthorized();
    }

    public function test_gateway_models_expose_reasoning_efforts_to_the_desktop_engine(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $model = $this->providerWithModel('glm-5.3-flash');
        $model->forceFill([
            'capabilities' => ['chat' => true, 'streaming' => true, 'reasoning' => true],
            'reasoning_options' => ['low', 'medium', 'high'],
        ])->save();

        $provider = $this->withToken($token)
            ->getJson('/v1/inference-providers?scope=usable')
            ->json('inferenceProviders.0');

        $config = $provider['models'][0]['config'];

        $this->assertTrue($config['reasoning']);
        $this->assertSame(
            [['type' => 'effort', 'values' => ['low', 'medium', 'high']]],
            $config['reasoning_options'],
        );
    }

    public function test_a_text_only_model_offers_image_attachment_only_when_vision_will_describe_it(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $this->providerWithModel('text-only-model');

        $config = fn (): array => $this->withToken($token)
            ->getJson('/v1/inference-providers?scope=usable')
            ->json('inferenceProviders.0.models.0.config');

        $this->assertArrayNotHasKey('attachment', $config());

        Setting::put('feature_vision_enabled', true, false, 'features');

        $this->assertTrue($config()['attachment']);
    }

    public function test_a_model_can_pin_vision_off_even_when_the_platform_enables_it(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $model = $this->providerWithModel('pinned-off-model');
        $model->forceFill(['feature_overrides' => ['vision' => false]])->save();

        Setting::put('feature_vision_enabled', true, false, 'features');

        $config = $this->withToken($token)
            ->getJson('/v1/inference-providers?scope=usable')
            ->json('inferenceProviders.0.models.0.config');

        $this->assertArrayNotHasKey('attachment', $config);
    }

    public function test_a_fresh_workspace_sees_its_combo_even_when_the_combo_carries_a_stale_plan(): void
    {
        // A brand-new account exactly as registration builds it: a personal
        // workspace with the free plan subscription.
        Plan::factory()->create(['slug' => 'free', 'name' => 'Free']);
        $business = Plan::factory()->create(['slug' => 'business', 'name' => 'Business']);
        $user = User::factory()->create();
        $organization = app(OnboardingService::class)->createPersonalOrganization($user);
        [, $token] = ApiKey::issue($organization, $user, 'Fresh desktop');

        // Two providers offering one family, with credentials: the shape that
        // produces an automatic combo.
        $this->providerWithModel('deepseek-v4-flash');
        $providerB = AiProvider::factory()->create([
            'slug' => 'upstream-b',
            'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
            'base_url' => 'https://upstream-b.test/v1',
            'api_key' => 'upstream-b-secret',
            'is_active' => true,
            'priority' => 10,
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $providerB->getKey(),
            'model_id' => 'deepseek-v4-flash',
            'display_name' => 'DeepSeek V4 Flash',
            'context_window' => 128000,
            'max_output_tokens' => 8192,
            'is_active' => true,
        ]);

        $this->assertSame(1, app(ComboModelService::class)->syncAutomaticCombos());

        $combo = AiModel::query()
            ->whereHas('provider', fn ($query) => $query->where('slug', ComboModelService::PROVIDER_SLUG))
            ->firstOrFail();

        // The stale copy: the combo claims Business while its members serve the
        // free plan. The workspace must still receive the combo, and only the
        // members it replaces may disappear.
        $combo->plans()->sync([$business->getKey()]);

        $provider = $this->withToken($token)
            ->withHeader('x-idexal-os-org-id', $organization->getKey())
            ->getJson('/v1/inference-providers?scope=usable')
            ->assertOk()
            ->json('inferenceProviders.0');

        $this->assertNotNull($provider);
        $upstreamIds = array_column($provider['models'], 'upstreamModelId');

        $this->assertContains('combo-deepseek-v4-flash', $upstreamIds);
        $this->assertNotContains('deepseek-v4-flash', $upstreamIds);

        // The combo must also answer: it resolves to a member at call time even
        // though its own plan rows no longer match the workspace.
        $gatewayToken = $this->withToken($token)
            ->getJson('/v1/inference-providers/'.$provider['id'].'/connect')
            ->json('inferenceProvider.apiKey');

        Http::fake([
            'https://upstream.test/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-a',
                'object' => 'chat.completion',
                'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'A'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
            ], 200),
            'https://upstream-b.test/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-b',
                'object' => 'chat.completion',
                'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'B'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
            ], 200),
        ]);

        $comboId = 'gwm_'.$organization->getKey().'_'.$organization->getKey().'_'.$combo->getKey();

        $this->withToken($gatewayToken)
            ->postJson('/v1/chat/completions', [
                'model' => $comboId,
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertOk();

        // The combo resolved to a member: exactly one upstream call, addressed
        // with the member's own model id rather than the combo's.
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['model'] === 'deepseek-v4-flash'
            && in_array($request->url(), [
                'https://upstream.test/v1/chat/completions',
                'https://upstream-b.test/v1/chat/completions',
            ], true));
    }

    public function test_the_wire_model_id_is_the_canonical_alias_and_ignores_a_slashy_model_id(): void
    {
        [$user, $organization, $token] = $this->workspace();
        // A namespaced upstream id (zai-org/GLM-5.3-Flash) must never leak into
        // the wire id: the desktop addresses a model by the ULID alias only.
        $model = $this->providerWithModel('zai-org/GLM-5.3-Flash');

        $row = $this->withToken($token)
            ->withHeader('x-idexal-os-org-id', $organization->getKey())
            ->getJson('/v1/inference-providers?scope=usable')
            ->assertOk()
            ->json('inferenceProviders.0.models.0');

        $this->assertCanonicalGatewayAlias($row, $organization);
        $this->assertSame('gwm_'.$organization->getKey().'_'.$organization->getKey().'_'.$model->getKey(), $row['id']);
        $this->assertSame('zai-org/GLM-5.3-Flash', $row['upstreamModelId']);
        $this->assertStringNotContainsString('zai-org', $row['id']);
    }

    /**
     * The desktop drops any gateway model whose id is not the canonical
     * lowercase Crockford alias, and silently loses the whole catalog when the
     * segments disagree. Both ends assert this so a casing regression surfaces
     * in CI instead of as an empty workspace.
     *
     * @param  array<string, mixed>  $model
     */
    private function assertCanonicalGatewayAlias(array $model, Organization $organization): void
    {
        $id = (string) ($model['id'] ?? '');
        $crockford = '[0-7][0-9a-hjkmnp-tv-z]{25}';

        $this->assertMatchesRegularExpression("/^gwm_{$crockford}_{$crockford}_{$crockford}$/", $id);
        $this->assertSame(strtolower($id), $id, 'the wire model id must be lowercase; the desktop regex rejects uppercase');

        $modelGroupId = (string) ($model['modelGroupId'] ?? '');
        $credentialSetId = (string) ($model['credentialSetId'] ?? '');

        $this->assertMatchesRegularExpression("/^gmg_{$crockford}$/", $modelGroupId);
        $this->assertMatchesRegularExpression("/^gcs_{$crockford}$/", $credentialSetId);
        $this->assertSame(strtolower($modelGroupId), $modelGroupId);
        $this->assertSame(strtolower($credentialSetId), $credentialSetId);

        $segments = explode('_', $id);
        $this->assertCount(4, $segments);
        $this->assertSame($organization->getKey(), $segments[1]);
        $this->assertSame($organization->getKey(), $segments[2]);
        $this->assertSame($segments[1], substr($modelGroupId, 4), 'the model-group segment must match the id');
        $this->assertSame($segments[2], substr($credentialSetId, 4), 'the credential-set segment must match the id');
        $this->assertSame($id, $model['config']['id'] ?? null);
    }

    private function providerWithModel(string $modelId): AiModel
    {
        $provider = AiProvider::factory()->create([
            'slug' => 'upstream',
            'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
            'base_url' => 'https://upstream.test/v1',
            'api_key' => 'upstream-secret',
            'is_active' => true,
            'priority' => 50,
        ]);

        return AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => $modelId,
            'display_name' => 'DeepSeek V4 Flash',
            'context_window' => 128000,
            'max_output_tokens' => 8192,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: User, 1: Organization, 2: string}
     */
    private function workspace(): array
    {
        $plan = Plan::factory()->create(['slug' => 'pro']);
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->getKey()]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        [, $token] = ApiKey::issue($organization, $user, 'Desktop test key');

        return [$user, $organization, $token];
    }
}
