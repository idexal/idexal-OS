<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\AuditLog;
use App\Models\ProviderApiKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Single source of truth for a provider's API credentials.
 *
 * A provider can hold one default key on its own row plus any number of extra
 * keys in `provider_api_keys`. Credentials are handed to the gateway in the
 * order they should be tried; the gateway reports back which one it used so
 * the rotation order keeps moving and an exhausted key sinks to the back.
 */
class ProviderKeyRotationService
{
    /**
     * Credentials to try for a provider, in order: the provider's own key
     * first, then its primary extras, then its backups, least-recently rotated
     * first so load is spread across keys.
     *
     * @return list<array{id: int|null, label: string, type: string, value: string}>
     */
    public function credentialsFor(AiProvider $provider): array
    {
        $credentials = [];

        $default = trim((string) $provider->api_key);

        if ($default !== '') {
            $credentials[] = [
                'id' => null,
                'label' => 'default',
                'type' => ProviderApiKey::TYPE_PRIMARY,
                'value' => $default,
            ];
        }

        foreach ($this->usableKeys($provider) as $key) {
            $value = trim((string) $key->api_key_encrypted);

            if ($value === '') {
                continue;
            }

            $credentials[] = [
                'id' => (int) $key->getKey(),
                'label' => (string) $key->key_name,
                'type' => (string) $key->key_type,
                'value' => $value,
            ];
        }

        if ($credentials === []) {
            // Keyless loopback providers (Ollama and friends) still need an
            // attempt so the request reaches them.
            $credentials[] = [
                'id' => null,
                'label' => 'default',
                'type' => ProviderApiKey::TYPE_PRIMARY,
                'value' => '',
            ];
        }

        return $credentials;
    }

    /**
     * Keys the gateway may use right now: switched on, not expired, least-used
     * first, with type only as a tie-break so usage really does rotate across
     * every key instead of always draining the primary ones first.
     *
     * @return Collection<int, ProviderApiKey>
     */
    public function usableKeys(AiProvider $provider): Collection
    {
        return ProviderApiKey::query()
            ->where('ai_provider_id', $provider->getKey())
            ->usable()
            ->orderBy('rotation_count')
            ->orderByRaw("CASE WHEN key_type = 'primary' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();
    }

    /**
     * Every key row for the provider, active or not, for the admin screen.
     *
     * @return Collection<int, ProviderApiKey>
     */
    public function allKeys(AiProvider $provider): Collection
    {
        return ProviderApiKey::query()
            ->where('ai_provider_id', $provider->getKey())
            ->orderByRaw("CASE WHEN key_type = 'primary' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();
    }

    /**
     * Active key rows as plain arrays, keyed for the admin screen. The
     * decrypted value stays server-side; render `masked`, never `value`.
     *
     * @return Collection<int, array{id: int, name: string, type: string, value: string, last_used_at: string|null, expires_at: string|null, is_active: bool, masked: string, expired: bool}>
     */
    public function getActiveKeys(AiProvider $provider): Collection
    {
        return $this->usableKeys($provider)->map(fn (ProviderApiKey $key): array => $this->toArray($key));
    }

    /**
     * @return array{id: int, name: string, type: string, value: string, last_used_at: string|null, expires_at: string|null, is_active: bool, masked: string, expired: bool}
     */
    public function toArray(ProviderApiKey $key): array
    {
        return [
            'id' => (int) $key->getKey(),
            'name' => (string) $key->key_name,
            'type' => (string) $key->key_type,
            'value' => (string) $key->api_key_encrypted,
            'last_used_at' => $key->last_used_at,
            'expires_at' => $key->expires_at?->toDateTimeString(),
            'is_active' => (bool) $key->is_active,
            'masked' => $key->maskedValue(),
            'expired' => $key->isExpired(),
        ];
    }

    /**
     * Move the rotation pointer: the key currently first in line is bumped so
     * the next key becomes the one a fresh request will try first.
     *
     * @return array{id: int, name: string, type: string, value: string, last_used_at: string|null, expires_at: string|null, is_active: bool, masked: string, expired: bool}
     */
    public function rotateToNextKey(AiProvider $provider): array
    {
        $next = $this->usableKeys($provider)->first();

        if (! $next instanceof ProviderApiKey) {
            throw new RuntimeException("No active keys found for provider {$provider->slug}");
        }

        $this->advance((int) $next->getKey());

        AuditLog::record('admin.provider_key.rotated', null, $provider, (string) $next->key_name);

        return $this->toArray($next);
    }

    /**
     * @param  array{api_key: string, key_name: string, key_type: string, expires_at: string|null}  $data
     */
    public function addKey(AiProvider $provider, array $data): ProviderApiKey
    {
        $key = $provider->apiKeys()->create([
            'key_name' => $data['key_name'],
            'key_type' => $data['key_type'],
            'api_key_encrypted' => $data['api_key'],
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => true,
        ]);

        AuditLog::record('admin.provider_key.created', null, $provider, (string) $key->key_name);

        return $key;
    }

    /**
     * Revoke one key. It stays on record but is never handed to the gateway
     * again.
     */
    public function deactivateKey(AiProvider $provider, int $keyId): bool
    {
        $key = ProviderApiKey::query()
            ->where('ai_provider_id', $provider->getKey())
            ->whereKey($keyId)
            ->first();

        if (! $key instanceof ProviderApiKey) {
            return false;
        }

        $key->forceFill(['is_active' => false])->save();

        AuditLog::record('admin.provider_key.revoked', null, $provider, (string) $key->key_name);

        return true;
    }

    /**
     * The gateway answered with this key, so push it to the back of the
     * rotation queue.
     */
    public function markUsed(?int $keyId): void
    {
        $this->advance($keyId);
    }

    /**
     * The key was rejected or ran out of quota. Same queue effect as a
     * success: the next request tries a different key first.
     */
    public function markExhausted(?int $keyId): void
    {
        $this->advance($keyId);
    }

    private function advance(?int $keyId): void
    {
        if ($keyId === null) {
            return;
        }

        ProviderApiKey::query()->whereKey($keyId)->update([
            'rotation_count' => DB::raw('rotation_count + 1'),
            'last_used_at' => now()->toIso8601String(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, array{id: int, name: string, expires_at: string|null, days_until_expiry: int}>
     */
    public function getExpiringKeysWithinDays(AiProvider $provider, int $days = 7): Collection
    {
        return ProviderApiKey::query()
            ->where('ai_provider_id', $provider->getKey())
            ->usable()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->orderBy('expires_at')
            ->get()
            ->map(fn (ProviderApiKey $key): array => [
                'id' => (int) $key->getKey(),
                'name' => (string) $key->key_name,
                'expires_at' => $key->expires_at?->toDateTimeString(),
                'days_until_expiry' => (int) round((float) now()->diffInDays($key->expires_at, false)),
            ]);
    }

    /**
     * @return array{rotated_count: int, failed_count: int}
     */
    public function autoRotateAllProviders(): array
    {
        $rotatedCount = 0;
        $failedCount = 0;

        AiProvider::query()
            ->where('supports_auto_key_rotation', true)
            ->get()
            ->each(function (AiProvider $provider) use (&$rotatedCount, &$failedCount): void {
                try {
                    $this->rotateToNextKey($provider);
                    $rotatedCount++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failedCount++;
                }
            });

        return [
            'rotated_count' => $rotatedCount,
            'failed_count' => $failedCount,
        ];
    }
}
