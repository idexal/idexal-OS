<?php

namespace App\Services;

use App\Models\ExtensionInstallation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The one-click extension marketplace.
 *
 * The bundled catalog (resources/marketplace/catalog.json) is always
 * available, so Connectors, Skills and Plugins are never an empty list even
 * when GitHub is unreachable. Installations are recorded in the database and
 * served to running desktops through /v1/me/library and
 * /v1/resources/marketplace-capabilities.
 */
class MarketplaceCatalogService
{
    public const MARKETPLACE_ID = 'mkt_idexal_official';

    /** Cache lifetime for the parsed catalog, in seconds. */
    private const CACHE_TTL = 300;

    public function __construct(
        private readonly ExtensionMarketplaceService $github,
    ) {}

    public function catalog(): array
    {
        return Cache::remember('marketplace.catalog', self::CACHE_TTL, function (): array {
            $items = $this->bundledItems();

            return [
                'version' => 1,
                'marketplace' => [
                    'id' => self::MARKETPLACE_ID,
                    'name' => 'idexal Os Marketplace',
                    'description' => 'Curated connectors, skills, and plugins maintained by the idexal Os team.',
                ],
                'items' => array_values(array_merge($items, $this->remoteItems())),
            ];
        });
    }

    /** A single catalog entry by slug, or null. @return array<string, mixed>|null */
    public function item(string $slug): ?array
    {
        foreach ($this->catalog()['items'] as $entry) {
            if (($entry['slug'] ?? null) === $slug) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Install a catalog item for a user. Installing a plugin installs the
     * skills it includes as well, so the one click delivers everything.
     *
     * @return array{success: bool, message: string, installation_id?: int}
     */
    public function install(string $slug, string $userId, ?string $organizationId = null): array
    {
        $entry = $this->item($slug);

        if ($entry === null) {
            return ['success' => false, 'message' => 'Extension not found in the marketplace.'];
        }

        // Installing twice is not an error: the second click confirms what is
        // already there instead of failing on the unique constraint.
        $installed = ExtensionInstallation::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'extension_name' => (string) $entry['slug'],
                'extension_type' => (string) $entry['type'],
            ],
            [
                'organization_id' => $organizationId,
                'version' => (string) ($entry['version'] ?? '1.0.0'),
                'source' => (string) ($entry['source'] ?? ''),
                'installed_at' => now(),
            ],
        );

        foreach ((array) ($entry['includes'] ?? []) as $included) {
            $this->install((string) $included, $userId, $organizationId);
        }

        return [
            'success' => true,
            'message' => (string) $entry['name'].' installed.',
            'installation_id' => (int) $installed->getKey(),
        ];
    }

    public function uninstall(int $installationId, string $userId): bool
    {
        return ExtensionInstallation::query()
            ->where('user_id', $userId)
            ->whereKey($installationId)
            ->delete() > 0;
    }

    /**
     * Extensions installed for the user or shared by their organization.
     *
     * @return Collection<int, ExtensionInstallation>
     */
    public function installedFor(string $userId, ?string $organizationId = null)
    {
        return ExtensionInstallation::query()
            ->where(function ($query) use ($userId, $organizationId): void {
                $query->where('user_id', $userId);

                if ($organizationId !== null) {
                    $query->orWhere('organization_id', $organizationId);
                }
            })
            ->orderBy('installed_at')
            ->get()
            ->unique(fn (ExtensionInstallation $row): string => $row->extension_type.'|'.$row->extension_name);
    }

    /** The desktop inventory revision: changes whenever installations or the catalog change. */
    public function revision(): string
    {
        $lastInstallation = ExtensionInstallation::query()->max('updated_at');

        // The revision has to move when a sync adds skills, or a desktop would
        // keep the inventory it resolved before the catalogue grew.
        $catalogue = md5_file(resource_path('marketplace/catalog.json'))
            .'|'.(is_file(resource_path('marketplace/library.json'))
                ? md5_file(resource_path('marketplace/library.json'))
                : 'none');

        return md5(self::MARKETPLACE_ID.'|'.$catalogue.'|'.($lastInstallation ?? 'none'));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function remoteItem(array $item): array
    {
        // GitHub items are files named "<slug>.md" or "<slug>.json"; the slug
        // is the filename without its extension so it matches the bundled
        // catalog identity.
        $rawName = (string) $item['name'];

        return [
            'slug' => Str::slug(pathinfo($rawName, PATHINFO_FILENAME)),
            'type' => (string) ($item['type'] ?? 'plugin'),
            'name' => (string) (pathinfo($rawName, PATHINFO_FILENAME) ?: $rawName),
            'description' => (string) ($item['description'] ?? ''),
            'version' => '1.0.0',
            'source' => (string) ($item['download_url'] ?? ($item['file_path'] ?? '')),
            'tags' => [],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function githubConnectors(): array
    {
        return $this->github->fetchConnectors()->all();
    }

    /** @return list<array<string, mixed>> */
    private function githubPlugins(): array
    {
        return $this->github->fetchPlugins()->all();
    }

    /**
     * Optional live rows from the public catalog repository.
     *
     * Off by default: the bundled catalog is the single source of truth, so the
     * marketplace is fully installable with no network calls. When enabled, a
     * failure there (network, rate limit, missing repo) must never break the
     * bundled marketplace.
     *
     * @return list<array<string, mixed>>
     */
    private function remoteItems(): array
    {
        if (! (bool) config('idexal.marketplace.remote', false)) {
            return [];
        }

        try {
            return collect([$this->githubConnectors(), $this->github->fetchSkills()->all(), $this->githubPlugins()])
                ->flatten(1)
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['name'] ?? null))
                ->map(fn (array $item): array => $this->remoteItem($item))
                ->reject(fn (array $item): bool => $this->bundledItem((string) $item['slug']) !== null)
                ->unique(fn (array $item): string => (string) $item['slug'])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * The bundled catalogue: the hand-curated entries first, then the skills
     * library generated by scripts/sync-marketplace-skills.php.
     *
     * The library is a separate generated file so a sync can never overwrite a
     * curated description, and the whole catalogue still ships inside the
     * application — the store is never empty because GitHub is unreachable.
     *
     * @return list<array<string, mixed>>
     */
    private function bundledItems(): array
    {
        $curated = $this->readItems(resource_path('marketplace/catalog.json'));
        $library = $this->readItems(resource_path('marketplace/library.json'));

        $seen = [];
        foreach ($curated as $entry) {
            if (isset($entry['slug'])) {
                $seen[(string) $entry['slug']] = true;
            }
        }

        foreach ($library as $entry) {
            $slug = (string) ($entry['slug'] ?? '');

            // A curated entry always wins, including its description.
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;
            $curated[] = $entry;
        }

        return $curated;
    }

    /**
     * The `items` array of one bundled JSON file, or an empty list when the
     * file is missing or unreadable. A broken file must not empty the store.
     *
     * @return list<array<string, mixed>>
     */
    private function readItems(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded['items'] ?? null) ? array_values($decoded['items']) : [];
    }

    /** @return array<string, mixed>|null */
    private function bundledItem(string $slug): ?array
    {
        foreach ($this->bundledItems() as $entry) {
            if (($entry['slug'] ?? null) === $slug) {
                return $entry;
            }
        }

        return null;
    }
}
