<?php

namespace App\Http\Middleware;

use App\Services\OnboardingService;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganization
{
    public function __construct(
        private readonly OrganizationContext $context,
        private readonly OnboardingService $onboarding,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $memberships = $user->memberships()
            ->with('organization')
            ->where('status', 'active')
            ->get()
            ->filter(fn ($membership): bool => $membership->organization !== null && ! $membership->organization->isSuspended())
            ->values();

        if ($memberships->isEmpty()) {
            $this->onboarding->createPersonalOrganization($user);

            $memberships = $user->memberships()
                ->with('organization')
                ->where('status', 'active')
                ->get()
                ->filter(fn ($membership): bool => $membership->organization !== null)
                ->values();
        }

        $activeOrganizationId = (string) $request->session()->get('active_organization_id', '');
        $membership = $memberships->firstWhere('organization_id', $activeOrganizationId)
            ?? $memberships->first();

        if ($membership === null) {
            abort(403, __('ui.errors.no_organization'));
        }

        $request->session()->put('active_organization_id', $membership->organization_id);

        $this->context->set($membership->organization, $membership);

        view()->share([
            'currentOrganization' => $membership->organization,
            'currentMembership' => $membership,
            'currentRole' => $membership->role,
            'userOrganizations' => $memberships->pluck('organization'),
        ]);

        $request->attributes->set('organization', $membership->organization);
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
