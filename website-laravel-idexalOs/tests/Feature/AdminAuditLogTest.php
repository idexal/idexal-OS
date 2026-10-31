<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The audit page is where an operator answers "who changed this, and when". The
 * filters have to match the way that question is asked: by person, by kind of
 * action, or by period.
 */
class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_searching_a_person_finds_what_they_did(): void
    {
        $admin = User::factory()->admin()->create();
        $colleague = User::factory()->admin()->create(['name' => 'Sam Operator', 'email' => 'sam@example.test']);

        $this->actingAs($colleague);
        AuditLog::record('admin.plan.created', null, null, 'Plan Growth created');

        $this->actingAs($admin);
        AuditLog::record('admin.plan.deleted', null, null, 'Plan Starter deleted');

        $this->actingAs($admin)->get('/admin/audit?q=sam@example.test')
            ->assertOk()
            ->assertSee('Plan Growth created')
            ->assertDontSee('Plan Starter deleted');
    }

    public function test_searching_an_action_key_narrows_to_that_kind_of_change(): void
    {
        $admin = User::factory()->admin()->create();

        AuditLog::record('admin.provider_key.rotated', null, null, 'Key rotated for Anthropic');
        AuditLog::record('admin.plan.updated', null, null, 'Plan Growth updated');

        $this->actingAs($admin)->get('/admin/audit?q=provider_key')
            ->assertOk()
            ->assertSee('Key rotated for Anthropic')
            ->assertDontSee('Plan Growth updated');
    }

    public function test_the_period_narrows_the_list_to_those_days(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->create();

        $old = AuditLog::record('admin.invoice.voided', $organization, null, 'Old invoice voided');
        AuditLog::query()->whereKey($old->getKey())->update(['created_at' => now()->subDays(30)]);

        AuditLog::record('admin.invoice.marked_paid', $organization, null, 'Today invoice paid');

        $since = now()->subDays(2)->toDateString();

        $this->actingAs($admin)->get('/admin/audit?from='.$since)
            ->assertOk()
            ->assertSee('Today invoice paid')
            ->assertDontSee('Old invoice voided');

        $until = now()->subDays(20)->toDateString();

        $this->actingAs($admin)->get('/admin/audit?to='.$until)
            ->assertOk()
            ->assertSee('Old invoice voided')
            ->assertDontSee('Today invoice paid');
    }

    public function test_the_action_filter_shows_how_many_entries_each_kind_holds(): void
    {
        $admin = User::factory()->admin()->create();

        AuditLog::record('admin.settings.updated', null, null, 'Settings saved');
        AuditLog::record('admin.settings.updated', null, null, 'Settings saved again');
        AuditLog::record('admin.plan.created', null, null, 'Plan Growth created');

        $this->actingAs($admin)->get('/admin/audit')
            ->assertOk()
            ->assertSee('admin.settings.updated (2)')
            ->assertSee('admin.plan.created (1)');
    }

    public function test_a_reversed_period_explains_why_nothing_matched(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/audit?from='.now()->toDateString().'&to='.now()->subDays(3)->toDateString())
            ->assertOk()
            ->assertSee(__('ui.admin.audit.range_reversed'));
    }

    public function test_a_malformed_date_is_ignored_instead_of_erroring(): void
    {
        $admin = User::factory()->admin()->create();

        AuditLog::record('admin.plan.created', null, null, 'Plan Growth created');

        $this->actingAs($admin)->get('/admin/audit?from=yesterday-ish')
            ->assertOk()
            ->assertSee('Plan Growth created');
    }
}
