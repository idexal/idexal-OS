<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The documentation is authored once and served by the site.
 *
 * These tests read the same files the product ships, so they fail if the
 * navigation, the MDX reduction or the agent-facing Markdown endpoint stops
 * answering — the things that made the documentation invisible before.
 */
class PublicDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_documentation_home_renders_the_first_page(): void
    {
        $response = $this->get('/docs')->assertOk();

        $response->assertSee('start-here/get-started', false);
        // The search field's label is an attribute, not a text node.
        $response->assertSee(__('ui.docs.search_placeholder'), false);
    }

    public function test_a_page_renders_its_body_and_its_place_in_the_tree(): void
    {
        $response = $this->get('/docs/start-here/get-started')->assertOk();

        // The body is Markdown, so this proves the renderer ran.
        $response->assertSee('<h2', false);
        $response->assertSeeText(__('ui.docs.on_this_page'));
        $response->assertSeeText(__('ui.docs.next'));
    }

    public function test_a_page_can_be_fetched_as_markdown_for_agents(): void
    {
        $response = $this->get('/docs/start-here/get-started.md')->assertOk();

        $this->assertStringContainsString('text/markdown', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('---', (string) $response->getContent());
    }

    public function test_the_navigation_carries_every_authored_page(): void
    {
        $response = $this->get('/docs')->assertOk();

        foreach (['start-here/get-started', 'self-host/evaluate-with-docker-compose', 'docs/ai-gateway/overview'] as $slug) {
            $response->assertSee($slug, false);
        }
    }

    public function test_a_search_lists_matching_pages(): void
    {
        $response = $this->get('/docs?q=mcp')->assertOk();

        $response->assertSeeText(__('ui.docs.search'));
        $response->assertSee('docs/', false);
    }

    public function test_an_unknown_page_is_not_found(): void
    {
        $this->get('/docs/does/not/exist')->assertNotFound();
    }

    public function test_a_traversal_attempt_is_not_found(): void
    {
        $this->get('/docs/..%2F..%2F.env')->assertNotFound();
    }

    public function test_the_api_reference_still_exists_where_it_moved(): void
    {
        $this->get('/api')->assertOk()->assertSeeText(__('ui.docs.title'));
    }
}
