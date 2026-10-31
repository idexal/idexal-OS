<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The public catalogue: every skill, plugin and connector the platform can
 * install in a desktop app, read from the same marketplace the admin console
 * installs from.
 *
 * The catalogue is bundled with the application (resources/marketplace) and
 * optionally enriched from GitHub, so the store is populated on a fresh
 * deployment and stays populated when GitHub is unreachable or rate-limited.
 * A page that silently showed an empty store is exactly the failure this
 * controller was rewritten to prevent.
 */
class IntegrationsController extends Controller
{
    /** Rows per page. The library carries hundreds of skills. */
    private const PER_PAGE = 48;

    /**
     * The curated shelf. Each entry is a skill the catalogue actually carries;
     * the copy is what a reader needs to decide whether it is useful, which a
     * directory name alone cannot say.
     *
     * @var list<array{slug: string, title: string, body: string, tag: string}>
     */
    private const FEATURED = [
        ['slug' => 'playwright-best-practices', 'title' => 'Browser testing', 'body' => 'Page objects, fixtures, flaky-test triage, network mocking and CI wiring for Playwright suites.', 'tag' => 'Engineering'],
        ['slug' => 'systematic-debugging', 'title' => 'Debugging', 'body' => 'A discipline for finding the cause before the fix: reproduce, isolate, prove, then change one thing.', 'tag' => 'Engineering'],
        ['slug' => 'security-review', 'title' => 'Security review', 'body' => 'A checklist-driven pass for injection, authorisation, secrets and unsafe deserialisation.', 'tag' => 'Security'],
        ['slug' => 'tdd', 'title' => 'Test-first delivery', 'body' => 'Red, green, refactor — with the integration-test shapes that survive a refactor.', 'tag' => 'Engineering'],
        ['slug' => 'frontend-design', 'title' => 'Interface design', 'body' => 'Type scales, spacing, motion and the judgement that keeps a screen from looking templated.', 'tag' => 'Design'],
        ['slug' => 'docx', 'title' => 'Word documents', 'body' => 'Create, read and edit .docx files: styles, tables of contents, tracked changes and comments.', 'tag' => 'Documents'],
        ['slug' => 'xlsx', 'title' => 'Spreadsheets', 'body' => 'Formulas, charts, pivot tables and clean imports from messy CSV into a real workbook.', 'tag' => 'Documents'],
        ['slug' => 'pptx', 'title' => 'Presentations', 'body' => 'Build and edit decks, including templates, speaker notes and slide-level layout.', 'tag' => 'Documents'],
        ['slug' => 'pdf', 'title' => 'PDFs', 'body' => 'Extract text and tables, merge and split, fill forms, and OCR a scanned document.', 'tag' => 'Documents'],
        ['slug' => 'mcp-builder', 'title' => 'MCP servers', 'body' => 'Design and ship a Model Context Protocol server with tools an agent can actually use.', 'tag' => 'Platform'],
        ['slug' => 'writing-plans', 'title' => 'Planning', 'body' => 'Turn a specification into an ordered plan with checkpoints before any code is written.', 'tag' => 'Delivery'],
        ['slug' => 'webapp-testing', 'title' => 'Web app testing', 'body' => 'Drive a local web app, assert what a user sees, and capture the proof in screenshots.', 'tag' => 'Engineering'],
    ];

    public function __construct(private readonly MarketplaceCatalogService $marketplace) {}

    /**
     * One catalogue entry, with the context a reader needs before installing
     * it: what it does, where it comes from, how it reaches a desktop, and
     * which entries are closest to it.
     */
    public function show(string $type, string $slug): View
    {
        abort_unless(in_array($type, ['skill', 'plugin', 'connector'], true), 404);

        $extensions = $this->catalogue();
        $item = $extensions->first(
            fn (array $entry): bool => $entry['slug'] === $slug && $entry['type'] === $type,
        );

        abort_if($item === null, 404);

        $related = $this->related($extensions, $item);

        return view('public.integration', [
            'item' => $item,
            'related' => $related,
            'type' => $type,
            'preview' => $type === 'skill' ? $this->instructionsPreview($slug) : null,
            'counts' => [
                'all' => $extensions->count(),
                'skill' => $extensions->where('type', 'skill')->count(),
                'plugin' => $extensions->where('type', 'plugin')->count(),
                'connector' => $extensions->where('type', 'connector')->count(),
            ],
        ]);
    }

    /**
     * The first part of a skill's own instructions.
     *
     * A skills store that only repeats a one-line description asks people to
     * install something they cannot read. The file ships with the platform, so
     * the page can show the opening of it without a network call.
     */
    private function instructionsPreview(string $slug): ?string
    {
        $path = resource_path('marketplace/skills/'.$slug.'.md');

        if (! is_file($path)) {
            return null;
        }

        $markdown = (string) file_get_contents($path);
        // Drop the YAML front matter: its fields are already on the page.
        $markdown = preg_replace('/\A---\r?\n.*?\r?\n---\r?\n/s', '', $markdown) ?? $markdown;
        $markdown = trim($markdown);

        return $markdown === '' ? null : Str::limit($markdown, 1400);
    }

