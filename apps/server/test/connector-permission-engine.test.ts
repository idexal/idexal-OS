import assert from "node:assert/strict";
import { test } from "node:test";

import { decideConnectorPermission } from "@idexal/types/den/connector-permissions-runtime";

import { ConnectorPermissionRegistry } from "../src/connector-permission-engine.js";

const SLUG = "github-mcp";

function state(
  revision: number,
  grant: { status: "active" | "revoked" | "expired"; scopes: string[]; expiresAt?: string | null } | null,
) {
  return {
    revision,
    scoped: [SLUG],
    grants: grant ? [{ slug: SLUG, revision, status: grant.status, scopes: grant.scopes, expiresAt: grant.expiresAt ?? null }] : [],
  };
}

test("a connector that asks for scopes is refused until it is approved", () => {
  const registry = new ConnectorPermissionRegistry();
  registry.apply(state(0, null));

  const denial = registry.denial(SLUG);

  assert.match(String(denial), /has not approved permissions/);
});

test("an approved connector runs", () => {
  const registry = new ConnectorPermissionRegistry();
  registry.apply(state(1, { status: "active", scopes: ["tools:invoke"] }));

  assert.equal(registry.denial(SLUG), null);
});

test("a revoked connector is refused with the revocation reason", () => {
  const registry = new ConnectorPermissionRegistry();
  registry.apply(state(1, { status: "active", scopes: ["tools:invoke"] }));
  registry.apply(state(2, { status: "revoked", scopes: [] }));

  assert.match(String(registry.denial(SLUG)), /revoked access/);
});

test("a connector that asks for no scopes is never refused", () => {
  const registry = new ConnectorPermissionRegistry();
  registry.apply(state(0, null));

  assert.equal(registry.denial("plain-mcp"), null);
});

test("while the feature is off nothing is refused", () => {
  const registry = new ConnectorPermissionRegistry();

  assert.equal(registry.active, false);
  assert.equal(registry.denial(SLUG), null);
});

test("a stale permission set cannot restore a revoked grant", () => {
  const registry = new ConnectorPermissionRegistry();
  registry.apply(state(1, { status: "active", scopes: ["tools:invoke"] }));
  registry.apply(state(2, { status: "revoked", scopes: [] }));

  // A late delivery that had not yet seen the revocation.
  assert.equal(registry.apply(state(1, { status: "active", scopes: ["tools:invoke"] })), false);
  assert.match(String(registry.denial(SLUG)), /revoked access/);
});

test("a newer revision always replaces the one held", () => {
  const registry = new ConnectorPermissionRegistry();
  registry.apply(state(2, { status: "revoked", scopes: [] }));

  assert.equal(registry.apply(state(3, { status: "active", scopes: ["tools:invoke"] })), true);
  assert.equal(registry.denial(SLUG), null);
});

test("a delivery with no block does not revoke what is held", () => {
  const registry = new ConnectorPermissionRegistry();
  registry.apply(state(1, { status: "revoked", scopes: [] }));

  assert.equal(registry.apply(undefined), false);
  assert.match(String(registry.denial(SLUG)), /revoked access/);
});

test("clearing the registry stops enforcement", () => {
  const registry = new ConnectorPermissionRegistry();
  registry.apply(state(1, { status: "revoked", scopes: [] }));
  registry.clear();

  assert.equal(registry.active, false);
  assert.equal(registry.denial(SLUG), null);
});

test("an expired approval is refused with the expiry reason", () => {
  const registry = new ConnectorPermissionRegistry();
  registry.apply(state(1, { status: "active", scopes: ["tools:invoke"] }));
  registry.apply(state(2, { status: "expired", scopes: [], expiresAt: "2026-01-01T00:00:00+00:00" }));

  assert.match(String(registry.denial(SLUG)), /has expired/);
});

test("a set mid-run whose deadline has passed is refused without a status flip", () => {
  // The platform has not swept the row yet, so the delivered status is still
  // active. Enforcement reads the deadline itself, which is what lets a
  // revocation-equivalent lapse reach a running desktop immediately.
  const decision = decideConnectorPermission(
    state(1, { status: "active", scopes: ["tools:invoke"], expiresAt: "2026-05-31T23:59:59Z" }),
    SLUG,
    Date.parse("2026-06-01T00:00:00Z"),
  );

  assert.equal(decision.allowed, false);
  assert.match(String(decision.allowed ? "" : decision.reason), /has expired/);
});

test("an approval whose deadline is still ahead is allowed", () => {
  const decision = decideConnectorPermission(
    state(1, { status: "active", scopes: ["tools:invoke"], expiresAt: "2026-06-30T00:00:00Z" }),
    SLUG,
    Date.parse("2026-06-01T00:00:00Z"),
  );

  assert.equal(decision.allowed, true);
});

test("an unreadable expiry never cuts a working connector off", () => {
  const decision = decideConnectorPermission(
    state(1, { status: "active", scopes: ["tools:invoke"], expiresAt: "not-a-date" }),
    SLUG,
    Date.parse("2030-01-01T00:00:00Z"),
  );

  assert.equal(decision.allowed, true);
});
