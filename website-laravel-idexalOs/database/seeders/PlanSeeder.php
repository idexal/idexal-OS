<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'free',
                'name' => 'Free',
                'description' => 'Start building with the idexal Os API and a personal workspace.',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'limits' => [
                    'requests_per_month' => 1000,
                    'tokens_per_month' => 250000,
                    'seats' => 1,
                    'api_keys' => 2,
                    'proxy_profiles' => 1,
                    'proxy_bandwidth_gb' => 5,
                ],
                'features' => [
                    'api_access' => true,
                    'team_members' => false,
                    'webhooks' => false,
                    'priority_support' => false,
                    'sso' => false,
                    'proxy' => true,
                    'proxy_residential' => true,
                    'proxy_rotation' => false,
                    'proxy_fingerprint' => false,
                    'browser_prewarm' => false,
                    'custom_providers' => true,
                    'multiple_workspaces' => true,
                    'manage_extensions' => true,
                ],
                'sort_order' => 0,
            ],
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'description' => 'For individuals shipping their first AI product.',
                'price_monthly' => 19,
                'price_yearly' => 190,
                'limits' => [
                    'requests_per_month' => 25000,
                    'tokens_per_month' => 5000000,
                    'seats' => 5,
                    'api_keys' => 10,
                    'proxy_profiles' => 10,
                    'proxy_bandwidth_gb' => 100,
                ],
                'features' => [
                    'api_access' => true,
                    'team_members' => true,
                    'webhooks' => true,
                    'priority_support' => false,
                    'sso' => false,
                    'proxy' => true,
                    'proxy_residential' => true,
                    'proxy_rotation' => true,
                    'proxy_fingerprint' => false,
                    'browser_prewarm' => true,
                    'custom_providers' => true,
                    'multiple_workspaces' => true,
                    'manage_extensions' => true,
                ],
                'sort_order' => 1,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'description' => 'For product teams that need more room to scale.',
                'price_monthly' => 49,
                'price_yearly' => 490,
                'limits' => [
                    'requests_per_month' => 100000,
                    'tokens_per_month' => 25000000,
                    'seats' => 20,
                    'api_keys' => 25,
                    'proxy_profiles' => 50,
                    'proxy_bandwidth_gb' => 500,
                ],
                'features' => [
                    'api_access' => true,
                    'team_members' => true,
                    'webhooks' => true,
                    'priority_support' => true,
                    'sso' => false,
                    'proxy' => true,
                    'proxy_residential' => true,
                    'proxy_rotation' => true,
                    'proxy_fingerprint' => true,
                    'browser_prewarm' => true,
                    'custom_providers' => true,
                    'multiple_workspaces' => true,
                    'manage_extensions' => true,
                ],
                'sort_order' => 2,
            ],
            [
                'slug' => 'business',
                'name' => 'Business',
                'description' => 'For organizations that need governance, volume, and support.',
                'price_monthly' => 199,
                'price_yearly' => 1990,
                'limits' => [
                    'requests_per_month' => 500000,
                    'tokens_per_month' => 150000000,
                    'seats' => 100,
                    'api_keys' => 100,
                    'proxy_profiles' => 200,
                    'proxy_bandwidth_gb' => 2000,
                ],
                'features' => [
                    'api_access' => true,
                    'team_members' => true,
                    'webhooks' => true,
                    'priority_support' => true,
                    'sso' => true,
                    'proxy' => true,
                    'proxy_residential' => true,
                    'proxy_rotation' => true,
                    'proxy_fingerprint' => true,
                    'browser_prewarm' => true,
                    'custom_providers' => true,
                    'multiple_workspaces' => true,
                    'manage_extensions' => true,
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            // Agent desktop control is a core capability for every plan; the
            // organization's agent-permission rules still gate each family,
            // and an administrator can revoke any family per plan. Plans
            // differentiate on proxies, providers, limits and support instead.
            $agentControl = true;
            $plan['features'] = [
                ...$plan['features'],
                'agent_control' => $agentControl,
                'agent_settings' => $agentControl,
                'agent_proxy' => $agentControl,
                'agent_extensions' => $agentControl,
                'agent_browser' => $agentControl,
                'agent_providers' => $agentControl,
            ];

            Plan::query()->firstOrCreate(
                ['slug' => $plan['slug']],
                $plan + ['currency' => config('idexal.billing.currency', 'USD'), 'is_public' => true, 'is_active' => true],
            );
        }
    }
}
