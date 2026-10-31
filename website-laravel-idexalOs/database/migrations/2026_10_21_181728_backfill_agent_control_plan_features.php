<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The agent desktop-control family is new. Write explicit values for the
 * seeded plans so every plan starts with the same complete agent capability;
 * administrators can then revoke any family per plan. Custom plans keep
 * whatever their administrator already chose.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $features = [
        'agent_control',
        'agent_settings',
        'agent_proxy',
        'agent_extensions',
        'agent_browser',
        'agent_providers',
    ];

    /**
     * @var list<string>
     */
    private array $paidPlans = ['starter', 'pro', 'business'];

    public function up(): void
    {
        $this->writePlanFeatures('free', true);
        $this->writePlanFeatures($this->paidPlans, true);
    }

    public function down(): void
    {
        // Additive keys; removing them would restore missing-key-as-allowed.
    }

    /**
     * @param  string|list<string>  $slugs
     */
    private function writePlanFeatures(string|array $slugs, bool $enabled): void
    {
        DB::table('plans')->whereIn('slug', (array) $slugs)->chunkById(100, function ($plans) use ($enabled): void {
            foreach ($plans as $plan) {
                $current = json_decode((string) $plan->features, true);
                $current = is_array($current) ? $current : [];
                $next = $current;

                foreach ($this->features as $feature) {
                    $next[$feature] = $enabled;
                }

                if ($next !== $current) {
                    DB::table('plans')->where('id', $plan->id)->update([
                        'features' => json_encode($next),
                    ]);
                }
            }
        });
    }
};
