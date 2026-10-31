<?php

namespace App\Services;

use App\Models\ExtensionInstallation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Extension Marketplace Service
 * Fetches, categorizes, and manages extensions from GitHub repositories.
 * Supports three types: connectors, skills, plugins
 */
class ExtensionMarketplaceService
{
    protected string $githubToken;

    protected string $marketplaceRepo;

    /** Additional repositories whose skills are offered beside the catalog's. */
    protected array $skillRepositories;

    public function __construct()
    {
        // Live catalog rows are opt-in; the bundled catalog is the default
        // source of truth, so no token or repository is required in a
        // self-contained installation.
        $this->githubToken = (string) (config('services.github.token') ?? env('GITHUB_TOKEN', ''));
        $this->marketplaceRepo = (string) config('idexal.marketplace.repository', 'idexal/extensions-catalog');

        // The catalog repo carries a handful of curated skills as files; the
        // team's skills library carries hundreds, one directory each. Both are
        // offered, and an operator still decides what any desktop receives.
        $this->skillRepositories = array_values(array_filter(array_map(
            'trim',
            (array) config('idexal.marketplace.skill_repositories', []),
        )));
    }

    /**
     * Fetch all available extensions from marketplace
     */
    public function fetchAll(): Collection
    {
        return collect([
            $this->fetchConnectors(),
            $this->fetchSkills(),
            $this->fetchPlugins(),
        ])->flatten()->sortBy('name');
    }

    /**
     * Fetch connector extensions from GitHub
     */
    public function fetchConnectors(): Collection
    {
        return $this->fetchFromRepo('connectors');
    }

    /**
     * Fetch skill extensions from GitHub.
     *
     * Two layouts are read: the catalog repo keeps one file per skill under
     * `skills/`, and a skills library keeps one directory per skill at its
     * root. A library skill has no single file to download, so its entry points
     * at the directory's `SKILL.md` and the directory name is its name.
     */
    public function fetchSkills(): Collection
    {
        return $this->fetchFromRepo('skills')
            ->merge($this->fetchSkillsFromLibraries())
            ->unique('file_path')
            ->values();
    }

    /**
     * Skills offered by the configured skills libraries, one directory each.
     */
    protected function fetchSkillsFromLibraries(): Collection
    {
        $skills = collect();

        foreach ($this->skillRepositories as $repository) {
            if (! preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository)) {
                continue;
            }

            foreach ($this->listRepositoryRoot($repository) as $entry) {
                $skills->push([
                    'type' => 'skill',
                    'name' => (string) $entry['name'],
                    'description' => '',
                    'file_path' => $entry['name'].'/SKILL.md',
                    'download_url' => "https://raw.githubusercontent.com/{$repository}/HEAD/{$entry['name']}/SKILL.md",
                    'sha' => (string) ($entry['sha'] ?? ''),
                    'last_updated' => time(),
                    'tags' => [$repository],
                    'rating' => 0,
                    'install_count' => 0,
                ]);
            }
        }

