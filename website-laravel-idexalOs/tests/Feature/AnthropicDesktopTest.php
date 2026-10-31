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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * An Anthropic-driver provider reaching the desktop.
 *
 * The desktop speaks the OpenAI chat shape, so an Anthropic provider used to be
 * filtered out of the catalog entirely: its models were invisible. With the
 * compatibility setting on, the gateway rewrites the request to a Messages
 * request and the answer back, so the models appear and work; with the setting
 * off nothing changes, which is what makes the switch a safe kill switch.
 */
class AnthropicDesktopTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['locale' => 'en']);
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->getKey()]);
        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $this->user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
        Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'is_active' => true, 'is_public' => true]);
        [, $this->token] = ApiKey::issue($this->organization, $this->user, 'Desktop key');
    }

    public function test_with_the_setting_off_an_anthropic_model_is_invisible_to_the_desktop(): void
    {
        $this->anthropicModel();

        $this->withToken($this->token)->getJson('/v1/inference-providers')
            ->assertOk()
            ->assertJsonPath('inferenceProviders', []);

        $this->withToken($this->token)->postJson('/v1/chat/completions', [
            'model' => 'claude-test',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])->assertStatus(404);
    }

    public function test_with_the_setting_on_the_model_is_offered_to_the_desktop(): void
    {
        Setting::put('gateway_anthropic_desktop', true);
        $this->anthropicModel();

        $response = $this->withToken($this->token)->getJson('/v1/inference-providers')->assertOk();

        $provider = $response->json('inferenceProviders.0');
        $this->assertSame('idexal Os Gateway', $provider['name']);
        $this->assertCount(1, $provider['models']);
        $this->assertSame('Claude', $provider['models'][0]['name']);
    }

    public function test_with_the_setting_on_the_chat_endpoint_calls_anthropic_messages(): void
    {
        Setting::put('gateway_anthropic_desktop', true);
        $this->anthropicModel();

        Http::fake([
            'https://api.anthropic.test/v1/messages' => Http::response([
                'id' => 'msg_test',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [['type' => 'text', 'text' => 'Hello from Claude.']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 8, 'output_tokens' => 4],
            ], 200),
        ]);

        $this->withToken($this->token)->postJson('/v1/chat/completions', [
            'model' => 'claude-test',
            'messages' => [
                ['role' => 'system', 'content' => 'Be brief.'],
                ['role' => 'user', 'content' => 'Hi'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', 'Hello from Claude.')
            ->assertJsonPath('choices.0.finish_reason', 'stop')
            ->assertJsonPath('usage.prompt_tokens', 8)
            ->assertJsonPath('usage.completion_tokens', 4);

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://api.anthropic.test/v1/messages') {
                return false;
            }

            // The Anthropic-shaped body: the system turn is lifted out, the
            // model is the upstream id, and `max_tokens` is always present.
            return $request->hasHeader('x-api-key', 'anthropic-secret')
                && $request->data()['model'] === 'claude-test'
                && $request->data()['system'] === 'Be brief.'
                && isset($request->data()['max_tokens'])
                && $request->data()['messages'][0]['content'][0]['type'] === 'text';
        });
    }

    public function test_the_delivery_diagnostic_stops_calling_it_undeliverable_when_the_setting_is_on(): void
    {
        $this->anthropicModel();

        $this->actingAs(User::factory()->admin()->create(['locale' => 'en']))
            ->get('/admin/gateway?delivery='.$this->organization->getKey())
            ->assertOk()
            ->assertSeeText('driver_not_desktop');

        Setting::put('gateway_anthropic_desktop', true);

        $this->actingAs(User::factory()->admin()->create(['locale' => 'en']))
            ->get('/admin/gateway?delivery='.$this->organization->getKey())
            ->assertOk()
            ->assertSeeText('claude-test')
            ->assertDontSeeText('driver_not_desktop');
    }

    public function test_a_streamed_anthropic_answer_is_rewritten_as_openai_deltas(): void
    {
        Setting::put('gateway_anthropic_desktop', true);
        $this->anthropicModel();

        $sse = implode("\n", [
            'event: message_start',
            'data: {"type":"message_start","message":{"usage":{"input_tokens":7}}}',
            '',
            'event: content_block_start',
            'data: {"type":"content_block_start","index":0,"content_block":{"type":"text","text":""}}',
            '',
            'event: content_block_delta',
            'data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"Hel"}}',
            '',
            'event: content_block_delta',
            'data: {"type":"content_block_delta","index":0,"delta":{"type":"text_delta","text":"lo"}}',
            '',
            'event: content_block_stop',
            'data: {"type":"content_block_stop","index":0}',
            '',
            'event: message_delta',
            'data: {"type":"message_delta","delta":{"stop_reason":"end_turn"},"usage":{"output_tokens":3}}',
            '',
            'event: message_stop',
            'data: {"type":"message_stop"}',
            '',
        ]);

        Http::fake([
            'https://api.anthropic.test/v1/messages' => Http::response($sse, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $response = $this->withToken($this->token)->postJson('/v1/chat/completions', [
            'model' => 'claude-test',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'stream' => true,
        ]);

        $response->assertOk();
        $body = $response->streamedContent();

        $this->assertStringContainsString('"content":"Hel"', $body);
        $this->assertStringContainsString('"content":"lo"', $body);
        $this->assertStringContainsString('"finish_reason":"stop"', $body);
        $this->assertStringContainsString('[DONE]', $body);
        // The Anthropic framing itself must never reach the client.
        $this->assertStringNotContainsString('content_block_delta', $body);
        $this->assertStringNotContainsString('message_stop', $body);
    }

    private function anthropicModel(): AiModel
    {
        $provider = AiProvider::factory()->create([
            'slug' => 'anthropic',
            'name' => 'Anthropic',
            'driver' => AiProvider::DRIVER_ANTHROPIC,
            'base_url' => 'https://api.anthropic.test/v1',
            'api_key' => 'anthropic-secret',
            'is_active' => true,
        ]);

        return AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'claude-test',
            'display_name' => 'Claude',
            'is_active' => true,
        ]);
    }
}
