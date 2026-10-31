<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAbility
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $apiKey = $request->attributes->get('api_key');

        if (! $apiKey instanceof ApiKey || ! $apiKey->hasAbility($ability)) {
            return response()->json([
                'error' => [
                    'message' => __('ui.errors.insufficient_scope'),
                    'type' => 'permission_error',
                    'code' => 'insufficient_scope',
                ],
            ], 403);
        }

        return $next($request);
    }
}
