<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\GatewayService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminCatalogController extends Controller
{
    public function __construct(private readonly GatewayService $gateway) {}

    public function index(Request $request): View
    {
        $organizations = Organization::query()->orderByDesc('created_at')->get();
        $organization = $organizations->firstWhere('id', (string) $request->query('organization'))
            ?? $organizations->first();

        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $organization instanceof Organization
            ? collect($this->gateway->diagnoseDesktopCatalog($organization))
            : collect();

        return view('admin.catalog', [
            'organizations' => $organizations,
            'organization' => $organization,
            'plan' => $organization instanceof Organization ? $organization->currentPlan() : null,
            'rows' => $rows,
            'visibleCount' => $rows->where('in_catalog', true)->count(),
            'grouped' => $rows->groupBy(fn (array $row): string => (string) ($row['reason'] ?? 'visible')),
        ]);
    }
}
