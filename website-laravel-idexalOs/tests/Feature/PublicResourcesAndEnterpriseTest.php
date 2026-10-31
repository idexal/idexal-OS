<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The resources hub and the enterprise page: the two destinations the header
 * points at that are not a product page.
 */
class PublicResourcesAndEnterpriseTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_resources_hub_links_every_card_to_a_real_page(): void
    {
        $response = $this->get('/resources')->assertOk();

        foreach (__('ui.resources.cards') as $card) {
            $response->assertSeeText($card['title']);
            $response->assertSee(route($card['route']), false);
        }
    }

    public function test_the_resources_hub_lists_the_recent_releases(): void
    {
        $response = $this->get('/resources')->assertOk();

        $response->assertSeeText(__('ui.resources.releases_title'));

        // The newest release is always shown, and its version is a link target
        // on the release record.
        $version = (string) (config('releases.history.0.version') ?? '');
        $this->assertNotSame('', $version);
        $response->assertSee($version, false);
    }

    public function test_the_resources_hub_speaks_arabic_when_the_visitor_does(): void
    {
        $response = $this->get('/resources?lang=ar')->assertOk();

        $response->assertSee('<html lang="ar" dir="rtl"', false);
        $response->assertSeeText(__('ui.resources.cards_title', locale: 'ar'));
    }

    public function test_the_enterprise_page_renders_its_capabilities(): void
    {
        $response = $this->get('/enterprise')->assertOk();

        $response->assertSeeText(__('ui.enterprise.title'));
        $response->assertSeeText(__('ui.enterprise.capabilities_title'));
        $response->assertSeeText(__('ui.enterprise.identity_title'));
        $response->assertSeeText(__('ui.enterprise.deploy_title'));

        foreach (__('ui.enterprise.capabilities') as $capability) {
            $response->assertSeeText($capability['title']);
        }

        foreach (__('ui.enterprise.identity') as $provider) {
            $response->assertSeeText($provider);
        }
    }

    public function test_the_enterprise_page_links_to_its_documentation(): void
    {
        $response = $this->get('/enterprise')->assertOk();

        $response->assertSee('/docs/'.__('ui.enterprise.docs'), false);
        $response->assertSee(route('contact'), false);
    }

    public function test_both_pages_are_in_the_sitemap(): void
    {
        $content = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.route('resources').'</loc>', $content);
        $this->assertStringContainsString('<loc>'.route('enterprise').'</loc>', $content);
    }
}
