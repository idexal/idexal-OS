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

    public function test_a_catalogue_entry_has_its_own_page(): void
    {
        $response = $this->get('/integrations/skill/accessibility')->assertOk();

        $response->assertSeeText('Accessibility');
        $response->assertSeeText(__('ui.integrations.detail_how_title'));
        // The breadcrumb leads back to the typed listing and the catalogue.
        $response->assertSee(route('integrations', ['type' => 'skill']), false);
    }

    public function test_an_unknown_entry_is_a_404(): void
    {
        $this->get('/integrations/skill/not-a-real-skill')->assertNotFound();
        $this->get('/integrations/bogus/accessibility')->assertNotFound();
    }

    public function test_the_listing_links_to_entry_pages(): void
    {
        $this->get('/integrations')
            ->assertOk()
            ->assertSee(route('integrations.show', ['type' => 'skill', 'slug' => 'accessibility']), false);
    }

    public function test_every_catalogue_entry_is_in_the_sitemap(): void
    {
        $sitemap = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(
            '<loc>'.route('integrations.show', ['type' => 'skill', 'slug' => 'accessibility']).'</loc>',
            $sitemap,
        );
    }

    public function test_an_entry_page_speaks_arabic(): void
    {
        $this->get('/integrations/skill/accessibility?lang=ar')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->assertSeeText(__('ui.integrations.detail_how_title', [], 'ar'));
    }

    public function test_a_skill_page_shows_the_opening_of_its_own_instructions(): void
    {
        $response = $this->get('/integrations/skill/accessibility')->assertOk();

        $response->assertSeeText(__('ui.integrations.detail_preview_title'));
        // Body text from SKILL.md itself, not from the one-line description.
        $response->assertSeeText('Evidence-led audit workflow');
        // Front matter is already rendered as fields, not repeated in the box.
        $response->assertDontSeeText('license: MIT');
    }

    public function test_related_entries_are_relevant_and_never_the_same_entry(): void
    {
        $response = $this->get('/integrations/skill/accessibility')->assertOk();

        $response->assertSeeText(__('ui.integrations.detail_related'));

        preg_match_all('#href="[^"]*/integrations/skill/([a-z0-9-]+)"#', (string) $response->getContent(), $matches);
        $related = array_values(array_unique(array_diff($matches[1], ['accessibility'])));

        $this->assertGreaterThanOrEqual(3, count($related), 'The shelf must lead to other entries.');
    }

    public function test_a_curation_marker_is_never_shown_as_a_category(): void
    {
        $html = (string) $this->get('/integrations/skill/accessibility')->assertOk()->getContent();

        $this->assertStringNotContainsString('Curated', $html);
        $this->assertStringNotContainsString('.curated', $html);
    }
}
