<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProviderApiKey;
use App\Models\User;
use App\Services\ProviderFallbackService;
use App\Services\ProviderKeyRotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderResilienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_rotation_order_spreads_usage_across_every_key(): void
    {
        $provider = $this->provider('primary', 100, 'default-key');
        $primary = $provider->apiKeys()->create([
            'key_name' => 'primary-1',
            'key_type' => ProviderApiKey::TYPE_PRIMARY,
            'api_key_encrypted' => 'primary-key',
            'is_active' => true,
        ]);
        $provider->apiKeys()->create([
            'key_name' => 'backup-1',
            'key_type' => ProviderApiKey::TYPE_BACKUP,
            'api_key_encrypted' => 'backup-key',
            'is_active' => true,
        ]);

        $rotation = app(ProviderKeyRotationService::class);
        $values = fn (): array => array_map(
            fn (array $credential): string => $credential['value'],
            $rotation->credentialsFor($provider)
        );

        $this->assertSame(['default-key', 'primary-key', 'backup-key'], $values());

        $rotation->markExhausted((int) $primary->getKey());

        $this->assertSame(['default-key', 'backup-key', 'primary-key'], $values());
    }

    public function test_a_key_problem_moves_to_the_next_key_of_the_same_provider(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $provider = $this->provider('primary', 100, 'first-key');
        $this->extraKey($provider, 'second-key');
        $this->model($provider, 'shared-model');

        Http::fakeSequence()
            ->push(['error' => ['message' => 'rate limited', 'type' => 'rate_limit_error']], 429)
            ->push($this->completion('Hello from the second key.'), 200);

        $this->withToken($token)
            ->postJson('/v1/chat/completions', [
                'model' => 'shared-model',
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', 'Hello from the second key.');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer first-key'));
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer second-key'));

        $this->assertDatabaseHas('gateway_requests', [
            'organization_id' => $organization->getKey(),
            'ai_provider_id' => $provider->getKey(),
            'status_code' => 429,
        ]);
    }

    public function test_the_fallback_chain_is_tried_before_lower_priority_providers(): void
    {
        [$user, $organization, $token] = $this->workspace();
        $main = $this->provider('main', 100);
        $middle = $this->provider('middle', 50);
        $chain = $this->provider('chain', 10);
        $this->model($main, 'shared-model');
        $this->model($middle, 'shared-model');
        $this->model($chain, 'shared-model');

        app(ProviderFallbackService::class)->syncChain($main, [(string) $chain->getKey()]);

        Http::fakeSequence()
            ->push(['error' => ['message' => 'overloaded', 'type' => 'server_error']], 503)
            ->push($this->completion('Hello from the chain.'), 200);

        $this->withToken($token)
            ->postJson('/v1/chat/completions', [
                'model' => 'shared-model',
                'messages' => [['role' => 'user', 'content' => 'Hi']],
            ])
            ->assertOk()
            ->assertJsonPath('choices.0.message.content', 'Hello from the chain.');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'chain.test'));
        $this->assertDatabaseMissing('gateway_requests', [
            'organization_id' => $organization->getKey(),
            'ai_provider_id' => $middle->getKey(),
        ]);
    }

    public function test_the_fallback_service_orders_the_configured_chain_first(): void
    {
        $main = $this->provider('main', 100);
        $middle = $this->provider('middle', 50);
        $chain = $this->provider('chain', 10);

        app(ProviderFallbackService::class)->syncChain($main, [(string) $chain->getKey()]);

        $ordered = collect(app(ProviderFallbackService::class)->order([
            $this->context($main),
            $this->context($middle),
            $this->context($chain),
        ]))->map(fn (array $context): string => (string) $context['provider']->getKey())->all();

        $this->assertSame([$main->getKey(), $chain->getKey(), $middle->getKey()], $ordered);
    }

    public function test_a_key_can_be_added_with_its_type(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = $this->provider('primary', 100);

        $this->actingAs($admin)
            ->post('/admin/providers/'.$provider->getKey().'/keys', [
                'api_key' => 'sk-backup-value',
                'key_name' => 'backup-1',
                'key_type' => ProviderApiKey::TYPE_BACKUP,
            ])
            ->assertRedirect();

        $key = ProviderApiKey::query()->firstOrFail();

        $this->assertSame($provider->getKey(), $key->ai_provider_id);
        $this->assertSame('backup-1', $key->key_name);
        $this->assertSame(ProviderApiKey::TYPE_BACKUP, $key->key_type);
        $this->assertTrue($key->is_active);
        $this->assertSame('sk-backup-value', $key->api_key_encrypted);
    }

    public function test_a_revoked_key_stops_being_usable(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = $this->provider('primary', 100);
        $key = $this->extraKey($provider, 'second-key');

        $this->actingAs($admin)
            ->delete('/admin/providers/'.$provider->getKey().'/keys/'.$key->getKey())
            ->assertRedirect();

        $this->assertFalse($key->fresh()->is_active);
    }

    public function test_the_fetch_models_page_lists_the_provider_catalog(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = $this->provider('primary', 100);

        Http::fake([
            '*/models' => Http::response(['data' => [['id' => 'gpt-4o-mini'], ['id' => 'gpt-4o']]], 200),
        ]);

        $this->actingAs($admin)
            ->get('/admin/providers/'.$provider->getKey().'/models/fetch')
            ->assertOk()
            ->assertSee('gpt-4o-mini')
            ->assertSee('gpt-4o');
    }

    public function test_bulk_import_skips_models_already_added(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = $this->provider('primary', 100);
        $this->model($provider, 'gpt-4o');

        $this->actingAs($admin)
            ->post('/admin/providers/'.$provider->getKey().'/models/import', [
                'models' => ['gpt-4o', 'gpt-4o-mini'],
                'input_price' => 1,
                'output_price' => 2,
            ])
            ->assertRedirect(route('admin.providers.edit', $provider));

        $this->assertSame(1, $provider->models()->where('model_id', 'gpt-4o-mini')->count());
        $this->assertSame(1, $provider->models()->where('model_id', 'gpt-4o')->count());
    }

    public function test_the_fallback_chain_saves_with_its_order(): void
    {
        $admin = User::factory()->admin()->create();
        $main = $this->provider('main', 100);
        $first = $this->provider('first', 50);
        $second = $this->provider('second', 10);

        $this->actingAs($admin)
            ->post('/admin/providers/'.$main->getKey().'/backup-chain', [
                'backup_providers' => [(string) $first->getKey(), (string) $second->getKey()],
                'backup_order' => [
                    (string) $first->getKey() => 2,
                    (string) $second->getKey() => 1,
                ],
            ])
            ->assertRedirect(route('admin.providers.edit', $main));

        $this->assertSame(
            [$second->getKey(), $first->getKey()],
            $main->backupProviders()->map(fn (AiProvider $provider): string => (string) $provider->getKey())->all()
        );
    }

    public function test_rotate_all_moves_every_auto_rotation_provider(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = $this->provider('auto', 100);
        $provider->forceFill(['supports_auto_key_rotation' => true])->save();
        $key = $this->extraKey($provider, 'second-key');

        $this->actingAs($admin)
            ->post('/admin/providers/rotate-all-keys')
            ->assertRedirect();

        $this->assertSame(1, $key->fresh()->rotation_count);
    }

    public function test_the_provider_admin_screens_render_in_both_locales(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = $this->provider('primary', 100);
        $other = $this->provider('other', 50);
        $this->extraKey($provider, 'second-key');
        $this->model($provider, 'shared-model');

        app(ProviderFallbackService::class)->syncChain($provider, [(string) $other->getKey()]);

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            foreach ([
                route('admin.providers.index'),
                route('admin.providers.edit', $provider),
                route('admin.provider-key-management.edit-keys', $provider),
            ] as $url) {
                $response = $this->actingAs($admin)->get($url)->assertOk();

                $this->assertSame(
                    0,
                    preg_match_all('/[\x{0660}-\x{0669}\x{06F0}-\x{06F9}]/u', $response->getContent()),
                    "Arabic-Indic digits found on {$url} ({$locale})"
                );
            }
        }
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

    private function extraKey(AiProvider $provider, string $value): ProviderApiKey
    {
        return $provider->apiKeys()->create([
            'key_name' => 'extra-'.$value,
            'key_type' => ProviderApiKey::TYPE_BACKUP,
            'api_key_encrypted' => $value,
            'is_active' => true,
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
    private function context(AiProvider $provider): array
    {
        return [
            'model' => $this->model($provider, 'shared-model'),
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
