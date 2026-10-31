<?php

namespace App\Providers;

use App\Services\OrganizationContext;
use App\Services\SettingsService;
use App\Services\Social\PinterestProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(OrganizationContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useTailwind();

        // Pinterest ships no Socialite driver, so idexal Os registers its own.
        // The credentials are injected from settings just before the driver is
        // resolved (see SocialLoginService::driver).
        Socialite::extend('pinterest', function (Application $app): SocialiteProvider {
            return Socialite::buildProvider(
                PinterestProvider::class,
                (array) $app['config']->get('services.pinterest', []),
            );
        });

        if (app()->isProduction()) {
            URL::forceScheme('https');

            $canonicalHost = trim((string) config('idexal.domains.canonical'));

            if ($canonicalHost !== '') {
                URL::forceRootUrl('https://'.$canonicalHost);
            }
        }

        if (! app()->isProduction() && ! app()->runningInConsole()) {
            Model::preventLazyLoading();
        }

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                Str::lower((string) $request->input('email')).'|'.$request->ip(),
            );
        });

        RateLimiter::for('desktop-handoff', function (Request $request): array {
            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perHour(60)->by($request->ip()),
            ];
        });

        RateLimiter::for('gateway', function (Request $request): Limit {
            return Limit::perMinute((int) config('idexal.gateway.rate_limit_per_minute', 120))
                ->by((string) ($request->bearerToken() ?: $request->ip()));
        });

        RateLimiter::for('contact', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
