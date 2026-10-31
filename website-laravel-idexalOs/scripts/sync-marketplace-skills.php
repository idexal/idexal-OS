<?php

/**
 * Rebuild the bundled skills library from a checkout of the skills repository.
 *
 * The marketplace must never depend on GitHub at request time: the public
 * catalogue and the desktop installer both read from disk. This script copies
 * every SKILL.md into resources/marketplace/skills/<slug>.md and writes
 * resources/marketplace/library.json, which MarketplaceCatalogService merges
 * with the hand-curated catalog.json.
 *
 * Usage:
 *   php scripts/sync-marketplace-skills.php /path/to/idexal-skills-checkout
 *
 * The curated entries in catalog.json always win: a skill that is already
 * described there keeps its hand-written name, description and tags.
 */

$source = $argv[1] ?? '';

if ($source === '' || ! is_dir($source)) {
    fwrite(STDERR, "Usage: php scripts/sync-marketplace-skills.php <skills-checkout>\n");
    exit(1);
}

$root = dirname(__DIR__);
$skillsDir = $root.'/resources/marketplace/skills';
$libraryPath = $root.'/resources/marketplace/library.json';
$catalogPath = $root.'/resources/marketplace/catalog.json';

if (! is_dir($skillsDir) && ! mkdir($skillsDir, 0775, true) && ! is_dir($skillsDir)) {
    fwrite(STDERR, "Cannot create {$skillsDir}\n");
    exit(1);
}

/** Slugs the curated catalog already owns. */
$curated = [];
$catalog = json_decode((string) @file_get_contents($catalogPath), true);
foreach ((array) ($catalog['items'] ?? []) as $item) {
    if (isset($item['slug'])) {
        $curated[(string) $item['slug']] = true;
    }
}

/**
 * Read the YAML front matter without a YAML dependency.
 *
 * Only the two keys the catalogue needs are parsed, and the values are read
 * the way skill authors actually write them: plain, single- or double-quoted,
 * folded (`>-`) and plain multi-line continuations.
 *
 * @return array{name: string, description: string}
 */
function readFrontMatter(string $markdown): array
{
    if (! preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n/s', $markdown, $match)) {
        return ['name' => '', 'description' => ''];
    }

    $lines = preg_split('/\r?\n/', $match[1]) ?: [];
    $values = [];
    $current = null;

    foreach ($lines as $line) {
        if (preg_match('/^([A-Za-z0-9_-]+):\s*(.*)$/', $line, $m)) {
            $current = $m[1];
            $values[$current] = trim($m[2]);

            continue;
        }

        // A continuation line belongs to the key above it.
        if ($current !== null && preg_match('/^\s+\S/', $line)) {
            $values[$current] = trim($values[$current].' '.trim($line));
        }
    }

    $clean = static function (string $value): string {
        $value = preg_replace('/^[>|][+-]?\s*/', '', $value) ?? $value;
        $value = trim($value);

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        return trim($value);
    };

    return [
        'name' => $clean((string) ($values['name'] ?? '')),
        'description' => $clean((string) ($values['description'] ?? '')),
    ];
}

function headline(string $slug): string
{
    $words = preg_split('/[-_]+/', $slug) ?: [$slug];

    return implode(' ', array_map(
        static fn (string $word): string => $word === '' ? '' : mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1),
        $words,
    ));
}

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if (! $file->isFile() || $file->getFilename() !== 'SKILL.md') {
        continue;
    }

    $path = $file->getPathname();
    $relative = ltrim(str_replace('\\', '/', substr($path, strlen($source))), '/');

    // Internal machine skills are not offered to users.
    if (str_starts_with($relative, '.system/') || str_contains($relative, '/.system/')) {
        continue;
    }

    $slug = basename(dirname($path));
    if ($slug === '' || $slug === '.' || isset($files[$slug])) {
        continue;
    }

    $files[$slug] = $path;
}

ksort($files);

$items = [];
$copied = 0;

foreach ($files as $slug => $path) {
    $markdown = (string) file_get_contents($path);
    $front = readFrontMatter($markdown);

    // Every skill the desktop installs is read from this directory.
    file_put_contents($skillsDir.'/'.$slug.'.md', $markdown);
    $copied++;

    if (isset($curated[$slug])) {
        continue;
    }

    $relative = ltrim(str_replace('\\', '/', substr($path, strlen($source))), '/');
    // Hidden directories are curation markers (.curated, .system), not
    // categories: the first meaningful path segment is the real grouping.
    $segments = array_values(array_filter(
        explode('/', $relative),
        static fn (string $segment): bool => $segment !== '' && ! str_starts_with($segment, '.'),
    ));
    array_pop($segments); // the SKILL.md filename
    $category = (string) ($segments[0] ?? '');

    // A top-level skill's directory is its own name, which repeats the
    // identifier instead of grouping anything.
    if ($category === $slug) {
        $category = '';
    }

    // Authors sometimes repeat the directory name in the front matter, so a
    // one-word slug such as "academy-guide" would otherwise be shown verbatim.
    $name = $front['name'] !== '' && str_contains($front['name'], ' ')
        ? $front['name']
        : headline($front['name'] !== '' ? $front['name'] : $slug);

    $items[] = [
        'slug' => $slug,
        'type' => 'skill',
        'name' => $name,
        'description' => $front['description'],
        'version' => '1.0.0',
        'source' => 'https://github.com/idexal/idexal-skills',
        'tags' => $category === '' ? [] : [$category],
    ];
}

$library = [
    'version' => 1,
    'generatedFrom' => 'idexal/idexal-skills',
    'items' => $items,
];

file_put_contents(
    $libraryPath,
    json_encode($library, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
);

printf("Copied %d SKILL.md files; %d new catalogue entries (%d curated already).\n", $copied, count($items), count($curated));
printf("Wrote %s\n", $libraryPath);
