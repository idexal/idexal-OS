import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { describe, expect, test } from "bun:test";
import { renderToStaticMarkup } from "react-dom/server";

// Another suite in the same run may have registered the DOM already.
if (!GlobalRegistrator.isRegistered) {
  GlobalRegistrator.register();
}

import { DenSignInSurface } from "../src/react-app/domains/cloud/den-signin-surface";

/**
 * The first screen a public install shows. It is the whole point of the
 * mandatory sign-in: there is a way in (sign in, or create an account with a
 * plan) and no way to skip it. This asserts the UI contract of the gate —
 * a welcome, a sign-in action, a paste-a-code fallback, and nothing that reads
 * as "use it without an account".
 */
function renderFullscreen(overrides: Partial<Parameters<typeof DenSignInSurface>[0]> = {}) {
  return renderToStaticMarkup(
    <DenSignInSurface
      variant="fullscreen"
      appName="idexal Os"
      developerMode={false}
      baseUrl="https://os.idexal.com"
      baseUrlDraft="https://os.idexal.com"
      baseUrlError={null}
      statusMessage={null}
      authError={null}
      authBusy={false}
      baseUrlBusy={false}
      sessionBusy={false}
      manualAuthOpen={false}
      manualAuthInput=""
      onBaseUrlDraftInput={() => {}}
      onResetBaseUrl={() => {}}
      onApplyBaseUrl={() => {}}
      onOpenControlPlane={() => {}}
      onOpenBrowserAuth={() => {}}
      onToggleManualAuth={() => {}}
      onManualAuthInput={() => {}}
      onSubmitManualAuth={() => {}}
      {...overrides}
    />,
  );
}

describe("the mandatory sign-in screen", () => {
  test("welcomes the person and offers both ways in, with no third option", () => {
    const html = renderFullscreen();

    expect(html).toContain("Welcome to");
    expect(html).toContain("idexal Os");
    // Both paths are visible on the gate itself: an existing account signs in,
    // a new one starts a subscription and picks a plan on the platform page.
    expect(html).toContain(">Sign in<");
    expect(html).toContain("Create account");
    expect(html).toContain("choose a plan");
  });

  test("offers a paste-a-code fallback when the browser cannot bounce back", () => {
    const html = renderFullscreen();
    expect(html).toContain("Paste sign-in code");
  });

  test("carries no skip, guest or offline-only affordance", () => {
    const html = renderFullscreen().toLowerCase();

    for (const forbidden of [
      "without an account",
      "without sign in",
      "without signing in",
      "skip for now",
      "continue as guest",
      "use offline",
      "use without cloud",
      "try it now",
    ]) {
      expect(html).not.toContain(forbidden);
    }
  });

  test("shows the sign-in link notice when the browser handoff is offered", () => {
    const html = renderFullscreen({ signinFallbackUrl: "https://os.idexal.com/desktop/authorize?grant=abc" });
    expect(html).toContain("os.idexal.com");
  });

  test("surfaces a sign-in error instead of silently continuing", () => {
    const html = renderFullscreen({ authError: "Failed to complete idexal Os Cloud sign-in." });
    expect(html).toContain("Failed to complete idexal Os Cloud sign-in.");
  });
});
