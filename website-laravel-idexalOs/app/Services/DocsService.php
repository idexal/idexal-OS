<?php

namespace App\Services;

use Illuminate\Support\Str;
use League\CommonMark\Extension\Table\TableExtension;

/**
 * The documentation the product ships, served on the public site.
 *
 * The docs are authored once in `docs/` (MDX with YAML front matter, and a
 * Mintlify `docs.json` that already carries the navigation). This service is
 * the only place that reads them, so the site, the sitemap and the agent-facing
 * `.md` endpoints all show the same tree — the one the authors defined — with
 * no second copy to keep in step.
 *
 * MDX is reduced to the Markdown underneath it: imports are dropped and the
 * Mintlify callouts become blockquotes, so a page that is rich in the docs
 * viewer still reads correctly as plain HTML here.
 */
class DocsService
{
    /** Components whose text should read as a callout rather than be dropped. */
    private const CALLOUTS = [
        'note' => 'Note',
        'info' => 'Note',
        'tip' => 'Tip',
        'warning' => 'Warning',
        'warn' => 'Warning',
        'danger' => 'Warning',
        'check' => 'Tip',
    ];

    private const CACHE_KEY = 'docs:index';

    /**
     * Every page in reading order, flattened from `docs.json`.
     *
     * @return list<array{slug: string, title: string, tab: ?string, group: ?string}>
     */
    public function pages(): array
    {
        return cache()->remember(self::CACHE_KEY, now()->addMinutes(10), fn (): array => $this->buildIndex());
    }

    /**
     * The navigation tree exactly as authored, with titles resolved from each
     * page's own front matter.
     *
     * @return list<array{tab: string, items: list<array<string, mixed>>}>
     */
    public function navigation(): array
    {
        $tabs = [];

        foreach ($this->config()['navigation']['tabs'] ?? [] as $tab) {
            if (! is_array($tab) || ! isset($tab['tab'])) {
                continue;
            }

            $tabs[] = [
                'tab' => (string) $tab['tab'],
                'items' => $this->navigationItems($tab['pages'] ?? [], null),
            ];
        }

        return $tabs;
    }

    /**
     * One page, ready to render, or null when the slug is not a document.
     *
     * @return array{slug: string, title: string, description: ?string, html: string, toc: list<array{level: int, text: string}>, prev: ?array, next: ?array, tab: ?string, group: ?string}|null
     */
    public function page(string $slug): ?array
    {
        $slug = $this->normalizeSlug($slug);

        if ($slug === null) {
            return null;
        }

        $path = $this->pathFor($slug);

        if ($path === null) {
            return null;
        }

        $source = (string) file_get_contents($path);
        [$front, $body] = $this->splitFrontMatter($source);
        $html = $this->render($body);

        $order = $this->pages();
        $position = null;

        foreach ($order as $index => $entry) {
            if ($entry['slug'] === $slug) {
                $position = $index;
                break;
            }
        }

        $entry = $position === null ? null : $order[$position];

        return [
            'slug' => $slug,
            'title' => (string) ($front['title'] ?? $front['sidebarTitle'] ?? $this->titleFromHtml($html) ?? $slug),
            'description' => isset($front['description']) ? (string) $front['description'] : null,
            'html' => $html,
            'toc' => $this->tocFromHtml($html),
            'prev' => $position !== null && $position > 0 ? $order[$position - 1] : null,
            'next' => $position !== null && $position < count($order) - 1 ? $order[$position + 1] : null,
            'tab' => $entry['tab'] ?? null,
            'group' => $entry['group'] ?? null,
        ];
    }

    /** The raw MDX of a page, for the agent-facing `.md` endpoints. */
    public function raw(string $slug): ?string
    {
        $slug = $this->normalizeSlug($slug);

        if ($slug === null) {
            return null;
        }

        $path = $this->pathFor($slug);

        return $path === null ? null : (string) file_get_contents($path);
    }

    /** The first page of the documentation, used for the bare `/docs` request. */
    public function firstSlug(): ?string
    {
        return $this->pages()[0]['slug'] ?? null;
    }

