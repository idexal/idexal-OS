<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicSiteSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_publishes_a_complete_seo_head(): void
    {
        $response = $this->get('/')->assertOk();

        $content = $response->getContent();

        $this->assertStringContainsString('<link rel="canonical"', $content);
        $this->assertStringContainsString('hreflang="en"', $content);
        $this->assertStringContainsString('hreflang="ar"', $content);
        $this->assertStringContainsString('hreflang="x-default"', $content);

        $this->assertStringContainsString('property="og:image"', $content);
        $this->assertStringContainsString('brand/og-default.png', $content);
        $this->assertStringContainsString('property="og:image:width" content="1200"', $content);
        $this->assertStringContainsString('property="og:image:height" content="630"', $content);
        $this->assertStringContainsString('name="twitter:card" content="summary_large_image"', $content);
        $this->assertStringContainsString('name="robots" content="index, follow', $content);

        $this->assertStringContainsString('"@type": "Organization"', $content);
        $this->assertStringContainsString('"@type": "WebSite"', $content);
        $this->assertStringContainsString('"@type": "SoftwareApplication"', $content);
        $this->assertStringContainsString('"@type": "FAQPage"', $content);
    }

    public function test_the_language_query_switches_and_remembers_the_language(): void
    {
        $arabic = $this->get('/?lang=ar')->assertOk();
        $arabic->assertSee('<html lang="ar" dir="rtl"', false);
        $this->assertStringContainsString('property="og:locale" content="ar_AR"', $arabic->getContent());
        $this->assertStringEndsWith('?lang=ar', $this->canonicalOf($arabic));

        // The choice is remembered, so the next page stays Arabic without the
        // query parameter and links keep their language.
        $next = $this->get('/pricing')->assertOk();
        $next->assertSee('<html lang="ar" dir="rtl"', false);
        $this->assertStringEndsWith('?lang=ar', $this->canonicalOf($next));

        $english = $this->get('/?lang=en')->assertOk();
        $english->assertSee('<html lang="en" dir="ltr"', false);
        $this->assertStringNotContainsString('?lang=', $this->canonicalOf($english));
    }

    public function test_an_unknown_language_falls_back_to_the_default(): void
    {
        $response = $this->get('/?lang=xx')->assertOk();

        $response->assertSee('<html lang="en" dir="ltr"', false);
        $this->assertStringNotContainsString('?lang=xx', $response->getContent());
    }

    public function test_the_sitemap_lists_every_public_page_with_language_alternates(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
        $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false);
        $response->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', false);

        foreach (['', 'pricing', 'docs', 'download', 'security', 'terms', 'privacy', 'contact'] as $path) {
            $this->assertStringContainsString('<loc>'.$this->urlFor($path).'</loc>', $response->getContent());
        }

        $this->assertStringContainsString('hreflang="en"', $response->getContent());
        $this->assertStringContainsString('hreflang="ar"', $response->getContent());
        $this->assertStringContainsString('hreflang="x-default"', $response->getContent());
    }

    public function test_robots_advertises_the_sitemap_and_keeps_private_surfaces_out(): void
    {
        $robots = (string) file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://os.idexal.com/sitemap.xml', $robots);
        $this->assertStringContainsString('Disallow: /dashboard', $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
    }

    public function test_the_share_image_is_a_1200_by_630_png(): void
    {
        $path = public_path('brand/og-default.png');

        $this->assertFileExists($path);

        $bytes = (string) file_get_contents($path);
        $dimensions = unpack('Nwidth/Nheight', substr($bytes, 16, 8));

        $this->assertSame("\x89PNG\r\n\x1a\n", substr($bytes, 0, 8));
        $this->assertSame(1200, $dimensions['width']);
        $this->assertSame(630, $dimensions['height']);
    }

    private function canonicalOf(TestResponse $response): string
    {
        preg_match('/<link rel="canonical" href="([^"]*)"/', (string) $response->getContent(), $matches);

        return $matches[1] ?? '';
    }

    private function urlFor(string $path): string
    {
        return $path === '' ? url('/') : url('/'.$path);
    }
}
