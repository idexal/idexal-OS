<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agent desktop control is part of every plan, including Free. The earlier
 * backfill wrote explicit values for the seeded plans but left Free without
 * the families; this turns them on for Free while preserving any other
 * feature or limit an administrator changed. Administrators can still revoke
 * any family per plan from the plan editor.
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

    public function up(): void
    {
        DB::table('plans')->where('slug', 'free')->chunkById(100, function ($plans): void {
            foreach ($plans as $plan) {
                $current = json_decode((string) $plan->features, true);
                $current = is_array($current) ? $current : [];
                $next = $current;

                foreach ($this->features as $feature) {
                    $next[$feature] = true;
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
        // Additive values; the prior backfill owns the disabled default.
    }
};
