<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The platform → desktop sync signal.
 *
 * Anything an admin can change that affects what a connected desktop may
 * offer (providers, models, combos, plans, settings, proxy providers,
 * organizations, memberships or extensions) announces itself here. The latest
 * change is kept for the inventory-events stream and a shared sequence is
 * advanced. The inventory revision folds that sequence in, so desktops re-read
 * their inventory right away instead of waiting out their polling interval.
 */
final class DesktopSync
{
    private const LATEST_KEY = 'desktop.sync.latest';

    private const SEQUENCE_KEY = 'desktop.sync.sequence';

    /**
     * Record a change and advance the sync sequence.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function announce(string $action, array $payload = []): void
    {
        Cache::put(self::LATEST_KEY, [
            'event' => 'desktop-sync',
            'action' => $action,
            ...$payload,
            'timestamp' => now()->toIso8601String(),
        ], now()->addMinutes(10));

        // The database store's increment() returns false and leaves the key
        // missing instead of creating it, so the sequence would never start
        // and the first change would be invisible to a connected desktop.
        // Seed it once; add() only writes when the key is absent.
        Cache::add(self::SEQUENCE_KEY, 0, now()->addYears(10));
        Cache::increment(self::SEQUENCE_KEY);
    }

    /** The current sync sequence: a change in it means the inventory moved. */
    public static function sequence(): int
    {
        return (int) Cache::get(self::SEQUENCE_KEY, 0);
    }

    /**
     * Newest change stamp per table that feeds a desktop inventory. Empty
     * tables read as 'none' so a missing row never looks like a real value.
     *
     * @return array<string, string>
     */
    public static function stamps(): array
    {
        $tables = [
            'ai_providers',
            'ai_models',
            'extension_installations',
            'settings',
            'plans',
            'proxy_providers',
            'organizations',
            'organization_user',
        ];

        $stamps = [];

        foreach ($tables as $table) {
            $stamps[$table] = (string) (DB::table($table)->max('updated_at') ?? 'none');
        }

        return $stamps;
    }

    /** One stamp covering every inventory table plus the sync sequence. */
    public static function revision(): string
    {
        return md5((string) json_encode([
            'stamps' => self::stamps(),
            'sequence' => self::sequence(),
        ]));
    }
}
