<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\Organization;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Builder;

/**
 * What an organization's plan lets its members use, provider by provider.
 *
 * This is the provider analogue of {@see ProxyEntitlementService} and the
 * single source for the `providerEntitlements` block the desktop reads from
 * `GET /v1/me/desktop-config`. The operator controls three things, all from
 * the admin panel:
 *
 * - whether the AI gateway is on at all (`gateway_enabled`);
 * - which providers are available (`is_active` on each provider, plus the
 *   `plan_ai_provider` assignment that narrows a provider to specific plans —
 *   a provider with no plan rows serves every plan);
 * - whether members may bring their own API keys (`byok`), which is the
 *   organization's `byok_killed` switch combined with the plan's
 *   `custom_providers` feature.
 *
 * The gateway already enforces the provider and model gates when it builds a
 * catalog; this payload exists so the desktop can *show* the same decision —
 * which providers a member has, and whether the "add your own key" surface
 * should be visible at all.
 */
class ProviderEntitlementService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function forOrganization(Organization $organization): array
    {
        $plan = $organization->currentPlan();

        return $this->payload($organization, $plan);
    }

    /**
     * The fallback the desktop uses when it is not signed in. No organization
     * means no managed providers; the member's own keys are always available
     * locally, so `byok` is true.
     *
     * @return array<string, mixed>
     */
    public function localFallback(): array
    {
        return [
            'enabled' => false,
            'byok' => true,
            'plan' => 'local',
            'providers' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Organization $organization, ?Plan $plan): array
    {
        $enabled = $this->settings->boolean('gateway_enabled', true) && $plan instanceof Plan;

        return [
            'enabled' => $enabled,
            'byok' => $this->byokAllowed($organization, $plan),
            'plan' => $plan?->slug,
            'providers' => $enabled ? $this->providersForPlan($organization, $plan) : [],
        ];
    }

    /**
     * BYOK is allowed when the plan does not deny `custom_providers` and the
     * organization's kill switch is off. The kill switch outranks the plan
     * default, but a plan that explicitly sets `custom_providers` to true is
     * the documented per-plan override.
     */
    private function byokAllowed(Organization $organization, ?Plan $plan): bool
    {
        if (! $plan instanceof Plan) {
            return false;
        }

        // The platform-wide switch is checked first, exactly as
        // MeController::customProvidersAllowed does, and it is off by default:
        // the desktop offers the platform's own models and nobody else's, so a
        // member cannot spend on a key the operator did not approve. Turning it
        // on in the provider console is what re-opens own-keys.
        if (! $this->settings->boolean('byok_enabled', false)) {
            return false;
        }

        $features = is_array($plan->features) ? $plan->features : [];
        $explicit = array_key_exists(Plan::FEATURE_CUSTOM_PROVIDERS, $features);
        $planAllows = ! $explicit || (bool) $features[Plan::FEATURE_CUSTOM_PROVIDERS];

        if (! $planAllows) {
            return false;
        }

        $orgKilled = (bool) ($organization->settings['byok_killed'] ?? false);

        return ! $orgKilled || $explicit;
    }

    /**
     * Every provider a plan may use: active, visible to the platform or this
     * organization, and either assigned to the plan or assigned to no plan at
     * all (a provider with no plan rows serves every plan). Combos are never
     * listed as providers; they are offered through their members.
     *
     * @return list<array<string, mixed>>
     */
    private function providersForPlan(Organization $organization, Plan $plan): array
    {
        // The same rule the gateway applies, read from the same switch: with
        // explicit assignment required, a provider with no plan row reaches no
        // plan, so the desktop is shown the same (empty) truth as the gateway.
        $unassignedServesAll = ! $this->settings->boolean('gateway_require_explicit_assignment', false);

        return AiProvider::query()
            ->where('is_active', true)
            ->where('slug', '!=', ComboModelService::PROVIDER_SLUG)
            ->where(function (Builder $query) use ($organization): void {
                $query->whereNull('organization_id')
                    ->orWhere('organization_id', $organization->getKey());
            })
            ->where(function (Builder $query) use ($plan, $unassignedServesAll): void {
                if ($unassignedServesAll) {
                    $query->whereDoesntHave('plans');
                }

                $assigned = fn (Builder $plans): Builder => $plans->whereKey($plan->getKey());

                if ($unassignedServesAll) {
                    $query->orWhereHas('plans', $assigned);
                } else {
                    $query->whereHas('plans', $assigned);
                }
            })
            ->orderBy('priority')
            ->orderBy('name')
            ->get()
            ->map(fn (AiProvider $provider): array => [
                'slug' => (string) $provider->slug,
                'name' => (string) $provider->name,
                'driver' => (string) $provider->driver,
            ])
            ->values()
            ->all();
    }
}
