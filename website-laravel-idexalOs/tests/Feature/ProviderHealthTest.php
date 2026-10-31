<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\ProviderHealthLog;
use App\Models\User;
use App\Services\ProviderHealthMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_probe_is_recorded_as_healthy(): void
    {
        $provider = $this->provider('healthy');

        Http::fake(['*' => Http::response(['data' => [['id' => 'gpt-4o']]], 200)]);

        $result = app(ProviderHealthMonitorService::class)->checkProviderHealth($provider);

        $this->assertSame(ProviderHealthLog::STATUS_HEALTHY, $result['status']);
        $this->assertSame(0.0, $result['error_rate']);
        $this->assertDatabaseHas('provider_health_logs', [
            'ai_provider_id' => $provider->getKey(),
            'status' => ProviderHealthLog::STATUS_HEALTHY,
        ]);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer secret'));
    }

    public function test_a_rejected_key_is_recorded_as_unreachable(): void
    {
        $provider = $this->provider('rejected');

        Http::fake(['*' => Http::response(['error' => ['message' => 'bad key']], 401)]);

        $result = app(ProviderHealthMonitorService::class)->checkProviderHealth($provider);

        $this->assertSame(ProviderHealthLog::STATUS_UNHEALTHY, $result['status']);
        $this->assertDatabaseHas('provider_health_logs', [
            'ai_provider_id' => $provider->getKey(),
            'status' => ProviderHealthLog::STATUS_UNHEALTHY,
        ]);
    }

    public function test_a_rate_limited_probe_is_recorded_as_degraded(): void
    {
        $provider = $this->provider('limited');

        Http::fake(['*' => Http::response(['error' => ['message' => 'slow down']], 429)]);

        $result = app(ProviderHealthMonitorService::class)->checkProviderHealth($provider);

        $this->assertSame(ProviderHealthLog::STATUS_DEGRADED, $result['status']);
    }

    public function test_a_provider_without_any_key_is_recorded_as_degraded_without_a_request(): void
    {
        $provider = $this->provider('keyless', '');
        Http::fake();

        $result = app(ProviderHealthMonitorService::class)->checkProviderHealth($provider);

        $this->assertSame(ProviderHealthLog::STATUS_DEGRADED, $result['status']);
        $this->assertSame(__('ui.admin.providers.no_key'), $result['error_message']);
        Http::assertNothingSent();
    }

    public function test_the_latest_probe_wins_for_the_admin_badge(): void
    {
        $provider = $this->provider('flapping');

        ProviderHealthLog::query()->create([
            'ai_provider_id' => $provider->getKey(),
            'check_time' => now()->subHour(),
            'status' => ProviderHealthLog::STATUS_UNHEALTHY,
            'error_rate' => 1.0,
        ]);
        ProviderHealthLog::query()->create([
            'ai_provider_id' => $provider->getKey(),
            'check_time' => now(),
            'status' => ProviderHealthLog::STATUS_HEALTHY,
            'error_rate' => 0.0,
        ]);

        $latest = app(ProviderHealthMonitorService::class)->latestByProvider()->get((string) $provider->getKey());

        $this->assertNotNull($latest);
        $this->assertSame(ProviderHealthLog::STATUS_HEALTHY, $latest->status);
    }

    public function test_checking_all_covers_every_active_provider(): void
    {
        $this->provider('one');
        $this->provider('two');
        $paused = $this->provider('paused');
        $paused->forceFill(['is_active' => false])->save();

        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $results = app(ProviderHealthMonitorService::class)->checkAllProviders();

        $this->assertSame(2, $results->count());
        $this->assertSame(2, ProviderHealthLog::query()->count());
    }

    public function test_the_admin_can_probe_one_provider(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = $this->provider('manual');

        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->actingAs($admin)
            ->post('/admin/providers/'.$provider->getKey().'/health-check')
            ->assertRedirect();

        $this->assertDatabaseHas('provider_health_logs', [
            'ai_provider_id' => $provider->getKey(),
            'status' => ProviderHealthLog::STATUS_HEALTHY,
        ]);
    }

    public function test_the_admin_can_probe_every_provider_at_once(): void
    {
        $admin = User::factory()->admin()->create();
        $this->provider('alpha');
        $this->provider('beta');

        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->actingAs($admin)
            ->post('/admin/providers/health-check-all')
            ->assertRedirect();

        $this->assertSame(2, ProviderHealthLog::query()->count());
    }

    public function test_the_providers_page_shows_a_health_badge(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = $this->provider('badge');

        ProviderHealthLog::query()->create([
            'ai_provider_id' => $provider->getKey(),
            'check_time' => now(),
            'status' => ProviderHealthLog::STATUS_DEGRADED,
            'error_rate' => 0.5,
        ]);

        $this->actingAs($admin)
            ->get('/admin/providers')
            ->assertOk()
            ->assertSee(__('ui.admin.providers.health_degraded'));
    }

    public function test_the_health_command_records_results(): void
    {
        $this->provider('commanded');

        Http::fake(['*' => Http::response(['data' => []], 200)]);

        $this->artisan('providers:health-checks')->assertSuccessful();

        $this->assertSame(1, ProviderHealthLog::query()->count());
    }

    private function provider(string $slug, string $apiKey = 'secret'): AiProvider
    {
        return AiProvider::factory()->create([
            'slug' => $slug,
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => "https://{$slug}.test/v1",
            'api_key' => $apiKey,
            'is_active' => true,
            'priority' => 0,
        ]);
    }
}