    /**
     * The four entries closest to this one.
     *
     * Shared tags are a weak signal once most skills carry none, so the ranking
     * uses the words in the name and description; a same-type fallback keeps
     * the shelf from being empty.
     *
     * @param  Collection<int, array<string, mixed>>  $extensions
     * @return Collection<int, array<string, mixed>>
     */
    private function related(Collection $extensions, array $item): Collection
    {
        $words = static function (string $text): array {
            $parts = preg_split('/[^a-z0-9]+/', Str::lower($text)) ?: [];

            return array_values(array_unique(array_filter($parts, static fn (string $word): bool => strlen($word) >= 4)));
        };

        $base = $words($item['name'].' '.$item['description']);

        $ranked = $extensions
            ->filter(fn (array $entry): bool => $entry['type'] === $item['type'] && $entry['slug'] !== $item['slug'])
            ->map(fn (array $entry): array => [
                'entry' => $entry,
                'score' => count(array_intersect($base, $words($entry['name'].' '.$entry['description']))),
            ])
            ->sortByDesc('score')
            ->filter(fn (array $row): bool => $row['score'] > 0)
            ->take(4)
            ->pluck('entry');

        if ($ranked->isNotEmpty()) {
            return $ranked->values();
        }

        return $extensions
            ->filter(fn (array $entry): bool => $entry['type'] === $item['type'] && $entry['slug'] !== $item['slug'])
            ->take(4)
            ->values();
    }

    public function index(Request $request): View
    {
        $extensions = $this->catalogue();

        $type = (string) $request->query('type', '');
        $type = in_array($type, ['skill', 'plugin', 'connector'], true) ? $type : '';

        $query = trim((string) $request->query('q', ''));

        $filtered = $extensions
            ->when($type !== '', fn (Collection $items): Collection => $items->where('type', $type))
            ->when($query !== '', fn (Collection $items): Collection => $items->filter(
                fn (array $item): bool => Str::contains(
                    Str::lower($item['name'].' '.$item['description']),
                    Str::lower($query),
                ),
            ))
            ->values();

        $page = max(1, (int) $request->query('page', 1));
        $items = $filtered->forPage($page, self::PER_PAGE)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $filtered->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('public.integrations', [
            'featured' => $this->featured($extensions),
            'items' => $items->all(),
            'paginator' => $paginator,
            'counts' => [
                'all' => $extensions->count(),
                'skill' => $extensions->where('type', 'skill')->count(),
                'plugin' => $extensions->where('type', 'plugin')->count(),
                'connector' => $extensions->where('type', 'connector')->count(),
            ],
            'type' => $type,
            'query' => $query,
            'total' => $filtered->count(),
        ]);
    }

    /**
     * The catalogue in the shape the page renders.
     *
     * @return Collection<int, array{slug: string, name: string, title: string, description: string, type: string, url: string|null, tags: list<string>}>
     */
    private function catalogue(): Collection
    {
        return collect($this->marketplace->catalog()['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['slug'] ?? null))
            ->map(fn (array $item): array => [
                'slug' => (string) $item['slug'],
                'name' => (string) ($item['name'] ?? $item['slug']),
                'title' => Str::headline((string) ($item['name'] ?? $item['slug'])),
                'description' => (string) ($item['description'] ?? ''),
                'type' => (string) ($item['type'] ?? 'skill'),
                'url' => $item['source'] ?? $item['url'] ?? null,
                'tags' => array_values(array_filter((array) ($item['tags'] ?? []))),
            ])
            ->unique('slug')
            ->values();
    }

    /**
     * Featured cards keep their curated copy only when the catalogue really
     * carries the skill; otherwise the shelf would link to nothing.
     *
     * @param  Collection<int, array<string, mixed>>  $extensions
     * @return list<array{title: string, body: string, tag: string, name: string, url: string|null}>
     */
    private function featured(Collection $extensions): array
    {
        $bySlug = $extensions->keyBy('slug');

        $cards = [];

        foreach (self::FEATURED as $entry) {
            $item = $bySlug->get($entry['slug']);

            if (! is_array($item)) {
                continue;
            }

            $cards[] = [
                'title' => $entry['title'],
                'body' => $entry['body'],
                'tag' => $entry['tag'],
                'name' => (string) $item['name'],
                'slug' => (string) $item['slug'],
                'type' => (string) $item['type'],
                'url' => $item['url'],
            ];
        }

        return $cards;
    }
}
