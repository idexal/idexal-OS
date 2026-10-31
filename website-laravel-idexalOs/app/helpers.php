<?php

/**
 * Numbers are always plain ASCII digits with latin separators
 * (US$ 1,234.50 / 9,073,504). The platform serves a global audience, so
 * numbers never follow the page language and never use Arabic-Indic numerals.
 */
if (! function_exists('money')) {
    function money(mixed $amount, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?: (string) config('idexal.billing.currency', 'USD'));
        $value = number_format((float) $amount, 2, '.', ',');
        $symbol = match ($currency) {
            'USD' => 'US$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CNY' => 'CN¥',
            default => $currency,
        };

        return $symbol.' '.$value;
    }
}

if (! function_exists('compact_number')) {
    function compact_number(mixed $value): string
    {
        return number_format((float) $value);
    }
}

if (! function_exists('locale_direction')) {
    function locale_direction(): string
    {
        return app()->getLocale() === 'ar' ? 'rtl' : 'ltr';
    }
}

/**
 * The languages the public site publishes. English is the global default;
 * Arabic is offered as a first-class alternative, never a fallback.
 *
 * @return list<string>
 */
if (! function_exists('supported_locales')) {
    function supported_locales(): array
    {
        $supported = config('idexal.locales.supported', ['en', 'ar']);

        return is_array($supported) && $supported !== []
            ? array_values($supported)
            : ['en', 'ar'];
    }
}

/**
 * The language an anonymous visitor gets before choosing one. Read from a key
 * the request middleware never rewrites, so it stays the site default.
 */
if (! function_exists('default_locale')) {
    function default_locale(): string
    {
        return (string) config('idexal.locales.default', 'en');
    }
}

/**
 * The current URL carrying an explicit language, so a link keeps its language
 * when it is shared or indexed. Passing null drops the parameter and resolves
 * by the browser session, which is what search engines use as x-default.
 */
if (! function_exists('locale_url')) {
    function locale_url(?string $locale = null): string
    {
        $query = request()->query();
        unset($query['lang']);

        if ($locale !== null) {
            $query['lang'] = $locale;
        }

        $base = url()->current();

        return $query === [] ? $base : $base.'?'.http_build_query($query);
    }
}

/**
 * A named route that keeps the visitor's language in the URL.
 *
 * The language lives in the query string, so an internal link must carry it
 * too. Without this, a visitor reading Arabic clicks "Pricing" and the address
 * bar silently drops `?lang=ar`: the page still renders Arabic from the
 * session, but copied, bookmarked or shared from that point it is English.
 * The default language keeps the bare, canonical URL.
 *
 * @param  array<string, mixed>  $parameters
 */
if (! function_exists('localized_route')) {
    function localized_route(string $name, array $parameters = [], ?string $locale = null): string
    {
        $url = route($name, $parameters);
        $locale ??= app()->getLocale();

        if ($locale === default_locale()) {
            return $url;
        }

        $fragment = '';
        if (($hash = strpos($url, '#')) !== false) {
            $fragment = substr($url, $hash);
            $url = substr($url, 0, $hash);
        }

        $query = [];
        if (($question = strpos($url, '?')) !== false) {
            parse_str(substr($url, $question + 1), $query);
            $url = substr($url, 0, $question);
        }

        $query['lang'] = $locale;

        return $url.'?'.http_build_query($query).$fragment;
    }
}

/**
 * The one URL a search engine should index for this page.
 */
if (! function_exists('canonical_url')) {
    function canonical_url(): string
    {
        return locale_url(app()->getLocale());
    }
}

/**
 * Render structured data as a JSON-LD string.
 *
 * @param  array<string, mixed>  $data
 */
if (! function_exists('json_ld')) {
    function json_ld(array $data): string
    {
        return (string) json_encode(
            $data,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRETTY_PRINT
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT,
        );
    }
}

if (! function_exists('percent_used')) {
    function percent_used(int|float $used, int|float|null $limit): float
    {
        if ($limit === null || $limit <= 0) {
            return 0.0;
        }

        return min(100, round(((float) $used / (float) $limit) * 100, 1));
    }
}
