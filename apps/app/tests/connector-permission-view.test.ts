import { describe, expect, test } from "bun:test";

import type { DesktopConnectorPermissions } from "@idexal/types/den/connector-permissions";

import {
  connectorApprovalAsk,
  connectorApprovalRequested,
  connectorPermissionView,
  connectorScopeLabel,
  connectorSlug,
  formatApprovalDate,
} from "../src/react-app/domains/settings/connector-permission-view";

const SLUG = "github-mcp";

function state(
  grants: Array<{ slug: string; status: "active" | "revoked" | "expired"; scopes: string[]; expiresAt?: string | null }>,
  scoped: string[] = [SLUG],
  revision = 1,
): DesktopConnectorPermissions {
  return {
    revision,
    scoped,
    grants: grants.map((grant) => ({ ...grant, revision, expiresAt: grant.expiresAt ?? null })),
  };
}

describe("connectorSlug", () => {
  test("strips the catalogue preset prefix", () => {
    expect(connectorSlug(`preset_${SLUG}`)).toBe(SLUG);
  });
});

describe("connectorPermissionView", () => {
  test("says nothing while the feature is off", () => {
    expect(connectorPermissionView(undefined, `preset_${SLUG}`)).toBeNull();
  });

  test("says nothing for a connector that asks for no scopes", () => {
    expect(connectorPermissionView(state([], []), `preset_${SLUG}`)).toBeNull();
  });

  test("waits for approval when the connector asked for scopes and has no grant", () => {
    expect(connectorPermissionView(state([]), `preset_${SLUG}`))
      .toEqual({ status: "pending", scopes: [], expiresAt: null });
  });

  test("waits for approval when an active grant approves no scopes", () => {
    expect(connectorPermissionView(state([{ slug: SLUG, status: "active", scopes: [] }]), `preset_${SLUG}`))
      .toEqual({ status: "pending", scopes: [], expiresAt: null });
  });

  test("surfaces the approved scopes", () => {
    const scopes = ["net:egress:api.githubcopilot.com", "tools:invoke"];
    expect(connectorPermissionView(state([{ slug: SLUG, status: "active", scopes }]), `preset_${SLUG}`))
      .toEqual({ status: "approved", scopes, expiresAt: null });
  });

  test("reports a revocation, even with no scopes left", () => {
    expect(connectorPermissionView(state([{ slug: SLUG, status: "revoked", scopes: [] }]), `preset_${SLUG}`))
      .toEqual({ status: "revoked", scopes: [], expiresAt: null });
  });

  test("an unknown connector in scoped has no grant yet", () => {
    expect(connectorPermissionView(state([], ["other-mcp"]), `preset_${SLUG}`)).toBeNull();
  });

  test("an approval the platform already called expired reads as expired", () => {
    expect(connectorPermissionView(state([{ slug: SLUG, status: "expired", scopes: [], expiresAt: "2026-01-01T00:00:00+00:00" }]), `preset_${SLUG}`))
      .toEqual({ status: "expired", scopes: [], expiresAt: "2026-01-01T00:00:00+00:00" });
  });

  test("an active approval whose deadline has passed reads as expired", () => {
    const now = Date.parse("2026-06-01T00:00:00Z");
    const view = connectorPermissionView(
      state([{ slug: SLUG, status: "active", scopes: ["tools:invoke"], expiresAt: "2026-05-31T23:59:59Z" }]),
      `preset_${SLUG}`,
      now,
    );

    expect(view).toEqual({ status: "expired", scopes: [], expiresAt: "2026-05-31T23:59:59Z" });
  });

  test("an active approval not yet lapsed stays approved and carries its deadline", () => {
    const now = Date.parse("2026-06-01T00:00:00Z");
    const view = connectorPermissionView(
      state([{ slug: SLUG, status: "active", scopes: ["tools:invoke"], expiresAt: "2026-06-30T00:00:00Z" }]),
      `preset_${SLUG}`,
      now,
    );

    expect(view).toEqual({ status: "approved", scopes: ["tools:invoke"], expiresAt: "2026-06-30T00:00:00Z" });
  });
});

describe("connectorApprovalAsk", () => {
  test("offers the ask whenever the connector is not approved", () => {
    // A pending, revoked or lapsed approval is the same dead end to the member,
    // and the same remedy: ask an admin.
    for (const status of ["pending", "revoked", "expired"] as const) {
      expect(connectorApprovalAsk({ status, scopes: [], expiresAt: null }, false))
        .toEqual({ askable: true, waiting: false });
    }
  });

  test("an approved connector needs no ask", () => {
    expect(connectorApprovalAsk({ status: "approved", scopes: [], expiresAt: null }, false))
      .toEqual({ askable: false, waiting: false });
  });

  test("a connector the feature never touched needs no ask", () => {
    expect(connectorApprovalAsk(null, false)).toEqual({ askable: false, waiting: false });
  });

  test("a waiting ask shows instead of a second button", () => {
    expect(connectorApprovalAsk({ status: "revoked", scopes: [], expiresAt: null }, true))
      .toEqual({ askable: true, waiting: true });
    // A stale "requested" flag cannot suppress an Approve-only state: an
    // approved connector never shows the ask.
    expect(connectorApprovalAsk({ status: "approved", scopes: [], expiresAt: null }, true))
      .toEqual({ askable: false, waiting: false });
  });
});

describe("connectorApprovalRequested", () => {
  const requests = (status: "open" | "approved" | "dismissed") => [
    { slug: SLUG, status, scopes: ["tools:invoke"], requestedAt: null },
  ];

  test("is false while the feature is off", () => {
    expect(connectorApprovalRequested(undefined, `preset_${SLUG}`)).toBe(false);
  });

  test("is true for the member's own open ask", () => {
    expect(connectorApprovalRequested(requests("open"), `preset_${SLUG}`)).toBe(true);
  });

  test("an answered ask no longer counts as waiting", () => {
    expect(connectorApprovalRequested(requests("approved"), `preset_${SLUG}`)).toBe(false);
    expect(connectorApprovalRequested(requests("dismissed"), `preset_${SLUG}`)).toBe(false);
  });

  test("another connector's ask never matches", () => {
    expect(connectorApprovalRequested(requests("open"), "preset_notion-mcp")).toBe(false);
  });
});

describe("connectorScopeLabel", () => {  const t = (key: string, params?: Record<string, string>) =>
    params ? `${key}:${Object.values(params).join(",")}` : key;

  test("names each known scope kind", () => {
    expect(connectorScopeLabel("tools:invoke", t)).toBe("extensions.connector_scope_tools_invoke");
    expect(connectorScopeLabel("net:egress:api.githubcopilot.com", t)).toBe("extensions.connector_scope_net_egress:api.githubcopilot.com");
    expect(connectorScopeLabel("fs:read:/Users/me/Documents", t)).toBe("extensions.connector_scope_fs_read:/Users/me/Documents");
    expect(connectorScopeLabel("fs:write:/tmp", t)).toBe("extensions.connector_scope_fs_write:/tmp");
  });

  test("shows an unknown kind verbatim rather than hiding it", () => {
    expect(connectorScopeLabel("future:kind:x", t)).toBe("future:kind:x");
  });
});

describe("formatApprovalDate", () => {
  test("formats a delivered instant", () => {
    expect(formatApprovalDate("2026-06-30T00:00:00Z")).not.toBe("");
  });

  test("shows an unparseable value verbatim rather than a wrong date", () => {
    expect(formatApprovalDate("not-a-date")).toBe("not-a-date");
  });
});
