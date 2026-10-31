<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\OrganizationContext;
use App\Services\UsageService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly UsageService $usage,
    ) {}

    public function index(): View
    {
        $organization = $this->context->requireOrganization();
        $membership = $this->context->membership();
        $summary = $this->usage->summary($organization);
        $series = $this->usage->dailySeries($organization, now()->subDays(29)->startOfDay(), now());

        $activeSessions = $organization->desktopSessions()->whereNull('revoked_at')->count();

        return view('dashboard.index', [
            'organization' => $organization,
            'membership' => $membership,
            'summary' => $summary,
            'series' => $series,
            'recentRequests' => $this->usage->recentRequests($organization, 6),
            'invoices' => $organization->invoices()->latest('issued_at')->latest('created_at')->limit(4)->get(),
            'membersCount' => $organization->memberships()->where('status', 'active')->count(),
            'keysCount' => $organization->apiKeys()->whereNull('revoked_at')->count(),
            'desktopSessionsCount' => $activeSessions,
            'latestDesktopSession' => $organization->desktopSessions()->whereNull('revoked_at')->latest('last_seen_at')->first(),
            'subscription' => $organization->currentSubscription(),
            'plan' => $organization->currentPlan(),
            'notifications' => request()->user()->appNotifications()->latest('created_at')->limit(4)->get(),
        ]);
    }
}
