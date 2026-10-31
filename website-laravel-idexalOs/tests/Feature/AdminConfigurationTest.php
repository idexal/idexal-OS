<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_update_platform_settings_and_stripe_secrets_are_encrypted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/settings', [
                'site_name' => 'idexal Os',
                'site_tagline' => 'The AI workspace without limits',
                'support_email' => 'os@idexal.com',
                'default_currency' => 'usd',
                'tax_rate' => '5',
                'invoice_due_days' => '14',
                'payment_instructions' => 'Bank transfer details',
                'desktop_latest_version' => '0.1.0',
                'desktop_min_version' => '0.0.1',
                'maintenance_message' => '',
                'registration_enabled' => '1',
                'require_email_verification' => '0',
                'gateway_enabled' => '1',
                'billing_enabled' => '1',
                'stripe_key' => 'pk_test_example',
                'stripe_secret' => 'sk_test_example',
                'stripe_webhook_secret' => 'whsec_example',
            ])
            ->assertRedirect('/admin/settings');

        $this->assertSame('idexal Os', Setting::get('site_name'));
        $this->assertSame('USD', Setting::get('default_currency'));
        $this->assertSame('pk_test_example', Setting::get('stripe_key'));
        $this->assertSame('sk_test_example', Setting::get('stripe_secret'));
        $this->assertSame('whsec_example', Setting::get('stripe_webhook_secret'));

        $secret = Setting::query()->whereKey('stripe_secret')->firstOrFail();

        $this->assertTrue($secret->encrypted);
        $this->assertNotSame('sk_test_example', $secret->value);
    }

    public function test_an_admin_can_disable_the_proxy_manager_while_keeping_prewarm_enabled(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch('/admin/settings', [
                'site_name' => 'idexal Os',
                'site_tagline' => 'The AI workspace without limits',
                'support_email' => 'os@idexal.com',
                'default_currency' => 'usd',
                'tax_rate' => '0',
                'invoice_due_days' => '14',
                'payment_instructions' => '',
                'desktop_latest_version' => '0.1.0',
                'desktop_min_version' => '0.0.1',
                'maintenance_message' => '',
                'registration_enabled' => '1',
                'require_email_verification' => '0',
                'gateway_enabled' => '1',
                'browser_prewarm_enabled' => '1',
                'billing_enabled' => '1',
            ])
            ->assertRedirect('/admin/settings');

        $this->assertFalse(Setting::get('proxy_enabled'));
        $this->assertTrue(Setting::get('browser_prewarm_enabled'));
    }

    public function test_an_admin_can_create_a_provider_model_and_test_the_provider(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/providers', [
                'name' => 'Example AI',
                'slug' => 'example-ai',
                'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
                'base_url' => 'https://api.example.test/v1/',
                'api_key' => 'provider-secret',
                'priority' => '5',
                'notes' => 'Primary gateway',
                'is_active' => '1',
                'supports_streaming' => '1',
            ])
            ->assertRedirect('/admin/providers/'.AiProvider::query()->where('slug', 'example-ai')->value('id').'/edit');

        $provider = AiProvider::query()->where('slug', 'example-ai')->firstOrFail();

        $this->assertSame('https://api.example.test/v1', $provider->base_url);
        $this->assertSame('provider-secret', $provider->api_key);

        $this->actingAs($admin)
            ->get("/admin/providers/{$provider->getKey()}/edit")
            ->assertOk();

        $this->actingAs($admin)
            ->post("/admin/providers/{$provider->getKey()}/models", [
                'model_id' => 'example-model',
                'display_name' => 'Example Model',
                'description' => 'A test model',
                'context_window' => '128000',
                'max_output_tokens' => '4096',
                'input_price' => '1.5',
                'output_price' => '3',
                'sort_order' => '1',
                'is_active' => '1',
                'capabilities' => ['chat' => '1', 'streaming' => '1', 'reasoning' => '1'],
                'reasoning_options' => ['low', 'high'],
            ])
            ->assertRedirect("/admin/providers/{$provider->getKey()}/edit");

        $this->assertDatabaseHas('ai_models', [
            'ai_provider_id' => $provider->getKey(),
            'model_id' => 'example-model',
            'is_active' => true,
        ]);

        $model = AiModel::query()->where('model_id', 'example-model')->firstOrFail();
        $this->assertSame(['low', 'high'], $model->reasoning_options);
        $this->assertTrue($model->supports('reasoning'));

        Http::fake([
            'https://api.example.test/v1/models' => Http::response(['data' => []], 200),
        ]);

        $this->actingAs($admin)
            ->post("/admin/providers/{$provider->getKey()}/test")
            ->assertSessionHas('status');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.example.test/v1/models'
            && $request->hasHeader('Authorization', 'Bearer provider-secret'));
    }
}
