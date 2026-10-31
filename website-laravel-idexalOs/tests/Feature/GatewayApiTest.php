<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewayApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_openai_compatible_chat_completions_are_proxied_and_metered(): void
    {
        [$user, $organization] = $this->workspace();
        $provider = AiProvider::factory()->create([
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => 'https://api.provider.test/v1',
            'api_key' => 'provider-secret',
            'is_active' => true,
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'test-model',
            'is_active' => true,
        ]);
        [, $token] = ApiKey::issue($organization, $user, 'Test key');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'choices' => [
                    ['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Hello there.'], 'finish_reason' => 'stop'],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5, 'total_tokens' => 15],
            ], 200),
        ]);

        $response = $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ]);

        $response->assertOk()
            ->assertJsonPath('choices.0.message.content', 'Hello there.')
            ->assertHeader('X-Request-Id');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.provider.test/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer provider-secret')
            && $request['model'] === 'test-model');

        $this->assertDatabaseHas('gateway_requests', [
            'organization_id' => $organization->getKey(),
            'model' => 'test-model',
            'status_code' => 200,
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
        ]);
    }

    public function test_anthropic_messages_use_anthropic_headers_and_payload(): void
    {
        [$user, $organization] = $this->workspace();
        $provider = AiProvider::factory()->create([
            'driver' => AiProvider::DRIVER_ANTHROPIC,
            'base_url' => 'https://api.anthropic.test/v1',
            'api_key' => 'anthropic-secret',
            'is_active' => true,
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'claude-test',
            'is_active' => true,
        ]);
        [, $token] = ApiKey::issue($organization, $user, 'Anthropic key');

        Http::fake([
            'https://api.anthropic.test/v1/messages' => Http::response([
                'id' => 'msg_test',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [['type' => 'text', 'text' => 'Hello from Claude.']],
                'usage' => ['input_tokens' => 8, 'output_tokens' => 4],
            ], 200),
        ]);

        $this->withToken($token)->postJson('/v1/messages', [
            'model' => 'claude-test',
            'max_tokens' => 64,
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])
            ->assertOk()
            ->assertJsonPath('content.0.text', 'Hello from Claude.');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.anthropic.test/v1/messages'
            && $request->hasHeader('x-api-key', 'anthropic-secret')
            && $request->hasHeader('anthropic-version')
            && $request['max_tokens'] === 64);

        $this->assertDatabaseHas('gateway_requests', [
            'organization_id' => $organization->getKey(),
            'endpoint' => 'messages',
            'prompt_tokens' => 8,
            'completion_tokens' => 4,
        ]);
    }

    public function test_requests_are_rejected_when_the_plan_quota_is_exhausted_and_there_are_no_credits(): void
    {
        [$user, $organization] = $this->workspace();
        Plan::factory()->create([
            'slug' => 'free',
            'limits' => ['requests_per_month' => 0, 'tokens_per_month' => 0],
        ]);
        [, $token] = ApiKey::issue($organization, $user, 'Quota key');

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])
            ->assertStatus(429)
            ->assertJsonPath('error.type', 'rate_limit_error');
    }

    public function test_api_keys_are_scoped_to_their_abilities(): void
    {
        [$user, $organization] = $this->workspace();
        [, $token] = ApiKey::issue($organization, $user, 'Desktop only', [ApiKey::ABILITY_DESKTOP]);

        $this->withToken($token)->getJson('/v1/me')->assertOk();
        $this->withToken($token)->getJson('/v1/models')->assertForbidden();
    }

    public function test_revoked_keys_are_rejected(): void
    {
        [$user, $organization] = $this->workspace();
        [$apiKey, $token] = ApiKey::issue($organization, $user, 'Revoked key');
        $apiKey->revoke();

        $this->withToken($token)->getJson('/v1/me')->assertUnauthorized();
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
}
