<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Services\ComboModelService;
use App\Support\DesktopSync;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPlanController extends Controller
{
    /**
     * @var list<string>
     */
    private const FEATURES = [
        'api_access',
        'team_members',
        'webhooks',
        'priority_support',
        'sso',
        Plan::FEATURE_PROXY,
        Plan::FEATURE_PROXY_RESIDENTIAL,
        Plan::FEATURE_PROXY_ROTATION,
        Plan::FEATURE_PROXY_FINGERPRINT,
        Plan::FEATURE_BROWSER_PREWARM,
        Plan::FEATURE_CUSTOM_PROVIDERS,
        Plan::FEATURE_MULTIPLE_WORKSPACES,
        Plan::FEATURE_MANAGE_EXTENSIONS,
        Plan::FEATURE_AGENT_CONTROL,
        Plan::FEATURE_AGENT_SETTINGS,
        Plan::FEATURE_AGENT_PROXY,
        Plan::FEATURE_AGENT_EXTENSIONS,
        Plan::FEATURE_AGENT_BROWSER,
        Plan::FEATURE_AGENT_PROVIDERS,
        Plan::FEATURE_DESKTOP_ACCESS,
    ];

    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => Plan::query()->withCount('subscriptions')->orderBy('sort_order')->orderBy('price_monthly')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.form', [
            'plan' => new Plan([
                'currency' => (string) config('idexal.billing.currency', 'USD'),
                'is_public' => true,
                'is_active' => true,
            ]),
            'features' => self::FEATURES,
            'aiProviders' => $this->aiProviders(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = Plan::query()->create($this->payload($request));
        $this->syncEntitlements($request, $plan);

        AuditLog::record('admin.plan.created', null, $plan, $plan->name);
        DesktopSync::announce('plan_created', ['plan' => $plan->slug]);

        return redirect()->route('admin.plans.index')->with('status', __('ui.common.saved'));
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.form', [
            'plan' => $plan,
            'features' => self::FEATURES,
            'aiProviders' => $this->aiProviders(),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->forceFill($this->payload($request, $plan))->save();
        $this->syncEntitlements($request, $plan);
        AuditLog::record('admin.plan.updated', null, $plan, $plan->name);
        DesktopSync::announce('plan_updated', ['plan' => $plan->slug]);

        return redirect()->route('admin.plans.index')->with('status', __('ui.common.saved'));
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->withErrors(['plan' => __('ui.admin.plans.in_use')]);
        }

        $plan->delete();
        AuditLog::record('admin.plan.deleted', null, $plan, $plan->name);
        DesktopSync::announce('plan_deleted', ['plan' => $plan->slug]);

        return redirect()->route('admin.plans.index')->with('status', __('ui.common.deleted'));
    }

    /**
     * The providers a plan can entitle: the real provider connections only. A
     * combo is offered through its members, so its system connection and the
     * hybrid models on it never appear here.
     *
     * @return Collection<int, AiProvider>
     */
    private function aiProviders(): Collection
    {
        return AiProvider::query()
            ->where('slug', '!=', ComboModelService::PROVIDER_SLUG)
            ->with(['models' => fn ($query) => $query
                ->whereNull('hybrid_config')
                ->orderBy('sort_order')
                ->orderBy('display_name')])
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->orderBy('name')
            ->get();
    }

    /**
     * An empty selection offers every active provider (and every active model
     * of those providers) to the plan. Requests without the markers (API
     * clients, unrelated edits) leave the existing assignment untouched.
     */
    private function syncEntitlements(Request $request, Plan $plan): void
    {
        if ($request->boolean('ai_providers_present') || $request->has('ai_provider_ids')) {
            $validated = $request->validate([
                'ai_provider_ids' => ['nullable', 'array'],
                'ai_provider_ids.*' => ['string', Rule::exists(AiProvider::class, 'id')],
            ]);

            $plan->aiProviders()->sync($this->entitlableProviderIds($validated['ai_provider_ids'] ?? []));
        }

        if ($request->boolean('ai_models_present') || $request->has('ai_model_ids')) {
            $validated = $request->validate([
                'ai_model_ids' => ['nullable', 'array'],
                'ai_model_ids.*' => ['string', Rule::exists(AiModel::class, 'id')],
            ]);

            $plan->aiModels()->sync($this->entitlableModelIds($validated['ai_model_ids'] ?? []));
        }
    }

    /**
     * A combo is never entitled directly: it is offered while one of its
     * members is, so a combo id posted here is dropped instead of stored as a
     * stale copy of the members' plans.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function entitlableModelIds(array $ids): array
    {
        return AiModel::query()
            ->whereIn('id', array_values(array_map('strval', $ids)))
            ->whereNull('hybrid_config')
            ->whereHas('provider', fn ($query) => $query->where('slug', '!=', ComboModelService::PROVIDER_SLUG))
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function entitlableProviderIds(array $ids): array
    {
        return AiProvider::query()
            ->whereIn('id', array_values(array_map('strval', $ids)))
            ->where('slug', '!=', ComboModelService::PROVIDER_SLUG)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, ?Plan $plan = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique(Plan::class)->ignore($plan?->getKey())],
            'description' => ['nullable', 'string', 'max:500'],
            'price_monthly' => ['required', 'numeric', 'min:0', 'max:100000'],
            'price_yearly' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'currency' => ['required', 'string', 'size:3'],
            'limits.requests_per_month' => ['nullable', 'integer', 'min:0'],
            'limits.tokens_per_month' => ['nullable', 'integer', 'min:0'],
            'limits.seats' => ['nullable', 'integer', 'min:0'],
            'limits.api_keys' => ['nullable', 'integer', 'min:0'],
            'limits.proxy_profiles' => ['nullable', 'integer', 'min:0'],
            'limits.proxy_bandwidth_gb' => ['nullable', 'integer', 'min:0'],
            'limits.monthly_cost_usd' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'stripe_price_monthly_id' => ['nullable', 'string', 'max:190'],
            'stripe_price_yearly_id' => ['nullable', 'string', 'max:190'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $limits = [];
        foreach ([
            Plan::LIMIT_REQUESTS,
            Plan::LIMIT_TOKENS,
            Plan::LIMIT_SEATS,
            Plan::LIMIT_API_KEYS,
            Plan::LIMIT_PROXY_PROFILES,
            Plan::LIMIT_PROXY_BANDWIDTH_GB,
        ] as $key) {
            $value = data_get($validated, 'limits.'.$key);
            $limits[$key] = $value === null || $value === '' ? null : (int) $value;
        }

        // A spend allowance is money, not a count, so it keeps its decimals.
        $costLimit = data_get($validated, 'limits.'.Plan::LIMIT_MONTHLY_COST_USD);
        $limits[Plan::LIMIT_MONTHLY_COST_USD] = $costLimit === null || $costLimit === ''
            ? null
            : round((float) $costLimit, 2);

        $features = [];
        foreach (self::FEATURES as $feature) {
            $features[$feature] = $request->boolean('features.'.$feature);
        }

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'price_monthly' => $validated['price_monthly'],
            'price_yearly' => $validated['price_yearly'],
            'currency' => strtoupper($validated['currency']),
            'limits' => $limits,
            'features' => $features,
            'stripe_price_monthly_id' => $validated['stripe_price_monthly_id'] ?? null,
            'stripe_price_yearly_id' => $validated['stripe_price_yearly_id'] ?? null,
            'is_public' => $request->boolean('is_public'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }
}
