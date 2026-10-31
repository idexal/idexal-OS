<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\ProxyProvider;
use App\Support\DesktopSync;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminProxyProviderController extends Controller
{
    /**
     * @var list<string>
     */
    private const DRIVERS = ['generic', 'webshare', 'oxylabs', 'brightdata', 'smartproxy'];

    /**
     * @var list<string>
     */
    private const PROTOCOLS = ['http', 'https', 'socks5'];

    public function index(): View
    {
        return view('admin.proxy-providers.index', [
            'providers' => ProxyProvider::query()
                ->withCount('plans')
                ->with('plans:id,name,slug')
                ->orderByDesc('is_active')
                ->orderBy('priority')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.proxy-providers.form', [
            'provider' => new ProxyProvider([
                'driver' => 'generic',
                'protocol' => 'http',
                'is_active' => true,
                'is_public' => true,
            ]),
            'drivers' => self::DRIVERS,
            'protocols' => self::PROTOCOLS,
            'plans' => $this->plans(),
            'selectedPlans' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = ProxyProvider::query()->create($this->payload($request));
        $provider->plans()->sync($request->input('plans', []));

        AuditLog::record('admin.proxy_provider.created', null, $provider, $provider->name);
        DesktopSync::announce('proxy_provider_created', ['provider' => $provider->slug]);

        return redirect()->route('admin.proxy-providers.edit', $provider)
            ->with('status', __('ui.admin.proxy_providers.saved'));
    }

    public function edit(ProxyProvider $proxyProvider): View
    {
        return view('admin.proxy-providers.form', [
            'provider' => $proxyProvider,
            'drivers' => self::DRIVERS,
            'protocols' => self::PROTOCOLS,
            'plans' => $this->plans(),
            'selectedPlans' => $proxyProvider->plans()->pluck('plans.id')->all(),
        ]);
    }

    public function update(Request $request, ProxyProvider $proxyProvider): RedirectResponse
    {
        $proxyProvider->forceFill($this->payload($request, $proxyProvider))->save();
        $proxyProvider->plans()->sync($request->input('plans', []));

        AuditLog::record('admin.proxy_provider.updated', null, $proxyProvider, $proxyProvider->name);
        DesktopSync::announce('proxy_provider_updated', ['provider' => $proxyProvider->slug]);

        return redirect()->route('admin.proxy-providers.edit', $proxyProvider)
            ->with('status', __('ui.admin.proxy_providers.saved'));
    }

    public function destroy(ProxyProvider $proxyProvider): RedirectResponse
    {
        $proxyProvider->delete();
        AuditLog::record('admin.proxy_provider.deleted', null, $proxyProvider, $proxyProvider->name);
        DesktopSync::announce('proxy_provider_deleted', ['provider' => $proxyProvider->slug]);

        return redirect()->route('admin.proxy-providers.index')
            ->with('status', __('ui.admin.proxy_providers.deleted'));
    }

    /**
     * @return Collection<int, Plan>
     */
    private function plans(): Collection
    {
        return Plan::query()->orderBy('sort_order')->orderBy('price_monthly')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, ?ProxyProvider $provider = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique(ProxyProvider::class)->ignore($provider?->getKey())],
            'driver' => ['required', Rule::in(self::DRIVERS)],
            'protocol' => ['required', Rule::in(self::PROTOCOLS)],
            'website_url' => ['nullable', 'url', 'max:2048'],
            'docs_url' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string', 'max:2000'],
            'countries' => ['nullable', 'string', 'max:1000'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'plans' => ['nullable', 'array'],
            'plans.*' => ['string', Rule::exists(Plan::class, 'id')],
        ]);

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'driver' => $validated['driver'],
            'protocol' => $validated['protocol'],
            'website_url' => $validated['website_url'] ?? null,
            'docs_url' => $validated['docs_url'] ?? null,
            'description' => $validated['description'] ?? null,
            'countries' => $this->countries($validated['countries'] ?? ''),
            'priority' => (int) ($validated['priority'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            'is_public' => $request->boolean('is_public'),
        ];
    }

    /**
     * @return list<string>
     */
    private function countries(string $value): array
    {
        return collect(preg_split('/[\s,;]+/', strtolower($value)) ?: [])
            ->map(fn (string $country): string => trim($country))
            ->filter()
            ->unique()
            ->take(100)
            ->values()
            ->all();
    }
}
