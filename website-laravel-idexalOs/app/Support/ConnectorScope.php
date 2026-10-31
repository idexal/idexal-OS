<?php

namespace App\Support;

/**
 * One thing a connector may do, as the marketplace declares it and an operator
 * approves it: read a path, write a path, reach a host on the network, or
 * invoke the connector's own tools.
 *
 * The stored form is canonical (`net:egress:api.example.com`), so the approval,
 * the desktop's enforcement and the audit row all compare the same string. A
 * scope this parser rejects is never stored and never approved.
 */
final class ConnectorScope
{
    public const KIND_FS_READ = 'fs:read';

    public const KIND_FS_WRITE = 'fs:write';

    public const KIND_NET_EGRESS = 'net:egress';

    public const KIND_TOOLS_INVOKE = 'tools:invoke';

    /** @var list<string> */
    public const KINDS = [
        self::KIND_FS_READ,
        self::KIND_FS_WRITE,
        self::KIND_NET_EGRESS,
        self::KIND_TOOLS_INVOKE,
    ];

    public const MAX_LENGTH = 300;

    private function __construct(
        public readonly string $kind,
        public readonly string $argument = '',
    ) {}

    /** The stored form: `kind:argument`, or the bare kind when it takes none. */
    public function value(): string
    {
        return $this->argument === '' ? $this->kind : $this->kind.':'.$this->argument;
    }

    /** The scope in its stored form, or null when it is not a valid scope. */
    public static function parse(mixed $raw): ?self
    {
        if (! is_string($raw)) {
            return null;
        }

        $value = trim($raw);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH || preg_match('/[\r\n\t]/', $value) === 1) {
            return null;
        }

        if ($value === self::KIND_TOOLS_INVOKE) {
            return new self(self::KIND_TOOLS_INVOKE);
        }

        if (preg_match('/^(fs:(?:read|write)):(.+)$/', $value, $match) === 1) {
            $path = self::normalizePath($match[2]);

            return $path === null ? null : new self($match[1], $path);
        }

        if (preg_match('/^net:egress:(.+)$/', $value, $match) === 1) {
            $host = self::normalizeHost($match[1]);

            return $host === null ? null : new self(self::KIND_NET_EGRESS, $host);
        }

        return null;
    }

    /** The operator-facing sentence for one scope. */
    public function label(): string
    {
        return match ($this->kind) {
            self::KIND_FS_READ => __('ui.admin.connectors.scope_fs_read', ['path' => $this->argument]),
            self::KIND_FS_WRITE => __('ui.admin.connectors.scope_fs_write', ['path' => $this->argument]),
            self::KIND_NET_EGRESS => __('ui.admin.connectors.scope_net_egress', ['host' => $this->argument]),
            default => __('ui.admin.connectors.scope_tools_invoke'),
        };
    }

    /**
     * A filesystem path in its stored form, or null when it is not one. Only
     * absolute (`/…`) and home (`~…`) paths are accepted, `..` segments are
     * refused so an approved path cannot be walked out of, and runs of slashes
     * collapse so two spellings of one path cannot be approved twice.
     */
    private static function normalizePath(string $value): ?string
    {
        $path = str_replace('\\', '/', trim($value));
        $path = preg_replace('#/{2,}#', '/', $path) ?? $path;

        if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
            return null;
        }

        if ($path[0] !== '/' && $path[0] !== '~') {
            return null;
        }

        if ($path !== '/' && $path !== '~') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? null : $path;
    }

    /**
     * A host in its stored form, or null when it is not one. A matching port is
     * kept, leading zeros dropped; a leading `*.` stays to allow subdomains.
     */
    private static function normalizeHost(string $value): ?string
    {
        $host = strtolower(trim($value));

        if ($host === '' || preg_match('/[\s?#@\\\\\/]/', $host) === 1) {
            return null;
        }

        $subdomains = str_starts_with($host, '*.');
        $hostAndPort = $subdomains ? substr($host, 2) : $host;

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

        $name = rtrim(strtolower($parsed['host']), '.');

        if ($name === '') {
            return null;
        }

        $port = isset($parsed['port']) ? ':'.(int) $parsed['port'] : '';

        return ($subdomains ? '*.' : '').$name.$port;
    }
}
