<?php

namespace App\Support;

/**
 * An organization's agent permissions, and the rules its members' apps receive.
 *
 * The rules are the contract: the desktop checks them in order and the last
 * match decides, so a blocked pattern comes last and always wins. They are
 * built here rather than in the API controller so the admin preview, the emit
 * and the audit all describe the same list.
 *
 * This is the platform's "everyone" policy. Teams do not exist here, so the
 * one scope is the whole organization; the resolver in
 * `packages/types/src/den/agent-permissions.ts` reduces to the same rules when
 * only "everyone" is set, which is what keeps the two implementations honest.
 */
final class AgentPermissionPolicy
{
    /** Who set a rule, as the desktop shows it in a denial. */
    public const SOURCE = 'Everyone';

    /** Matches the desktop's own list cap, so a stored list cannot be longer. */
    public const MAX_PATTERNS = 100;

    private const MAX_PATTERN_LENGTH = 300;

    /** The decisions a stored setting may hold. */
    public const DECISIONS = ['allow', 'ask', 'deny'];

    /**
     * Settings in stored form: valid decisions and patterns only, trimmed,
     * deduplicated and with nothing empty.
     *
     * A switch saved as "ask" is stored as "block", because a switch can only
     * allow or block; settings saved before a family lost its lists keep
     * blocking when any blocked name survived.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, array{decision?: string, allow?: list<string>, block?: list<string>}>
     */
    public static function normalize(array $value): array
    {
        $settings = [];

        foreach (AgentPermission::cases() as $permission) {
            $setting = self::normalizeSetting($permission, $value[$permission->value] ?? null);

            if ($setting !== null) {
                $settings[$permission->value] = $setting;
            }
        }

        return $settings;
    }

    /**
     * The ordered rules a member's apps receive. A decision other than allow
     * becomes a `*` rule, its allowed patterns follow as exceptions, and
     * blocked patterns come last. A family that allows contributes nothing, so
     * the member's own desktop settings apply.
     *
     * @param  array<string, mixed>  $settings  stored (already normalized) settings
     * @return list<array{action: string, resource: string, effect: string, source: string}>
     */
    public static function rules(array $settings): array
    {
        $rules = [];

        foreach (AgentPermission::cases() as $permission) {
            $setting = is_array($settings[$permission->value] ?? null) ? $settings[$permission->value] : null;

            if ($setting === null) {
                continue;
            }

            $decision = self::decision($permission, $setting['decision'] ?? null);

            if ($decision !== 'allow') {
                $rules[] = self::rule($permission->action(), '*', $decision);

                foreach (self::patterns($setting['allow'] ?? null) as $pattern) {
                    $rules[] = self::rule($permission->action(), $pattern, 'allow');
                }
            }

            foreach (self::patterns($setting['block'] ?? null) as $pattern) {
                $rules[] = self::rule($permission->action(), $pattern, 'deny');
            }
        }

        // Any command can change files, so blocking file edits blocks commands
        // too. Last, so no allowed command pattern reopens it.
        $edits = $settings[AgentPermission::FileEdits->value] ?? null;

        if (is_array($edits) && ($edits['decision'] ?? null) === 'deny') {
            $rules[] = self::rule('shell', '*', 'deny');
        }

        return $rules;
    }

    /**
     * A permission's setting as one line, for the audit table. Both sides of a
     * change are described the same way, so a row reads as a real before/after
     * even when only one list moved.
     *
     * @param  array{decision?: string, allow?: list<string>, block?: list<string>}|null  $setting
     */
    public static function summary(AgentPermission $permission, ?array $setting): string
    {
        if ($setting === null) {
            return __('ui.admin.agent_permissions.unrestricted');
        }

        $parts = [__('ui.admin.agent_permissions.decision_'.self::decision($permission, $setting['decision'] ?? null))];
        $allow = count(self::patterns($setting['allow'] ?? null));
        $block = count(self::patterns($setting['block'] ?? null));

        if ($allow > 0) {
            $parts[] = __('ui.admin.agent_permissions.allowed_count', ['count' => $allow]);
        }

        if ($block > 0) {
            $parts[] = __('ui.admin.agent_permissions.blocked_count', ['count' => $block]);
        }

        return implode(' · ', $parts);
    }

