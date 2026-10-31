<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class DownloadController extends Controller
{
    public function index(): View
    {
        $manifest = $this->manifest();
        $releases = collect($manifest['releases'] ?? [])
            ->filter(fn (mixed $release): bool => is_array($release)
                && is_string($release['version'] ?? null)
                && is_string($release['platform'] ?? null)
                && is_string($release['file'] ?? null))
            ->sortByDesc(fn (array $release): string => $release['publishedAt'] ?? '')
            ->values();

        $byPlatform = collect(['windows', 'macos', 'linux'])
            ->mapWithKeys(fn (string $platform): array => [
                $platform => $this->latestForPlatform($releases, $platform),
            ]);

        return view('public.download', [
            'latestVersion' => (string) ($manifest['latest'] ?? $releases->first()['version'] ?? ''),
            'byPlatform' => $byPlatform,
            'releases' => $releases,
            'publishedAt' => (string) ($manifest['publishedAt'] ?? ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $path = public_path('desktop/releases.json');

        if (! is_file($path)) {
            return ['releases' => []];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : ['releases' => []];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $releases
     * @return Collection<int, array<string, mixed>>
     */
    private function latestForPlatform(Collection $releases, string $platform): Collection
    {
        return $releases
            ->where('platform', $platform)
            ->groupBy(fn (array $release): string => (string) ($release['arch'] ?? 'x64'))
            ->map(fn (Collection $group): array => $group
                ->sortByDesc(fn (array $release): string => (string) ($release['version'] ?? ''), SORT_NATURAL)
                ->first())
            ->sortKeys()
            ->values();
    }
}
