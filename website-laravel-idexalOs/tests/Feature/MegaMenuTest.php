<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The header mega menu: every panel is a real, complete list — not a stub —
 * and each trigger points at the panel it opens.
 */
class MegaMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_menu_trigger_controls_a_panel(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        // Three mega menus plus the two plain links.
        $this->assertSame(3, substr_count($html, 'aria-haspopup="true"'));

        preg_match_all('/aria-controls="(menu-[a-f0-9]+)"/', $html, $controls);
        preg_match_all('/id="(menu-[a-f0-9]+)"/', $html, $panels);

        $this->assertCount(3, $controls[1]);
        // A trigger must point at a panel that exists on the page.
        foreach ($controls[1] as $id) {
            $this->assertContains($id, $panels[1], 'Every aria-controls target must be rendered.');
        }
    }

    public function test_every_product_and_use_case_is_in_the_menu(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (__('ui.products.items') as $product) {
            $response->assertSeeText($product['name']);
            $response->assertSeeText($product['tagline']);
        }

        foreach (__('ui.use_cases.items') as $useCase) {
            $response->assertSeeText($useCase['role']);
        }
    }

    public function test_every_resource_card_is_in_the_menu(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (__('ui.resources.cards') as $card) {
            $response->assertSee($card['title']);
        }
    }

    public function test_the_menu_carries_its_keyboard_and_outside_click_contract(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('@keydown.down.prevent', $html);
        $this->assertStringContainsString('@keydown.escape.stop.prevent', $html);
        $this->assertStringContainsString('@click.outside', $html);
        $this->assertStringContainsString('place()', $html);
    }

    public function test_the_arabic_menu_is_right_to_left(): void
    {
        $this->get('/?lang=ar')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl"', false)
            ->assertSeeText(__('ui.products.items')['desktop']['tagline'], false);
    }
}
