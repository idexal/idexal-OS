<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plans created before a desktop feature existed have no key for it. The API
 * treats a missing key as allowed, but the admin edit form submits every
 * checkbox in its catalog: saving an unrelated field then materializes the
 * missing keys as false and silently removes entitlements. Backfill the keys
 * with the effective value (allowed) so the form shows, and saves, the truth.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $features = [
        'api_access',
        'team_members',
        'webhooks',
        'priority_support',
        'sso',
        'proxy',
        'proxy_residential',
        'proxy_rotation',
        'proxy_fingerprint',
        'browser_prewarm',
        'custom_providers',
        'multiple_workspaces',
        'manage_extensions',
    ];

    public function up(): void
    {
        DB::table('plans')->orderBy('id')->chunkById(100, function ($plans): void {
            foreach ($plans as $plan) {
                $current = json_decode((string) $plan->features, true);
                $current = is_array($current) ? $current : [];
                $next = $current;

                foreach ($this->features as $feature) {
                    if (! array_key_exists($feature, $next)) {
                        $next[$feature] = true;
                    }
                }

                if ($next !== $current) {
                    DB::table('plans')->where('id', $plan->id)->update([
                        'features' => json_encode($next),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // The keys are additive; removing them would restore the silent lockout.
    }
};
