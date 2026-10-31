import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { describe, expect, test } from "bun:test";

// Another suite in the same run may have registered the DOM already.
if (!GlobalRegistrator.isRegistered) {
  GlobalRegistrator.register();
}

import { buildDenAuthUrl } from "../src/app/lib/den";
import { openDesktopEmbeddedAuth } from "../src/app/lib/desktop";

/**
 * The mandatory sign-in gate opens the platform inside a window of the app's
 * own. Two things have to hold for that to work from the app side: the URL must
 * carry the embed flag, and the bridge must refuse (rather than throw) when the
 * embedded window is unavailable, so the gate can fall back to a browser.
 */
describe("the embedded sign-in gate", () => {
  test("the auth URL asks the platform for an automatic hand-back", () => {
    const url = new URL(buildDenAuthUrl("https://os.idexal.com", "sign-up", { desktopEmbed: true }));

    expect(url.searchParams.get("mode")).toBe("sign-up");
    expect(url.searchParams.get("desktopAuth")).toBe("1");
    expect(url.searchParams.get("desktopEmbed")).toBe("1");
  });

  test("a plain browser hand-off never sets the embed flag", () => {
    const url = new URL(buildDenAuthUrl("https://os.idexal.com", "sign-in"));

    expect(url.searchParams.get("desktopAuth")).toBe("1");
    expect(url.searchParams.get("desktopEmbed")).toBeNull();
  });

  test("opening the embedded window reports failure instead of throwing when there is no bridge", async () => {
    delete (window as unknown as { __IDEXAL_ELECTRON__?: unknown }).__IDEXAL_ELECTRON__;

    await expect(openDesktopEmbeddedAuth("https://os.idexal.com/desktop/authorize")).resolves.toBe(false);
  });

  test("opening the embedded window reports what the bridge returns", async () => {
    const calls: Array<[string, ...unknown[]]> = [];
    (window as unknown as { __IDEXAL_ELECTRON__?: unknown }).__IDEXAL_ELECTRON__ = {
      invokeDesktop: async (command: string, ...args: unknown[]) => {
        calls.push([command, ...args]);
        return { ok: true, reused: false };
      },
    };

    await expect(openDesktopEmbeddedAuth("https://os.idexal.com/login")).resolves.toBe(true);
    expect(calls).toEqual([["openEmbeddedAuth", "https://os.idexal.com/login"]]);

    delete (window as unknown as { __IDEXAL_ELECTRON__?: unknown }).__IDEXAL_ELECTRON__;
  });
});
