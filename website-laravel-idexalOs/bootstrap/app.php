<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureApiAbility;
use App\Http\Middleware\EnsureEmailIsVerifiedIfRequired;
use App\Http\Middleware\EnsureOrganizationRole;
use App\Http\Middleware\PreferCanonicalHost;
use App\Http\Middleware\ResolveOrganization;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The production site is served through Cloudflare, which forwards the
        // original scheme and client IP. Without trusted proxies Laravel builds
        // http:// URLs, rejects secure session cookies and rate-limits every
        // member against the edge IP.
        $middleware->trustProxies(at: '*');

        // One installation, more than one hostname. This runs before routing so
        // browser surfaces land on the canonical host while machine API paths
        // stay reachable on every host.
        $middleware->prepend(PreferCanonicalHost::class);

        $middleware->validateCsrfTokens(except: [
            'webhooks/stripe',
            'webhooks/paypal',
        ]);

        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'org' => ResolveOrganization::class,
            'org.role' => EnsureOrganizationRole::class,
            'api.key' => AuthenticateApiKey::class,
            'api.ability' => EnsureApiAbility::class,
            'verified.required' => EnsureEmailIsVerifiedIfRequired::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->is('v1/*')
                || $request->is('webhooks/*')
                || $request->expectsJson(),
        );
    })->create();
