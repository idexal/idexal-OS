<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_active_paid_subscription_grants_its_plan(): void
    {
        $paid = $this->plan('pro');
        $organization = Organization::factory()->create();

        Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $paid->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertSame($paid->getKey(), $this->currentPlan($organization)?->getKey());
    }

    public function test_cancel_at_period_end_falls_back_after_the_period_ends(): void
    {
        $this->plan('free');
        $paid = $this->plan('pro');
        $organization = Organization::factory()->create();

        Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $paid->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_end' => now()->subMinute(),
            'canceled_at' => now()->subMonth(),
        ]);

        $this->assertSame('free', $this->currentPlan($organization)?->slug);
    }

    public function test_past_due_keeps_a_seven_day_grace_window(): void
    {
        $paid = $this->plan('pro');
        $organization = Organization::factory()->create();

        Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $paid->getKey(),
            'status' => Subscription::STATUS_PAST_DUE,
            'current_period_end' => now()->subDays(3),
        ]);

        $this->assertSame($paid->getKey(), $this->currentPlan($organization)?->getKey());
    }

    public function test_past_due_past_the_grace_window_no_longer_grants_the_plan(): void
    {
        $this->plan('free');
        $paid = $this->plan('pro');
        $organization = Organization::factory()->create();

        Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $paid->getKey(),
            'status' => Subscription::STATUS_PAST_DUE,
            'current_period_end' => now()->subDays(8),
        ]);

        $this->assertSame('free', $this->currentPlan($organization)?->slug);
    }

    public function test_an_incomplete_checkout_does_not_grant_the_plan(): void
    {
        $this->plan('free');
        $paid = $this->plan('pro');
        $organization = Organization::factory()->create();

        Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $paid->getKey(),
            'status' => Subscription::STATUS_INCOMPLETE,
            'current_period_end' => null,
        ]);

        $this->assertSame('free', $this->currentPlan($organization)?->slug);
    }

    public function test_reconcile_closes_ended_and_stale_subscriptions(): void
    {
        $paid = $this->plan('pro');
        $organization = Organization::factory()->create();

        $ended = Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $paid->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_end' => now()->subMinute(),
            'canceled_at' => now()->subMonth(),
        ]);
        $abandoned = Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $paid->getKey(),
            'status' => Subscription::STATUS_INCOMPLETE,
            'current_period_end' => null,
        ]);
        $abandoned->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->artisan('subscriptions:reconcile')->assertSuccessful();

        $this->assertSame(Subscription::STATUS_CANCELED, $ended->refresh()->status);
        $this->assertSame(Subscription::STATUS_CANCELED, $abandoned->refresh()->status);
    }

    private function plan(string $slug): Plan
    {
        return Plan::factory()->create(['slug' => $slug, 'is_active' => true, 'is_public' => true]);
    }

    private function currentPlan(Organization $organization): ?Plan
    {
        return Organization::query()->findOrFail($organization->getKey())->currentPlan();
    }
}
