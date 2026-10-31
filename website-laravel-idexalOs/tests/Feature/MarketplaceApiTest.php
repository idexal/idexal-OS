<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->admin()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->getKey()]);

        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $this->user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        [, $this->token] = ApiKey::issue($this->organization, $this->user, 'Marketplace key');
    }

    public function test_marketplace_endpoints_expose_resolved_catalog(): void
    {
        $list = $this->withToken($this->token)->getJson('/v1/marketplaces');

        $list->assertOk();
        $this->assertNotEmpty($list->json('items'));

        $resolved = $this->withToken($this->token)
            ->getJson('/v1/marketplaces/mkt_idexal_official/resolved')
            ->assertOk()
            ->json('item');

        $this->assertSame('mkt_idexal_official', $resolved['marketplace']['id']);
        $this->assertNotEmpty($resolved['plugins']);

        $pluginId = $resolved['plugins'][0]['id'];
        $this->assertStringStartsWith('plg_', $pluginId);

        $plugin = $this->withToken($this->token)->getJson("/v1/plugins/{$pluginId}/resolved");
        $plugin->assertOk();
        $this->assertNotEmpty($plugin->json('items'));

        foreach ($plugin->json('items') as $membership) {
            $this->assertSame('skill', $membership['configObject']['objectType']);
        }
    }

    public function test_library_lists_installed_extensions_after_install(): void
    {
        $this->install('idexal-gateway-cookbook');

        $response = $this->withToken($this->token)->getJson('/v1/me/library');

        $response->assertOk();
        $items = $response->json('items');
        $this->assertNotEmpty($items);

        $first = $items[0];
        $this->assertSame('plugin', $first['type']);
        $this->assertIsString($first['id']);
        $this->assertStringStartsWith('plg_', $first['id']);
        $this->assertNotSame('', $first['name']);
    }

    public function test_marketplace_capabilities_use_modeled_object_types(): void
    {
        $this->install('idexal-gateway-cookbook');

        $response = $this->withToken($this->token)->getJson('/v1/resources/marketplace-capabilities');

        $response->assertOk();
        $items = $response->json('items');
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertSame('skill', $item['objectType']);
            $this->assertStringStartsWith('co_', $item['configObjectId']);
            $this->assertStringStartsWith('plg_', $item['pluginId']);
        }
    }

    public function test_resources_marketplace_snapshot_uses_maps(): void
    {
        $this->install('idexal-gateway-cookbook');

        $response = $this->withToken($this->token)->getJson('/v1/resources');

        $response->assertOk();
        $resources = $response->json('resources');
        $this->assertIsArray($resources['llmProviders']);
        $this->assertArrayHasKey('mkt_idexal_official', $resources['marketplaces']);
        $this->assertArrayHasKey('lastUpdatedAt', $resources['marketplaces']['mkt_idexal_official']);
    }

    public function test_mcp_connection_presets_expose_connectors(): void
    {
        $response = $this->withToken($this->token)->getJson('/v1/mcp-connections/presets');

        $response->assertOk();
        $presets = $response->json('presets');
        $this->assertNotEmpty($presets);

        foreach ($presets as $preset) {
            $this->assertStringStartsWith('preset_', $preset['presetId']);
            $this->assertContains($preset['authType'], ['oauth', 'apikey', 'none']);
            $this->assertStringStartsWith('https://', $preset['url']);
        }
    }

    public function test_inventory_revision_changes_after_install(): void
    {
        $before = $this->withToken($this->token)->getJson('/v1/org/inventory-revision')->json('revision');

        $this->install('idexal-gateway-cookbook');

        $after = $this->withToken($this->token)->getJson('/v1/org/inventory-revision')->json('revision');

        $this->assertIsString($before);
        $this->assertIsString($after);
        $this->assertNotSame($before, $after);
    }

    public function test_marketplace_is_self_contained_with_no_network_calls(): void
    {
        $this->assertFalse((bool) config('idexal.marketplace.remote'));

        Cache::forget('marketplace.catalog');
        Http::fake();

        $response = $this->withToken($this->token)->getJson('/v1/marketplaces');

        $response->assertOk();
        $this->assertNotEmpty($response->json('items'));

        Http::assertNothingSent();
    }

    /** Install through the admin web surface, the way operators do. */
    private function install(string $slug): void
    {
        $this->actingAs($this->user)->post('/admin/extensions/install', [
            'extension_slug' => $slug,
        ])->assertRedirect();
    }
}
