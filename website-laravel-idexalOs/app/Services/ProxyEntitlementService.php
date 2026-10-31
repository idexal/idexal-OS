<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\ProxyProvider;
use Illuminate\Database\Eloquent\Builder;

class ProxyEntitlementService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function forOrganization(Organization $organization): array
    {
        $plan = $organization->currentPlan();
        $enabled = $this->settings->boolean('proxy_enabled', true);

        return $this->payload($plan, $enabled);
    }

    /**
     * The desktop uses this when it is not signed in. The app stays useful
     * offline with one static proxy profile and no premium helpers.
     *
     * @return array<string, mixed>
     */
    public function localFallback(): array
    {
        return [
            'enabled' => true,
            'plan' => 'local',
            'features' => [
                'proxy' => true,
                'residential' => true,
                'rotation' => false,
                'fingerprint' => false,
                'prewarm' => true,
            ],
            'limits' => [
                'profiles' => 1,
                'bandwidthGb' => 5,
            ],
            'providers' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(?Plan $plan, bool $globallyEnabled): array
    {
        $hasPlan = $plan instanceof Plan;
        $enabled = $globallyEnabled && $hasPlan && $plan->hasFeature(Plan::FEATURE_PROXY);

        return [
            'enabled' => $enabled,
            'plan' => $plan?->slug,
            'features' => [
                'proxy' => $enabled,
                'residential' => $enabled && $plan->hasFeature(Plan::FEATURE_PROXY_RESIDENTIAL),
                'rotation' => $enabled && $plan->hasFeature(Plan::FEATURE_PROXY_ROTATION),
                'fingerprint' => $enabled && $plan->hasFeature(Plan::FEATURE_PROXY_FINGERPRINT),
                'prewarm' => $enabled
                    && $this->settings->boolean('browser_prewarm_enabled', true)
                    && $plan->hasFeature(Plan::FEATURE_BROWSER_PREWARM),
            ],
            'limits' => [
                'profiles' => $hasPlan ? $this->nullableLimit($plan->limit(Plan::LIMIT_PROXY_PROFILES)) : 1,
                'bandwidthGb' => $hasPlan ? $this->nullableLimit($plan->limit(Plan::LIMIT_PROXY_BANDWIDTH_GB)) : 5,
            ],
            'providers' => $enabled ? $this->providersForPlan($plan) : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function providersForPlan(Plan $plan): array
    {
        return ProxyProvider::query()
            ->available()
            ->where(function (Builder $query) use ($plan): void {
                $query->whereDoesntHave('plans')
                    ->orWhereHas('plans', fn (Builder $plans): Builder => $plans->whereKey($plan->getKey()));
            })
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->map(fn (ProxyProvider $provider): array => $provider->toPublicPayload())
            ->all();
    }

    private function nullableLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }
}
