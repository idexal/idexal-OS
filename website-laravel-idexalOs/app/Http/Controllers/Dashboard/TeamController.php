<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Mail\TeamInvitationMail;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\DesktopSession;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Plan;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OrganizationContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly NotificationService $notifications,
    ) {}

    public function index(): View
    {
        $organization = $this->context->requireOrganization();
        $plan = $organization->currentPlan();
        $seatLimit = $plan?->limit(Plan::LIMIT_SEATS);

        return view('dashboard.team.index', [
            'organization' => $organization,
            'memberships' => $organization->memberships()
                ->with('user:id,name,email,last_login_at')
                ->where('status', Membership::STATUS_ACTIVE)
                ->orderByRaw("FIELD(role, 'owner', 'admin', 'developer', 'billing', 'member')")
                ->get(),
            'invitations' => $organization->invitations()
                ->with('inviter:id,name,email')
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->latest('created_at')
                ->get(),
            'roles' => collect(Membership::assignableRoles())->except(Membership::ROLE_OWNER)->keys()->all(),
            'seatLimit' => $seatLimit === null ? null : (int) $seatLimit,
            'canManage' => $this->context->canManageMembers(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $organization = $this->context->requireOrganization();

        if (! $this->context->canManageMembers()) {
            abort(403, __('ui.errors.not_allowed'));
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'role' => ['required', Rule::in(array_keys(collect(Membership::assignableRoles())->except(Membership::ROLE_OWNER)->all()))],
        ]);

        $email = Str::lower($validated['email']);

        if ($organization->members()->where('email', $email)->exists()) {
            return back()->withErrors(['email' => __('ui.team.already_member')])->withInput();
        }

        $plan = $organization->currentPlan();
        $seatLimit = $plan?->limit(Plan::LIMIT_SEATS);

        if ($seatLimit !== null && $organization->memberships()->where('status', Membership::STATUS_ACTIVE)->count() >= (int) $seatLimit) {
            return back()->withErrors(['email' => __('ui.team.limit_reached')])->withInput();
        }

        $token = Str::random(48);
        $expiresAt = now()->addDays(7);
        $invitation = $organization->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->first();

        if ($invitation instanceof Invitation) {
            $invitation->forceFill([
                'role' => $validated['role'],
                'token_hash' => hash('sha256', $token),
                'invited_by' => $request->user()->getKey(),
                'expires_at' => $expiresAt,
            ])->save();
        } else {
            $invitation = $organization->invitations()->create([
                'email' => $email,
                'role' => $validated['role'],
                'token_hash' => hash('sha256', $token),
                'invited_by' => $request->user()->getKey(),
                'expires_at' => $expiresAt,
            ]);
        }

        try {
            Mail::to($email)->send(new TeamInvitationMail($organization, $invitation, $token, $request->user()));
        } catch (\Throwable $exception) {
            report($exception);
        }

        $invitedUser = User::query()->where('email', $email)->first();

        if ($invitedUser instanceof User) {
            $this->notifications->send(
                $invitedUser,
                'team.invitation',
                __('ui.notifications.invitation_title'),
                __('ui.notifications.invitation_body', ['organization' => $organization->name]),
                route('invitations.show', $token),
                $organization,
            );
        }

        AuditLog::record('team.invitation.sent', $organization, $invitation, $email);

        return redirect()->route('dashboard.team')->with('status', __('ui.team.invitation_sent'));
    }

    public function update(Request $request, Membership $membership): RedirectResponse
    {
        $organization = $this->context->requireOrganization();

        if (! $this->context->canManageMembers()) {
            abort(403, __('ui.errors.not_allowed'));
        }

        abort_unless($membership->organization_id === $organization->getKey(), 404);

        if ($membership->isOwner()) {
            return back()->withErrors(['role' => __('ui.team.cannot_change_owner')]);
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in(array_keys(collect(Membership::assignableRoles())->except(Membership::ROLE_OWNER)->all()))],
        ]);

        $membership->forceFill(['role' => $validated['role']])->save();
        AuditLog::record('team.member.role_updated', $organization, $membership, $validated['role']);

        return redirect()->route('dashboard.team')->with('status', __('ui.team.updated'));
    }

    public function destroy(Request $request, Membership $membership): RedirectResponse
    {
        $organization = $this->context->requireOrganization();

        if (! $this->context->canManageMembers()) {
            abort(403, __('ui.errors.not_allowed'));
        }

        abort_unless($membership->organization_id === $organization->getKey(), 404);

        if ($membership->isOwner()) {
            return back()->withErrors(['member' => __('ui.team.cannot_remove_owner')]);
        }

        if ($membership->user_id === $request->user()->getKey()) {
            return back()->withErrors(['member' => __('ui.errors.not_allowed')]);
        }

        DB::transaction(function () use ($membership, $organization): void {
            ApiKey::query()
                ->where('organization_id', $organization->getKey())
                ->where('user_id', $membership->user_id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            DesktopSession::query()
                ->where('organization_id', $organization->getKey())
                ->where('user_id', $membership->user_id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $membership->delete();
        });

        AuditLog::record('team.member.removed', $organization, null, $membership->user_id);

        return redirect()->route('dashboard.team')->with('status', __('ui.team.removed'));
    }

    public function cancelInvitation(Invitation $invitation): RedirectResponse
    {
        $organization = $this->context->requireOrganization();

        if (! $this->context->canManageMembers()) {
            abort(403, __('ui.errors.not_allowed'));
        }

        abort_unless($invitation->organization_id === $organization->getKey(), 404);

        $invitation->delete();
        AuditLog::record('team.invitation.canceled', $organization, null, $invitation->email);

        return redirect()->route('dashboard.team')->with('status', __('ui.team.invite_canceled'));
    }
}
