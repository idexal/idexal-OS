<?php

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Services\DesktopAuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DesktopAuthorizationController extends Controller
{
    public function __construct(private readonly DesktopAuthService $desktopAuth) {}

    public function create(Request $request): View|RedirectResponse
    {
        $memberships = $request->user()
            ->memberships()
            ->with('organization')
            ->where('status', Membership::STATUS_ACTIVE)
            ->get()
            ->filter(fn (Membership $membership): bool => $membership->organization instanceof Organization
                && ! $membership->organization->isSuspended())
            ->values();

        if ($memberships->isEmpty()) {
            return redirect()->route('dashboard.index')->withErrors([
                'organization' => __('ui.errors.no_organization'),
            ]);
        }

        $selected = (string) $request->query('organization', (string) session('desktop_auth_org', $memberships->first()->organization_id));

        if ($memberships->doesntContain(fn (Membership $membership): bool => $membership->organization_id === $selected)) {
            $selected = (string) $memberships->first()->organization_id;
        }

        return view('desktop.authorize', [
            'memberships' => $memberships,
            'selectedOrganization' => $selected,
            'desktopScheme' => (string) session('desktop_scheme', config('idexal.desktop.scheme', 'idexalos')),
            'handoffMinutes' => (int) config('idexal.desktop.handoff_ttl_minutes', 10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'max:26'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $membership = $request->user()
            ->memberships()
            ->with('organization')
            ->where('status', Membership::STATUS_ACTIVE)
            ->where('organization_id', $validated['organization_id'])
            ->first();

        $organization = $membership?->organization;

        if (! $membership instanceof Membership || ! $organization instanceof Organization || $organization->isSuspended()) {
            return back()->withErrors(['organization_id' => __('ui.errors.no_organization')])->withInput();
        }

        ['handoff' => $handoff, 'grant' => $grant] = $this->desktopAuth->issueHandoff(
            $request->user(),
            $organization,
            $request,
            $validated['device_name'] ?? null,
        );

        $request->session()->put('desktop_auth_org', $organization->getKey());
        $request->session()->put('desktop_grant', $grant);
        $request->session()->put('desktop_grant_expires', $handoff->expires_at->toIso8601String());

        AuditLog::record('desktop.handoff.issued', $organization, $handoff, __('ui.desktop.authorize_title'));

        // The desktop sign-in flag has done its job once the handoff exists;
        // forgetting it keeps a later visit to billing from bouncing back here.
        $request->session()->forget('desktop_auth');

        return redirect()->route('desktop.complete');
    }

    public function complete(Request $request): View|RedirectResponse
    {
        $grant = (string) $request->session()->get('desktop_grant', '');

        if ($grant === '') {
            return redirect()->route('desktop.authorize');
        }

        $expiresAt = $request->session()->get('desktop_grant_expires');

        return view('desktop.complete', [
            'deepLink' => $this->desktopAuth->deepLink(
                $grant,
                url('/'),
                (string) $request->session()->get('desktop_scheme', config('idexal.desktop.scheme')),
            ),
            // Set only when the desktop opened this page in its own window, so
            // the page hands itself back without a click. Browsers never set
            // it, and the link stays on the page either way.
            'autoRedirect' => (bool) $request->session()->get('desktop_embed', false),
            'expiresAt' => $expiresAt,
            'handoffMinutes' => (int) config('idexal.desktop.handoff_ttl_minutes', 10),
        ]);
    }
}
