<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public site promises that a language is a URL, not a session secret.
 * These cover the two halves of that promise a visitor actually touches: the
 * switch keeps the language in the address bar, and a signed-in member keeps
 * it on their account.
 */
class PublicSiteLanguageSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_language_switch_writes_the_language_into_the_url(): void
    {
        $english = $this->get('/')->assertOk();
        $this->assertSwitchLink($english->getContent(), url('/').'?lang=ar', 'ar');

        $arabic = $this->get('/pricing?lang=ar')->assertOk();
        // The switch always names the language explicitly, so it works even
        // when a session already remembers the other one.
        $this->assertSwitchLink($arabic->getContent(), url('/pricing').'?lang=en', 'en');
    }

    public function test_a_signed_in_member_keeps_the_language_on_their_account(): void
    {
        $member = User::factory()->create(['locale' => 'ar']);

        $this->actingAs($member)->get('/?lang=en')->assertOk();

        $this->assertSame('en', $member->fresh()->locale);
    }

    public function test_choosing_a_language_never_writes_anything_for_a_guest(): void
    {
        $this->get('/?lang=ar')->assertOk();

        $this->assertSame(0, User::query()->count());
    }

    public function test_the_public_layout_is_reachable_by_keyboard_and_screen_reader(): void
    {
        $home = $this->get('/')->assertOk();
        $home->assertSee('href="#main"', false);
        $home->assertSee('id="main"', false);
        $home->assertSee('aria-controls="site-menu"', false);
        $home->assertSee('aria-label="'.__('ui.nav.primary').'"', false);
        $home->assertSee(__('ui.common.skip_to_content'), false);

        $pricing = $this->get('/pricing')->assertOk();
        $pricing->assertSee('aria-current="page"', false);
    }

    public function test_the_share_card_is_written_in_the_language_being_shared(): void
    {
        $this->assertFileExists(public_path('brand/og-ar.png'));

        $english = $this->get('/')->assertOk();
        $english->assertSee('brand/og-default.png', false);
        $english->assertDontSee('brand/og-ar.png', false);

        $arabic = $this->get('/?lang=ar')->assertOk();
        $arabic->assertSee('brand/og-ar.png', false);
        $arabic->assertDontSee('brand/og-default.png', false);
    }

    private function assertSwitchLink(string $html, string $target, string $hreflang): void
    {
        $this->assertMatchesRegularExpression(
            '/<a\s[^>]*href="'.preg_quote($target, '/').'"\s[^>]*hreflang="'.preg_quote($hreflang, '/').'"/',
            $html,
            "Expected a language switch link to {$target} tagged hreflang=\"{$hreflang}\".",
        );
    }
}
