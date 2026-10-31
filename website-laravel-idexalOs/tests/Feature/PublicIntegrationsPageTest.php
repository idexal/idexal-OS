<?php

namespace Tests\Feature;

use App\Services\MarketplaceCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public catalogue page.
 *
 * These tests deliberately read the bundled catalogue instead of seeding the
 * cache: the production failure was a page that rendered "0 items" whenever
 * GitHub was unreachable, because the page had no bundled fallback. A test
 * that seeds the cache can never catch that, so the page must pass on a fresh
 * checkout with no network and an empty cache.
 */
class PublicIntegrationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bundled_catalogue_is_not_empty(): void
    {
        $items = app(MarketplaceCatalogService::class)->catalog()['items'] ?? [];

        $this->assertGreaterThan(100, count($items), 'The store must ship with the full skills library.');
    }

    public function test_the_page_lists_skills_plugins_and_connectors_with_counts(): void
    {
        cache()->forget('marketplace_extensions');

        $response = $this->get('/integrations')->assertOk();

        // A curated skill, a library skill, a connector and a plugin all render.
        $response->assertSeeText('Systematic Debugging');
        $response->assertSeeText('Accessibility');
        $response->assertSeeText('GitHub');

        $counts = app(MarketplaceCatalogService::class)->catalog()['items'];
        $this->assertNotEmpty(array_filter($counts, fn (array $i): bool => ($i['type'] ?? '') === 'connector'));
    }

    public function test_the_type_filter_narrows_the_catalogue(): void
    {
        $response = $this->get('/integrations?type=connector')->assertOk();

        $response->assertSeeText('GitHub');
        $response->assertDontSeeText('Systematic Debugging');
    }

    public function test_search_matches_name_or_description(): void
    {
        $response = $this->get('/integrations?q=accessibility')->assertOk();

        $response->assertSeeText('Accessibility');
    }

    public function test_the_catalogue_is_paginated(): void
    {
        $response = $this->get('/integrations')->assertOk();

        $response->assertSeeText(__('ui.integrations.page_of', ['page' => 1, 'last' => 6]));
        $response->assertSeeText(__('ui.integrations.next'));
    }

    public function test_a_corrupt_cache_entry_does_not_empty_the_store(): void
    {
        // Production hit this: an entry written by an older build unserialized
        // into __PHP_Incomplete_Class and the typed return threw a 500.
        cache()->put('marketplace_extensions', new \stdClass, 3600);
        cache()->put('marketplace_catalogue_checked', true, 600);

        $response = $this->get('/integrations')->assertOk();

        $response->assertSeeText('Accessibility');
        $response->assertDontSeeText(__('ui.integrations.empty'));
    }

    public function test_the_page_speaks_arabic_when_the_visitor_does(): void
    {
        $response = $this->get('/integrations?lang=ar')->assertOk();

        $response->assertSee('<html lang="ar" dir="rtl"', false);
        $response->assertSeeText(__('ui.integrations.title', [], 'ar'));
    }

    public function test_the_page_is_in_the_sitemap(): void
    {
        $this->assertStringContainsString(
            '<loc>'.route('integrations').'</loc>',
            (string) $this->get('/sitemap.xml')->assertOk()->getContent(),
        );
    }
}
