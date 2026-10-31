<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\GatewayRequest;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The gateway feature pipeline: request-side transformations (sanitizer, PII,
 * schema fit, web search, model substitution, vision) and response-side ones
 * (PII restore, JSON repair, repetition guard, patient retry).
 */
class GatewayFeaturePipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_param_sanitizer_strips_agent_fields_and_leaked_service_tags(): void
    {
        $this->enable(['param_sanitizer']);
        [, $token] = $this->workspaceWithModel('test-model');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response(
                $this->completion('Done.</tool_call>'),
                200,
            ),
        ]);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'messages' => [
                ['role' => 'user', 'content' => 'Hi', 'responsibility' => 'own this task'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', 'Done.');

        Http::assertSent(function ($request): bool {
            $message = $request['messages'][0];

            return ! isset($message['responsibility']) && $message['role'] === 'user';
        });
    }

    public function test_pii_is_redacted_before_forwarding_and_restored_in_the_answer(): void
    {
        $this->enable(['pii_sanitizer']);
        [, $token] = $this->workspaceWithModel('test-model');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => function ($request) {
                $content = (string) $request['messages'][0]['content'];

                return Http::response($this->completion('Saved '.$content), 200);
            },
        ]);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'messages' => [['role' => 'user', 'content' => 'Write to ada@example.com from 10.0.0.5']],
        ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', 'Saved Write to ada@example.com from 10.0.0.5');

        Http::assertSent(function ($request): bool {
            $content = (string) $request['messages'][0]['content'];

            return ! str_contains($content, 'ada@example.com')
                && ! str_contains($content, '10.0.0.5')
                && str_contains($content, '[[PII:');
        });
    }

    public function test_an_unknown_model_is_substituted_with_the_default_model(): void
    {
        $this->enable(['model_substitute'], ['feature_default_model_id' => 'test-model']);
        [, $token] = $this->workspaceWithModel('test-model');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response($this->completion('Substituted.'), 200),
        ]);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'does-not-exist',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', 'Substituted.')
            ->assertJsonPath('model', 'does-not-exist');

        Http::assertSent(fn ($request): bool => $request['model'] === 'test-model');
    }

    public function test_json_schema_auto_fit_strips_annotation_keys_and_reports_it(): void
    {
        $this->enable(['json_schema']);
        [, $token] = $this->workspaceWithModel('test-model');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response($this->completion('{"ok":true}'), 200),
        ]);

        $properties = [];
        foreach (range(0, 600) as $index) {
            $properties['p'.$index] = ['type' => 'string', 'title' => 'P'.$index, 'description' => 'Field '.$index];
        }

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'answer',
                    'schema' => [
                        'type' => 'object',
                        'title' => 'Answer',
                        'properties' => $properties,
                    ],
                ],
            ],
        ])
            ->assertOk()
            ->assertHeader('X-Json-Schema-Compacted', 'true')
            ->assertHeader('X-Json-Schema-Notice');

        Http::assertSent(function ($request): bool {
            $schema = $request['response_format']['json_schema']['schema'];

            return ! isset($schema['title'])
                && ! isset($schema['properties']['p0']['title'])
                && isset($schema['properties']['p0']['type']);
        });
    }

    public function test_json_mode_repair_unwraps_a_fenced_answer(): void
    {
        $this->enable(['json_schema']);
        [, $token] = $this->workspaceWithModel('test-model');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response(
                $this->completion("```json\n{\"ok\": true}\n```\nHope that helps!"),
                200,
            ),
        ]);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'response_format' => ['type' => 'json_object'],
        ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', '{"ok": true}');
    }

    public function test_the_repetition_guard_ends_a_looping_answer(): void
    {
        $this->enable(['repetition_guard']);
        [, $token] = $this->workspaceWithModel('test-model');

        $unit = 'I will explain this again in a moment. ';
        $looping = 'Here is the answer. '.str_repeat($unit, 6);

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response($this->completion($looping), 200),
        ]);

        $response = $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])->assertOk();

        $content = (string) $response->json('choices.0.message.content');

        $this->assertLessThan(strlen($looping), strlen($content));
        $this->assertSame('stop', $response->json('choices.0.finish_reason'));
        $this->assertStringStartsWith('Here is the answer. '.$unit, $content);
    }

    public function test_web_search_is_only_sent_when_the_operator_enabled_it(): void
    {
        [, $token] = $this->workspaceWithModel('test-model');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response($this->completion('No search.'), 200),
        ]);

        // Off: even a client-supplied flag is dropped.
        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'web_search' => true,
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])->assertOk();

        Http::assertSent(fn ($request): bool => ! array_key_exists('web_search', $request->data()));

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response($this->completion('Searched.'), 200),
        ]);

        $this->enable(['web_search']);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])->assertOk();

        Http::assertSent(fn ($request): bool => ($request->data()['web_search'] ?? null) === true);
    }

    public function test_patient_retry_keeps_retrying_a_429_on_the_same_slot(): void
    {
        $this->enable(['patient_retry'], [
            'feature_patient_retry_attempts' => 3,
            'feature_patient_retry_budget' => 30,
        ]);
        [, $token] = $this->workspaceWithModel('test-model');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::sequence()
                ->push(['error' => ['message' => 'Too many requests', 'type' => 'rate_limit_error']], 429)
                ->push($this->completion('Second time lucky.'), 200),
        ]);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', 'Second time lucky.');

        Http::assertSentCount(2);
    }

    public function test_vision_describes_images_for_a_text_only_model(): void
    {
        $this->enable(['vision'], ['feature_vision_model_id' => 'vision-model']);
        [$user, $organization] = $this->workspace();

        $textProvider = AiProvider::factory()->create([
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => 'https://api.provider.test/v1',
            'api_key' => 'provider-secret',
            'is_active' => true,
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $textProvider->getKey(),
            'model_id' => 'test-model',
            'capabilities' => ['chat' => true, 'vision' => false],
            'is_active' => true,
        ]);

        $visionProvider = AiProvider::factory()->create([
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => 'https://api.vision.test/v1',
            'api_key' => 'vision-secret',
            'is_active' => true,
        ]);
        AiModel::factory()->create([
            'ai_provider_id' => $visionProvider->getKey(),
            'model_id' => 'vision-model',
            'capabilities' => ['chat' => true, 'vision' => true],
            'is_active' => true,
        ]);

        [, $token] = ApiKey::issue($organization, $user, 'Vision key');

        Http::fake([
            'https://api.vision.test/v1/chat/completions' => Http::response($this->completion('A red barn at dusk.'), 200),
            'https://api.provider.test/v1/chat/completions' => Http::response($this->completion('I can see a barn.'), 200),
        ]);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'What is this?'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'https://img.test/barn.png']],
                ],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', 'I can see a barn.');

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://api.provider.test/v1/chat/completions') {
                return false;
            }

            $encoded = json_encode($request['messages']);

            return str_contains((string) $encoded, 'A red barn at dusk.')
                && ! str_contains((string) $encoded, 'barn.png');
        });
    }

    public function test_the_repetition_guard_ends_a_streaming_loop(): void
    {
        $this->enable(['repetition_guard']);
        [, $token] = $this->workspaceWithModel('test-model');

        $unit = 'the same sentence keeps coming back ';
        $stream = 'data: '.json_encode(['choices' => [['delta' => ['content' => 'Intro. ']]]])."\n\n";

        foreach (range(1, 12) as $ignored) {
            $stream .= 'data: '.json_encode(['choices' => [['delta' => ['content' => $unit]]]])."\n\n";
        }

        $stream .= 'data: [DONE]'."\n\n";

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response($stream, 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $content = $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'stream' => true,
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])->assertOk()->streamedContent();

        $this->assertStringContainsString('"finish_reason":"stop"', $content);
        $this->assertSame(1, substr_count($content, '[DONE]'));
        $this->assertLessThanOrEqual(4, substr_count($content, $unit));
    }

    public function test_pii_is_restored_in_each_streamed_chunk(): void
    {
        $this->enable(['pii_sanitizer']);
        [, $token] = $this->workspaceWithModel('test-model');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => function ($request) {
                $echoed = (string) $request['messages'][0]['content'];
                $stream = 'data: '.json_encode(['choices' => [['delta' => ['content' => $echoed]]]])."\n\n"
                    .'data: [DONE]'."\n\n";

                return Http::response($stream, 200, ['Content-Type' => 'text/event-stream']);
            },
        ]);

        $content = $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'stream' => true,
            'messages' => [['role' => 'user', 'content' => 'mail ada@example.com']],
        ])->assertOk()->streamedContent();

        $this->assertStringContainsString('ada@example.com', $content);
        $this->assertStringNotContainsString('[[PII:', $content);
    }

    public function test_the_request_log_records_which_features_were_applied(): void
    {
        $this->enable(['param_sanitizer', 'pii_sanitizer', 'repetition_guard']);
        [, $token] = $this->workspaceWithModel('test-model');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response($this->completion('Saved.'), 200),
        ]);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'messages' => [
                ['role' => 'user', 'content' => 'mail ada@example.com', 'responsibility' => 'own this'],
            ],
        ])->assertOk();

        $record = GatewayRequest::query()->orderByDesc('id')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['param_sanitizer', 'pii_sanitizer', 'repetition_guard'],
            $record->meta['features'],
        );
        $this->assertArrayNotHasKey('web_search_price', $record->meta);
    }

    public function test_web_search_records_its_feature_and_adds_its_flat_fee(): void
    {
        $this->enable(['web_search'], ['feature_web_search_price' => 0.02]);
        [, $token] = $this->workspaceWithModel('test-model');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response($this->completion('Fresh.'), 200),
        ]);

        // The factory prices 10 prompt + 5 completion tokens at 0.00002 together.
        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])->assertOk();

        $record = GatewayRequest::query()->orderByDesc('id')->firstOrFail();

        $this->assertContains('web_search', $record->meta['features']);
        $this->assertSame(0.02, (float) $record->meta['web_search_price']);
        $this->assertEqualsWithDelta(0.02002, (float) $record->cost, 1e-9);
    }

    public function test_a_substituted_model_is_flagged_in_the_audit_trail(): void
    {
        $this->enable(['model_substitute'], ['feature_default_model_id' => 'test-model']);
        [, $token] = $this->workspaceWithModel('test-model');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response($this->completion('Substituted.'), 200),
        ]);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'does-not-exist',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])->assertOk();

        $record = GatewayRequest::query()->orderByDesc('id')->firstOrFail();

        $this->assertContains('model_substitute', $record->meta['features']);
    }

    public function test_a_streamed_request_records_its_features_and_search_fee(): void
    {
        $this->enable(['web_search'], ['feature_web_search_price' => 0.02]);
        [, $token] = $this->workspaceWithModel('test-model');

        Http::fake([
            'https://api.provider.test/v1/chat/completions' => Http::response(
                'data: '.json_encode(['choices' => [['delta' => ['content' => 'Hi']]]])."\n\n"
                    .'data: '.json_encode([
                        'choices' => [['delta' => [], 'finish_reason' => 'stop']],
                        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
                    ])."\n\n"
                    .'data: [DONE]'."\n\n",
                200,
                ['Content-Type' => 'text/event-stream'],
            ),
        ]);

        $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'test-model',
            'stream' => true,
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ])->assertOk()->streamedContent();

        $record = GatewayRequest::query()->orderByDesc('id')->firstOrFail();

        $this->assertTrue($record->is_stream);
        $this->assertContains('web_search', $record->meta['features']);
        $this->assertEqualsWithDelta(0.02002, (float) $record->cost, 1e-9);
    }

    /**
     * @param  list<string>  $features
     * @param  array<string, mixed>  $extra
     */
    private function enable(array $features, array $extra = []): void
    {
        foreach ($features as $feature) {
            Setting::put('feature_'.$feature.'_enabled', true, false, 'features');
        }

        foreach ($extra as $key => $value) {
            Setting::put($key, $value, false, 'features');
        }
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
     * @return array{0: Organization, 1: string}
     */
    private function workspaceWithModel(string $modelId): array
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
            'model_id' => $modelId,
            'is_active' => true,
        ]);

        [, $token] = ApiKey::issue($organization, $user, 'Feature key');

        return [$organization, $token];
    }
}
