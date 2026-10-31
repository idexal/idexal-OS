<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        // A shared link may carry its language, e.g. /?lang=ar. It wins for
        // this request and is remembered so navigation keeps the language.
        $requested = $request->query('lang');

        if (is_string($requested) && in_array($requested, supported_locales(), true)) {
            $request->session()->put('locale', $requested);

            // A signed-in member keeps the language on their account, so it
            // follows them to the next device instead of only this session.
            $user = $request->user();

            if ($user !== null && $user->locale !== $requested) {
                $user->forceFill(['locale' => $requested])->save();
            }
        }

        $locale = (string) ($request->session()->get('locale')
            ?? $request->user()?->locale
            ?? default_locale());

        if (! in_array($locale, supported_locales(), true)) {
            $locale = default_locale();
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        view()->share([
            'siteName' => $this->settings->get('site_name', config('idexal.brand.name')),
            'siteTagline' => $this->settings->get('site_tagline', config('idexal.brand.tagline')),
            'supportEmail' => $this->settings->get('support_email', config('idexal.brand.support_email')),
            'gatewayEnabled' => $this->settings->boolean('gateway_enabled', true),
            'billingEnabled' => $this->settings->boolean('billing_enabled', true),
            'currentLocale' => $locale,
            'direction' => $locale === 'ar' ? 'rtl' : 'ltr',
        ]);

        return $next($request);
    }
}