        return $skills;
    }

    /**
     * The directories at the root of a repository, or an empty list when it
     * cannot be read: one unreachable library must not empty the marketplace.
     *
     * @return list<array<string, mixed>>
     */
    protected function listRepositoryRoot(string $repository): array
    {
        $url = "https://api.github.com/repos/{$repository}/contents";

        try {
            $response = Http::withHeaders($this->githubHeaders())->timeout(30)->get($url);

            if (! $response->successful()) {
                report("GitHub API error listing {$repository}: ".$response->body());

                return [];
            }

            $data = $response->json();

            if (! is_array($data)) {
                return [];
            }

            return array_values(array_filter(
                $data,
                static fn ($entry): bool => is_array($entry) && ($entry['type'] ?? null) === 'dir',
            ));
        } catch (\Throwable $e) {
            report('Error listing skills library: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    protected function githubHeaders(): array
    {
        return empty($this->githubToken)
            ? ['Accept' => 'application/vnd.github.v3+json']
            : ['Accept' => 'application/vnd.github.v3+json', 'Authorization' => 'Bearer '.$this->githubToken];
    }

    /**
     * Fetch plugin extensions from GitHub
     */
    public function fetchPlugins(): Collection
    {
        return $this->fetchFromRepo('plugins');
    }

    /**
     * Fetch extensions from a specific category directory in the catalog repo.
     *
     * The repository layout is one directory per category:
     * connectors/, skills/, plugins/ — each holding one file per extension.
     */
    protected function fetchFromRepo(string $category): Collection
    {
        $url = "https://api.github.com/repos/{$this->marketplaceRepo}/contents/{$category}";

        if (! empty($this->githubToken)) {
            $headers = [
                'Accept' => 'application/vnd.github.v3+json',
                'Authorization' => 'Bearer '.$this->githubToken,
            ];
        } else {
            $headers = ['Accept' => 'application/vnd.github.v3+json'];
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->get($url);

            if (! $response->successful()) {
                report("GitHub API error fetching {$category}: ".$response->body());

                return collect();
            }

            $data = $response->json();

            if (! is_array($data)) {
                return collect();
            }

            return collect($data)
                ->filter(fn ($item): bool => is_array($item) && ($item['type'] ?? null) === 'file')
                ->map(function ($item) use ($category) {
                    return [
                        'type' => $category,
                        'name' => $item['name'],
                        'description' => $item['description'] ?? '',
                        'file_path' => $item['path'],
                        'download_url' => $item['download_url'] ?? null,
                        'sha' => $item['sha'],
                        'last_updated' => time(),
                        'tags' => [],
                        'rating' => 0,
                        'install_count' => 0,
                    ];
                });

        } catch (\Throwable $e) {
            report('Error fetching extensions: '.$e->getMessage());

            return collect();
        }
    }

    /**
     * Install an extension from GitHub
     *
     * @param  array  $extension  Extension data from fetchAll()
     * @param  string  $userId  User ID to install for
     * @return array Result of installation
     */
    public function installExtension(array $extension, string $userId): array
    {
        $type = $extension['type'] ?? 'unknown';
        $downloadUrl = $extension['download_url'] ?? null;

        if (empty($downloadUrl)) {
            return [
                'success' => false,
                'message' => 'No download URL available for this extension',
            ];
        }

        try {
            // Download the extension file
            $response = Http::withHeaders([
                'Accept' => 'application/vnd.github.v3+raw',
            ])
                ->timeout(60)
                ->get($downloadUrl);

            if (! $response->successful()) {
                throw new \Exception('Failed to download extension: HTTP '.$response->status());
            }

            $content = $response->body();

            // Save to user's workspace extensions directory
            $filename = Str::slug($extension['name']).'.'.$this->getExtensionType($type);
            $directory = "users/{$userId}/extensions/{$type}s";

            Storage::put("$directory/{$filename}", $content);

            // Record installation in database — the slug is the identity
            // the desktop and admin surface key on, not the display name.
            $installation = ExtensionInstallation::create([
                'user_id' => $userId,
                'extension_name' => (string) ($extension['slug'] ?? Str::slug($extension['name'])),
                'extension_type' => $type,
                'version' => '1.0.0',
                'source' => $downloadUrl,
                'installed_at' => now(),
            ]);

            // Send notification for immediate desktop sync
            broadcast([
                'event' => 'extension-installed',
                'data' => [
                    'installation_id' => $installation->id,
                    'extension_name' => $extension['name'],
                    'type' => $type,
                    'timestamp' => now()->toIso8601String(),
                ],
            ])->toOthers();

            return [
                'success' => true,
                'message' => "{$extension['name']} installed successfully",
                'installation_id' => $installation->id,
                'file_path' => "$directory/{$filename}",
            ];

        } catch (\Throwable $e) {
            report('Extension installation failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Installation failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get file extension based on type
     */
    protected function getExtensionType(string $type): string
    {
        return match ($type) {
            'connector' => 'connector.js',
            'skill' => '.opencode/skills/',
            'plugin' => 'plugin.ts',
            default => 'js'
        };
    }

    /**
     * Uninstall an extension
     */
    public function uninstallExtension(int $installationId): bool
    {
        $installation = ExtensionInstallation::find($installationId);

        if (! $installation) {
            return false;
        }

        // Delete file from storage
        $filepath = "users/{$installation->user_id}/extensions/{$installation->extension_type}s/"
                   .Str::slug($installation->extension_name).'.'.$this->getExtensionType($installation->extension_type);

        if (Storage::exists($filepath)) {
            Storage::delete($filepath);
        }

        // Remove from database
        return $installation->delete();
    }

    /**
     * Check if extension is already installed for user
     */
    public function isInstalled(string $extensionName, string $type, int $userId): bool
    {
        return ExtensionInstallation::where('user_id', $userId)
            ->where('extension_name', $extensionName)
            ->where('extension_type', $type)
            ->exists();
    }

    /**
     * Search extensions by keyword
     */
    public function search(string $query, ?string $type = null): Collection
    {
        $allExtensions = $this->fetchAll();

        return $allExtensions->filter(function ($extension) use ($query, $type) {
            if ($type && $extension['type'] !== $type) {
                return false;
            }

            $lowerQuery = mb_strtolower($query);

            return str_contains(mb_strtolower($extension['name']), $lowerQuery)
                || str_contains(mb_strtolower($extension['description']), $lowerQuery);
        })->values();
    }

    /**
     * Cache extensions locally for performance
     */
    public function cacheExtensions(): void
    {
        $extensions = $this->fetchAll();
        cache()->put('marketplace_extensions', $extensions, 3600); // Cache for 1 hour
    }

    /**
     * Get cached extensions.
     *
     * A cache entry written by an older build can unserialize into something
     * that is not a collection at all (PHP reports __PHP_Incomplete_Class).
     * That is a corrupt entry, not a catalogue: drop it and let the caller
     * refill, instead of failing the page with a type error.
     */
    public function getCachedExtensions(): Collection
    {
        $cached = cache()->get('marketplace_extensions', collect());

        if ($cached instanceof Collection) {
            return $cached;
        }

        if (is_array($cached)) {
            return collect($cached)
                ->filter(fn ($item): bool => is_array($item))
                ->values();
        }

        cache()->forget('marketplace_extensions');

        return collect();
    }

    /**
     * The catalogue a public page may show: the cached rows, refilled at most
     * once every ten minutes so a visitor never waits on GitHub twice and an
     * outage cannot turn into a request storm.
     *
     * GitHub is an enrichment, never the source of truth: when the cache is
     * cold and the fetch fails or is rate-limited, the bundled marketplace
     * (resources/marketplace) is returned instead of an empty list. A store
     * that renders "0 items" because one third-party API was unreachable is
     * the failure this fallback exists to prevent.
     */
    public function publicCatalogue(): Collection
    {
        $cached = $this->getCachedExtensions();

        if ($cached->isNotEmpty()) {
            return $cached;
        }

        if (! cache()->has('marketplace_catalogue_checked')) {
            try {
                $this->cacheExtensions();
            } catch (\Throwable $e) {
                report('Marketplace catalogue unavailable: '.$e->getMessage());
            }

            cache()->put('marketplace_catalogue_checked', true, 600);

            $cached = $this->getCachedExtensions();

            if ($cached->isNotEmpty()) {
                return $cached;
            }
        }

        return $this->bundledAsCatalogue();
    }

    /**
     * The bundled catalogue in the shape this service's GitHub rows use, so
     * every consumer reads one list whether or not the network is reachable.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function bundledAsCatalogue(): Collection
    {
        $items = app(\App\Services\MarketplaceCatalogService::class)->catalog()['items'] ?? [];

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['slug'] ?? null))
            ->map(fn (array $item): array => [
                'type' => (string) ($item['type'] ?? 'skill'),
                'name' => (string) ($item['name'] ?? $item['slug']),
                'description' => (string) ($item['description'] ?? ''),
                'file_path' => (string) ($item['slug'] ?? ''),
                'download_url' => $item['source'] ?? ($item['url'] ?? null),
                'sha' => '',
                'last_updated' => time(),
                'tags' => (array) ($item['tags'] ?? []),
                'rating' => 0,
                'install_count' => 0,
            ])
            ->values();
    }
}
