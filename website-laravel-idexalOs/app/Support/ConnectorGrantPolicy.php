<?php

namespace App\Support;

use App\Models\ConnectorGrant;
use Illuminate\Support\Carbon;

/**
 * An organization's connector grants: what each installed connector may do,
 * and the revision it was approved at.
 *
 * The catalogue's own `permissions` list is the request; a grant is the
 * operator's answer. Both are held to the same canonical scope form here, so a
 * scope the catalogue declares is exactly a scope an operator can approve, and
 * the desktop receives one vocabulary on both sides.
 */
final class ConnectorGrantPolicy
{
    /** Matches the desktop's own per-connector cap. */
    public const MAX_SCOPES = 50;

    /** An approval that lasts until an operator revokes it. */
    public const EXPIRY_NEVER = 'never';

    /**
     * The durations an operator can choose, in days. A closed set keeps the
     * value a form submits checkable and the lease it writes predictable.
     *
     * @var list<int>
     */
    public const EXPIRY_DAYS = [7, 30, 90];

    /**
     * Scopes in stored form: valid, canonical, deduplicated, and nothing empty.
     *
     * @return list<string>
     */
    public static function normalizeScopes(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $scopes = [];

        foreach ($raw as $entry) {
            $scope = ConnectorScope::parse($entry);

            if ($scope !== null) {
                $scopes[$scope->value()] = $scope->value();
            }
        }

        return array_slice(array_values($scopes), 0, self::MAX_SCOPES);
    }

    /**
     * The scopes a marketplace entry asks for, in catalogue order.
     *
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    public static function requestedScopes(array $entry): array
    {
        return self::normalizeScopes($entry['permissions'] ?? []);
    }

    /** One line describing a grant, for the audit table. */
    public static function summary(?ConnectorGrant $grant): string
    {
        if (! $grant instanceof ConnectorGrant) {
            return __('ui.admin.connectors.summary_none');
        }

        if ($grant->status === ConnectorGrant::STATUS_REVOKED) {
            $reason = is_string($grant->reason) ? trim($grant->reason) : '';

            return $reason === ''
                ? __('ui.admin.connectors.summary_revoked')
                : __('ui.admin.connectors.summary_revoked_reason', ['reason' => $reason]);
        }

        $lease = $grant->lease_expires_at;
        $count = count((array) $grant->scopes);

        if ($grant->status === ConnectorGrant::STATUS_EXPIRED) {
            return $lease instanceof Carbon
                ? __('ui.admin.connectors.summary_expired_on', ['date' => $lease->translatedFormat('d M Y H:i')])
                : __('ui.admin.connectors.summary_expired');
        }

        // An approval with a duration says when it lapses, so shortening or
        // dropping a lease is a change an operator can see in the audit log.
        return $lease instanceof Carbon
            ? __('ui.admin.connectors.summary_active_expiring', [
                'count' => $count,
                'date' => $lease->translatedFormat('d M Y H:i'),
            ])
            : __('ui.admin.connectors.summary_active', ['count' => $count]);
    }

    /**
     * The lease a chosen duration writes, or null for "until revoked". Only the
     * offered durations are accepted; anything else means it never expires.
     */
    public static function expiryFromChoice(mixed $choice, ?Carbon $from = null): ?Carbon
    {
        $days = is_scalar($choice) && in_array((string) $choice, array_map('strval', self::EXPIRY_DAYS), true)
            ? (int) $choice
            : 0;

        return $days > 0 ? ($from ?? now())->copy()->addDays($days) : null;
    }

    /** The duration choice a stored lease maps back to, for the form default. */
    public static function expiryChoiceFor(?ConnectorGrant $grant): string
    {
        $lease = $grant?->lease_expires_at;

        if (! $lease instanceof Carbon) {
            return self::EXPIRY_NEVER;
        }

        $days = (int) ceil(max(0.0, now()->diffInDays($lease, false)));

        foreach (self::EXPIRY_DAYS as $option) {
            if ($days <= $option) {
                return (string) $option;
            }
        }

        return (string) self::EXPIRY_DAYS[array_key_last(self::EXPIRY_DAYS)];
    }

    /**
     * Only the connectors whose grant actually moved, as slug => before/after
     * summaries, so a save that changes nothing leaves no audit row behind.
     *
     * The summaries are taken before and after the write, not from two sets of
     * models: the row an operator edits is the row loaded for the current
     * state, so comparing that same instance with itself would report every
     * revocation as no change.
     *
     * @param  array<string, string>  $before  slug => summary
     * @param  array<string, string>  $after  slug => summary
     * @return array<string, array{from: string, to: string}>
     */
    public static function changes(array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $slug) {
            $from = $before[$slug] ?? self::summary(null);
            $to = $after[$slug] ?? self::summary(null);

            if ($from !== $to) {
                $changes[(string) $slug] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    /**
     * A one-line summary of the same diff for the audit description. Null when
     * nothing moved.
     *
     * @param  array<string, array{from: string, to: string}>  $changes
     */
    public static function describe(array $changes): ?string
    {
        if ($changes === []) {
            return null;
        }

        $parts = [];

        foreach ($changes as $slug => $change) {
            $parts[] = $slug.': '.$change['from'].' → '.$change['to'];
        }

        return implode(', ', $parts);
    }
}
