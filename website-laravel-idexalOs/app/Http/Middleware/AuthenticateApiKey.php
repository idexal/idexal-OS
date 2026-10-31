<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Services\GatewayToken;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(private readonly OrganizationContext $context) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) $request->bearerToken());

        if ($token === '') {
            return $this->unauthorized(__('ui.errors.missing_api_key'));
        }

        if (str_starts_with($token, GatewayToken::PREFIX)) {
            $apiKey = app(GatewayToken::class)->verify($token);
        } elseif (str_starts_with($token, 'ixo_') || str_starts_with($token, 'ow_inf_')) {
            $apiKey = ApiKey::query()
                ->with(['user', 'organization'])
                ->where('key_hash', hash('sha256', $token))
                ->first();
        } else {
            return $this->unauthorized(__('ui.errors.missing_api_key'));
        }

        if (! $apiKey instanceof ApiKey || ! $apiKey->isActive()) {
            return $this->unauthorized(__('ui.errors.invalid_api_key'));
        }

        $user = $apiKey->user;
        $organization = $apiKey->organization;

        if ($user === null || $user->isSuspended()) {
            return $this->unauthorized(__('ui.errors.account_suspended'));
        }

        if ($organization === null || $organization->isSuspended()) {
            return $this->forbidden(__('ui.errors.organization_suspended'));
        }

        $membership = $organization->membershipFor($user);

        if ($membership === null || ! $membership->isActive()) {
            return $this->forbidden(__('ui.errors.not_a_member'));
        }

        Auth::setUser($user);
        $this->context->set($organization, $membership);

        $locale = in_array($user->locale, supported_locales(), true)
            ? (string) $user->locale
            : default_locale();

        App::setLocale($locale);
        Carbon::setLocale($locale);

        $apiKey->markUsed();

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('organization', $organization);
        $request->attributes->set('membership', $membership);

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json([
            'error' => [
                'message' => $message,
                'type' => 'authentication_error',
                'code' => 'invalid_api_key',
            ],
        ], 401);
    }

    private function forbidden(string $message): Response
    {
        return response()->json([
            'error' => [
                'message' => $message,
                'type' => 'permission_error',
                'code' => 'access_denied',
            ],
        ], 403);
    }
}
