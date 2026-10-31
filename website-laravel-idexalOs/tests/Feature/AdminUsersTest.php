<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\DesktopSession;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin Users console: list, filter, inspect, create, edit, suspend,
 * verify, reset, revoke and delete, with the guards that keep a deployment
 * from locking itself out.
 */
class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['locale' => 'en']);
    }

    public function test_the_index_lists_users_and_reports_stats(): void
    {
        $user = User::factory()->create(['name' => 'Riley Member', 'locale' => 'en']);
        User::factory()->create(['status' => User::STATUS_SUSPENDED, 'locale' => 'en']);

        $this->actingAs($this->admin)->get('/admin/users')
            ->assertOk()
            ->assertSeeText('Riley Member')
            ->assertSeeText($user->email)
            ->assertSeeText(__('ui.admin.users.stat_total'));
    }

    public function test_the_index_filters_by_status_and_search(): void
    {
        $active = User::factory()->create(['name' => 'Ada Active', 'status' => User::STATUS_ACTIVE, 'locale' => 'en']);
        User::factory()->create(['name' => 'Sam Suspended', 'status' => User::STATUS_SUSPENDED, 'locale' => 'en']);

        $this->actingAs($this->admin)->get('/admin/users?status=suspended')
            ->assertOk()
            ->assertSeeText('Sam Suspended')
            ->assertDontSeeText('Ada Active');

        $this->actingAs($this->admin)->get('/admin/users?q=Ada')
            ->assertOk()
            ->assertSeeText('Ada Active')
            ->assertDontSeeText('Sam Suspended');
    }

    public function test_an_operator_can_open_a_user_detail_page(): void
    {
        $user = User::factory()->create(['name' => 'Nadia Viewer', 'locale' => 'en']);
        $organization = Organization::factory()->create(['owner_id' => $this->admin->getKey()]);
        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_MEMBER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $this->actingAs($this->admin)->get('/admin/users/'.$user->getKey())
            ->assertOk()
            ->assertSeeText('Nadia Viewer')
            ->assertSeeText($organization->name)
            ->assertSeeText(__('ui.admin.users.profile'))
            ->assertSeeText(__('ui.admin.users.activity_log'));
    }

    public function test_an_operator_can_create_a_user(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'New Person',
            'email' => 'New.Person@example.com',
            'role' => User::ROLE_DEVELOPER,
            'status' => User::STATUS_ACTIVE,
            'locale' => 'en',
            'password' => 'a-long-enough-password',
            'email_verified' => '1',
        ]);

        $response->assertRedirect();

        $user = User::query()->where('email', 'new.person@example.com')->sole();
        $this->assertSame('New Person', $user->name);
        $this->assertSame(User::ROLE_DEVELOPER, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('a-long-enough-password', (string) $user->password));
    }

    public function test_creating_a_user_can_add_a_workspace_membership(): void
    {
        $organization = Organization::factory()->create(['owner_id' => $this->admin->getKey()]);

        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Joined Person',
            'email' => 'joined@example.com',
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
            'organization_id' => $organization->getKey(),
            'org_role' => Membership::ROLE_ADMIN,
            'email_verified' => '1',
        ])->assertRedirect();

        $user = User::query()->where('email', 'joined@example.com')->sole();
        $membership = $user->membershipFor($organization);

        $this->assertNotNull($membership);
        $this->assertSame(Membership::ROLE_ADMIN, $membership->role);
    }

    public function test_suspending_a_user_revokes_keys_and_sessions(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $organization = Organization::factory()->create(['owner_id' => $this->admin->getKey()]);
        [$key] = ApiKey::issue($organization, $user, 'Device');
        DesktopSession::query()->create([
            'user_id' => $user->getKey(),
            'organization_id' => $organization->getKey(),
            'api_key_id' => $key->getKey(),
            'device_name' => 'Laptop',
        ]);

        $this->actingAs($this->admin)
            ->patch('/admin/users/'.$user->getKey().'/status', ['status' => User::STATUS_SUSPENDED])
            ->assertRedirect();

        $this->assertSame(User::STATUS_SUSPENDED, $user->fresh()->status);
        $this->assertNotNull($key->fresh()->revoked_at);
        $this->assertNotNull(DesktopSession::query()->where('user_id', $user->getKey())->sole()->revoked_at);
    }

    public function test_an_operator_can_revoke_keys_and_sessions_on_demand(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $organization = Organization::factory()->create(['owner_id' => $this->admin->getKey()]);
        [$key] = ApiKey::issue($organization, $user, 'Device');
        DesktopSession::query()->create([
            'user_id' => $user->getKey(),
            'organization_id' => $organization->getKey(),
            'api_key_id' => $key->getKey(),
            'device_name' => 'Test Laptop',
        ]);

        $this->actingAs($this->admin)->post('/admin/users/'.$user->getKey().'/revoke-keys')->assertRedirect();
        $this->assertNotNull($key->fresh()->revoked_at);

        $this->actingAs($this->admin)->post('/admin/users/'.$user->getKey().'/revoke-sessions')->assertRedirect();
        $this->assertNotNull(DesktopSession::query()->where('user_id', $user->getKey())->sole()->revoked_at);
    }

    public function test_an_operator_can_verify_an_email_without_the_person_signs_in(): void
    {
        $user = User::factory()->unverified()->create(['locale' => 'en']);
        $this->assertNull($user->email_verified_at);

        $this->actingAs($this->admin)->post('/admin/users/'.$user->getKey().'/verify')->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_an_operator_can_update_a_profile(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($this->admin)->patch('/admin/users/'.$user->getKey(), [
            'name' => 'Renamed Person',
            'email' => $user->email,
            'role' => User::ROLE_DEVELOPER,
            'status' => User::STATUS_ACTIVE,
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'job_title' => 'Engineer',
        ])->assertRedirect();

        $updated = $user->fresh();
        $this->assertSame('Renamed Person', $updated->name);
        $this->assertSame(User::ROLE_DEVELOPER, $updated->role);
        $this->assertSame('ar', $updated->locale);
        $this->assertSame('Asia/Riyadh', $updated->timezone);
    }

    public function test_changing_the_email_clears_verification(): void
    {
        $user = User::factory()->create(['locale' => 'en']);
        $this->assertNotNull($user->email_verified_at);

        $this->actingAs($this->admin)->patch('/admin/users/'.$user->getKey(), [
            'name' => $user->name,
            'email' => 'moved@example.com',
            'role' => $user->role,
            'status' => $user->status,
        ])->assertRedirect();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_an_operator_can_delete_a_user(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($this->admin)
            ->delete('/admin/users/'.$user->getKey())
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted($user);
    }

    public function test_an_operator_cannot_delete_their_own_account(): void
    {
        $this->actingAs($this->admin)
            ->delete('/admin/users/'.$this->admin->getKey())
            ->assertSessionHasErrors('delete');

        $this->assertNotSoftDeleted($this->admin);
    }

    public function test_the_last_admin_cannot_be_demoted_or_deleted(): void
    {
        $onlyAdmin = User::query()->where('role', User::ROLE_ADMIN)->sole();

        $this->actingAs($this->admin)->patch('/admin/users/'.$onlyAdmin->getKey(), [
            'name' => $onlyAdmin->name,
            'email' => $onlyAdmin->email,
            'role' => User::ROLE_USER,
            'status' => User::STATUS_ACTIVE,
        ])->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_ADMIN, $onlyAdmin->fresh()->role);

        $this->actingAs($this->admin)->delete('/admin/users/'.$onlyAdmin->getKey())
            ->assertSessionHasErrors('delete');

        $this->assertNotSoftDeleted($onlyAdmin);
    }

    public function test_a_user_that_owns_a_workspace_cannot_be_deleted(): void
    {
        $owner = User::factory()->create(['locale' => 'en']);
        Organization::factory()->create(['owner_id' => $owner->getKey()]);

        $this->actingAs($this->admin)->delete('/admin/users/'.$owner->getKey())
            ->assertSessionHasErrors('delete');

        $this->assertNotSoftDeleted($owner);
    }

    public function test_bulk_suspend_skips_the_operators_own_account(): void
    {
        $other = User::factory()->create(['locale' => 'en']);

        $this->actingAs($this->admin)->post('/admin/users/bulk', [
            'action' => 'suspend',
            'ids' => [$other->getKey(), $this->admin->getKey()],
        ])->assertRedirect();

        $this->assertSame(User::STATUS_SUSPENDED, $other->fresh()->status);
        $this->assertSame(User::STATUS_ACTIVE, $this->admin->fresh()->status, 'The operator is never suspended by a bulk action.');
    }

    public function test_bulk_activate_restores_suspended_users(): void
    {
        $suspended = User::factory()->create(['status' => User::STATUS_SUSPENDED, 'locale' => 'en']);

        $this->actingAs($this->admin)->post('/admin/users/bulk', [
            'action' => 'activate',
            'ids' => [$suspended->getKey()],
        ])->assertRedirect();

        $this->assertSame(User::STATUS_ACTIVE, $suspended->fresh()->status);
    }

    public function test_the_export_returns_a_csv_of_the_filtered_users(): void
    {
        User::factory()->create(['name' => 'Exported Person', 'email' => 'exported@example.com', 'locale' => 'en']);

        $response = $this->actingAs($this->admin)->get('/admin/users/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('exported@example.com', $csv);
        $this->assertStringContainsString('name,email,role', $csv);
    }

    public function test_the_export_does_not_lazy_load_relations(): void
    {
        // The app runs with lazy-loading forbidden in production; the test
        // suite does not, which is how the first export shipped a lazy load of
        // `memberships`. Turn the strictness on here so it cannot return.
        Model::preventLazyLoading();

        try {
            User::factory()->create(['name' => 'Strict Person', 'email' => 'strict@example.com', 'locale' => 'en']);

            $response = $this->actingAs($this->admin)->get('/admin/users/export')->assertOk();

            $this->assertStringContainsString('strict@example.com', $response->streamedContent());
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_a_member_cannot_reach_the_admin_users_console(): void
    {
        $member = User::factory()->create(['locale' => 'en']);

        $this->actingAs($member)->get('/admin/users')->assertForbidden();
        $this->actingAs($member)->post('/admin/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'role' => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_the_console_speaks_arabic_when_the_operator_does(): void
    {
        User::factory()->create(['name' => 'Yasmin Member', 'locale' => 'ar']);

        $response = $this->actingAs($this->admin)->get('/admin/users?lang=ar')->assertOk();

        $response->assertSeeText(__('ui.admin.users.title', [], 'ar'));
        $response->assertSeeText(__('ui.admin.users.stat_total', [], 'ar'));
        $response->assertSeeText('Yasmin Member');
    }

    public function test_the_create_form_renders(): void
    {
        $this->actingAs($this->admin)->get('/admin/users/create')
            ->assertOk()
            ->assertSeeText(__('ui.admin.users.create'))
            ->assertSeeText(__('ui.admin.users.temporary_password'));
    }
}
