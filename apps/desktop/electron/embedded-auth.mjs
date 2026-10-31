/**
 * The hand-off detection for the embedded sign-in window.
 *
 * `X-Frame-Options: SAMEORIGIN` stops a platform page being framed, so the
 * mandatory first-launch sign-in is a real top-level window of the app's own
 * rather than an iframe — framing rules do not apply to top-level navigation,
 * and the platform's clickjacking protection is left exactly as it is. This is
 * the one thing that has to be true for the hand-off to happen, so it lives
 * here on its own and is tested without booting Electron.
 */

/**
 * The grant a navigated URL carries, when that URL is this app's own scheme.
 *
 * @param {unknown} navigationUrl the URL the auth window is navigating to.
 * @param {string} scheme this app's protocol scheme, without the colon.
 * @returns {string | null} the URL to hand to the renderer, or null when the
 *   navigation is something else and should be left alone.
 */
export function embeddedAuthHandoffUrl(navigationUrl, scheme) {
  const value = String(navigationUrl ?? "").trim();
  if (!value) return null;

  let parsed;
  try {
    parsed = new URL(value);
  } catch {
    return null;
  }

  // Only this app's scheme, and only the den-auth grant path.
  if (parsed.protocol !== `${scheme}:`) return null;
  if (parsed.hostname !== "den-auth") return null;
  if (!parsed.searchParams.get("grant")) return null;

  return value;
}

/**
 * The URL of a navigation event, whichever the Electron version supplies.
 *
 * Recent Electron exposes the target URL on the event (`event.url`) and still
 * passes the older positional `url` argument as deprecated. Reading both keeps
 * the hand-off working across those versions instead of silently seeing an
 * undefined URL and never handing the grant back.
 *
 * @param {unknown} event the navigation event.
 * @param {unknown} positionalUrl the deprecated positional URL, if any.
 * @returns {string} the navigation URL, or "" when neither is present.
 */
export function embeddedAuthNavigationUrl(event, positionalUrl) {
  const candidate =
    typeof positionalUrl === "string" && positionalUrl
      ? positionalUrl
      : (event && typeof event === "object" && "url" in event ? event.url : null);

  return typeof candidate === "string" ? candidate : "";
}
