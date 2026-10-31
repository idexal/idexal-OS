<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\ProxyProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminProxyProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_and_regular_users_are_forbidden_from_proxy_provider_admin(): void
    {
        $this->get('/admin/proxy-providers')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/admin/proxy-providers')
            ->assertForbidden();
    }

    public function test_an_admin_can_create_a_proxy_provider_and_attach_it_to_plans(): void
    {
        $admin = User::factory()->admin()->create();
        $free = Plan::factory()->create(['slug' => 'free']);
        $pro = Plan::factory()->create(['slug' => 'pro']);

        $this->actingAs($admin)
            ->post('/admin/proxy-providers', [
                'name' => 'Example Residential',
                'slug' => 'example-residential',
                'driver' => 'generic',
                'protocol' => 'http',
                'website_url' => 'https://proxy.example.test/',
                'docs_url' => 'https://proxy.example.test/docs',
                'description' => 'Example provider',
                'countries' => 'US, gb; de us',
                'priority' => '7',
                'plans' => [$free->getKey(), $pro->getKey()],
                'is_active' => '1',
                'is_public' => '1',
            ])
            ->assertRedirect();

        $provider = ProxyProvider::query()->where('slug', 'example-residential')->firstOrFail();

        $this->assertSame(['us', 'gb', 'de'], $provider->countries);
        $this->assertTrue($provider->is_active);
        $this->assertTrue($provider->is_public);
        $this->assertSame(
            collect([$free->getKey(), $pro->getKey()])->sort()->values()->all(),
            $provider->plans()->pluck('plans.id')->sort()->values()->all(),
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.proxy_provider.created',
            'subject_id' => $provider->getKey(),
        ]);

        $this->actingAs($admin)->get('/admin/proxy-providers')->assertOk();
        $this->actingAs($admin)->get('/admin/proxy-providers/create')->assertOk();
        $this->actingAs($admin)->get("/admin/proxy-providers/{$provider->getKey()}/edit")->assertOk();
    }

    public function test_an_admin_can_update_a_proxy_provider_and_replace_its_plan_visibility(): void
    {
        $admin = User::factory()->admin()->create();
        $free = Plan::factory()->create(['slug' => 'free']);
        $pro = Plan::factory()->create(['slug' => 'pro']);
        $provider = ProxyProvider::factory()->create(['slug' => 'example']);
        $provider->plans()->attach($free);

        $this->actingAs($admin)
            ->patch("/admin/proxy-providers/{$provider->getKey()}", [
                'name' => 'Updated Provider',
                'slug' => 'example',
                'driver' => 'webshare',
                'protocol' => 'socks5',
                'countries' => 'fr',
                'priority' => '1',
                'plans' => [$pro->getKey()],
                'is_active' => '1',
                'is_public' => '1',
            ])
            ->assertRedirect("/admin/proxy-providers/{$provider->getKey()}/edit");

        $provider->refresh();

        $this->assertSame('Updated Provider', $provider->name);
        $this->assertSame('webshare', $provider->driver);
        $this->assertSame('socks5', $provider->protocol);
        $this->assertSame(['fr'], $provider->countries);
        $this->assertSame([$pro->getKey()], $provider->plans()->pluck('plans.id')->all());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.proxy_provider.updated',
            'subject_id' => $provider->getKey(),
        ]);
    }

    public function test_an_admin_can_delete_a_proxy_provider(): void
    {
        $admin = User::factory()->admin()->create();
        $provider = ProxyProvider::factory()->create();

        $this->actingAs($admin)
            ->delete("/admin/proxy-providers/{$provider->getKey()}")
            ->assertRedirect('/admin/proxy-providers');

        $this->assertModelMissing($provider);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.proxy_provider.deleted',
            'subject_id' => $provider->getKey(),
        ]);
    }

    public function test_an_admin_receives_validation_errors_for_an_empty_proxy_provider(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/proxy-providers', [])
            ->assertSessionHasErrors(['name', 'slug', 'driver', 'protocol']);

        $this->assertSame(0, ProxyProvider::query()->count());
    }

    public function test_a_proxy_provider_cannot_use_an_existing_slug(): void
    {
        $admin = User::factory()->admin()->create();
        ProxyProvider::factory()->create(['slug' => 'taken']);

        $this->actingAs($admin)
            ->post('/admin/proxy-providers', [
                'name' => 'Duplicate',
                'slug' => 'taken',
                'driver' => 'generic',
                'protocol' => 'http',
            ])
            ->assertSessionHasErrors('slug');
    }
}
