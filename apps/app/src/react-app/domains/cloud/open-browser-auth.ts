import { openDesktopEmbeddedAuth, openDesktopUrl } from "../../../app/lib/desktop";
import { isDesktopRuntime } from "../../../app/utils";

/**
 * Open an auth page in the system browser. Used where staying in the app is
 * not required (a re-auth prompt, the cloud settings panel).
 */
export async function tryOpenBrowserAuthUrl(url: string): Promise<boolean> {
  if (isDesktopRuntime()) {
    try {
      await openDesktopUrl(url);
      return true;
    } catch (error) {
      console.error("[den-auth] failed to open browser:", error);
      return false;
    }
  }

  return window.open(url, "_blank") !== null;
}

/**
 * Open the mandatory sign-in gate's page the way the product wants it: inside
 * a window of the app's own, so the user signs in or subscribes without ever
 * leaving the app.
 *
 * Falls back to the system browser when the embedded window cannot open — an
 * Electron side that predates the command, or a build where the platform page
 * refuses to run in an app window. The gate must always have a way through,
 * so a failure here is never fatal; the caller only uses the result to decide
 * whether to reveal the manual one-time-code path.
 */
export async function tryOpenEmbeddedAuthUrl(url: string): Promise<boolean> {
  if (!isDesktopRuntime()) {
    return window.open(url, "_blank") !== null;
  }

  if (await openDesktopEmbeddedAuth(url)) {
    return true;
  }

  try {
    await openDesktopUrl(url);
    return true;
  } catch (error) {
    console.error("[den-auth] failed to open auth page:", error);
    return false;
  }
}
