<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicChangelogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_changelog_lists_every_published_release(): void
    {
        $response = $this->get('/changelog')->assertOk();

        $response->assertSeeText('What\'s new');
        $response->assertSee('id="v2.7.0"', false);
        $response->assertSee('id="v2.6.0"', false);
        $response->assertSeeText('Your language follows every link, and the desktop app speaks Arabic');
        $response->assertSee('application/ld+json', false);
        $response->assertSee('href="#v2.7.0"', false);
        $response->assertSee('aria-label="Link to release 2.7.0"', false);
    }

    public function test_the_changelog_speaks_arabic_when_the_visitor_does(): void
    {
        $response = $this->get('/changelog?lang=ar')->assertOk();

        $response->assertSee('<html lang="ar" dir="rtl"', false);
        $response->assertSeeText('لغتك تتبع كل رابط، والتطبيق يتحدث العربية');
        $response->assertSee('href="'.route('changelog').'?lang=ar"', false);
        $response->assertSee('aria-label="رابط إلى الإصدار 2.7.0"', false);
    }

    public function test_the_sitemap_lists_the_changelog(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringContainsString('<loc>'.route('changelog').'</loc>', (string) $response->getContent());
    }
}