    /**
     * @return list<array{slug: string, title: string, tab: ?string, group: ?string, snippet: string}>
     */
    public function search(string $query, int $limit = 20): array
    {
        $needle = Str::lower(trim($query));

        if ($needle === '') {
            return [];
        }

        $results = [];

        foreach ($this->pages() as $entry) {
            $path = $this->pathFor($entry['slug']);

            if ($path === null) {
                continue;
            }

            $source = Str::lower((string) file_get_contents($path));
            if (! Str::contains($source, $needle)) {
                continue;
            }

            $position = Str::position($source, $needle);
            $snippet = Str::of(Str::substr($source, max(0, $position - 80), 200))->squish()->toString();

            $results[] = $entry + ['snippet' => $snippet];

            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * Turn one MDX document into HTML, dropping the parts that only exist for
     * the docs viewer and keeping every word a reader needs.
     */
    public function render(string $markdown): string
    {
        $markdown = $this->reduceMdx($markdown);

        return (string) Str::markdown($markdown, [
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ], [
            new TableExtension,
        ]);
    }

    /** MDX imports, exports and JSX down to plain Markdown. */
    private function reduceMdx(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $kept = [];
        $depth = 0;

        foreach ($lines as $line) {
            $trimmed = ltrim($line);

            // `import X from "..."` / `export const X = ...` never render.
            if (Str::startsWith($trimmed, ['import ', 'export '])) {
                continue;
            }

            // JSX comments never render.
            $line = preg_replace('#\{/\*.*?\*/\}#s', '', $line) ?? $line;
            $trimmed = ltrim($line);

            if (trim($trimmed) === '') {
                $kept[] = '';

                continue;
            }

            // A callout opens with its component tag on a line of its own; its
            // body is ordinary Markdown that follows, so the tag becomes a
            // labelled quote and the body is kept.
            if (preg_match('#^<([A-Za-z][A-Za-z0-9]*)\b[^>]*>\s*$#', $trimmed, $match)) {
                $label = self::CALLOUTS[Str::lower($match[1])] ?? null;

                if ($label !== null) {
                    $kept[] = '> **'.$label.':**';
                }

                $depth++;

                continue;
            }

            // A closing tag on its own line ends the component.
            if (preg_match('#^</[A-Za-z][A-Za-z0-9]*\s*>\s*$#', $trimmed)) {
                $depth = max(0, $depth - 1);

                continue;
            }

            // A self-closing or void tag on its own line has nothing to keep.
            if (preg_match('#^<[A-Za-z][A-Za-z0-9]*\b[^>]*/?>\s*$#', $trimmed)) {
                continue;
            }

            // Inside a component the author indented for JSX, and that
            // indentation is not Markdown's indented-code block: without this
            // the intro cards rendered as <pre><code> walls of text.
            if ($depth > 0) {
                $line = ltrim($line);
            }

            // Tags used inline around text keep the text.
            $line = preg_replace('#</?[A-Za-z][A-Za-z0-9]*\b[^>]*>#', '', $line) ?? $line;

            $kept[] = $line;
        }

        return Str::of(implode("\n", $kept))->replaceMatches("/\n{3,}/", "\n\n")->toString();
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function splitFrontMatter(string $source): array
    {
        if (! preg_match('/^---\r?\n(.*?)\r?\n---\r?\n?/s', $source, $match)) {
            return [[], $source];
        }

        return [$this->parseFrontMatter($match[1]), Str::after($source, $match[0])];
    }

    /**
     * The front matter these documents use: `key: value` lines, optionally
     * quoted. It is parsed here rather than with a YAML package so the site
     * serves the documentation on any host — a deployment that has not
     * installed a new dependency must not be the reason a page 500s.
     *
     * @return array<string, mixed>
     */
    private function parseFrontMatter(string $block): array
    {
        $front = [];

        foreach (preg_split('/\r\n|\r|\n/', $block) ?: [] as $line) {
            if (! preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', trim($line), $match)) {
                continue;
            }

            $value = trim($match[2]);

            // Nested blocks belong to another key; only scalars are read here.
            if ($value === '' || Str::startsWith($value, ['{', '['])) {
                continue;
            }

            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && str_ends_with($value, $value[0])) {
                $value = substr($value, 1, -1);
            }

            $front[$match[1]] = $value;
        }

        return $front;
    }

    /**
     * @return list<array{slug: string, title: string, tab: ?string, group: ?string}>
     */
    private function buildIndex(): array
    {
        $index = [];

        foreach ($this->config()['navigation']['tabs'] ?? [] as $tab) {
            if (! is_array($tab) || ! isset($tab['tab'])) {
                continue;
            }

            $this->collectPages($tab['pages'] ?? [], (string) $tab['tab'], null, $index);
        }

        // A page can be listed under more than one tab for navigation, but it
        // is one document: the first place it appears owns its URL, its
        // previous/next neighbours and its sitemap entry.
        $seen = [];
        $unique = [];

        foreach ($index as $entry) {
            if (isset($seen[$entry['slug']])) {
                continue;
            }

            $seen[$entry['slug']] = true;
            $unique[] = $entry;
        }

        return $unique;
    }

    /**
     * @param  list<array{slug: string, title: string, tab: ?string, group: ?string}>  $index
     */
    private function collectPages(mixed $pages, string $tab, ?string $group, array &$index): void
    {
        if (! is_array($pages)) {
            return;
        }

        foreach ($pages as $entry) {
            if (is_string($entry)) {
                $slug = $this->normalizeSlug($entry);

                if ($slug === null) {
                    continue;
                }

                $path = $this->pathFor($slug);
                [$front] = $path === null ? [[], ''] : $this->splitFrontMatter((string) file_get_contents($path));

                $index[] = [
                    'slug' => $slug,
                    'title' => (string) ($front['sidebarTitle'] ?? $front['title'] ?? Str::headline((string) Str::afterLast($slug, '/'))),
                    'tab' => $tab,
                    'group' => $group,
                ];

                continue;
            }

            if (is_array($entry) && isset($entry['group'])) {
                $this->collectPages($entry['pages'] ?? [], $tab, (string) $entry['group'], $index);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function navigationItems(mixed $pages, ?string $group): array
    {
        if (! is_array($pages)) {
            return [];
        }

        $items = [];

        foreach ($pages as $entry) {
            if (is_string($entry)) {
                $slug = $this->normalizeSlug($entry);

                if ($slug === null) {
                    continue;
                }

                $path = $this->pathFor($slug);
                [$front] = $path === null ? [[], ''] : $this->splitFrontMatter((string) file_get_contents($path));

                $items[] = [
                    'type' => 'page',
                    'slug' => $slug,
                    'title' => (string) ($front['sidebarTitle'] ?? $front['title'] ?? Str::headline((string) Str::afterLast($slug, '/'))),
                ];

                continue;
            }

            if (is_array($entry) && isset($entry['group'])) {
                $items[] = [
                    'type' => 'group',
                    'title' => (string) $entry['group'],
                    'items' => $this->navigationItems($entry['pages'] ?? [], (string) $entry['group']),
                ];
            }
        }

        return $items;
    }

    /** Reject traversal and shapes that are not a document path. */
    private function normalizeSlug(string $slug): ?string
    {
        $slug = trim($slug);
        $slug = (string) Str::of($slug)->ltrim('/')->replaceEnd('.md', '')->replaceEnd('.mdx', '');

        if ($slug === '' || Str::contains($slug, '..') || ! preg_match('#^[A-Za-z0-9][A-Za-z0-9/_-]*$#', $slug)) {
            return null;
        }

        return $slug;
    }

    private function pathFor(string $slug): ?string
    {
        foreach ([$slug.'.mdx', $slug.'.md'] as $candidate) {
            $path = $this->root().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $candidate);

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function root(): string
    {
        return base_path('docs');
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        $path = $this->root().DIRECTORY_SEPARATOR.'docs.json';

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return list<array{level: int, text: string}> */
    private function tocFromHtml(string $html): array
    {
        preg_match_all('#<h([23])[^>]*>(.*?)</h\1>#s', $html, $matches, PREG_SET_ORDER);

        $toc = [];

        foreach ($matches as $match) {
            $text = trim(html_entity_decode(strip_tags($match[2])));

            if ($text !== '') {
                $toc[] = ['level' => (int) $match[1], 'text' => $text];
            }
        }

        return $toc;
    }

    private function titleFromHtml(string $html): ?string
    {
        if (preg_match('/<h1[^>]*>(.*?)</h1>/s', $html, $match)) {
            $text = trim(html_entity_decode(strip_tags($match[1])));

            return $text === '' ? null : $text;
        }

        return null;
    }
}
