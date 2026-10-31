<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * One machine-readable list of every public page, with a language alternate
 * for each so search engines can serve the right language to the right
 * visitor anywhere in the world.
 */
class SitemapController extends Controller
{
    /**
     * Public pages in priority order.
     *
     * @var list<array{route: string, changefreq: string, priority: string}>
     */
    private const PAGES = [
        ['route' => 'home', 'changefreq' => 'weekly', 'priority' => '1.0'],
        ['route' => 'download', 'changefreq' => 'weekly', 'priority' => '0.9'],
        ['route' => 'pricing', 'changefreq' => 'monthly', 'priority' => '0.8'],
        ['route' => 'products', 'changefreq' => 'monthly', 'priority' => '0.8'],
        ['route' => 'use-cases', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['route' => 'resources', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['route' => 'enterprise', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['route' => 'docs', 'changefreq' => 'weekly', 'priority' => '0.8'],
        ['route' => 'developers', 'changefreq' => 'monthly', 'priority' => '0.7'],
        ['route' => 'api', 'changefreq' => 'monthly', 'priority' => '0.6'],
        ['route' => 'providers', 'changefreq' => 'weekly', 'priority' => '0.7'],
        ['route' => 'integrations', 'changefreq' => 'weekly', 'priority' => '0.7'],
        ['route' => 'changelog', 'changefreq' => 'weekly', 'priority' => '0.6'],
        ['route' => 'security', 'changefreq' => 'monthly', 'priority' => '0.6'],
        ['route' => 'contact', 'changefreq' => 'yearly', 'priority' => '0.5'],
        ['route' => 'legal.terms', 'changefreq' => 'yearly', 'priority' => '0.3'],
        ['route' => 'legal.privacy', 'changefreq' => 'yearly', 'priority' => '0.3'],
    ];

    public function index(): Response
    {
        $defaultLocale = default_locale();

        $pages = collect($this->entries())->map(function (array $page) use ($defaultLocale): array {
            $url = route($page['route'], $page['parameters'] ?? []);

            $alternates = collect(supported_locales())
                ->mapWithKeys(fn (string $locale): array => [
                    str_replace('_', '-', $locale) => $locale === $defaultLocale
                        ? $url
                        : $url.'?'.http_build_query(['lang' => $locale]),
                ])
                ->all();

            return [
                'url' => $url,
                'changefreq' => $page['changefreq'],
                'priority' => $page['priority'],
                'alternates' => $alternates,
                'x_default' => $url,
            ];
        });

        return response()
            ->view('public.sitemap', [
                'pages' => $pages,
                'lastmod' => now()->toAtomString(),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * The named pages plus one entry per product, so a product page is
     * discoverable the same way the pages around it are.
     *
     * @return list<array{route: string, changefreq: string, priority: string, parameters?: array<string, string>}>
     */
    private function entries(): array
    {
        $products = __('ui.products.items');
        $productPages = [];

        if (is_array($products)) {
            foreach (array_keys($products) as $slug) {
                $productPages[] = [
                    'route' => 'products.show',
                    'changefreq' => 'monthly',
                    'priority' => '0.7',
                    'parameters' => ['slug' => (string) $slug],
                ];
            }
        }

        $useCases = __('ui.use_cases.items');
        $useCasePages = [];

        if (is_array($useCases)) {
            foreach (array_keys($useCases) as $slug) {
                $useCasePages[] = [
                    'route' => 'use-cases.show',
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                    'parameters' => ['slug' => (string) $slug],
                ];
            }
        }

        return [...self::PAGES, ...$productPages, ...$useCasePages, ...$this->cataloguePages()];
    }

    /**
     * One URL per marketplace entry. The store is the part of the site most
     * likely to be found by a search for a specific skill, so a detail page
     * that is not in the sitemap is a page nobody can discover.
     *
     * @return list<array{route: string, changefreq: string, priority: string, parameters: array<string, string>}>
     */
    private function cataloguePages(): array
    {
        $items = app(\App\Services\MarketplaceCatalogService::class)->catalog()['items'] ?? [];

        $pages = [];

        foreach ((array) $items as $item) {
            if (! is_array($item) || ! filled($item['slug'] ?? null) || ! filled($item['type'] ?? null)) {
                continue;
            }

            if (! in_array($item['type'], ['skill', 'plugin', 'connector'], true)) {
                continue;
            }

            $pages[] = [
                'route' => 'integrations.show',
                'changefreq' => 'monthly',
                'priority' => '0.4',
                'parameters' => ['type' => (string) $item['type'], 'slug' => (string) $item['slug']],
            ];
        }

        return $pages;
    }
}
