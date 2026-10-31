<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\NotificationService;
use App\Services\OrganizationContext;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationSwitchController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly SubscriptionService $subscriptions,
        private readonly NotificationService $notifications,
    ) {}

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'max:26'],
        ]);

        $membership = $request->user()
            ->memberships()
            ->where('organization_id', $validated['organization_id'])
            ->where('status', Membership::STATUS_ACTIVE)
            ->first();

        if (! $membership instanceof Membership) {
            abort(403, __('ui.errors.not_allowed'));
        }

        $request->session()->put('active_organization_id', $membership->organization_id);

        return redirect()->route('dashboard.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $user = $request->user();

        $organization = DB::transaction(function () use ($validated, $user): Organization {
            $baseSlug = Str::slug($validated['name']) ?: 'team';
            $baseSlug = Str::limit($baseSlug, 60, '');
            $slug = $baseSlug;

            while (Organization::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.Str::lower(Str::random(5));
            }

            $organization = Organization::query()->create([
                'name' => $validated['name'],
                'slug' => $slug,
                'owner_id' => $user->getKey(),
                'type' => Organization::TYPE_TEAM,
                'status' => Organization::STATUS_ACTIVE,
                'billing_email' => $user->email,
                'currency' => (string) config('idexal.billing.currency', 'USD'),
            ]);

            $organization->memberships()->create([
                'user_id' => $user->getKey(),
                'role' => Membership::ROLE_OWNER,
                'status' => Membership::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);

            $free = Plan::query()->where('slug', 'free')->first();

            if ($free instanceof Plan) {
                $this->subscriptions->start($organization, $free);
            }

            AuditLog::record('organization.created', $organization, $organization, $organization->name);

            return $organization;
        });

        $request->session()->put('active_organization_id', $organization->getKey());

        $this->notifications->send(
            $user,
            'organization.created',
            __('ui.notifications.welcome_title'),
            __('ui.notifications.welcome_body', ['name' => $organization->name]),
            route('dashboard.index'),
            $organization,
        );

        return redirect()->route('dashboard.index')->with('status', __('ui.common.saved'));
    }
}
