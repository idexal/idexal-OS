<?php

namespace Tests\Feature;

use App\Models\AgentPermissionPolicy;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Support\AgentPermission;
use App\Support\AgentPermissionPolicy as Policy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The desktop enforces `agentPermissions.rules` from `/v1/me/desktop-config`
 * on both engines, so the platform has to produce that list, keep it strict
 * (the desktop drops the whole list when one rule is malformed), and leave
 * every workspace that never touched the screen exactly as it was.
 */
class AgentPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['locale' => 'en']);
        $this->organization = $this->workspace($this->admin);
        [, $this->token] = ApiKey::issue($this->organization, $this->admin, 'Agent permissions key');
    }

    public function test_a_workspace_that_never_set_a_policy_sends_no_rules(): void
    {
        $config = $this->withToken($this->token)->getJson('/v1/me/desktop-config')->assertOk();

        $this->assertSame([], $config->json('agentPermissions.rules'));
        $this->assertSame(0, AgentPermissionPolicy::query()->count());
    }

    public function test_every_emitted_rule_has_the_shape_the_desktop_requires(): void
    {
        $this->save([
            'decision' => [
                'commands' => 'ask',
                'websites' => 'deny',
                'webSearch' => 'deny',
            ],
            'allow' => ['commands' => "npm test *\ngit status"],
            'block' => [
                'commands' => 'rm -rf *',
                'websites' => '*.internal.example',
            ],
        ]);

        $rules = $this->withToken($this->token)->getJson('/v1/me/desktop-config')->json('agentPermissions.rules');

        $this->assertNotEmpty($rules);

        foreach ($rules as $rule) {
            // The desktop parses the array with a strict schema: a single
            // missing or unknown field discards every rule it sent.
            $this->assertSame(['action', 'resource', 'effect', 'source'], array_keys($rule));
            $this->assertContains($rule['action'], ['shell', 'edit', 'webfetch', 'websearch', 'skill', 'mcp', 'settings', 'proxy', 'extensions', 'browser']);
            $this->assertContains($rule['effect'], ['allow', 'ask', 'deny']);
            $this->assertNotSame('', $rule['resource']);
            $this->assertSame(Policy::SOURCE, $rule['source']);
        }
    }

    public function test_a_restricted_permission_sends_its_decision_first_and_its_exceptions_after(): void
    {
        $this->save([
            'decision' => ['commands' => 'ask', 'websites' => 'deny'],
            'allow' => ['commands' => "npm test *\ngit status", 'websites' => 'docs.example.com'],
            'block' => ['commands' => 'rm -rf *'],
        ]);

        $rules = $this->withToken($this->token)->getJson('/v1/me/desktop-config')->json('agentPermissions.rules');

        $this->assertSame([
            ['action' => 'shell', 'resource' => '*', 'effect' => 'ask', 'source' => 'Everyone'],
            ['action' => 'shell', 'resource' => 'npm test *', 'effect' => 'allow', 'source' => 'Everyone'],
            ['action' => 'shell', 'resource' => 'git status', 'effect' => 'allow', 'source' => 'Everyone'],
            ['action' => 'shell', 'resource' => 'rm -rf *', 'effect' => 'deny', 'source' => 'Everyone'],
            ['action' => 'webfetch', 'resource' => '*', 'effect' => 'deny', 'source' => 'Everyone'],
            ['action' => 'webfetch', 'resource' => 'docs.example.com', 'effect' => 'allow', 'source' => 'Everyone'],
        ], $rules);
    }

    public function test_blocking_file_edits_blocks_commands_last(): void
    {
        $this->save([
            'decision' => ['fileEdits' => 'deny'],
            'allow' => ['commands' => 'git status'],
        ]);

        $rules = $this->withToken($this->token)->getJson('/v1/me/desktop-config')->json('agentPermissions.rules');

        $this->assertSame(['action' => 'edit', 'resource' => '*', 'effect' => 'deny', 'source' => 'Everyone'], $rules[0]);
        // Last, so no allowed command pattern reopens the door.
        $this->assertSame(['action' => 'shell', 'resource' => '*', 'effect' => 'deny', 'source' => 'Everyone'], $rules[array_key_last($rules)]);
    }

    public function test_a_permission_left_alone_sends_nothing(): void
    {
        $this->save(['decision' => ['commands' => 'allow']]);

        $this->assertSame([], $this->withToken($this->token)->getJson('/v1/me/desktop-config')->json('agentPermissions.rules'));
        $this->assertSame([], AgentPermissionPolicy::query()->sole()->settings);
    }

    public function test_a_switch_saved_as_ask_first_blocks_instead(): void
    {
        // A switch offers only allow and block; storing "ask" would make the
        // desktop show a prompt this screen cannot configure, so it fails
        // closed rather than open.
        $this->save(['decision' => ['webSearch' => 'ask']]);

        $this->assertSame(['webSearch' => ['decision' => 'deny']], AgentPermissionPolicy::query()->sole()->settings);
        $this->assertSame(
            [['action' => 'websearch', 'resource' => '*', 'effect' => 'deny', 'source' => 'Everyone']],
            $this->withToken($this->token)->getJson('/v1/me/desktop-config')->json('agentPermissions.rules'),
        );
    }

    public function test_a_website_pattern_is_stored_in_the_form_the_desktop_matches(): void
    {
        $this->save([
            'decision' => ['websites' => 'deny'],
            'allow' => ['websites' => "HTTPS://Example.COM./docs/\n*.example.org\nhttps://example.net/private/*"],
        ]);

        $rules = $this->withToken($this->token)->getJson('/v1/me/desktop-config')->json('agentPermissions.rules');

        $this->assertSame(
            ['*', 'https://example.com/docs', '*.example.org', 'https://example.net/private'],
            array_column($rules, 'resource'),
        );
    }

    public function test_an_invalid_pattern_is_rejected_instead_of_dropped_silently(): void
    {
        $page = $this->actingAs($this->admin)->patch('/admin/agent-permissions', [
            'organization' => $this->organization->getKey(),
            'decision' => ['websites' => 'deny'],
            'allow' => ['websites' => 'ftp://files.example.com'],
        ]);

        $page->assertSessionHasErrors('allow.websites');
        $this->assertSame(0, AgentPermissionPolicy::query()->count());
    }

    public function test_a_save_names_every_permission_that_moved(): void
    {
        $this->save([
            'decision' => ['commands' => 'ask', 'websites' => 'deny'],
            'block' => ['websites' => '*.internal.example'],
        ]);

        $entry = AuditLog::query()->where('action', 'admin.agent_permissions.updated')->sole();

        $this->assertSame($this->organization->getKey(), $entry->organization_id);
        $this->assertSame(
            ['commands' => ['from' => 'Not restricted', 'to' => 'Ask first'], 'websites' => ['from' => 'Not restricted', 'to' => 'Block · 1 blocked']],
            $entry->meta['agent_permissions'],
        );
        $this->assertStringContainsString('Run commands: Not restricted → Ask first', (string) $entry->description);
        $this->assertArrayHasKey('agent_permissions', $entry->changeSections());
    }

    public function test_an_unchanged_save_writes_no_audit_entry(): void
    {
        $this->save(['decision' => ['commands' => 'ask']]);
        AuditLog::query()->delete();

        $this->save(['decision' => ['commands' => 'ask']]);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_the_screen_lists_every_permission_and_the_rules_it_will_send(): void
    {
        $this->save(['decision' => ['commands' => 'ask'], 'block' => ['commands' => 'rm -rf *']]);

        $page = $this->actingAs($this->admin)
            ->get('/admin/agent-permissions?organization='.$this->organization->getKey())
            ->assertOk();

        foreach (AgentPermission::cases() as $permission) {
            $page->assertSee(__($permission->labelKey()));
            $page->assertSee('decision['.$permission->value.']', false);
        }

        $page->assertSee(__('ui.admin.agent_permissions.rules_title'));
        $page->assertSee(__('ui.admin.agent_permissions.status_restricted'));
        $page->assertSee('rm -rf *', false);
    }

    public function test_a_member_who_is_not_an_admin_cannot_read_or_change_permissions(): void
    {
        $member = User::factory()->create();
        $this->workspace($member, $this->organization);

        $this->actingAs($member)->get('/admin/agent-permissions')->assertForbidden();
        $this->actingAs($member)->patch('/admin/agent-permissions', ['organization' => $this->organization->getKey()])->assertForbidden();
    }

    public function test_one_workspace_policy_never_reaches_another(): void
    {
        $this->save(['decision' => ['commands' => 'deny']]);

        $otherUser = User::factory()->create();
        $other = $this->workspace($otherUser);
        [, $otherToken] = ApiKey::issue($other, $otherUser, 'Other workspace key');

        $this->assertSame([], $this->withToken($otherToken)->getJson('/v1/me/desktop-config')->json('agentPermissions.rules'));
        $this->assertNotEmpty($this->withToken($this->token)->getJson('/v1/me/desktop-config')->json('agentPermissions.rules'));
    }

    public function test_the_screen_and_its_rules_read_in_arabic(): void
    {
        $this->save(['decision' => ['commands' => 'ask']]);

        $this->admin->forceFill(['locale' => 'ar'])->save();

        $page = $this->actingAs($this->admin)
            ->get('/admin/agent-permissions?organization='.$this->organization->getKey())
            ->assertOk();

        $page->assertSee(__('ui.admin.agent_permissions.title', [], 'ar'));
        $page->assertSee(__('ui.admin.agent_permissions.commands_name', [], 'ar'));
        $page->assertSee(__('ui.admin.agent_permissions.decision_ask', [], 'ar'));
        $page->assertDontSee('Ask first');
    }

    /**
     * Submit the screen's form the way a browser does.
     *
     * @param  array<string, array<string, string>>  $fields
     */
    private function save(array $fields): void
    {
        $this->actingAs($this->admin)->patch('/admin/agent-permissions', [
            'organization' => $this->organization->getKey(),
            ...$fields,
        ])->assertSessionHasNoErrors();
    }

    private function workspace(User $user, ?Organization $organization = null): Organization
    {
        $organization ??= Organization::factory()->create(['owner_id' => $user->getKey()]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        return $organization;
    }
}
