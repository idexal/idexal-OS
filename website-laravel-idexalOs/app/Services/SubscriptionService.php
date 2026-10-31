<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function start(
        Organization $organization,
        Plan $plan,
        string $billingCycle = 'monthly',
        ?Subscription $subscription = null,
        ?string $stripeSubscriptionId = null,
    ): Subscription {
        return DB::transaction(function () use ($organization, $plan, $billingCycle, $subscription, $stripeSubscriptionId): Subscription {
            $organization->subscriptions()
                ->when($subscription !== null, fn ($query) => $query->whereKeyNot($subscription->getKey()))
                ->whereIn('status', [
                    Subscription::STATUS_ACTIVE,
                    Subscription::STATUS_TRIALING,
                    Subscription::STATUS_PAST_DUE,
                    Subscription::STATUS_INCOMPLETE,
                ])
                ->update([
                    'status' => Subscription::STATUS_CANCELED,
                    'canceled_at' => now(),
                    'updated_at' => now(),
                ]);

            $subscription ??= new Subscription([
                'organization_id' => $organization->getKey(),
                'plan_id' => $plan->getKey(),
            ]);

            $periodStart = now();
            $periodEnd = $billingCycle === 'yearly'
                ? $periodStart->copy()->addYearNoOverflow()
                : $periodStart->copy()->addMonthNoOverflow();

            $subscription->fill([
                'organization_id' => $organization->getKey(),
                'plan_id' => $plan->getKey(),
                'status' => Subscription::STATUS_ACTIVE,
                'billing_cycle' => $billingCycle,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'canceled_at' => null,
                'stripe_subscription_id' => $stripeSubscriptionId ?? $subscription->stripe_subscription_id,
            ]);
            $subscription->save();

            return $subscription;
        });
    }

    public function pending(
        Organization $organization,
        Plan $plan,
        string $billingCycle = 'monthly',
    ): Subscription {
        return Subscription::query()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_INCOMPLETE,
            'billing_cycle' => $billingCycle,
        ]);
    }

    public function cancel(Organization $organization): void
    {
        $organization->subscriptions()
            ->whereIn('status', [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_TRIALING,
                Subscription::STATUS_PAST_DUE,
                Subscription::STATUS_INCOMPLETE,
            ])
            ->update([
                'status' => Subscription::STATUS_CANCELED,
                'canceled_at' => now(),
                'updated_at' => now(),
            ]);

        $freePlan = Plan::query()->where('slug', 'free')->first();

        if ($freePlan instanceof Plan) {
            $this->start($organization, $freePlan);
        }
    }

    public function markPastDue(Subscription $subscription): void
    {
        $subscription->forceFill(['status' => Subscription::STATUS_PAST_DUE])->save();
    }

    public function syncFromStripe(Subscription $subscription, object $stripeSubscription): void
    {
        $status = match ($stripeSubscription->status) {
            'active' => Subscription::STATUS_ACTIVE,
            'trialing' => Subscription::STATUS_TRIALING,
            'past_due' => Subscription::STATUS_PAST_DUE,
            'canceled', 'incomplete_expired' => Subscription::STATUS_CANCELED,
            'unpaid' => Subscription::STATUS_UNPAID,
            default => Subscription::STATUS_INCOMPLETE,
        };

        $subscription->forceFill([
            'status' => $status,
            'stripe_status' => $stripeSubscription->status,
            'current_period_start' => isset($stripeSubscription->current_period_start)
                ? now()->setTimestamp((int) $stripeSubscription->current_period_start)
                : $subscription->current_period_start,
            'current_period_end' => isset($stripeSubscription->current_period_end)
                ? now()->setTimestamp((int) $stripeSubscription->current_period_end)
                : $subscription->current_period_end,
            'canceled_at' => $status === Subscription::STATUS_CANCELED ? now() : null,
        ])->save();
    }
}
