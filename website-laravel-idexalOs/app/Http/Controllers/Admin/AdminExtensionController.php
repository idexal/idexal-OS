<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExtensionInstallation;
use App\Services\MarketplaceCatalogService;
use App\Support\DesktopSync;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The extension marketplace: what the platform offers, what this operator has
 * installed, and the two buttons that change it.
 *
 * The catalog itself is the service's business. This screen only decides what to
 * show and how to say it, so every message an operator reads comes from the
 * language table rather than from the service's internal strings.
 */
class AdminExtensionController extends Controller
{
    /** The filters the tabs offer, in display order. */
    private const TABS = ['all', 'connector', 'skill', 'plugin'];

    public function __construct(
        private readonly MarketplaceCatalogService $catalog,
    ) {}

    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $type = (string) $request->query('tab', 'all');

        if (! in_array($type, self::TABS, true)) {
            $type = 'all';
        }

        $catalog = collect($this->catalog->catalog()['items']);

        $items = $catalog
            ->when($type !== 'all', fn (Collection $all): Collection => $all->where('type', $type)->values())
            ->when($query !== '', fn (Collection $matching): Collection => $matching
                ->filter(fn (array $entry): bool => Str::contains($this->haystack($entry), Str::lower($query)))
                ->values());

        $counts = ['all' => $catalog->count()];

        foreach (self::TABS as $key) {
            if ($key !== 'all') {
                $counts[$key] = $catalog->where('type', $key)->count();
            }
        }

        return view('admin.extensions', [
            'items' => $items,
            'counts' => $counts,
            'tabs' => self::TABS,
            'activeTab' => $type,
            'searchQuery' => $query,
            'installed' => $this->installedExtensions(),
        ]);
    }

    public function search(Request $request): RedirectResponse
    {
        return redirect()->route('admin.extensions.index', [
            'q' => (string) $request->input('q', ''),
            'tab' => (string) $request->input('type', 'all'),
        ]);
    }

    public function install(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'extension_slug' => ['required', 'string', 'max:190'],
        ]);

        $slug = (string) $validated['extension_slug'];
        $entry = $this->catalog->item($slug);

        // The catalog is the authority on what can be installed, so a missing
        // entry is refused before anything is written.
        if ($entry === null) {
            return back()->withErrors(['message' => __('ui.admin.extensions.not_found')]);
        }

        try {
            $result = $this->catalog->install($slug, (string) auth()->id());
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['message' => __('ui.admin.extensions.install_failed')]);
        }

        if (! ($result['success'] ?? false)) {
            return back()->withErrors(['message' => __('ui.admin.extensions.not_found')]);
        }

        DesktopSync::announce('extension_installed', ['extension' => $slug]);

        return back()->with('status', __('ui.admin.extensions.installed', ['name' => (string) $entry['name']]));
    }

    public function uninstall(int $installationId): RedirectResponse
    {
        try {
            $removed = $this->catalog->uninstall($installationId, (string) auth()->id());
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['message' => __('ui.admin.extensions.uninstall_failed')]);
        }

        if (! $removed) {
            return back()->withErrors(['message' => __('ui.admin.extensions.installation_missing')]);
        }

        DesktopSync::announce('extension_uninstalled', ['installation' => $installationId]);

        return back()->with('status', __('ui.admin.extensions.uninstalled'));
    }

    public function refresh(): RedirectResponse
    {
        Cache::forget('marketplace.catalog');
        DesktopSync::announce('catalog_refreshed');

        return back()->with('status', (bool) config('idexal.marketplace.remote')
            ? __('ui.admin.extensions.refreshed_remote')
            : __('ui.admin.extensions.refreshed_bundled'));
    }

    /**
     * The extensions installed by this operator, keyed by catalog slug so each
     * card knows whether to offer Install or Uninstall.
     *
     * @return array<string, array{id: int, type: string, name: string}>
     */
    private function installedExtensions(): array
    {
        $user = Auth::user();

        if ($user === null) {
            return [];
        }

        $installed = [];

        foreach (ExtensionInstallation::query()->where('user_id', $user->getKey())->get() as $row) {
            $slug = (string) $row->extension_name;
            $entry = $this->catalog->item($slug);

            $installed[$slug] = [
                'id' => (int) $row->getKey(),
                'type' => (string) $row->extension_type,
                'name' => (string) ($entry['name'] ?? $slug),
            ];
        }

        return $installed;
    }

    /**
     * Everything a search term is matched against, lowercased once.
     *
     * @param  array<string, mixed>  $entry
     */
    private function haystack(array $entry): string
    {
        return Str::lower(implode(' ', [
            (string) ($entry['name'] ?? ''),
            (string) ($entry['slug'] ?? ''),
            (string) ($entry['description'] ?? ''),
            implode(' ', (array) ($entry['tags'] ?? [])),
        ]));
    }
}
