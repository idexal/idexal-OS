<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\CreditService;
use App\Services\NotificationService;
use App\Support\DesktopSync;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminOrganizationController extends Controller
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $type = (string) $request->query('type', '');

        $organizations = Organization::query()
            ->with('owner:id,name,email')
            ->withCount(['memberships', 'apiKeys', 'invoices'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('billing_email', 'like', '%'.$search.'%');
            }))
            ->when(in_array($status, [Organization::STATUS_ACTIVE, Organization::STATUS_SUSPENDED], true), fn ($query) => $query->where('status', $status))
            ->when(in_array($type, [Organization::TYPE_PERSONAL, Organization::TYPE_TEAM], true), fn ($query) => $query->where('type', $type))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.organizations.index', [
            'organizations' => $organizations,
            'search' => $search,
            'status' => $status,
            'type' => $type,
        ]);
    }

    public function show(Organization $organization): View
    {
        $organization->load([
            'owner:id,name,email',
            'memberships' => fn ($query) => $query->with('user:id,name,email')->orderBy('created_at'),
            'subscriptions' => fn ($query) => $query->with('plan')->latest('created_at')->limit(10),
            'invoices' => fn ($query) => $query->latest('created_at')->limit(10),
            'payments' => fn ($query) => $query->latest('created_at')->limit(10),
            'creditTransactions' => fn ($query) => $query->with('user:id,name')->latest('created_at')->limit(15),
            'apiKeys' => fn ($query) => $query->with('user:id,name')->latest('created_at')->limit(10),
            'desktopSessions' => fn ($query) => $query->with('user:id,name')->latest('created_at')->limit(10),
        ]);

        return view('admin.organizations.show', [
            'organization' => $organization,
            'plan' => $organization->currentPlan(),
            'subscription' => $organization->currentSubscription(),
        ]);
    }

    public function updateStatus(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([Organization::STATUS_ACTIVE, Organization::STATUS_SUSPENDED])],
        ]);

        if ($organization->owner_id === $request->user()->getKey() && $validated['status'] === Organization::STATUS_SUSPENDED) {
            return back()->withErrors(['status' => __('ui.errors.not_allowed')]);
        }

        $organization->forceFill(['status' => $validated['status']])->save();
        AuditLog::record('admin.organization.status', $organization, $organization, $validated['status']);
        DesktopSync::announce('organization_status', ['organization' => $organization->slug, 'status' => $validated['status']]);

        if ($organization->owner instanceof User) {
            $this->notifications->send(
                $organization->owner,
                'organization.status',
                __('ui.notifications.organization_status_title'),
                __('ui.notifications.organization_status_body', [
                    'organization' => $organization->name,
                    'status' => __('ui.statuses.'.$validated['status']),
                ]),
                route('dashboard.index'),
                $organization,
            );
        }

        return redirect()->route('admin.organizations.show', $organization)->with('status', __('ui.common.saved'));
    }

    /**
     * The organization-level BYOK kill switch. When on, no member of this
     * organization may add their own provider keys, whatever their plan says —
     * a plan can still opt back in by explicitly granting `custom_providers`.
     * This is the "force subscription-only" control the operator asked for.
     */
    public function updateByok(Request $request, Organization $organization): RedirectResponse
    {
        $killed = $request->boolean('byok_killed');

        $settings = $organization->settings ?? [];
        $settings['byok_killed'] = $killed;
        $organization->forceFill(['settings' => $settings])->save();

        AuditLog::record(
            'admin.organization.byok',
            $organization,
            $organization,
            $killed ? 'killed' : 'allowed',
        );
        DesktopSync::announce('organization_byok', [
            'organization' => $organization->slug,
            'byokKilled' => $killed,
        ]);

        return redirect()->route('admin.organizations.show', $organization)->with('status', __('ui.common.saved'));
    }

    public function addCredit(Request $request, Organization $organization): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'description' => ['nullable', 'string', 'max:190'],
        ]);

        $this->credits->add(
            $organization,
            (float) $validated['amount'],
            $validated['description'] ?: __('ui.admin.organizations.credit_added'),
            $request->user(),
            'admin',
            $request->user()->getKey(),
        );

        AuditLog::record('admin.organization.credit_added', $organization, $organization, (string) $validated['amount']);
        DesktopSync::announce('organization_credit_added', ['organization' => $organization->slug]);

        if ($organization->owner instanceof User) {
            $this->notifications->send(
                $organization->owner,
                'billing.credit_added',
                __('ui.notifications.credit_added_title'),
                __('ui.notifications.credit_added_body', ['amount' => money($validated['amount'], $organization->currency)]),
                route('dashboard.billing'),
                $organization,
            );
        }

        return redirect()->route('admin.organizations.show', $organization)->with('status', __('ui.common.saved'));
    }
}
