<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

class PricingController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('public.pricing', [
            'plans' => $plans,
            'yearly' => request()->boolean('yearly'),
            'comparison' => $this->comparison($plans),
            'catalog' => $this->catalog($plans),
        ]);
    }

    /**
     * The full plan matrix, grouped the way a buyer reads it: what it costs,
     * what it limits, what it unlocks, and what it includes. Every cell is
     * derived from the plan row the billing engine charges from, so the page
     * cannot promise something checkout would refuse.
     *
     * @param  Collection<int, Plan>  $plans
     * @return list<array{group: string, label: string, cells: list<array{kind: string, value: string|null}>}>
     */
    private function comparison(Collection $plans): array
    {
        $rows = [];

        $rows[] = $this->row('price', __('ui.pricing.row_price'), $plans, fn (Plan $plan): array => [
            'kind' => 'text',
            'value' => $plan->isFree() ? __('ui.pricing.free') : $plan->formattedPrice('monthly'),
        ]);

        $rows[] = $this->row('price', __('ui.pricing.row_yearly'), $plans, fn (Plan $plan): array => [
            'kind' => 'text',
            'value' => $plan->isFree() ? __('ui.pricing.free') : $plan->formattedPrice('yearly'),
        ]);

        foreach ([
            [Plan::LIMIT_REQUESTS, __('ui.pricing.limit_requests')],
            [Plan::LIMIT_TOKENS, __('ui.pricing.limit_tokens')],
            [Plan::LIMIT_SEATS, __('ui.pricing.limit_seats')],
            [Plan::LIMIT_API_KEYS, __('ui.pricing.limit_keys')],
            [Plan::LIMIT_PROXY_PROFILES, __('ui.pricing.limit_proxy_profiles')],
            [Plan::LIMIT_PROXY_BANDWIDTH_GB, __('ui.pricing.limit_proxy_bandwidth')],
            [Plan::LIMIT_MONTHLY_COST_USD, __('ui.pricing.limit_monthly_cost')],
        ] as [$key, $label]) {
            $rows[] = $this->row('limits', $label, $plans, function (Plan $plan) use ($key): array {
                $limit = $plan->limit($key);

                return [
                    'kind' => 'text',
                    'value' => $limit === null ? __('ui.pricing.unlimited') : compact_number($limit),
                ];
            });
        }

        foreach ([
            ['desktop_access', Plan::FEATURE_DESKTOP_ACCESS],
            ['api_access', 'api_access'],
            ['webhooks', 'webhooks'],
            ['custom_providers', Plan::FEATURE_CUSTOM_PROVIDERS],
            ['multiple_workspaces', Plan::FEATURE_MULTIPLE_WORKSPACES],
            ['manage_extensions', Plan::FEATURE_MANAGE_EXTENSIONS],
            ['agent_control', Plan::FEATURE_AGENT_CONTROL],
            ['agent_browser', Plan::FEATURE_AGENT_BROWSER],
            ['proxy', Plan::FEATURE_PROXY],
            ['proxy_rotation', Plan::FEATURE_PROXY_ROTATION],
            ['proxy_fingerprint', Plan::FEATURE_PROXY_FINGERPRINT],
            ['browser_prewarm', Plan::FEATURE_BROWSER_PREWARM],
            ['sso', 'sso'],
            ['priority_support', 'priority_support'],
        ] as [$key, $feature]) {
            $rows[] = $this->row('features', __('ui.pricing.feature_'.$key), $plans, fn (Plan $plan): array => [
                'kind' => $plan->hasFeature($feature) ? 'yes' : 'no',
                'value' => null,
            ]);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Plan>  $plans
     * @param  callable(Plan): array{kind: string, value: string|null}  $cell
     * @return array{group: string, label: string, cells: list<array{kind: string, value: string|null}>}
     */
    private function row(string $group, string $label, Collection $plans, callable $cell): array
    {
        return [
            'group' => $group,
            'label' => $label,
            'cells' => $plans->map(fn (Plan $plan): array => $cell($plan))->values()->all(),
        ];
    }

    /**
     * Which providers and models each plan can actually reach. A provider
     * assigned to no plan is offered on every plan, which is the gateway's
     * existing rule, so it is reported that way here too.
     *
     * @param  Collection<int, Plan>  $plans
     * @return list<array{plan: string, providers: list<array{name: string, models: int}>, modelCount: int}>
     */
    private function catalog(Collection $plans): array
    {
        $providers = AiProvider::query()
            ->withCount(['models' => fn ($query) => $query
                ->where('is_active', true)
                ->whereNull('hybrid_config')])
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $assignments = [];

        foreach ($providers as $provider) {
            foreach ($provider->plans()->pluck('plans.id')->all() as $planId) {
                $assignments[(string) $planId][] = $provider;
            }
        }

        return $plans->map(function (Plan $plan) use ($providers, $assignments): array {
            $assigned = $assignments[(string) $plan->getKey()] ?? [];
            $available = $assigned === [] ? $providers->all() : $assigned;

            return [
                'plan' => (string) $plan->name,
                'providers' => array_map(fn (AiProvider $provider): array => [
                    'name' => (string) $provider->name,
                    'models' => (int) $provider->models_count,
                ], $available),
                'modelCount' => array_sum(array_map(
                    fn (AiProvider $provider): int => (int) $provider->models_count,
                    $available,
                )),
            ];
        })->values()->all();
    }
}
