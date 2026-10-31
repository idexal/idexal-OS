<?php

/**
 * Publish a desktop build into the download manifest the public site reads.
 *
 * The download page and `GET /v1/app-version` are served from
 * `public/desktop/releases.json`, which is what tells a visitor which build to
 * download and what the in-app updater polls. The release pipeline normally
 * writes it; this is the same job for a build published outside that pipeline.
 *
 * Usage:
 *   php tools/publish-desktop-release.php <version> <file> <size> <sha512> [platform] [arch]
 *
 * It keeps every existing entry and prepends the new one, so no older build
 * loses its file, size or checksum.
 */
$version = $argv[1] ?? null;
$file = $argv[2] ?? null;
$size = $argv[3] ?? null;
$sha512 = $argv[4] ?? null;
$platform = $argv[5] ?? 'windows';
$arch = $argv[6] ?? 'x64';

if ($version === null || $file === null || $size === null || $sha512 === null) {
    fwrite(STDERR, "usage: php tools/publish-desktop-release.php <version> <file> <size> <sha512> [platform] [arch]\n");
    exit(1);
}

$directory = dirname(__DIR__).'/public/desktop';
if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
    fwrite(STDERR, "cannot create {$directory}\n");
    exit(1);
}

$path = $directory.'/releases.json';
$manifest = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
if (! is_array($manifest)) {
    $manifest = [];
}

$entry = [
    'version' => (string) $version,
    'platform' => (string) $platform,
    'arch' => (string) $arch,
    'file' => (string) $file,
    'size' => (int) $size,
    'sha512' => (string) $sha512,
    'notes' => 'Sign in, or start a subscription and choose a plan, on first launch.',
    'publishedAt' => gmdate('c'),
];

$releases = is_array($manifest['releases'] ?? null) ? $manifest['releases'] : [];
// Replace an entry for the same version instead of duplicating it.
$releases = array_values(array_filter(
    $releases,
    static fn ($release): bool => (string) ($release['version'] ?? '') !== (string) $version,
));
array_unshift($releases, $entry);

$manifest['latest'] = (string) $version;
$manifest['publishedAt'] = gmdate('c');
$manifest['releases'] = $releases;

file_put_contents(
    $path,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
);

printf("latest=%s entries=%d\n", $manifest['latest'], count($releases));