    /**
     * Only the permissions whose effect actually moved, as field => before/after
     * summaries. An unchanged permission stays out of the audit entry.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: string, to: string}>
     */
    public static function changes(array $before, array $after): array
    {
        $changes = [];

        foreach (AgentPermission::cases() as $permission) {
            $was = self::stored($before[$permission->value] ?? null);
            $now = self::stored($after[$permission->value] ?? null);

            $from = self::summary($permission, $was);
            $to = self::summary($permission, $now);

            if ($from !== $to) {
                $changes[$permission->value] = ['from' => $from, 'to' => $to];
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

        foreach ($changes as $key => $change) {
            $permission = AgentPermission::fromSettingKey((string) $key);
            $label = $permission instanceof AgentPermission ? __($permission->labelKey()) : (string) $key;

            $parts[] = $label.': '.$change['from'].' → '.$change['to'];
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array{decision?: string, allow?: list<string>, block?: list<string>}|null
     */
    private static function normalizeSetting(AgentPermission $permission, mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $submitted = $value['decision'] ?? null;
        $blocked = $permission->patternKind() === null && self::patterns($value['block'] ?? null) !== [];
        // A switch cannot ask first, and one that blocked any names blocks
        // everything: both are stored as the strictest decision it can hold.
        $stored = ! $permission->isChoice() && ($submitted === 'ask' || $blocked) ? 'deny' : $submitted;
        $decision = is_string($stored) && in_array($stored, $permission->decisions(), true) ? $stored : null;

        // Allow is the default, so storing it would make an untouched
        // permission look configured and hide a real change from the audit.
        if ($decision === 'allow') {
            $decision = null;
        }
        $allow = self::normalizePatterns($permission, $value['allow'] ?? null);
        $block = self::normalizePatterns($permission, $value['block'] ?? null);

        if ($decision === null && $allow === [] && $block === []) {
            return null;
        }

        return [
            ...($decision !== null ? ['decision' => $decision] : []),
            ...($allow !== [] ? ['allow' => $allow] : []),
            ...($block !== [] ? ['block' => $block] : []),
        ];
    }

    /** A stored decision that the row still offers, or allow when it sets none. */
    private static function decision(AgentPermission $permission, mixed $value): string
    {
        return is_string($value) && in_array($value, $permission->decisions(), true) ? $value : 'allow';
    }

    /**
     * @return list<string>
     */
    private static function patterns(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /**
     * @param  array{decision?: string, allow?: list<string>, block?: list<string>}|null  $setting
     */
    private static function stored(mixed $setting): ?array
    {
        return is_array($setting) && $setting !== [] ? $setting : null;
    }

    /**
     * @return array{action: string, resource: string, effect: string, source: string}
     */
    private static function rule(string $action, string $resource, string $effect): array
    {
        return [
            'action' => $action,
            'resource' => $resource,
            'effect' => $effect,
            'source' => self::SOURCE,
        ];
    }

    /**
     * @return list<string>
     */
    private static function normalizePatterns(AgentPermission $permission, mixed $value): array
    {
        if ($permission->patternKind() === null || ! is_array($value)) {
            return [];
        }

        $patterns = [];

        foreach ($value as $entry) {
            $pattern = is_string($entry) ? self::normalizePattern($permission, $entry) : null;

            if ($pattern !== null) {
                $patterns[$pattern] = $pattern;
            }
        }

        return array_slice(array_values($patterns), 0, self::MAX_PATTERNS);
    }

    /**
     * A pattern in its stored form, or null when it is not a valid pattern of
     * that family. The admin form validates each line with this before saving,
     * so a typo is rejected instead of silently dropped.
     */
    public static function normalizePattern(AgentPermission $permission, string $input): ?string
    {
        $value = trim($input);

        if ($value === '' || mb_strlen($value) > self::MAX_PATTERN_LENGTH || preg_match('/[\r\n]/', $value) === 1) {
            return null;
        }

        return $permission->patternKind() === 'website' ? self::normalizeWebsite($value) : $value;
    }

    /**
     * A site pattern in its stored form, or null when it is not one.
     *
     * The desktop matches these itself, so the stored form has to be the one
     * its parser produces: `example.com.` is `example.com`, a trailing `/` or
     * `/*` adds nothing because paths match as prefixes, and a port is written
     * without leading zeros.
     */
    private static function normalizeWebsite(string $value): ?string
    {
        if ($value === '*') {
            return '*';
        }

        if (preg_match('/[\s?#@\\\\]/', $value) === 1) {
            return null;
        }

        $scheme = null;
        $rest = $value;

        if (preg_match('#^([A-Za-z][A-Za-z0-9+.-]*)://#', $value, $match) === 1) {
            $candidate = strtolower($match[1]);

            if ($candidate !== 'http' && $candidate !== 'https') {
                return null;
            }

            $scheme = $candidate;
            $rest = substr($value, strlen($match[0]));
        }

        $slash = strpos($rest, '/');
        $authority = $slash === false ? $rest : substr($rest, 0, $slash);
        $path = $slash === false ? '' : substr($rest, $slash);

        if (str_ends_with($path, '/*')) {
            $path = substr($path, 0, -2);
        }

        $path = rtrim($path, '/');

        if (str_contains($path, '*')) {
            return null;
        }

        $subdomains = str_starts_with($authority, '*.');
        $hostAndPort = $subdomains ? substr($authority, 2) : $authority;

        if ($hostAndPort === '' || str_contains($hostAndPort, '*')) {
            return null;
        }

        $parsed = parse_url('http://'.$hostAndPort);

        if (! is_array($parsed) || ! isset($parsed['host']) || isset($parsed['user']) || isset($parsed['pass'])) {
            return null;
        }

        if (($parsed['path'] ?? '/') !== '/') {
            return null;
        }

        $host = strtolower($parsed['host']);
        $host = str_ends_with($host, '.') ? substr($host, 0, -1) : $host;

        if ($host === '') {
            return null;
        }

        $port = isset($parsed['port']) ? ':'.(int) $parsed['port'] : '';

        return ($scheme !== null ? $scheme.'://' : '').($subdomains ? '*.' : '').$host.$port.$path;
    }
}
