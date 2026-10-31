<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The diff between the settings a form submits and what was stored before it.
 *
 * Admin settings screens save a whole page at once, so "settings updated" in the
 * audit log says nothing: the operator cannot tell a currency change from a
 * payment gateway going live. This compares the two states and reports only the
 * keys that moved, with secrets replaced by a marker so a rotation is visible
 * without the value ever being written to the log.
 */
final class SettingsChanges
{
    /** Stands in for a stored secret, so a change is visible but the value is not. */
    public const SECRET_MARKER = '***';

    /**
     * The stored values for the keys a save is about to write, read before the
     * write so they still describe the previous state.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    public static function snapshot(array $keys): array
    {
        $before = [];

        foreach ($keys as $key) {
            $before[$key] = Setting::get($key);
        }

        return $before;
    }

    /**
     * Only the keys whose stored value actually changed.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<string>  $secretKeys
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function between(array $before, array $after, array $secretKeys = []): array
    {
        $changes = [];

        foreach ($after as $key => $value) {
            $previous = $before[$key] ?? null;

            if (! self::differs($previous, $value)) {
                continue;
            }

            $changes[$key] = in_array($key, $secretKeys, true)
                ? ['from' => self::mask($previous), 'to' => self::mask($value)]
                : ['from' => $previous, 'to' => $value];
        }

        return $changes;
    }

    /**
     * A form posts every field as a string while a stored value keeps the type it
     * was written with, so compare on the value rather than on the type. An unset
     * key and an empty one are the same state: a first save that writes "" or
     * false over "nothing" is not an edit an operator made.
     */
    private static function differs(mixed $before, mixed $after): bool
    {
        if (self::isEmpty($before) && self::isEmpty($after)) {
            return false;
        }

        if (is_numeric($before) && is_numeric($after)) {
            return (float) $before !== (float) $after;
        }

        return self::comparable($before) !== self::comparable($after);
    }

    private static function isEmpty(mixed $value): bool
    {
        return in_array($value, [null, '', false, 0, 0.0, '0'], true);
    }

    private static function comparable(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            $value === null => '',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }

    private static function mask(mixed $value): ?string
    {
        return filled($value) ? self::SECRET_MARKER : null;
    }
}
