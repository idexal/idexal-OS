import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { describe, expect, mock, test } from "bun:test";
import { renderToStaticMarkup } from "react-dom/server";

// Another suite in the same run may have registered the DOM already; a second
// register() throws, which would fail an unrelated file.
if (!GlobalRegistrator.isRegistered) {
  GlobalRegistrator.register();
}

let desktopConfigOverride: Record<string, unknown> = {};

mock.module("../src/react-app/domains/cloud/desktop-config-provider", () => ({
  useDesktopConfig: () => ({
    config: desktopConfigOverride,
    loading: false,
    freshConfigStatus: "ready" as const,
    known: true,
    refresh: async () => {},
    refreshFresh: async () => desktopConfigOverride,
    checkRestriction: () => false,
    connectPolicySync: { state: "idle" as const },
  }),
  useDesktopAccessGrant: () => {
    const access = desktopConfigOverride.desktopAccess as
      | { required?: boolean; granted?: boolean; planSlug?: string | null }
      | undefined;
    return {
      required: access?.required ?? false,
      granted: access?.granted ?? true,
      planSlug: access?.planSlug ?? null,
    };
  },
  useOrgRestrictions: () => desktopConfigOverride,
  useCheckDesktopRestriction: () => () => false,
  useDesktopRestriction: () => false,
  DesktopConfigProvider: ({ children }: { children: React.ReactNode }) => children,
}));

mock.module("../src/react-app/domains/cloud/den-auth-provider", () => ({
  useDenAuth: () => ({
    status: "signed_in" as const,
    user: { id: "user-1", email: "member@example.com", name: "Member" },
    verifiedIdentity: null,
    error: null,
    isSignedIn: true,
    refresh: async () => {},
  }),
}));

mock.module("../src/react-app/kernel/platform", () => ({
  PlatformProvider: ({ children }: { children: React.ReactNode }) => children,
  usePlatform: () => ({
    openLink: () => {},
    platform: "desktop",
    capabilities: { canOpenExternal: true },
  }),
  createDefaultPlatform: () => ({}),
}));

import { PlanRequiredGate } from "../src/react-app/domains/cloud/plan-required-gate";

function renderGate(config: Record<string, unknown>): string {
  desktopConfigOverride = config;
  return renderToStaticMarkup(<PlanRequiredGate><div data-testid="the-app-shell">the app shell</div></PlanRequiredGate>);
}

describe("PlanRequiredGate", () => {
  test("renders children when no desktopAccess block is delivered", () => {
    const html = renderGate({});
    expect(html).toContain('data-testid="the-app-shell"');
    expect(html).not.toContain('data-testid="plan-required-gate"');
  });

  test("renders children when granted is true", () => {
    const html = renderGate({
      desktopAccess: { required: true, granted: true, planSlug: "pro" },
    });
    expect(html).toContain('data-testid="the-app-shell"');
    expect(html).not.toContain('data-testid="plan-required-gate"');
  });

  test("renders children when required is false and granted is true", () => {
    const html = renderGate({
      desktopAccess: { required: false, granted: true },
    });
    expect(html).toContain('data-testid="the-app-shell"');
    expect(html).not.toContain('data-testid="plan-required-gate"');
  });

  test("shows the pick-a-plan screen when required and not granted, naming the plan", () => {
    const html = renderGate({
      desktopAccess: { required: true, granted: false, planSlug: "basic" },
    });
    expect(html).toContain('data-testid="plan-required-gate"');
    expect(html).toContain('data-plan-slug="basic"');
    expect(html).not.toContain('data-testid="the-app-shell"');
  });

  test("shows the pick-a-plan screen with an empty slug when no plan is active", () => {
    const html = renderGate({
      desktopAccess: { required: true, granted: false },
    });
    expect(html).toContain('data-testid="plan-required-gate"');
    expect(html).toContain('data-plan-slug=""');
    expect(html).not.toContain('data-testid="the-app-shell"');
  });

  test("the blocked screen offers sign-out and retry actions", () => {
    const html = renderGate({
      desktopAccess: { required: true, granted: false, planSlug: "basic" },
    });
    const buttons = (html.match(/<button/g) ?? []).length;
    expect(buttons).toBeGreaterThanOrEqual(2);
  });
});
