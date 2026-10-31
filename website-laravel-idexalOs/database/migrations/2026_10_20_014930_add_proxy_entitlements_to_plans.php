<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Give existing installations the proxy entitlements without overwriting
     * values an operator already changed in /admin.
     */
    public function up(): void
    {
        $defaults = [
            'free' => [
                'features' => [
                    Plan::FEATURE_PROXY => true,
                    Plan::FEATURE_PROXY_RESIDENTIAL => true,
                    Plan::FEATURE_PROXY_ROTATION => false,
                    Plan::FEATURE_PROXY_FINGERPRINT => false,
                    Plan::FEATURE_BROWSER_PREWARM => false,
                ],
                'limits' => [
                    Plan::LIMIT_PROXY_PROFILES => 1,
                    Plan::LIMIT_PROXY_BANDWIDTH_GB => 5,
                ],
            ],
            'starter' => [
                'features' => [
                    Plan::FEATURE_PROXY => true,
                    Plan::FEATURE_PROXY_RESIDENTIAL => true,
                    Plan::FEATURE_PROXY_ROTATION => true,
                    Plan::FEATURE_PROXY_FINGERPRINT => false,
                    Plan::FEATURE_BROWSER_PREWARM => true,
                ],
                'limits' => [
                    Plan::LIMIT_PROXY_PROFILES => 10,
                    Plan::LIMIT_PROXY_BANDWIDTH_GB => 100,
                ],
            ],
            'pro' => [
                'features' => [
                    Plan::FEATURE_PROXY => true,
                    Plan::FEATURE_PROXY_RESIDENTIAL => true,
                    Plan::FEATURE_PROXY_ROTATION => true,
                    Plan::FEATURE_PROXY_FINGERPRINT => true,
                    Plan::FEATURE_BROWSER_PREWARM => true,
                ],
                'limits' => [
                    Plan::LIMIT_PROXY_PROFILES => 50,
                    Plan::LIMIT_PROXY_BANDWIDTH_GB => 500,
                ],
            ],
            'business' => [
                'features' => [
                    Plan::FEATURE_PROXY => true,
                    Plan::FEATURE_PROXY_RESIDENTIAL => true,
                    Plan::FEATURE_PROXY_ROTATION => true,
                    Plan::FEATURE_PROXY_FINGERPRINT => true,
                    Plan::FEATURE_BROWSER_PREWARM => true,
                ],
                'limits' => [
                    Plan::LIMIT_PROXY_PROFILES => 200,
                    Plan::LIMIT_PROXY_BANDWIDTH_GB => 2000,
                ],
            ],
        ];

        foreach ($defaults as $slug => $values) {
            $plan = Plan::query()->where('slug', $slug)->first();
            if (! $plan) {
                continue;
            }

            $features = $plan->features ?? [];
            $limits = $plan->limits ?? [];

            foreach ($values['features'] as $key => $value) {
                if (! array_key_exists($key, $features)) {
                    $features[$key] = $value;
                }
            }

            foreach ($values['limits'] as $key => $value) {
                if (! array_key_exists($key, $limits)) {
                    $limits[$key] = $value;
                }
            }

            $plan->forceFill([
                'features' => $features,
                'limits' => $limits,
            ])->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Entitlement values are operator-owned after this migration; leave them.
    }
};
