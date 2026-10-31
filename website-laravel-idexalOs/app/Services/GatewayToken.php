<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Carbon;

/**
 * Derived gateway credential for managed desktop providers.
 *
 * The desktop must call the platform gateway without ever receiving an
 * upstream provider key. The token is an HMAC-signed reference to the
 * desktop's own ApiKey: revocation and expiry stay authoritative in the
 * database, and nothing new needs to be stored per device.
 */
class GatewayToken
{
    public const PREFIX = 'ow_gw_';

    public function issue(ApiKey $apiKey, ?Carbon $expiresAt = null): string
    {
        $expiry = $expiresAt ?? $apiKey->expires_at ?? now()->addYear();
        $payload = $this->encode((string) json_encode([
            'sub' => $apiKey->getKey(),
            'exp' => $expiry->getTimestamp(),
        ], JSON_THROW_ON_ERROR));

        return self::PREFIX.$payload.'.'.$this->sign($payload);
    }

    public function verify(string $token): ?ApiKey
    {
        if (! str_starts_with($token, self::PREFIX)) {
            return null;
        }

        $parts = explode('.', substr($token, strlen(self::PREFIX)), 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$payload, $signature] = $parts;

        if (! hash_equals($this->sign($payload), $signature)) {
            return null;
        }

        $decoded = json_decode($this->decode($payload), true);

        if (! is_array($decoded)
            || ! is_string($decoded['sub'] ?? null)
            || ! is_int($decoded['exp'] ?? null)
            || $decoded['exp'] < time()) {
            return null;
        }

        $apiKey = ApiKey::query()->with(['user', 'organization'])->find($decoded['sub']);

        return $apiKey instanceof ApiKey && $apiKey->isActive() ? $apiKey : null;
    }

    private function sign(string $payload): string
    {
        return $this->encode(hash_hmac('sha256', $payload, (string) config('app.key'), true));
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
