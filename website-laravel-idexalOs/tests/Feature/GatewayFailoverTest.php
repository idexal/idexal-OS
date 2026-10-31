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

class GatewayFailoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_retryable_provider_failure_falls_back_to_the_next_priority_provider(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $primary = $this->provider('primary', 100);
        $backup = $this->provider('backup', 10);
        $this->model($primary, 'shared-model');
        $this->model($backup, 'shared-model');

        Http::fakeSequence()
            ->push(['error' => ['message' => 'overloaded', 'type' => 'server_error']], 503)
            ->push($this->completion('Hello from backup.'), 200);

        $this->withToken($token)
            ->postJson('/v1/chat/completions', [
                'model' => 'shared-model',
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', 'Hello from backup.');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'primary.test'));
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'backup.test'));

        $this->assertDatabaseHas('gateway_requests', [
            'organization_id' => $organization->getKey(),
            'ai_provider_id' => $primary->getKey(),
            'status_code' => 503,
        ]);
        $this->assertDatabaseHas('gateway_requests', [
            'organization_id' => $organization->getKey(),
            'ai_provider_id' => $backup->getKey(),
            'status_code' => 200,
        ]);
    }

    public function test_a_client_error_is_not_replayed_on_another_provider(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $primary = $this->provider('primary', 100);
        $backup = $this->provider('backup', 10);
        $this->model($primary, 'shared-model');
        $this->model($backup, 'shared-model');

        Http::fakeSequence()
            ->push(['error' => ['message' => 'invalid request', 'type' => 'invalid_request_error']], 400);

        $this->withToken($token)
            ->postJson('/v1/chat/completions', [
                'model' => 'shared-model',
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertStatus(400);

        Http::assertSentCount(1);
        $this->assertDatabaseMissing('gateway_requests', [
            'organization_id' => $organization->getKey(),
            'ai_provider_id' => $backup->getKey(),
        ]);
    }

    public function test_a_streaming_request_falls_back_before_the_stream_starts(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $primary = $this->provider('primary', 100);
        $backup = $this->provider('backup', 10);
        $this->model($primary, 'shared-model');
        $this->model($backup, 'shared-model');

        $stream = "data: {\"choices\":[{\"delta\":{\"content\":\"Hello from backup.\"}}]}\n\ndata: [DONE]\n\n";
        Http::fakeSequence()
            ->push(['error' => ['message' => 'overloaded', 'type' => 'server_error']], 503)
            ->push($stream, 200, ['Content-Type' => 'text/event-stream']);

        $response = $this->withToken($token)
            ->postJson('/v1/chat/completions', [
                'model' => 'shared-model',
                'stream' => true,
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertOk();

        $this->assertStringContainsString('Hello from backup.', $response->streamedContent());
        Http::assertSentCount(2);
        $this->assertDatabaseHas('gateway_requests', [
            'organization_id' => $organization->getKey(),
            'ai_provider_id' => $backup->getKey(),
            'status_code' => 200,
        ]);
    }

    private function provider(string $slug, int $priority): AiProvider
    {
        return AiProvider::factory()->create([
            'slug' => $slug,
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => "https://{$slug}.test/v1",
            'api_key' => 'secret',
            'is_active' => true,
            'priority' => $priority,
        ]);
    }

    private function model(AiProvider $provider, string $modelId): AiModel
    {
        return AiModel::factory()->create([
            'ai_provider_id' => $provider->getKey(),
            'model_id' => $modelId,
            'is_active' => true,
        ]);
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
     * @return array{0: User, 1: Organization, 2: string}
     */
    private function workspace(): array
    {
        Plan::factory()->create(['slug' => 'free']);
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->getKey()]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        [, $token] = ApiKey::issue($organization, $user, 'Test key');

        return [$user, $organization, $token];
    }
}
