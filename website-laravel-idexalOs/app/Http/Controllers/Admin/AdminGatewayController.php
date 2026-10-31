<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\GatewayRequest;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\GatewayService;
use App\Support\GatewayFeature;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminGatewayController extends Controller
{
    public function __construct(private readonly GatewayService $gateway, private readonly AdminUsageRequestController $usageRequests) {}

    public function index(Request $request): View
    {
        $status = $request->integer('status');
        $model = (string) $request->query('model', '');
        $organization = (string) $request->query('organization', '');
        $feature = (string) $request->query('feature', '');
        $deliveryOrganization = (string) $request->query('delivery', '');

        if (! in_array($feature, GatewayFeature::keys(), true)) {
            $feature = '';
        }

        $requests = GatewayRequest::query()
            ->with(['organization:id,name,slug', 'user:id,name,email', 'aiModel:id,display_name,model_id'])
            ->when($status > 0, fn ($query) => $query->where('status_code', $status))
            ->when($model !== '', fn ($query) => $query->where('model', 'like', '%'.$model.'%'))
            ->when($organization !== '', fn ($query) => $query->where('organization_id', $organization))
            ->when($feature !== '', fn ($query) => $query->whereJsonContains('meta->features', $feature))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        $month = GatewayRequest::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COUNT(*) as requests, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(cost), 0) as cost, COALESCE(SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END), 0) as errors')
            ->first();

        return view('admin.gateway', [
            'requests' => $requests,
            'series' => $this->series(),
            'stats' => [
                'monthRequests' => (int) ($month->requests ?? 0),
                'monthTokens' => (int) ($month->tokens ?? 0),
                'monthCost' => (float) ($month->cost ?? 0),
                'errorRate' => (int) ($month->requests ?? 0) > 0
                    ? round(((int) $month->errors / (int) $month->requests) * 100, 2)
                    : 0.0,
                'todayRequests' => GatewayRequest::query()->whereDate('created_at', today())->count(),
            ],
            'topModels' => GatewayRequest::query()
                ->where('created_at', '>=', now()->startOfMonth())
                ->selectRaw('model, COUNT(*) as requests, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(cost), 0) as cost')
                ->groupBy('model')
                ->orderByDesc('requests')
                ->limit(8)
                ->get(),
            'topOrganizations' => GatewayRequest::query()
                ->where('created_at', '>=', now()->startOfMonth())
                ->selectRaw('organization_id, COUNT(*) as requests, COALESCE(SUM(total_tokens), 0) as tokens, COALESCE(SUM(cost), 0) as cost')
                ->with('organization:id,name,slug')
                ->groupBy('organization_id')
                ->orderByDesc('requests')
                ->limit(8)
                ->get(),
            'filterStatus' => $status,
            'filterModel' => $model,
            'filterOrganization' => $organization,
            'filterFeature' => $feature,
            'features' => GatewayFeature::labels(),
            'featureUsage' => $this->featureUsage(),
            'errorCount' => GatewayRequest::query()->where('status_code', '>=', 400)->where('created_at', '>=', now()->subDays(7))->count(),
            ...$this->delivery($deliveryOrganization),
            'usageRequests' => $this->usageRequests->pending(),
        ]);
    }

    /**
     * Why the desktop shows (or does not show) models, for one organization.
     *
     * The desktop is served exactly one gateway provider whose models come from
     * `desktopGatewayModels()`. When that list is empty a member sees no model at
     * all, and until now the operator had no way to see which gate dropped what.
     * This mirrors `diagnoseDesktopCatalog()` and names the gate, so "our models
     * do not show up" has an answer on the screen instead of a guess.
     *
     * @return array<string, mixed>
     */
    private function delivery(string $organizationId): array
    {
        $organizations = Organization::query()->orderBy('name')->get(['id', 'name', 'slug']);

        $organization = $organizationId !== ''
            ? $organizations->firstWhere('id', $organizationId)
            : $organizations->first();

        if (! $organization instanceof Organization) {
            return [
                'deliveryOrganizations' => $organizations,
                'deliveryOrganization' => null,
                'deliveryPlan' => null,
                'deliveryRows' => [],
                'deliveryDelivered' => 0,
                'deliveryReasons' => [],
                'deliveryHealth' => [
                    'no_free_plan' => ! Plan::query()->where('slug', 'free')->exists(),
                    'providers_without_credential' => 0,
                    'active_providers' => 0,
                    'desktop_drivers' => 0,
                ],
            ];
        }

        $rows = $this->gateway->diagnoseDesktopCatalog($organization);

        $reasons = [];
        foreach ($rows as $row) {
            if ($row['in_catalog']) {
                continue;
            }

            $reason = (string) ($row['reason'] ?? 'unknown');
            $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
        }

        $activeProviders = AiProvider::query()->where('is_active', true)->get();
        $withoutCredential = $activeProviders->filter(function (AiProvider $provider): bool {
            if (trim((string) $provider->api_key) !== '') {
                return false;
            }

            if ($provider->apiKeys()->usable()->exists()) {
                return false;
            }

            return in_array($provider->headers, [null, []], true);
        })->count();

        return [
            'deliveryOrganizations' => $organizations,
            'deliveryOrganization' => $organization,
            'deliveryPlan' => $organization->currentPlan(),
            'deliverySubscription' => $organization->currentSubscription(),
            'deliveryRows' => $rows,
            'deliveryDelivered' => count(array_filter($rows, fn (array $row): bool => (bool) $row['in_catalog'])),
            'deliveryReasons' => $reasons,
            'deliveryHealth' => [
                'no_free_plan' => ! Plan::query()->where('slug', 'free')->exists(),
                'providers_without_credential' => $withoutCredential,
                'active_providers' => $activeProviders->count(),
                'desktop_drivers' => $activeProviders
                    ->whereIn('driver', [AiProvider::DRIVER_OPENAI, AiProvider::DRIVER_OPENAI_COMPATIBLE])
                    ->count(),
                'reason_labels' => $this->reasonLabels(),
            ],
        ];
    }

    /**
     * The fix each exclusion reason points at, in the operator's words.
     *
     * @return array<string, string>
     */
    private function reasonLabels(): array
    {
        return [
            'model_inactive' => __('ui.admin.gateway.delivery_reason_model_inactive'),
            'provider_missing' => __('ui.admin.gateway.delivery_reason_provider_missing'),
            'provider_inactive' => __('ui.admin.gateway.delivery_reason_provider_inactive'),
            'driver_not_desktop' => __('ui.admin.gateway.delivery_reason_driver'),
            'provider_other_organization' => __('ui.admin.gateway.delivery_reason_other_org'),
            'provider_plan_mismatch' => __('ui.admin.gateway.delivery_reason_provider_plan'),
            'model_plan_mismatch' => __('ui.admin.gateway.delivery_reason_model_plan'),
            'combo_members_unavailable' => __('ui.admin.gateway.delivery_reason_combo_members'),
            'combo_member_hidden' => __('ui.admin.gateway.delivery_reason_combo_hidden'),
            'provider_without_credential' => __('ui.admin.gateway.delivery_reason_credential'),
            'duplicate_model_id' => __('ui.admin.gateway.delivery_reason_duplicate'),
        ];
    }

    /**
     * How often each gateway feature ran this month, so the operator sees which
     * transforms are actually earning their place in the pipeline.
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    private function featureUsage(): array
    {
        $since = now()->startOfMonth();
        $usage = [];

        foreach (GatewayFeature::cases() as $feature) {
            $usage[] = [
                'key' => $feature->value,
                'label' => __($feature->labelKey()),
                'count' => GatewayRequest::query()
                    ->where('created_at', '>=', $since)
                    ->whereJsonContains('meta->features', $feature->value)
                    ->count(),
            ];
        }

        return $usage;
    }

    /**
     * @return array{labels: list<string>, requests: list<int>, tokens: list<int>}
     */
    private function series(): array
    {
        $from = now()->subDays(29)->startOfDay();
        $rows = GatewayRequest::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as requests, COALESCE(SUM(total_tokens), 0) as tokens')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $labels = [];
        $requests = [];
        $tokens = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $from->copy()->addDays($i);
            $row = $rows->get($date->toDateString());
            $labels[] = $date->translatedFormat('d M');
            $requests[] = (int) ($row->requests ?? 0);
            $tokens[] = (int) ($row->tokens ?? 0);
        }

        return ['labels' => $labels, 'requests' => $requests, 'tokens' => $tokens];
    }
}
