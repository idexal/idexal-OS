<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The developer centre: a public hub that points at the real surfaces a
 * builder needs — the reference, keys, webhooks, models and releases — and the
 * one canonical address for the store.
 */
class DevelopersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_developer_hub_lists_every_real_surface(): void
    {
        $response = $this->get('/developers')->assertOk();

        $response->assertSeeText(__('ui.developers.title'));
        $response->assertSeeText(__('ui.developers.cards')[0]['title']);
        $response->assertSeeText(__('ui.developers.quickstart_title'));

        // The links resolve to real routes, not placeholders.
        $response->assertSee(route('api'), false);
        $response->assertSee(route('dashboard.api-keys.index'), false);
        $response->assertSee(route('providers'), false);
    }

    public function test_the_developer_hub_speaks_arabic(): void
    {
        $response = $this->get('/developers?lang=ar')->assertOk();

        $response->assertSee('<html lang="ar" dir="rtl"', false);
        $response->assertSeeText(__('ui.developers.title', [], 'ar'));
    }

    public function test_the_store_has_one_address_and_marketplace_redirects_to_it(): void
    {
        $this->get('/marketplace')->assertStatus(301)->assertRedirect('/integrations');
    }

    public function test_the_developer_hub_is_in_the_sitemap(): void
    {
        $this->assertStringContainsString(
            '<loc>'.route('developers').'</loc>',
            (string) $this->get('/sitemap.xml')->assertOk()->getContent(),
        );
    }
}
