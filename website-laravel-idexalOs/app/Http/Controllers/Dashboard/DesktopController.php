<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DesktopSession;
use App\Services\DesktopAuthService;
use App\Services\OrganizationContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DesktopController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly DesktopAuthService $desktopAuth,
    ) {}

    public function index(): View
    {
        $organization = $this->context->requireOrganization();

        return view('dashboard.desktop.index', [
            'organization' => $organization,
            'sessions' => $organization->desktopSessions()
                ->with(['user:id,name,email', 'apiKey:id,name,key_prefix,last_used_at'])
                ->orderByDesc('last_seen_at')
                ->get(),
            'canManage' => $this->context->canManageApiKeys(),
        ]);
    }

    public function destroy(Request $request, DesktopSession $desktopSession): RedirectResponse
    {
        $organization = $this->context->requireOrganization();
        abort_unless($desktopSession->organization_id === $organization->getKey(), 404);

        if (! $this->context->canManageApiKeys() && $desktopSession->user_id !== $request->user()->getKey()) {
            abort(403, __('ui.errors.not_allowed'));
        }

        $this->desktopAuth->revoke($desktopSession);

        return redirect()->route('dashboard.desktop')->with('status', __('ui.desktop.revoked'));
    }
}
