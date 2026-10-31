import { z } from "zod";

/**
 * Connector permissions: what each installed connector may do on a member's
 * computer.
 *
 * The marketplace declares the scopes a connector needs (`fs:read:<path>`,
 * `fs:write:<path>`, `net:egress:<host>`, `tools:invoke`); an organization's
 * admin approves a set of them and can revoke it later. The platform delivers
 * the answer at `GET /v1/me/desktop-config` as `connectorPermissions`, one
 * block for the organization:
 *
 *   - `revision` orders the whole set. It only ever increases, so a desktop
 *     replaces what it holds only when the incoming revision is newer. A
 *     stale inventory event that arrives late with an older revision — even
 *     one carrying the permission set from before a revocation — is ignored.
 *   - `grants[].status` is `active`, `revoked` or `expired`. A connector with
 *     no grant is not approved: the desktop refuses it until an admin
 *     approves it.
 *   - `grants[].expiresAt` is the instant an approval stops being valid, or
 *     null when it lasts until it is revoked. An approval whose `expiresAt`
 *     has passed is refused even before the platform flips its status, so an
 *     approval that was meant to lapse cannot outlive its deadline just
 *     because the next inventory sync has not run.
 *
 * Connectors that declare no scopes are not represented and are unaffected,
 * which keeps every connection that predates this feature working unchanged.
 * While the feature is off the platform sends no block and nothing is
 * enforced.
 */

/** A grant's life cycle. A revocation is a state, never a deleted row. */
export const connectorGrantStatusSchema = z.enum(["active", "revoked", "expired"]);

export type ConnectorGrantStatus = z.infer<typeof connectorGrantStatusSchema>;

/** One connector's approved scopes, at the revision they were approved at. */
export const desktopConnectorGrantSchema = z
  .object({
    slug: z.string().trim().min(1).max(190),
    revision: z.number().int().nonnegative(),
    status: connectorGrantStatusSchema,
    scopes: z.array(z.string().trim().min(1).max(300)).max(50),
    /**
     * When the approval stops being valid, as an ISO instant, or null when it
     * lasts until it is revoked. Defaulted so a platform from before this
     * field existed still parses.
     */
    expiresAt: z.string().trim().min(1).max(40).nullable().default(null),
  })
  .meta({ ref: "DenConnectorGrant" });

export type DesktopConnectorGrant = z.infer<typeof desktopConnectorGrantSchema>;

/** The organization's whole permission set, delivered in the desktop config. */
export const desktopConnectorPermissionsSchema = z
  .object({
    revision: z.number().int().nonnegative(),
    /** Every catalogue connector that asks for scopes, whether or not a grant exists. */
    scoped: z.array(z.string().trim().min(1).max(190)).max(500).default([]),
    grants: z.array(desktopConnectorGrantSchema).max(500),
  })
  .meta({ ref: "DenDesktopConnectorPermissions" });

export type DesktopConnectorPermissions = z.infer<typeof desktopConnectorPermissionsSchema>;

/**
 * One member's ask for a connector the organization has not approved yet.
 *
 * The platform delivers the member's own open asks at `GET /v1/me/desktop-config`
 * as `connectorRequests`, so the Library can show "approval requested" instead
 * of offering the same ask twice. A request is not a grant: an admin still
 * approves the connector, and while the feature is off the platform sends no
 * block and the Library is unchanged.
 */
export const desktopConnectorRequestSchema = z
  .object({
    slug: z.string().trim().min(1).max(190),
    status: z.enum(["open", "approved", "dismissed"]),
    /**
     * The scopes the catalogue declares for the connector. Defaulted and
     * lenient so a platform from before this block still parses.
     */
    scopes: z.array(z.string().trim().min(1).max(300)).max(50).default([]),
    requestedAt: z.string().trim().max(40).nullable().default(null),
  })
  .meta({ ref: "DenConnectorRequest" });

export type DesktopConnectorRequest = z.infer<typeof desktopConnectorRequestSchema>;

/**
 * The member's open ask for a connector, or undefined when they never asked or
 * the ask was already answered. Only an `open` ask counts: an approved or
 * dismissed one is history, and the grant or the operator's answer supersedes it.
 */
