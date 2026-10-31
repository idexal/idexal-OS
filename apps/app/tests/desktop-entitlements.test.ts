import { describe, expect, test } from "bun:test";

import { normalizeDesktopConfig } from "@idexal/types/den/desktop-policies";

/**
 * The two entitlement blocks the desktop gate reads. Both must be permissive
 * when the platform does not send them — a platform from before the field
 * existed must never lock a user out — and must carry the explicit decision
 * when it does.
 */
describe("desktopAccess normalization", () => {
  test("a missing block is not restrictive", () => {
    const config = normalizeDesktopConfig({});
    expect(config.desktopAccess).toBeUndefined();
  });

  test("carries the platform decision", () => {
    const config = normalizeDesktopConfig({
      desktopAccess: { required: true, granted: false, planSlug: "basic" },
    });
    expect(config.desktopAccess).toEqual({ required: true, granted: false, planSlug: "basic" });
  });

  test("accepts a granted decision without a plan slug", () => {
    const config = normalizeDesktopConfig({ desktopAccess: { required: true, granted: true } });
    expect(config.desktopAccess?.granted).toBe(true);
    expect(config.desktopAccess?.planSlug).toBeUndefined();
  });

  test("drops a block with an unexpected shape instead of throwing", () => {
    const config = normalizeDesktopConfig({ desktopAccess: { granted: "yes" } });
    expect(config.desktopAccess).toBeUndefined();
  });
});

describe("providerEntitlements normalization", () => {
  test("a missing block is not restrictive", () => {
    const config = normalizeDesktopConfig({});
    expect(config.providerEntitlements).toBeUndefined();
  });

  test("carries the provider list and the BYOK decision", () => {
    const config = normalizeDesktopConfig({
      providerEntitlements: {
        enabled: true,
        byok: false,
        plan: "pro",
        providers: [
          { slug: "openai", name: "OpenAI", driver: "openai" },
          { slug: "anthropic", name: "Anthropic" },
        ],
      },
    });

    expect(config.providerEntitlements?.enabled).toBe(true);
    expect(config.providerEntitlements?.byok).toBe(false);
    expect(config.providerEntitlements?.plan).toBe("pro");
    expect(config.providerEntitlements?.providers?.map((provider) => provider.slug)).toEqual([
      "openai",
      "anthropic",
    ]);
  });

  test("accepts a null plan and no providers between plans", () => {
    const config = normalizeDesktopConfig({
      providerEntitlements: { enabled: false, byok: true, plan: null, providers: [] },
    });
    expect(config.providerEntitlements?.plan).toBeNull();
    expect(config.providerEntitlements?.providers).toEqual([]);
  });

  test("drops a provider without a slug instead of throwing", () => {
    const config = normalizeDesktopConfig({
      providerEntitlements: { byok: true, providers: [{ name: "No slug" }] },
    });
    expect(config.providerEntitlements).toBeUndefined();
  });
});
