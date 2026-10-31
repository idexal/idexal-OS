<?php

namespace App\Support;

/**
 * What an agent may do on a member's computer.
 *
 * The desktop already reads an ordered list of permission rules from
 * `GET /v1/me/desktop-config` (`agentPermissions.rules`) and enforces them:
 * settings, proxies, extensions and the built-in browser on both engines,
 * commands, edits, fetches and searches on the v2 engine. Until this catalogue
 * existed the platform never sent that list, so an administrator's "Ask first"
 * or "Block" had nowhere to come from.
 *
 * Each case names the exact key the desktop's own catalogue uses
 * (`packages/types/src/den/agent-permissions.ts`), its OpenCode action, whether
 * it is a picklist or a switch, and which patterns it may hold. The admin form,
 * the stored settings and the emitted rules all derive from here.
 */
enum AgentPermission: string
{
    case Commands = 'commands';

    case FileEdits = 'fileEdits';

    case Websites = 'websites';

    case WebSearch = 'webSearch';

    case LocalSkills = 'localSkills';

    case LocalMcpServers = 'localMcpServers';

    case DesktopSettings = 'desktopSettings';

    case DesktopProxy = 'desktopProxy';

    case DesktopExtensions = 'desktopExtensions';

    case DesktopBrowser = 'desktopBrowser';

    /** The OpenCode permission action this family maps onto. */
    public function action(): string
    {
        return match ($this) {
            self::Commands => 'shell',
            self::FileEdits => 'edit',
            self::Websites => 'webfetch',
            self::WebSearch => 'websearch',
            self::LocalSkills => 'skill',
            self::LocalMcpServers => 'mcp',
            self::DesktopSettings => 'settings',
            self::DesktopProxy => 'proxy',
            self::DesktopExtensions => 'extensions',
            self::DesktopBrowser => 'browser',
        };
    }

    /**
     * A picklist of three decisions, or a switch that can only allow or block.
     * Only a picklist can ask first.
     */
    public function isChoice(): bool
    {
        return $this === self::Commands || $this === self::FileEdits || $this === self::Websites;
    }

    /**
     * The kind of pattern its two lists hold, or null when it has no lists.
     * Only the picklist families that name something (a command, a site) have
     * lists; a switch either allows everything or nothing.
     */
    public function patternKind(): ?string
    {
        return match ($this) {
            self::Commands => 'command',
            self::Websites => 'website',
            default => null,
        };
    }

    /** The group the admin form shows this row under. */
    public function section(): string
    {
        return match ($this) {
            self::Commands => 'commands',
            self::FileEdits => 'files',
            self::Websites, self::WebSearch => 'web',
            self::LocalSkills, self::LocalMcpServers => 'extensions',
            default => 'desktop',
        };
    }

    /** The decisions this row offers, in the order the picklist shows them. */
    public function decisions(): array
    {
        return $this->isChoice() ? ['allow', 'ask', 'deny'] : ['allow', 'deny'];
    }

    /** The operator-facing name of the row. */
    public function labelKey(): string
    {
        return 'ui.admin.agent_permissions.'.$this->slug().'_name';
    }

    /** The one sentence under the row. */
    public function bodyKey(): string
    {
        return 'ui.admin.agent_permissions.'.$this->slug().'_body';
    }

    /** The snake_case form used for language keys. */
    public function slug(): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->value) ?? $this->value);
    }

    /**
     * The row a stored settings key belongs to, for audit labels.
     */
    public static function fromSettingKey(string $key): ?self
    {
        return self::tryFrom($key);
    }
}
