<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\ProviderApiKey;
use App\Services\ProviderKeyRotationService;
use App\Support\DesktopSync;
use App\Support\OutboundHttp;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Manage the extra API keys a provider can hold, and the order the gateway
 * tries them in.
 */
class AdminProviderKeyManagementController extends Controller
{
    public function __construct(
        private readonly ProviderKeyRotationService $keyRotation
    ) {}

    public function editKeys(AiProvider $aiProvider): View
    {
        return view('admin.providers.keys-manage', [
            'provider' => $aiProvider,
            'keys' => $this->keyRotation->allKeys($aiProvider)->map(fn (ProviderApiKey $key): array => $this->keyRotation->toArray($key)),
            'expiringKeys' => $this->keyRotation->getExpiringKeysWithinDays($aiProvider, 7),
        ]);
    }

    public function storeKey(Request $request, AiProvider $aiProvider): RedirectResponse
    {
        $validated = $request->validate([
            'api_key' => ['required', 'string', 'max:1000'],
            'key_name' => ['nullable', 'string', 'max:80'],
            'key_type' => ['required', 'in:primary,backup'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $this->keyRotation->addKey($aiProvider, [
            'api_key' => $validated['api_key'],
            'key_name' => $validated['key_name'] ?: 'key-'.now()->format('YmdHis'),
            'key_type' => $validated['key_type'],
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        DesktopSync::announce('provider_key_added', ['provider' => $aiProvider->slug]);

        return back()->with('status', __('ui.admin.providers.key_added'));
    }

    public function rotateKey(AiProvider $aiProvider): RedirectResponse
    {
        try {
            $next = $this->keyRotation->rotateToNextKey($aiProvider);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['provider' => __('ui.admin.providers.rotate_failed')]);
        }

        DesktopSync::announce('provider_key_rotated', ['provider' => $aiProvider->slug]);

        return back()->with('status', __('ui.admin.providers.rotate_done', ['name' => $next['name']]));
    }

    public function deactivateKey(AiProvider $aiProvider, int $keyId): RedirectResponse
    {
        if (! $this->keyRotation->deactivateKey($aiProvider, $keyId)) {
            return back()->withErrors(['provider' => __('ui.admin.providers.key_missing')]);
        }

        DesktopSync::announce('provider_key_revoked', ['provider' => $aiProvider->slug]);

        return back()->with('status', __('ui.admin.providers.key_revoked'));
    }

    public function testKey(Request $request, AiProvider $aiProvider): RedirectResponse
    {
        $validated = $request->validate([
            'test_api_key' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $response = OutboundHttp::client()
                ->withHeaders($this->probeHeaders($aiProvider, $validated['test_api_key']))
                ->connectTimeout(8)
                ->timeout(20)
                ->get(rtrim($aiProvider->base_url, '/').'/models');
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['test_api_key' => __('ui.admin.providers.test_failed', ['message' => $exception->getMessage()])]);
        }

        if (! $response->successful()) {
            return back()->withErrors(['test_api_key' => __('ui.admin.providers.test_failed', ['message' => 'HTTP '.$response->status()])]);
        }

        return back()->with('status', __('ui.admin.providers.test_ok'));
    }

    public function bulkRotateAll(): RedirectResponse
    {
        $results = $this->keyRotation->autoRotateAllProviders();

        return back()->with('status', __('ui.admin.providers.rotate_all_done', [
            'rotated' => $results['rotated_count'],
            'failed' => $results['failed_count'],
        ]));
    }

    /**
     * @return array<string, string>
     */
    private function probeHeaders(AiProvider $provider, string $apiKey): array
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'idexal-os-admin/1.0',
        ];

        if ($provider->driver === AiProvider::DRIVER_ANTHROPIC) {
            $headers['x-api-key'] = $apiKey;
            $headers['anthropic-version'] = '2023-06-01';
        } else {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        return $headers;
    }
}
