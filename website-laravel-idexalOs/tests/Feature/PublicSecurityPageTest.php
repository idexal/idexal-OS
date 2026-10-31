<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSecurityPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_security_page_renders_the_governance_controls(): void
    {
        $response = $this->get('/security')->assertOk();

        $response->assertSee(__('ui.security.title'));
        $response->assertSee(__('ui.security.browser_title'));
        $response->assertSee(__('ui.security.agents_title'));
        $response->assertSee(route('contact'));
        $response->assertDontSee('ui.security.title');
    }
}
