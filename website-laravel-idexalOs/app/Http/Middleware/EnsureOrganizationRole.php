<?php

namespace App\Http\Middleware;

use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationRole
{
    public function __construct(private readonly OrganizationContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $membership = $this->context->membership();

        if ($membership === null || ! in_array($membership->role, $roles, true)) {
            abort(403, __('ui.errors.not_allowed'));
        }

        return $next($request);
    }
}
