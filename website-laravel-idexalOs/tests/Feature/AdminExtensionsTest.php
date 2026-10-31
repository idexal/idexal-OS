<?php

namespace Tests\Feature;

use App\Models\ExtensionInstallation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The marketplace is the one admin screen that writes to what every desktop
 * offers, so both the list and the two buttons have to be readable and honest.
 */
class AdminExtensionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_marketplace_lists_the_bundled_catalog(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/extensions')
            ->assertOk()
            ->assertSee(__('ui.admin.extensions.title'))
            ->assertSee('Gateway API Cookbook')
            ->assertSee(__('ui.admin.extensions.types.skill'))
            ->assertSee(__('ui.admin.extensions.install'));
    }

    public function test_a_search_narrows_the_catalog(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/extensions?q=arabic')
            ->assertOk()
            ->assertSee('Arabic UI Quality Review')
            ->assertDontSee('Git History Cleanup');
    }

    public function test_a_search_with_no_match_says_so(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/extensions?q=zzz-nothing-matches')
            ->assertOk()
            ->assertSee(__('ui.admin.extensions.empty'));
    }

    public function test_an_operator_can_install_then_remove_an_extension(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/extensions/install', ['extension_slug' => 'idexal-release-notes'])
            ->assertRedirect()
            ->assertSessionHas('status', __('ui.admin.extensions.installed', ['name' => 'Release Notes Writer']));

        $this->assertDatabaseHas('extension_installations', [
            'user_id' => (string) $admin->getKey(),
            'extension_name' => 'idexal-release-notes',
        ]);

        $installation = ExtensionInstallation::query()
            ->where('extension_name', 'idexal-release-notes')
            ->sole();

        $this->actingAs($admin)
            ->post('/admin/extensions/'.$installation->getKey().'/uninstall')
            ->assertRedirect()
            ->assertSessionHas('status', __('ui.admin.extensions.uninstalled'));

        $this->assertDatabaseMissing('extension_installations', ['id' => $installation->getKey()]);
    }

    public function test_an_installed_extension_is_marked_and_offered_for_removal(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/extensions/install', ['extension_slug' => 'idexal-release-notes']);

        $this->actingAs($admin)->get('/admin/extensions')
            ->assertOk()
            ->assertSee(__('ui.admin.extensions.installed_title'))
            ->assertSee(__('ui.admin.extensions.installed_badge'))
            ->assertSee(__('ui.admin.extensions.installed_count', ['count' => 1]));
    }

    public function test_each_admin_sees_only_their_own_installations(): void
    {
        $first = User::factory()->admin()->create();
        $second = User::factory()->admin()->create();

        $this->actingAs($first)->post('/admin/extensions/install', ['extension_slug' => 'idexal-release-notes']);
        $this->actingAs($second)->post('/admin/extensions/install', ['extension_slug' => 'idexal-gateway-cookbook']);

        $this->actingAs($first)->get('/admin/extensions')
            ->assertOk()
            ->assertSee(__('ui.admin.extensions.installed_count', ['count' => 1]));

        $this->actingAs($second)->get('/admin/extensions')
            ->assertOk()
            ->assertSee(__('ui.admin.extensions.installed_count', ['count' => 1]));

        $this->assertDatabaseHas('extension_installations', [
            'user_id' => (string) $first->getKey(),
            'extension_name' => 'idexal-release-notes',
        ]);

        $this->assertDatabaseHas('extension_installations', [
            'user_id' => (string) $second->getKey(),
            'extension_name' => 'idexal-gateway-cookbook',
        ]);
    }

    public function test_installing_the_same_extension_twice_is_not_an_error(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/extensions/install', ['extension_slug' => 'idexal-release-notes']);
        $this->actingAs($admin)
            ->post('/admin/extensions/install', ['extension_slug' => 'idexal-release-notes'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ExtensionInstallation::query()->count());
    }

    public function test_an_unknown_extension_is_refused_in_the_reader_language(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/extensions/install', ['extension_slug' => 'no-such-extension'])
            ->assertSessionHasErrors('message');

        $message = session('errors')->first('message');

        $this->assertSame(__('ui.admin.extensions.not_found'), $message);
        $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $message);
        $this->assertSame(0, ExtensionInstallation::query()->count());
    }

    public function test_removing_an_installation_that_is_gone_says_so(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/extensions/999/uninstall')
            ->assertSessionHasErrors('message');

        $this->assertSame(__('ui.admin.extensions.installation_missing'), session('errors')->first('message'));
    }

    public function test_reloading_the_catalog_confirms_in_the_reader_language(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/admin/extensions/refresh')
            ->assertRedirect()
            ->assertSessionHas('status', __('ui.admin.extensions.refreshed_bundled'));
    }
}
