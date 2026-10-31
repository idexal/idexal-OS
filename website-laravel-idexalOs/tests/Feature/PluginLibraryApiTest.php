<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\ExtensionInstallation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PluginAccessGrant;
use App\Models\User;
use App\Services\MarketplaceCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The plugin endpoints the desktop's Library reads. They never existed on this
 * platform, so the access view failed instead of loading; these assert the
 * shape the client parses and the honest org-wide grant this platform can
 * express.
 */
class PluginLibraryApiTest extends TestCase
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
        [, $this->token] = ApiKey::issue($this->organization, $this->user, 'Library key');
    }

    public function test_the_plugin_list_includes_an_org_wide_access_grant(): void
    {
        $slug = $this->installPlugin();

        $response = $this->withToken($this->token)
            ->getJson('/v1/plugins?status=active&limit=100&includeAccess=true')
            ->assertOk();

        $items = $response->json('items');
        $this->assertNotEmpty($items, 'The installed plugin is listed.');

        $item = collect($items)->firstWhere('id', 'plg_'.$slug);
        $this->assertNotNull($item, 'The plugin is keyed by its prefixed id.');
        $this->assertArrayHasKey('access', $item);
        $this->assertTrue($item['access'][0]['orgWide'], 'Access on this platform is organization-wide.');
        $this->assertSame('viewer', $item['access'][0]['role']);
    }

    public function test_access_is_omitted_unless_requested(): void
    {
        $slug = $this->installPlugin();

        $response = $this->withToken($this->token)->getJson('/v1/plugins')->assertOk();

        $item = collect($response->json('items'))->firstWhere('id', 'plg_'.$slug);
        $this->assertNotNull($item);
        $this->assertArrayNotHasKey('access', $item);
    }

    public function test_one_plugins_access_list_loads(): void
    {
        $slug = $this->installPlugin();

        $this->withToken($this->token)
            ->getJson('/v1/plugins/plg_'.$slug.'/access?limit=100')
            ->assertOk()
            ->assertJsonPath('items.0.orgWide', true)
            ->assertJsonPath('items.0.role', 'viewer');
    }

    public function test_library_rows_report_what_a_plugin_is_made_of(): void
    {
        $slug = $this->installPlugin();

        $response = $this->withToken($this->token)->getJson('/v1/me/library')->assertOk();

        $item = collect($response->json('items'))->firstWhere('id', 'plg_'.$slug);
        $this->assertNotNull($item, 'The installed plugin is listed.');
        $this->assertArrayHasKey('componentKinds', $item);
        $this->assertNotEmpty($item['componentKinds'], 'A plugin row says what it is made of, not an empty list.');
        $this->assertGreaterThanOrEqual(1, $item['componentCount']);
    }

    public function test_the_batched_resolver_answers_a_page_of_plugins(): void
    {
        $slug = $this->installPlugin();

        $response = $this->withToken($this->token)
            ->getJson('/v1/resolved-plugins?pluginIds=plg_'.$slug)
            ->assertOk();

        $resolved = $response->json('plugins.plg_'.$slug);
        $this->assertNotNull($resolved, 'The plugin is keyed by the id the caller asked for.');
        $this->assertArrayHasKey('items', $resolved);
        $this->assertNotEmpty($resolved['items'], 'The plugin resolves to at least one configuration member.');
        $this->assertSame('plg_'.$slug, $resolved['items'][0]['pluginId']);
    }

    public function test_the_batched_resolver_reports_one_unknown_plugin_without_failing_the_batch(): void
    {
        $slug = $this->installPlugin();

        $response = $this->withToken($this->token)
            ->getJson('/v1/resolved-plugins?pluginIds=plg_'.$slug.',plg_no-such-plugin')
            ->assertOk();

        $this->assertNotNull($response->json('plugins.plg_'.$slug), 'The known plugin still resolves.');
        $this->assertSame(404, $response->json('failures.plg_no-such-plugin.status'));
        $this->assertSame('plugin_not_found', $response->json('failures.plg_no-such-plugin.error'));
    }

    public function test_the_batched_resolver_needs_at_least_one_id(): void
    {
        $this->withToken($this->token)->getJson('/v1/resolved-plugins')->assertStatus(422);
    }

    public function test_the_single_and_batched_resolvers_agree(): void
    {
        $slug = $this->installPlugin();

        $single = $this->withToken($this->token)->getJson('/v1/plugins/plg_'.$slug.'/resolved')->assertOk();
        $batch = $this->withToken($this->token)->getJson('/v1/resolved-plugins?pluginIds=plg_'.$slug)->assertOk();

        $this->assertSame(
            $single->json('items'),
            $batch->json('plugins.plg_'.$slug.'.items'),
            'Both resolvers share one implementation, so they cannot disagree.',
        );
    }

    public function test_an_unknown_plugin_is_a_404(): void
    {
        $this->withToken($this->token)
            ->getJson('/v1/plugins/plg_no-such-plugin/access')
            ->assertNotFound();
    }

    public function test_a_grant_narrows_a_plugin_to_the_members_named(): void
    {
        $slug = $this->installPlugin();

        // A second member of the same workspace, who is not granted.
        $otherUser = User::factory()->create(['locale' => 'en']);
        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $otherUser->getKey(),
            'role' => Membership::ROLE_MEMBER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
        [, $otherToken] = ApiKey::issue($this->organization, $otherUser, 'Other key');

        // The owner keeps it; grant it to the owner's membership explicitly,
        // which narrows the plugin to them.
        $ownerMembership = $this->organization->membershipFor($this->user);

        $this->withToken($this->token)
            ->postJson('/v1/plugins/plg_'.$slug.'/access', [
                'orgMembershipId' => $ownerMembership->getKey(),
                'role' => 'manager',
            ])
            ->assertOk()
            ->assertJsonPath('item.orgWide', false)
            ->assertJsonPath('item.orgMembershipId', $ownerMembership->getKey())
            ->assertJsonPath('item.role', 'manager');

        // The granted member still sees it; the other does not.
        $mine = collect($this->withToken($this->token)->getJson('/v1/me/library')->json('items'));
        $this->assertContains('plg_'.$slug, $mine->pluck('id')->all());

        $theirs = collect($this->withToken($otherToken)->getJson('/v1/me/library')->json('items'));
        $this->assertNotContains('plg_'.$slug, $theirs->pluck('id')->all());
    }

    public function test_revoking_a_grant_restores_org_wide_access(): void
    {
        $slug = $this->installPlugin();
        $membership = $this->organization->membershipFor($this->user);

        $grantId = $this->withToken($this->token)
            ->postJson('/v1/plugins/plg_'.$slug.'/access', ['orgMembershipId' => $membership->getKey()])
            ->assertOk()
            ->json('item.id');

        $this->withToken($this->token)
            ->deleteJson('/v1/plugins/plg_'.$slug.'/access/'.$grantId)
            ->assertOk();

        // With no grants left, the plugin names nobody and is available again.
        $this->withToken($this->token)
            ->getJson('/v1/plugins/plg_'.$slug.'/access')
            ->assertOk()
            ->assertJsonPath('items.0.orgWide', true)
            ->assertJsonPath('total', 1);
    }

    public function test_an_org_wide_grant_is_reported_and_is_explicit(): void
    {
        $slug = $this->installPlugin();

        $this->withToken($this->token)
            ->postJson('/v1/plugins/plg_'.$slug.'/access', ['orgWide' => true, 'role' => 'editor'])
            ->assertOk()
            ->assertJsonPath('item.orgWide', true)
            ->assertJsonPath('item.role', 'editor');

        // Naming the whole workspace never denies anyone.
        $items = collect($this->withToken($this->token)->getJson('/v1/me/library')->json('items'));
        $this->assertContains('plg_'.$slug, $items->pluck('id')->all());
    }

    public function test_a_team_target_is_refused_with_a_reason(): void
    {
        $slug = $this->installPlugin();

        $this->withToken($this->token)
            ->postJson('/v1/plugins/plg_'.$slug.'/access', ['teamId' => 'team_1'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'teams_unsupported');

        $this->assertSame(0, PluginAccessGrant::query()->count());
    }

    public function test_a_grant_for_an_unknown_membership_is_refused(): void
    {
        $slug = $this->installPlugin();

        $this->withToken($this->token)
            ->postJson('/v1/plugins/plg_'.$slug.'/access', ['orgMembershipId' => '01zzzzzzzzzzzzzzzzzzzzzzzz'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'unknown_membership');

        $this->assertSame(0, PluginAccessGrant::query()->count());
    }

    public function test_a_member_without_a_key_is_refused(): void
    {
        $this->getJson('/v1/plugins')->assertUnauthorized();
    }

    private function installPlugin(): string
    {
        // The marketplace catalogue is served from disk; one of its plugins is
        // installed for this organization so the Library has something to list.
        $slug = (string) (collect(app(MarketplaceCatalogService::class)->catalog()['items'])
            ->firstWhere('type', 'skill')['slug'] ?? '');

        $this->assertNotSame('', $slug, 'The catalogue exposes at least one skill.');

        ExtensionInstallation::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $this->user->getKey(),
            'extension_name' => $slug,
            'extension_type' => 'skill',
            'status' => 'active',
        ]);

        return $slug;
    }
}
