// Connector permissions (packages/types/src/den/connector-permissions.ts) as the
// server enforces them. An organization's admins approve what each installed
// connector may do and can revoke it later; the platform delivers that answer
// in the desktop config. This module turns it into a decision the server can
// apply, and — the reason it exists at all — remembers the last revision it
// accepted so a stale delivery can never bring a revoked grant back.
//
// It has no engine SDK or server dependency so the v2 plugin can bundle it.
import {
  connectorPermissionDenial,
  normalizeConnectorPermissions,
  shouldApplyConnectorPermissions,
  type DesktopConnectorPermissions,
} from "@idexal/types/den/connector-permissions-runtime";

/** The connector permission set in a verified desktop policy, or undefined. */
export function connectorPermissionsOf(
  policy: { connectorPermissions?: unknown } | undefined,
): DesktopConnectorPermissions | undefined {
  return normalizeConnectorPermissions(policy?.connectorPermissions);
}

/**
 * The last permission set accepted for one sign-in.
 *
 * Revisions are monotonic, so the guard is a single comparison: a delivery
 * whose revision is not newer is refused. A reconnect that replays an old
 * permission set — including the one from before a revocation — is exactly
 * what this refuses, which is the invariant the feature exists to hold.
 */
export class ConnectorPermissionRegistry {
  private current: DesktopConnectorPermissions | undefined;

  /** The set held, for the app to show what is approved and what was revoked. */
  get state(): DesktopConnectorPermissions | undefined {
    return this.current;
  }

  /**
   * Accept a delivered set when it is newer than the one held, and report
   * whether it was accepted. A missing set leaves what is held untouched: the
   * platform stops sending the block when the feature is off, and that must
   * not be read as "revoke everything".
   */
  apply(incoming: DesktopConnectorPermissions | undefined): boolean {
    if (incoming === undefined) return false;
    if (!shouldApplyConnectorPermissions(incoming, this.current)) return false;
    this.current = incoming;
    return true;
  }

  /** Whether the set held may be applied at all (only while the feature is on). */
  get active(): boolean {
    return this.current !== undefined;
  }

  /** Drop the stored set (sign-out, or a different account). */
  clear(): void {
    this.current = undefined;
  }

  /**
   * Why the connector is refused right now, or null. Whether the connector
   * asks for scopes comes from the delivered set, so a client cannot widen its
   * own access by claiming a connector asks for none.
   */
  denial(slug: string): string | null {
    return connectorPermissionDenial(this.current, slug);
  }
}
