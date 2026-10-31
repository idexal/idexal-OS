<?php

namespace App\Services;

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Support\Facades\Cache;

/**
 * Remembers which provider + model targets just failed so the gateway tries
 * them last instead of first.
 *
 * A dead upstream used to be re-attempted at the head of every request, so
 * every message paid for a timeout before a backup answered. After a run of
 * retryable failures a target is marked cooling for a short while, and the
 * fallback order moves it behind the healthy candidates. Nothing is ever
 * dropped: when every target is cooling the first one is still tried, so a
 * workspace with a single provider behaves exactly as before.
 *
 * State lives in the cache, so no schema change is needed and the marker
 * expires on its own even if the operator never returns.
 */
class ProviderCooldownService
{
    /** Retryable failures within the window before a target cools down. */
    public const FAILURES_BEFORE_COOLDOWN = 2;

    /** How long failures keep counting toward the threshold. */
    public const FAILURE_WINDOW_SECONDS = 300;

    /** How long a cooling target stays behind its healthy peers. */
    public const COOLDOWN_SECONDS = 120;

    /**
     * Display-only index of cooling targets, target key => expiry timestamp.
     * Routing never reads it, so a lost entry only hides a badge.
     */
    private const INDEX_KEY = 'gateway:provider-cooldown:index';

    /**
     * Count one retryable failure for this target.
     *
     * @return bool whether the target is now cooling down
     */
    public function recordFailure(AiProvider $provider, AiModel $model): bool
    {
        $target = self::target($provider, $model);
        $counterKey = self::failureKey($target);
        $failures = (int) Cache::get($counterKey, 0) + 1;

        Cache::put($counterKey, $failures, self::FAILURE_WINDOW_SECONDS);

        if ($failures < self::FAILURES_BEFORE_COOLDOWN) {
            return false;
        }

        $expiresAt = now()->addSeconds(self::COOLDOWN_SECONDS)->getTimestamp();

        Cache::put(self::markerKey($target), $expiresAt, self::COOLDOWN_SECONDS);
        $this->remember($target, $expiresAt);

        return true;
    }

    /**
     * A target answered: forget both the cooldown and the failure run.
     */
    public function recordSuccess(AiProvider $provider, AiModel $model): void
    {
        $this->forget(self::target($provider, $model));
    }

    public function isCoolingDown(AiProvider $provider, AiModel $model): bool
    {
        $expiresAt = Cache::get(self::markerKey(self::target($provider, $model)));

        return is_numeric($expiresAt) && (int) $expiresAt > now()->getTimestamp();
    }

    /**
     * Cooling targets per provider id, for the admin screen.
     *
     * @return array<string, int>
     */
    public function coolingCountsByProvider(): array
    {
        $counts = [];

        foreach (array_keys($this->liveIndex()) as $target) {
            $providerId = self::providerIdFromTarget($target);
            $counts[$providerId] = ($counts[$providerId] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Return a provider's targets to the front immediately — used after an
     * operator fixes it and a health probe answers green.
     *
     * @return int how many targets were released
     */
    public function clearForProvider(AiProvider $provider): int
    {
        $providerId = (string) $provider->getKey();
        $index = $this->liveIndex();
        $released = 0;

        foreach (array_keys($index) as $target) {
            if (self::providerIdFromTarget($target) !== $providerId) {
                continue;
            }

            $this->forgetStored($target);
            unset($index[$target]);
            $released++;
        }

        if ($released > 0) {
            $this->storeIndex($index);
        }

        return $released;
    }

    private function forget(string $target): void
    {
        $this->forgetStored($target);

        $index = $this->liveIndex();

        if (array_key_exists($target, $index)) {
            unset($index[$target]);
            $this->storeIndex($index);
        }
    }

    private function forgetStored(string $target): void
    {
        Cache::forget(self::markerKey($target));
        Cache::forget(self::failureKey($target));
    }

    private function remember(string $target, int $expiresAt): void
    {
        $index = $this->liveIndex();
        $index[$target] = $expiresAt;

        $this->storeIndex($index);
    }

    /**
     * @param  array<string, int>  $index
     */
    private function storeIndex(array $index): void
    {
        Cache::put(self::INDEX_KEY, $index, self::COOLDOWN_SECONDS * 2);
    }

    /**
     * @return array<string, int>
     */
    private function liveIndex(): array
    {
        $index = Cache::get(self::INDEX_KEY, []);

        if (! is_array($index)) {
            return [];
        }

        $now = now()->getTimestamp();
        $live = [];

        foreach ($index as $target => $expiresAt) {
            if (is_string($target) && is_numeric($expiresAt) && (int) $expiresAt > $now) {
                $live[$target] = (int) $expiresAt;
            }
        }

        return $live;
    }

    private static function target(AiProvider $provider, AiModel $model): string
    {
        // The provider key is a ULID, so it can never contain the separator.
        return $provider->getKey().'|'.$model->getKey();
    }

    private static function providerIdFromTarget(string $target): string
    {
        return explode('|', $target, 2)[0];
    }

    private static function markerKey(string $target): string
    {
        return 'gateway:provider-cooldown:target:'.$target;
    }

    private static function failureKey(string $target): string
    {
        return 'gateway:provider-cooldown:failures:'.$target;
    }
}
