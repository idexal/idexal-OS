import { describe, expect, test } from "bun:test";

import { createBrowserTabRegistry } from "@idexal/browser-tabs";

describe("browser tab toolbar ownership", () => {
  test("a tab opened before any conversation is visible stays usable once one is", () => {
    const registry = createBrowserTabRegistry();
    registry.add({ tabId: "tab_shared", ownerSessionId: null });
    registry.select("tab_shared");
    registry.setVisibleSession("ses_alpha");

    expect(registry.onScreenTabId()).toBe("tab_shared");
    expect(registry.acceptsOnScreenInput(registry.onScreenTabId())).toBe(true);
  });

  test("a conversation drives its own on-screen tab", () => {
    const registry = createBrowserTabRegistry();
    registry.add({ tabId: "tab_alpha", ownerSessionId: "ses_alpha" });
    registry.setVisibleSession("ses_alpha");

    expect(registry.onScreenTabId()).toBe("tab_alpha");
    expect(registry.acceptsOnScreenInput()).toBe(true);
  });

  test("another conversation's tab stays closed to toolbar input", () => {
    const registry = createBrowserTabRegistry();
    registry.add({ tabId: "tab_alpha", ownerSessionId: "ses_alpha" });
    registry.add({ tabId: "tab_beta", ownerSessionId: "ses_beta" });
    registry.setVisibleSession("ses_beta");

    expect(registry.acceptsOnScreenInput("tab_alpha")).toBe(false);
    expect(registry.acceptsOnScreenInput("tab_beta")).toBe(true);
  });

  test("without an on-screen tab there is nothing to conflict with", () => {
    const registry = createBrowserTabRegistry();
    registry.setVisibleSession("ses_alpha");

    expect(registry.onScreenTabId()).toBeNull();
    expect(registry.acceptsOnScreenInput()).toBe(true);
  });
});
