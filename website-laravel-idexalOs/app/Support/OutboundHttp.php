<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Outbound HTTP client for provider and webhook destinations.
 *
 * Windows and some containers ship no OpenSSL store usable by cURL, so the
 * optional bundle keeps TLS verification on instead of disabling it.
 */
final class OutboundHttp
{
    public static function client(): PendingRequest
    {
        $caBundle = trim((string) config('idexal.gateway.ca_bundle', ''));

        return Http::withOptions($caBundle === '' ? [] : ['verify' => $caBundle]);
    }
}
