import {
  connectorGrantExpired,
  connectorGrantFor,
  connectorRequestFor,
  type DesktopConnectorPermissions,
  type DesktopConnectorRequest,
} from "@idexal/types/den/connector-permissions";

/**
 * What the organization decided about one connector, for the Library.
 *
 * The platform declares the scopes a connector needs and an admin approves a
 * set of them; while the feature is off no block arrives and the Library looks
 * exactly as it did before. This turns the delivered block into the three
 * things the UI needs — whether the connector still has to be approved, which
 * scopes were approved, and when that approval lapses — without the pages
 * reading the delivery shape.
 */
export type ConnectorPermissionView = {
  status: "approved" | "pending" | "revoked" | "expired";
  /** The approved scopes, in the order the admin approved them. */
  scopes: string[];
  /** When the approval lapses, or null when it lasts until it is revoked. */
  expiresAt: string | null;
};

/**
 * The slug the platform, the desktop and the catalogue all use for a preset.
 * The catalogue prefixes its id with `preset_`; the grant carries the bare slug.
 */
export function connectorSlug(presetId: string): string {
  return presetId.replace(/^preset_/, "");
}

/**
 * What to show for one connector, or null when there is nothing to say: the
 * feature is off, or the connector never asked for scopes and is unaffected.
 *
 * `now` is injectable so an approval's expiry can be tested without a clock.
 */
export function connectorPermissionView(
  state: DesktopConnectorPermissions | undefined,
  presetId: string,
  now: number = Date.now(),
): ConnectorPermissionView | null {
  if (!state) return null;

  const slug = connectorSlug(presetId);
  if (!state.scoped.includes(slug)) return null;

  const grant = connectorGrantFor(state, slug);
  if (grant?.status === "revoked") {
    return { status: "revoked", scopes: [], expiresAt: null };
  }
  // An approval that lapsed is refused even if the platform has not yet
  // flipped its status: the deadline in `expiresAt` is enough.
  if (grant?.status === "expired" || (grant?.status === "active" && connectorGrantExpired(grant.expiresAt, now))) {
    return { status: "expired", scopes: [], expiresAt: grant.expiresAt ?? null };
  }
  if (!grant || grant.status !== "active" || grant.scopes.length === 0) {
    return { status: "pending", scopes: [], expiresAt: null };
  }

  return { status: "approved", scopes: grant.scopes, expiresAt: grant.expiresAt ?? null };
}

/**
 * Whether the member may ask an admin, and whether they already have.
 *
 * A connector the organization has not approved cannot be added, and the same
 * remedy fits every one of those cases — asking an admin — so the Library
 * offers the ask while approval is pending, revoked or lapsed, rather than
 * leaving the member at a dead end. Once an ask is waiting, the row says so.
 */
export function connectorApprovalAsk(
  view: ConnectorPermissionView | null,
  requested: boolean,
): { askable: boolean; waiting: boolean } {
  const askable = view !== null && view.status !== "approved";

  return { askable, waiting: askable && requested };
}

/**
 * Whether the member has already asked an admin to approve this connector.
 *
 * The delivered block carries the member's own open asks, so the Library can
 * show "approval requested" instead of offering a button that would ask twice.
 * A missing block (feature off) means nobody can be waiting on an ask.
 */
export function connectorApprovalRequested(
  requests: readonly DesktopConnectorRequest[] | undefined,
  presetId: string,
): boolean {
  return connectorRequestFor(requests, connectorSlug(presetId)) !== undefined;
}

/**
 * A scope in the words a member reads, or the raw scope when it is one this
 * build does not know (a newer platform's kind must still be shown, never hidden).
 */
export function connectorScopeLabel(scope: string, t: (key: string, params?: Record<string, string>) => string): string {
  const value = scope.trim();

  if (value === "tools:invoke") return t("extensions.connector_scope_tools_invoke");

  if (value.startsWith("net:egress:")) {
    return t("extensions.connector_scope_net_egress", { host: value.slice("net:egress:".length) });
  }
  if (value.startsWith("fs:read:")) {
    return t("extensions.connector_scope_fs_read", { path: value.slice("fs:read:".length) });
  }
  if (value.startsWith("fs:write:")) {
    return t("extensions.connector_scope_fs_write", { path: value.slice("fs:write:".length) });
  }

  return value;
}

/**
 * A delivered ISO instant as the short date a member reads, or the raw value
 * when the build cannot parse it (never hide what the platform said).
 */
export function formatApprovalDate(expiresAt: string): string {
  const at = new Date(expiresAt);
  if (Number.isNaN(at.getTime())) return expiresAt;

  return new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(at);
}
