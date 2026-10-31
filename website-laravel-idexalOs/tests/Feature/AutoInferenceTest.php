<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\FreeInferenceCredential;
use App\Models\GatewayRequest;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutoInferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_desktop_key_exchanges_for_the_auto_contract_credential(): void
    {
        [$user, $organization, $desktopToken, $model] = $this->workspace();

        $response = $this->withToken($desktopToken)
            ->postJson('/v1/inference/free/credential')
            ->assertOk()
            ->assertJsonPath('credential.modelID', 'openai/gpt-6-luna')
            ->assertJsonPath('credential.baseURL', url('/api/v1'))
            ->assertJsonPath('credential.statusURL', url('/api/v1/auto/status'));

        $apiKey = (string) $response->json('credential.apiKey');
        $this->assertMatchesRegularExpression('/^ow_inf_[A-Za-z0-9_-]{43}$/', $apiKey);
        $this->assertDatabaseHas('free_inference_credentials', [
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
        ]);
    }

    public function test_auto_status_reports_the_weekly_allowance_from_auto_usage(): void
    {
        [$user, $organization, $desktopToken, $model] = $this->workspace();
        $autoKey = $this->autoKey($desktopToken);
        $credential = FreeInferenceCredential::query()->where('user_id', $user->getKey())->firstOrFail();

        GatewayRequest::query()->create([
            'request_id' => 'req_auto_usage',
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'api_key_id' => $credential->api_key_id,
            'endpoint' => 'chat/completions',
            'model' => (string) $model->model_id,
            'status_code' => 200,
            'cost' => 4,
            'created_at' => now(),
        ]);

        $this->withToken($autoKey)
            ->getJson('/api/v1/auto/status')
            ->assertOk()
            ->assertJsonPath('state', 'ready')
            ->assertJsonPath('allowance.limitUsd', 5)
            ->assertJsonPath('allowance.usedUsd', 4)
            ->assertJsonPath('allowance.remainingUsd', 1);
    }

    public function test_auto_models_expose_the_single_stable_alias(): void
    {
        [$user, $organization, $desktopToken, $model] = $this->workspace();
        $autoKey = $this->autoKey($desktopToken);

        $this->withToken($autoKey)
            ->getJson('/api/v1/models')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'openai/gpt-6-luna')
            ->assertJsonPath('data.0.owned_by', 'idexal-os-free');
    }

    public function test_auto_chat_routes_the_alias_to_the_configured_gateway_model(): void
    {
        [$user, $organization, $desktopToken, $model] = $this->workspace();
        $autoKey = $this->autoKey($desktopToken);

        Http::fake([
            'https://upstream.test/v1/chat/completions' => Http::response([
                'id' => 'chatcmpl-auto',
                'object' => 'chat.completion',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'Auto answered.'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 4, 'total_tokens' => 15],
            ], 200),
        ]);

        $this->withToken($autoKey)
            ->postJson('/api/v1/chat/completions', [
                'model' => 'openai/gpt-6-luna',
                'messages' => [['role' => 'user', 'content' => 'Hello Auto']],
            ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', 'Auto answered.')
            ->assertJsonPath('model', 'openai/gpt-6-luna');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://upstream.test/v1/chat/completions'
            && $request['model'] === (string) $model->model_id);

        $credential = FreeInferenceCredential::query()->where('user_id', $user->getKey())->firstOrFail();
        $this->assertDatabaseHas('gateway_requests', [
            'organization_id' => $organization->getKey(),
            'api_key_id' => $credential->api_key_id,
            'endpoint' => 'chat/completions',
            'prompt_tokens' => 11,
            'completion_tokens' => 4,
        ]);
    }

    public function test_auto_chat_streams_through_the_gateway(): void
    {
        [$user, $organization, $desktopToken, $model] = $this->workspace();
        $autoKey = $this->autoKey($desktopToken);

        $stream = 'data: {"choices":[{"delta":{"content":"Auto stream"}}],"model":"upstream"}'."\n\n"
            .'data: {"choices":[{"delta":[],"finish_reason":"stop"}],"usage":{"prompt_tokens":3,"completion_tokens":2}}'."\n\n"
            .'data: [DONE]'."\n\n";

        Http::fake([
            'https://upstream.test/v1/chat/completions' => Http::response($stream, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $response = $this->withToken($autoKey)
            ->postJson('/api/v1/chat/completions', [
                'model' => 'openai/gpt-6-luna',
                'stream' => true,
                'messages' => [['role' => 'user', 'content' => 'Stream please']],
            ])
            ->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('Auto stream', $content);
        $this->assertStringContainsString('[DONE]', $content);
    }

    /**
     * @return array{0: User, 1: Organization, 2: string, 3: AiModel}
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

        $provider = AiProvider::factory()->create([
            'slug' => 'upstream-auto',
            'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
            'base_url' => 'https://upstream.test/v1',
            'api_key' => 'upstream-secret',
            'is_active' => true,
            'priority' => 10,
        ]);

        $model = AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'auto-real-model',
            'display_name' => 'Auto real model',
            'input_price' => 1,
            'output_price' => 1,
            'is_active' => true,
        ]);

        Setting::put('free_auto_model_id', $model->model_id, false, 'general');

        [, $desktopToken] = ApiKey::issue(
            $organization,
            $user,
            'Desktop Auto test',
            [ApiKey::ABILITY_DESKTOP, ApiKey::ABILITY_GATEWAY_CHAT, ApiKey::ABILITY_GATEWAY_MODELS],
        );

        return [$user, $organization, $desktopToken, $model];
    }

    private function autoKey(string $desktopToken): string
    {
        return (string) $this->withToken($desktopToken)
            ->postJson('/v1/inference/free/credential')
            ->assertOk()
            ->json('credential.apiKey');
    }
}
