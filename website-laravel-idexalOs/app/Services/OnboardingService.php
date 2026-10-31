<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OnboardingService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly NotificationService $notifications,
        private readonly SettingsService $settings,
    ) {}

    public function createPersonalOrganization(User $user): Organization
    {
        return DB::transaction(function () use ($user): Organization {
            $baseSlug = Str::slug($user->name) ?: Str::before($user->email, '@');
            $baseSlug = Str::limit($baseSlug, 60, '');
            $slug = $baseSlug;

            while (Organization::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.Str::lower(Str::random(5));
            }

            $organization = Organization::query()->create([
                'name' => $user->name,
                'slug' => $slug,
                'owner_id' => $user->getKey(),
                'type' => Organization::TYPE_PERSONAL,
                'status' => Organization::STATUS_ACTIVE,
                'billing_email' => $user->email,
                'currency' => (string) config('idexal.billing.currency', 'USD'),
            ]);

            $organization->memberships()->create([
                'user_id' => $user->getKey(),
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $freePlan = Plan::query()->where('slug', 'free')->first();

            // When the operator requires everyone to hold a plan of their own,
            // a new account is deliberately left without a subscription: the
            // desktop then holds it at the plan chooser instead of quietly
            // granting the free plan.
            // Every new account picks a plan before the desktop opens. The
            // operator can relax this in /admin/features; the default is the
            // commercial promise: no workspace without a subscription.
            $requirePlanSelection = $this->settings->boolean('feature_require_plan_selection', true);

            if ($freePlan instanceof Plan && ! $requirePlanSelection) {
                $this->subscriptions->start($organization, $freePlan);
            }

            $this->notifications->send(
                $user,
                'welcome',
                __('ui.notifications.welcome_title'),
                __('ui.notifications.welcome_body'),
                url('/dashboard'),
                $organization,
            );

            return $organization;
        });
    }
}
