<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The language lives in the URL, so every internal link has to carry it. A
 * visitor who switches to Arabic and clicks "Pricing" must not silently land
 * on an English address that they would then share with the next person.
 */
class PublicSiteLanguageLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_arabic_pages_keep_the_language_on_every_navigation_link(): void
    {
        $response = $this->get('/?lang=ar')->assertOk();

        foreach (['pricing', 'products', 'use-cases', 'resources', 'enterprise', 'docs', 'download', 'security', 'contact', 'changelog'] as $route) {
            $response->assertSee('href="'.route($route).'?lang=ar"', false);
        }

        // A product page is an internal link too, and it must keep the language.
        $response->assertSee('href="'.route('products.show', ['slug' => 'desktop']).'?lang=ar"', false);

        // The logo keeps it too.
        $response->assertSee('href="'.route('home').'?lang=ar"', false);
    }

    public function test_the_default_language_keeps_the_canonical_url_clean(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('href="'.route('pricing').'"', false);
        $this->assertStringNotContainsString(route('pricing').'?lang=', (string) $response->getContent());
    }

    public function test_the_pricing_toggle_keeps_the_language_and_the_interval(): void
    {
        $response = $this->get('/pricing?lang=ar')->assertOk();

        $response->assertSee('href="'.route('pricing').'?yearly=1&amp;lang=ar"', false);
    }

    public function test_the_footer_states_the_rights_in_the_visitors_language(): void
    {
        $english = $this->get('/')->assertOk();
        $english->assertSeeText('All rights reserved.');
        $this->assertStringNotContainsString('All Terms of Service', (string) $english->getContent());

        $arabic = $this->get('/?lang=ar')->assertOk();
        $arabic->assertSeeText('جميع الحقوق محفوظة.');
    }
}
