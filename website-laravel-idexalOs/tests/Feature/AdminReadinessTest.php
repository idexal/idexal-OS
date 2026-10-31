<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The readiness page: the states that break a launch silently, surfaced to the
 * operator with a fix. The tests pin the two that were actually broken in
 * production (mail on the log transport, no payment gateway) and the guard
 * that keeps the page admin-only.
 */
class AdminReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_the_sign_in_page(): void
    {
        $this->get('/admin/readiness')->assertRedirect(route('login'));
    }

    public function test_ordinary_users_cannot_open_it(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->get('/admin/readiness')->assertForbidden();
    }

    public function test_the_page_lists_every_check(): void
    {
        $admin = User::factory()->admin()->create(['locale' => 'en']);

        $response = $this->actingAs($admin)->get('/admin/readiness')->assertOk();

        foreach (['runtime', 'https', 'mail', 'payments', 'queue', 'scheduler', 'backup', 'storage', 'catalogue', 'desktop_gate'] as $id) {
            $response->assertSee('data-check="'.$id.'"', false);
        }
    }

    public function test_a_log_mail_transport_is_reported_as_blocked(): void
    {
        config(['mail.default' => 'log']);
        $admin = User::factory()->admin()->create(['locale' => 'en']);

        $this->actingAs($admin)->get('/admin/readiness')
            ->assertOk()
            ->assertSee('data-check="mail"', false)
            ->assertSee('data-status="fail"', false)
            ->assertSeeText(__('ui.admin.readiness.mail_hint'));
    }

    public function test_a_working_mail_transport_is_reported_as_ready(): void
    {
        config(['mail.default' => 'smtp']);
        $admin = User::factory()->admin()->create(['locale' => 'en']);

        $html = (string) $this->actingAs($admin)->get('/admin/readiness')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-check="mail" data-status="ok"/', $html);
    }

    public function test_the_desktop_gate_defaults_to_required(): void
    {
        $admin = User::factory()->admin()->create(['locale' => 'en']);

        $html = (string) $this->actingAs($admin)->get('/admin/readiness')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-check="desktop_gate" data-status="ok"/', $html);
    }

    public function test_turning_plan_selection_off_is_reported_as_attention(): void
    {
        Setting::put('feature_require_plan_selection', 0);
        $admin = User::factory()->admin()->create(['locale' => 'en']);

        $html = (string) $this->actingAs($admin)->get('/admin/readiness')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-check="desktop_gate" data-status="warn"/', $html);
    }
}
