<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The product pages: eight surfaces described once, in both languages, each
 * with its own page, its place in the navigation and its line in the sitemap.
 */
class PublicProductsPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function slugs(): array
    {
        return array_keys(__('ui.products.items'));
    }

    public function test_the_index_lists_every_product(): void
    {
        $response = $this->get('/products')->assertOk();

        foreach (__('ui.products.items') as $product) {
            $response->assertSeeText($product['name']);
            $response->assertSeeText($product['tagline']);
        }
    }

    public function test_every_product_has_a_page_that_renders_its_sections(): void
    {
        foreach ($this->slugs() as $slug) {
            $product = __('ui.products.items.'.$slug);

            $response = $this->get('/product/'.$slug)->assertOk();

            $response->assertSeeText($product['name']);
            $response->assertSeeText($product['summary']);
            $response->assertSeeText(__('ui.products.capabilities_title'));
            $response->assertSeeText(__('ui.products.steps_title'));

            foreach ($product['capabilities'] as $capability) {
                $response->assertSeeText($capability['title']);
            }
        }
    }

    public function test_a_product_links_to_the_documentation_it_belongs_to(): void
    {
        $response = $this->get('/product/gateway')->assertOk();

        $docs = __('ui.products.items.gateway.docs');

        $this->assertNotEmpty($docs);
        $response->assertSee('/docs/'.$docs, false);
    }

    public function test_a_product_lists_the_products_it_works_with(): void
    {
        $response = $this->get('/product/desktop')->assertOk();

        foreach (__('ui.products.items.desktop.related') as $related) {
            $response->assertSee('/product/'.$related, false);
        }
    }

    public function test_an_unknown_product_is_not_found(): void
    {
        $this->get('/product/not-a-product')->assertNotFound();
    }

    public function test_the_page_speaks_arabic_when_the_visitor_does(): void
    {
        $response = $this->get('/product/desktop?lang=ar')->assertOk();

        $response->assertSee('<html lang="ar" dir="rtl"', false);
        $response->assertSeeText(__('ui.products.items.desktop.name', locale: 'ar'));
    }

    public function test_every_product_is_in_the_sitemap(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringContainsString('<loc>'.route('products').'</loc>', (string) $response->getContent());

        foreach ($this->slugs() as $slug) {
            $this->assertStringContainsString(
                '<loc>'.route('products.show', ['slug' => $slug]).'</loc>',
                (string) $response->getContent(),
            );
        }
    }
}
