<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The gateway reference is the customer's guide to the badges their usage page
 * shows, so the features section has to render alongside the rest. It lives at
 * `/api` now; `/docs` is the documentation.
 */
class DocsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_gateway_reference_explains_the_gateway_features(): void
    {
        $page = $this->get('/api')
            ->assertOk()
            ->assertSee(__('ui.docs.features'));

        foreach ([
            'feature_web_search',
            'feature_vision',
            'feature_privacy',
            'feature_sanitizer',
            'feature_substitute',
            'feature_retry',
            'feature_repetition',
            'feature_headers',
        ] as $feature) {
            $page->assertSee(__('ui.docs.'.$feature));
        }
    }

    public function test_the_features_section_did_not_displace_the_streaming_section(): void
    {
        $this->get('/api')
            ->assertOk()
            ->assertSee(__('ui.docs.streaming'))
            ->assertSee(__('ui.docs.streaming_body'));
    }
}
