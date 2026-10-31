<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\ProviderCooldownService;
use App\Services\ProviderFallbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderCooldownTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_target_cools_down_after_two_retryable_failures_and_recovers_on_success(): void
    {
        $provider = $this->provider('primary', 100);
        $model = $this->model($provider, 'shared-model');
        $cooldown = app(ProviderCooldownService::class);

        $this->assertFalse($cooldown->isCoolingDown($provider, $model));

        $this->assertFalse($cooldown->recordFailure($provider, $model));
        $this->assertFalse($cooldown->isCoolingDown($provider, $model));

        $this->assertTrue($cooldown->recordFailure($provider, $model));
        $this->assertTrue($cooldown->isCoolingDown($provider, $model));
        $this->assertSame([$provider->getKey() => 1], $cooldown->coolingCountsByProvider());

        $cooldown->recordSuccess($provider, $model);

        $this->assertFalse($cooldown->isCoolingDown($provider, $model));
        $this->assertSame([], $cooldown->coolingCountsByProvider());
    }

    public function test_a_cooling_target_moves_behind_a_warm_one_without_losing_the_chain_order(): void
    {
        $main = $this->provider('main', 100);
        $middle = $this->provider('middle', 50);
        $chain = $this->provider('chain', 10);

        $mainModel = $this->model($main, 'shared-model');
        $middleModel = $this->model($middle, 'shared-model');
        $chainModel = $this->model($chain, 'shared-model');

        $fallback = app(ProviderFallbackService::class);
        $fallback->syncChain($main, [(string) $chain->getKey()]);

        $contexts = [
            $this->context($main, $mainModel),
            $this->context($middle, $middleModel),
            $this->context($chain, $chainModel),
        ];

        $keys = fn (array $ordered): array => collect($ordered)
            ->map(fn (array $context): string => (string) $context['provider']->getKey())
            ->all();

        // The operator's chain keeps its place: main, then chain, then middle.
        $this->assertSame(
            [$main->getKey(), $chain->getKey(), $middle->getKey()],
            $keys($fallback->order($contexts)),
        );

        // Chain is cooling: it drops behind middle but the rest is untouched.
        $cooldown = app(ProviderCooldownService::class);
        $cooldown->recordFailure($chain, $chainModel);
        $cooldown->recordFailure($chain, $chainModel);

        $this->assertSame(
            [$main->getKey(), $middle->getKey(), $chain->getKey()],
            $keys($fallback->order($contexts)),
        );
    }

    public function test_every_candidate_that_is_cooling_leaves_the_order_untouched(): void
    {
        $main = $this->provider('main', 100);
        $backup = $this->provider('backup', 10);

        $mainModel = $this->model($main, 'shared-model');
        $backupModel = $this->model($backup, 'shared-model');

        $cooldown = app(ProviderCooldownService::class);
        $contexts = [$this->context($main, $mainModel), $this->context($backup, $backupModel)];

        foreach ([[$main, $mainModel], [$backup, $backupModel]] as [$provider, $model]) {
            $cooldown->recordFailure($provider, $model);
            $cooldown->recordFailure($provider, $model);
        }

        $ordered = app(ProviderFallbackService::class)->order($contexts);

        $this->assertSame(
            [$main->getKey(), $backup->getKey()],
            collect($ordered)->map(fn (array $context): string => (string) $context['provider']->getKey())->all(),
        );
    }

    public function test_a_provider_that_failed_twice_is_tried_last_on_the_next_request(): void
    {
        [$user, $organization, $token] = $this->workspace();

        $main = $this->provider('main', 100);
        $backup = $this->provider('backup', 10);
        $model = $this->model($main, 'shared-model');
        $this->model($backup, 'shared-model');

        Http::fake([
            'https://main.test/*' => Http::response(['error' => ['message' => 'overloaded', 'type' => 'server_error']], 503),
            'https://backup.test/*' => Http::response($this->completion('Hello from the backup.'), 200),
        ]);

        $send = fn () => $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'shared-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ]);

        // Two requests pay for the failing head of the list and cool it down.
        $send()->assertOk()->assertJsonPath('choices.0.message.content', 'Hello from the backup.');
        $send()->assertOk();

        $this->assertTrue(app(ProviderCooldownService::class)->isCoolingDown($main, $model));

        // The third request starts with the healthy provider.
        $before = count(Http::recorded());
        $send()->assertOk()->assertJsonPath('choices.0.message.content', 'Hello from the backup.');

        $thirdAttempt = collect(Http::recorded())
            ->slice($before)
            ->map(fn (array $pair): string => $pair[0]->url())
            ->values()
            ->all();

        $this->assertCount(1, $thirdAttempt);
        $this->assertStringContainsString('backup.test', $thirdAttempt[0]);
    }

    public function test_a_single_provider_is_still_tried_while_cooling(): void
    {
        [, , $token] = $this->workspace();

        $only = $this->provider('only', 100);
        $model = $this->model($only, 'shared-model');

        Http::fake([
            'https://only.test/*' => Http::response(['error' => ['message' => 'overloaded', 'type' => 'server_error']], 503),
        ]);

        $send = fn () => $this->withToken($token)->postJson('/v1/chat/completions', [
            'model' => 'shared-model',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
        ]);

        $send()->assertStatus(503);
        $send()->assertStatus(503);

        $this->assertTrue(app(ProviderCooldownService::class)->isCoolingDown($only, $model));

        // Nothing to fall back to: the cooling provider is still the attempt.
        $send()->assertStatus(503);

        Http::assertSentCount(3);
    }

    public function test_the_providers_screen_flags_a_cooling_provider(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = $this->provider('primary', 100);
        $model = $this->model($provider, 'shared-model');

        $screen = $this->actingAs($admin)->get(route('admin.providers.index'));

        $screen->assertOk()->assertDontSee(__('ui.admin.providers.cooling'));

        $cooldown = app(ProviderCooldownService::class);
        $cooldown->recordFailure($provider, $model);
        $cooldown->recordFailure($provider, $model);

        $this->actingAs($admin)
            ->get(route('admin.providers.index'))
            ->assertOk()
            ->assertSee(__('ui.admin.providers.cooling'))
            ->assertSee(__('ui.admin.providers.cooling_hint'));
    }

    public function test_a_green_health_check_returns_a_cooling_provider_to_the_front(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = $this->provider('primary', 100);
        $model = $this->model($provider, 'shared-model');

        $cooldown = app(ProviderCooldownService::class);
        $cooldown->recordFailure($provider, $model);
        $cooldown->recordFailure($provider, $model);

        $this->assertTrue($cooldown->isCoolingDown($provider, $model));

        Http::fake(['*/models' => Http::response(['data' => [['id' => 'shared-model']]], 200)]);

        $this->actingAs($admin)
            ->post(route('admin.providers.health-check', $provider))
            ->assertRedirect();

        $this->assertFalse($cooldown->isCoolingDown($provider, $model));
        $this->assertSame([], $cooldown->coolingCountsByProvider());
    }

    private function provider(string $slug, int $priority, string $apiKey = 'secret'): AiProvider
    {
        return AiProvider::factory()->create([
            'slug' => $slug,
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => "https://{$slug}.test/v1",
            'api_key' => $apiKey,
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
     * @return array{model: AiModel, provider: AiProvider, url: string, admission: array{allowed: bool, overage: bool, reason: string|null}}
     */
    private function context(AiProvider $provider, AiModel $model): array
    {
        return [
            'model' => $model,
            'provider' => $provider,
            'url' => $provider->base_url.'/chat/completions',
            'admission' => ['allowed' => true, 'overage' => false, 'reason' => null],
        ];
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
        $user = User::factory()->create(['locale' => 'en']);
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
