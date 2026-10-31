<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFeatureBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_plan_features_are_backfilled_as_allowed(): void
    {
        $plan = Plan::factory()->create([
            'slug' => 'legacy-plan',
            'features' => ['proxy' => true, 'proxy_rotation' => false],
        ]);

        $migration = require database_path('migrations/2026_10_20_220500_backfill_plan_feature_defaults.php');
        $migration->up();

        $features = $plan->refresh()->features;

        $this->assertTrue($features['custom_providers']);
        $this->assertTrue($features['multiple_workspaces']);
        $this->assertTrue($features['manage_extensions']);
        $this->assertTrue($features['proxy_fingerprint']);
        $this->assertFalse($features['proxy_rotation']);
        $this->assertCount(13, $features);
    }

    public function test_backfill_is_idempotent(): void
    {
        $plan = Plan::factory()->create([
            'slug' => 'legacy-plan-2',
            'features' => ['proxy' => false],
        ]);

        $migration = require database_path('migrations/2026_10_20_220500_backfill_plan_feature_defaults.php');
        $migration->up();
        $first = $plan->refresh()->features;
        $migration->up();
        $second = $plan->refresh()->features;

        $this->assertSame($first, $second);
        $this->assertFalse($second['proxy']);
    }

    public function test_agent_control_backfill_allows_every_seeded_plan(): void
    {
        $free = Plan::factory()->create(['slug' => 'free', 'features' => ['proxy' => true]]);
        $starter = Plan::factory()->create(['slug' => 'starter', 'features' => []]);
        $pro = Plan::factory()->create(['slug' => 'pro', 'features' => []]);

        $migration = require database_path('migrations/2026_10_21_181728_backfill_agent_control_plan_features.php');
        $migration->up();

        foreach ([$free, $starter, $pro] as $plan) {
            $features = $plan->refresh()->features;
            foreach (['agent_control', 'agent_settings', 'agent_proxy', 'agent_extensions', 'agent_browser', 'agent_providers'] as $feature) {
                $this->assertTrue($features[$feature]);
            }
        }

        $this->assertTrue($free->refresh()->features['proxy']);
    }

    public function test_free_plan_agent_control_migration_preserves_other_features(): void
    {
        $free = Plan::factory()->create([
            'slug' => 'free',
            'features' => ['proxy' => true, 'agent_control' => false, 'agent_browser' => false],
        ]);
        $starter = Plan::factory()->create(['slug' => 'starter', 'features' => ['agent_control' => true]]);

        $migration = require database_path('migrations/2026_10_21_220000_enable_free_agent_control.php');
        $migration->up();
        $migration->up();

        $freeFeatures = $free->refresh()->features;
        $this->assertTrue($freeFeatures['proxy']);
        foreach (['agent_control', 'agent_settings', 'agent_proxy', 'agent_extensions', 'agent_browser', 'agent_providers'] as $feature) {
            $this->assertTrue($freeFeatures[$feature]);
        }

        $this->assertTrue($starter->refresh()->features['agent_control']);
    }

    public function test_plan_seeder_writes_explicit_agent_control_defaults(): void
    {
        $this->seed(PlanSeeder::class);

        $free = Plan::query()->where('slug', 'free')->firstOrFail();
        $starter = Plan::query()->where('slug', 'starter')->firstOrFail();

        $this->assertTrue($free->features['agent_control']);
        $this->assertTrue($free->features['agent_browser']);
        $this->assertTrue($starter->features['agent_control']);
        $this->assertTrue($starter->features['agent_browser']);
    }
}