export function connectorRequestFor(
  requests: readonly DesktopConnectorRequest[] | undefined,
  slug: string,
): DesktopConnectorRequest | undefined {
  return requests?.find((request) => request.slug === slug && request.status === "open");
}

/**
 * Parse a delivered request list, or undefined when it is not one. Lenient on
 * unknown fields so a newer platform never breaks an older desktop.
 */
export function normalizeConnectorRequests(value: unknown): DesktopConnectorRequest[] | undefined {
  const parsed = z.array(desktopConnectorRequestSchema).max(500).safeParse(value);
  return parsed.success ? parsed.data : undefined;
}

/**
 * Parse a delivered set, or undefined when it is not one. Lenient on unknown
 * fields (a newer platform must not disable enforcement on an older desktop)
 * and strict on the fields it does understand.
 */
export function normalizeConnectorPermissions(value: unknown): DesktopConnectorPermissions | undefined {
  const parsed = desktopConnectorPermissionsSchema.safeParse(value);
  return parsed.success ? parsed.data : undefined;
}

/**
 * Whether a delivered set may replace the one held. A missing current set is
 * always replaced — on a fresh start the platform is authoritative — and a
 * higher revision always wins. An equal or older revision is refused, which is
 * the whole point: a stale inventory response can never restore a revoked
 * grant.
 */
export function shouldApplyConnectorPermissions(
  incoming: DesktopConnectorPermissions,
  current: DesktopConnectorPermissions | undefined,
): boolean {
  return current === undefined || incoming.revision > current.revision;
}

/** The grant held for a connector, or undefined when it was never approved. */
export function connectorGrantFor(
  state: DesktopConnectorPermissions | undefined,
  slug: string,
): DesktopConnectorGrant | undefined {
  return state?.grants.find((grant) => grant.slug === slug);
}

export type ConnectorPermissionDecision =
  | { allowed: true }
  | { allowed: false; reason: string };

/**
 * Whether an approval's expiry has passed at `now` (milliseconds since the
 * epoch). A missing or unparseable instant never expires: an expiry the
 * desktop cannot read must not silently cut a working connector off.
 */
export function connectorGrantExpired(
  expiresAt: string | null | undefined,
  now: number = Date.now(),
): boolean {
  if (!expiresAt) return false;
  const at = Date.parse(expiresAt);
  return Number.isFinite(at) && at <= now;
}

/**
 * The decision for one connector. Whether the connector asks for scopes at all
 * is read from the delivered set, not from the caller: a connector that is not
 * in `scoped` is outside this feature and is always allowed, and one that is
 * keeps working only while an active grant approves it.
 *
 * `now` is injectable so expiry can be tested without waiting for a clock.
 */
export function decideConnectorPermission(
  state: DesktopConnectorPermissions | undefined,
  slug: string,
  now: number = Date.now(),
): ConnectorPermissionDecision {
  const grant = connectorGrantFor(state, slug);

  if (grant?.status === "revoked") {
    return { allowed: false, reason: connectorPermissionDenialMessage(slug) };
  }

  if (grant?.status === "expired") {
    return { allowed: false, reason: connectorPermissionExpiredMessage(slug) };
  }

  if (!state || !state.scoped.includes(slug)) {
    return { allowed: true };
  }

  if (!grant || grant.status !== "active" || grant.scopes.length === 0) {
    return { allowed: false, reason: `Your organization has not approved permissions for the connector "${slug}" yet.` };
  }

  if (connectorGrantExpired(grant.expiresAt, now)) {
    return { allowed: false, reason: connectorPermissionExpiredMessage(slug) };
  }

  return { allowed: true };
}

/** The sentence a member sees when a connector is refused, or null when it is allowed. */
export function connectorPermissionDenial(
  state: DesktopConnectorPermissions | undefined,
  slug: string,
  now: number = Date.now(),
): string | null {
  const decision = decideConnectorPermission(state, slug, now);
  return decision.allowed ? null : decision.reason;
}

function connectorPermissionDenialMessage(slug: string): string {
  return `Your organization revoked access for the connector "${slug}".`;
}

function connectorPermissionExpiredMessage(slug: string): string {
  return `Your organization's approval for the connector "${slug}" has expired. Ask an admin to approve it again.`;
}
