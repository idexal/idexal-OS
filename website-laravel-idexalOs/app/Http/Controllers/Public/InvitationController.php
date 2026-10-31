<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function show(string $token): View
    {
        $invitation = $this->findInvitation($token);

        if (! $invitation instanceof Invitation) {
            abort(404, __('ui.team.not_found'));
        }

        if (! auth()->check() && $invitation->isPending()) {
            session(['url.intended' => route('invitations.show', $token)]);
        }

        return view('public.invitation', [
            'invitation' => $invitation,
            'token' => $token,
            'state' => match (true) {
                $invitation->isAccepted() => 'accepted',
                $invitation->isExpired() => 'expired',
                default => 'pending',
            },
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->findInvitation($token);

        if (! $invitation instanceof Invitation) {
            abort(404, __('ui.team.not_found'));
        }

        if ($invitation->isExpired()) {
            abort(410, __('ui.team.expired'));
        }

        if ($invitation->isAccepted()) {
            return redirect()->route('dashboard.index')->with('status', __('ui.team.already_accepted'));
        }

        $user = $request->user();
        $organization = $invitation->organization;

        if (! $user instanceof User || ! $organization instanceof Organization) {
            abort(403, __('ui.errors.not_allowed'));
        }

        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            abort(403, __('ui.team.wrong_email'));
        }

        if ($organization->isSuspended()) {
            abort(403, __('ui.errors.organization_suspended'));
        }

        $existingMembership = $organization->memberships()
            ->where('user_id', $user->getKey())
            ->first();
        $consumesSeat = ! $existingMembership instanceof Membership || ! $existingMembership->isActive();

        if ($consumesSeat) {
            $seatLimit = $organization->currentPlan()?->limit(Plan::LIMIT_SEATS);

            if ($seatLimit !== null
                && $organization->memberships()->where('status', Membership::STATUS_ACTIVE)->count() >= (int) $seatLimit) {
                return redirect()->route('dashboard.index')->withErrors([
                    'team' => __('ui.team.limit_reached'),
                ]);
            }
        }

        DB::transaction(function () use ($invitation, $organization, $user): void {
            // The pre-check handles the common path; this lock and re-check
            // close the race where two pending invitations accept at once.
            $organization->newQuery()->whereKey($organization->getKey())->lockForUpdate()->first();

            $membership = $organization->memberships()
                ->where('user_id', $user->getKey())
                ->first();

            if ((! $membership instanceof Membership || ! $membership->isActive())) {
                $seatLimit = $organization->currentPlan()?->limit(Plan::LIMIT_SEATS);

                if ($seatLimit !== null
                    && $organization->memberships()->where('status', Membership::STATUS_ACTIVE)->count() >= (int) $seatLimit) {
                    throw ValidationException::withMessages([
                        'team' => __('ui.team.limit_reached'),
                    ]);
                }
            }

            if ($membership instanceof Membership) {
                if (! $membership->isOwner()) {
                    $membership->forceFill([
                        'role' => $invitation->role,
                        'status' => Membership::STATUS_ACTIVE,
                        'joined_at' => $membership->joined_at ?? now(),
                    ])->save();
                }
            } else {
                $organization->memberships()->create([
                    'user_id' => $user->getKey(),
                    'role' => $invitation->role,
                    'status' => Membership::STATUS_ACTIVE,
                    'invited_by' => $invitation->invited_by,
                    'joined_at' => now(),
                ]);
            }

            $invitation->forceFill(['accepted_at' => now()])->save();

            AuditLog::record('invitation.accepted', $organization, $invitation, __('ui.team.accepted'));
        });

        if ($invitation->inviter instanceof User) {
            $this->notifications->send(
                $invitation->inviter,
                'team.invitation_accepted',
                __('ui.notifications.invitation_accepted_title'),
                __('ui.notifications.invitation_accepted_body', ['name' => $user->name]),
                route('dashboard.team'),
                $organization,
            );
        }

        return redirect()->route('dashboard.index')->with('status', __('ui.team.accepted'));
    }

    private function findInvitation(string $token): ?Invitation
    {
        return Invitation::query()
            ->with(['organization', 'inviter'])
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }
}
