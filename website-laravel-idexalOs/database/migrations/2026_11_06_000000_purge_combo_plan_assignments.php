<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A combo never carried an entitlement of its own: the rows this removes were
 * a stale copy of its members' plans. The gateway now derives a combo's
 * visibility from its members, so the copy is worse than useless — it goes
 * stale on the next plan edit and can hide every member behind a combo nobody
 * can run.
 *
 * The stale copy exists at both levels and both are purged: the combo model
 * row in `plan_ai_model`, and the synthetic `idexal-combo` connection in
 * `plan_ai_provider`. The provider row was the sneakier of the two — it hid
 * every combo at once for any workspace not on the assigned plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comboModelIds = DB::table('ai_models')
            ->leftJoin('ai_providers', 'ai_providers.id', '=', 'ai_models.ai_provider_id')
            ->where(function ($query): void {
                $query->where('ai_providers.slug', 'idexal-combo')
                    ->orWhereNotNull('ai_models.hybrid_config');
            })
            ->pluck('ai_models.id');

        if ($comboModelIds->isNotEmpty()) {
            DB::table('plan_ai_model')
                ->whereIn('ai_model_id', $comboModelIds->all())
                ->delete();
        }

        $comboProviderId = DB::table('ai_providers')
            ->where('slug', 'idexal-combo')
            ->value('id');

        if ($comboProviderId !== null) {
            DB::table('plan_ai_provider')
                ->where('ai_provider_id', $comboProviderId)
                ->delete();
        }
    }

    public function down(): void
    {
        // The removed rows were derived data with a single source of truth
        // (the members' plans), so there is nothing meaningful to restore.
    }
};
