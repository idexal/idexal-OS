<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The desktop experiences this platform can switch on.
 *
 * A desktop reads its switches from GET /v1/org (signed in) and GET /v1/features
 * (signed out) and hides a feature whose key is missing or false. Until those
 * endpoints published these keys, every experience below stayed off no matter
 * what an operator wanted, because the platform had no way to say "on".
 *
 * Each case names the exact key the desktop reads, so the API response, the
 * admin checkbox and the audit row all derive from one place.
 *
 * `engineV2Upgrade` is deliberately absent: this platform ships the v1 engine
 * and never offers the upgrade notice.
 */
enum DesktopSwitch: string
{
    case AppBuildProgress = 'appBuildProgress';

    case BackgroundShellTasks = 'backgroundShellTasks';

    case OfflineRecovery = 'offlineRecovery';

    case LastTurnModelFooter = 'lastTurnModelFooter';

    case ModelRemovalRecovery = 'modelRemovalRecovery';

    case RememberLastOrganization = 'rememberLastOrganization';

    case SecureSecretFields = 'secureSecretFields';

    case RemoteSessionSubagents = 'remoteSessionSubagents';

    /**
     * Connector permission scopes: what an installed connector may read,
     * write, reach and invoke, approved by an operator on /admin/connectors.
     * Unlike the other switches this one also opens a platform screen, so it
     * ships off until the operator turns it on here.
     */
    case ConnectorPermissionScopes = 'connectorPermissionScopes';

    /**
     * Desktop access requires a plan: a signed-in user opens the desktop app
     * only while the organization's current plan grants desktop access. The
     * public installer always requires sign-in with or without this switch;
     * this one decides whether sign-in alone is enough, or a plan is also
     * needed. Ships off so a deployment that has not adopted the gate treats
     * every plan as granting access.
     */
    case AccountRequiredForDesktopAccess = 'accountRequiredForDesktopAccess';

    /** The settings row the operator toggle writes. */
    public function settingKey(): string
    {
        return 'desktop_switch_'.$this->slug().'_enabled';
    }

    /** The name of the checkbox the Features panel submits. */
    public function fieldKey(): string
    {
        return 'desktop_switch_'.$this->slug();
    }

    /** The snake_case form used for settings rows and language keys. */
    public function slug(): string
    {
        return Str::snake($this->value);
    }

    /** The operator-facing name shown in the panel, the form, and the log. */
    public function labelKey(): string
    {
        return 'ui.admin.features.desktop.'.$this->slug().'_name';
    }

    /** The one-line explanation on the Features panel card. */
    public function bodyKey(): string
    {
        return 'ui.admin.features.desktop.'.$this->slug().'_body';
    }

    /** The switch a stored settings row belongs to, for audit labels. */
    public static function fromSettingKey(string $key): ?self
    {
        foreach (self::cases() as $switch) {
            if ($switch->settingKey() === $key) {
                return $switch;
            }
        }

        return null;
    }
}
