<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Support\PlatformFeatures;
use Illuminate\Http\JsonResponse;

class ApiMetaController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'service' => 'idexal-os',
            'version' => (string) config('idexal.version', '1.0.0'),
            'time' => now()->toIso8601String(),
        ]);
    }

    public function features(): JsonResponse
    {
        // One map for every consumer: the deployment view below and the
        // signed-in view in /v1/org must never disagree about a key.
        return response()->json([
            'features' => PlatformFeatures::map($this->settings),
        ]);
    }

    public function appVersion(): JsonResponse
    {
        $latestSetting = trim((string) $this->settings->get('desktop_latest_version', '0.0.0')) ?: '0.0.0';
        $minimum = trim((string) $this->settings->get('desktop_min_version', '0.0.0')) ?: '0.0.0';
        $published = $this->publishedDesktopVersions();
        $latest = $published[0] ?? $latestSetting;
        if (version_compare($latestSetting, $latest, '>')) {
            $latest = $latestSetting;
        }

        return response()->json([
            'latestAppVersion' => $latest,
            'minAppVersion' => $minimum,
            'publishedDesktopVersions' => $published !== [] ? $published : [$latest],
            'webUrl' => url('/'),
        ]);
    }

    /**
     * The release center manifest is the source of truth for shipped desktop
     * builds. Publishing an installer therefore updates the version gate
     * without a second manual setting.
     *
     * @return list<string> newest first
     */
    private function publishedDesktopVersions(): array
    {
        $path = public_path('desktop/releases.json');
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return [];
        }
        $versions = [];
        foreach ($decoded['releases'] ?? [] as $release) {
            $version = is_array($release) ? trim((string) ($release['version'] ?? '')) : '';
            if (preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                $versions[$version] = true;
            }
        }
        $versions = array_keys($versions);
        usort($versions, 'version_compare');

        return array_reverse($versions);
    }
}
