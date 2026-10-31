import { describe, expect, test } from "bun:test";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import {
  gateBlocksAppRoutes,
  resolveDenSigninRedirect,
  shouldRenderPreparedRedirect,
  type DenSigninRouteInput,
} from "../src/react-app/shell/den-signin-routing";

const base: DenSigninRouteInput = {
  authChecking: false,
  isSignedIn: false,
  requireSignin: false,
  hasPreparedBootstrap: false,
  onSignin: false,
  onOnboarding: false,
  orgSelectionPending: false,
};

function route(overrides: Partial<DenSigninRouteInput>) {
  return resolveDenSigninRedirect({ ...base, ...overrides });
}

describe("den sign-in gate routing", () => {
  test("waits for the first auth check", () => {
    expect(route({ authChecking: true, requireSignin: true })).toBeNull();
  });

  test("forced sign-in still holds an unprepared, signed-out desktop at /signin", () => {
    expect(route({ requireSignin: true })).toBe("/signin");
    expect(route({ requireSignin: true, onOnboarding: true })).toBe("/signin");
    expect(route({ requireSignin: true, onSignin: true })).toBeNull();
  });

  test("forced sign-in shows a prepared, signed-out desktop its Setup complete page", () => {
    const prepared = { requireSignin: true, hasPreparedBootstrap: true };
    expect(route(prepared)).toBe("/onboarding");
    expect(route({ ...prepared, onOnboarding: true })).toBeNull();
    expect(shouldRenderPreparedRedirect({ ...base, ...prepared })).toBe(true);
  });

  test("a prepared desktop can still choose to sign in with an existing account", () => {
    expect(route({ requireSignin: true, hasPreparedBootstrap: true, onSignin: true })).toBeNull();
    expect(route({ hasPreparedBootstrap: true, onSignin: true })).toBeNull();
    expect(shouldRenderPreparedRedirect({ ...base, requireSignin: true, hasPreparedBootstrap: true, onSignin: true })).toBe(false);
  });

  test("a signed-in user leaves /signin for their signed-in home", () => {
    expect(route({ requireSignin: true, isSignedIn: true, onSignin: true })).toBe("signed-in-home");
    expect(route({ requireSignin: true, isSignedIn: true, hasPreparedBootstrap: true })).toBeNull();
    expect(route({ isSignedIn: true, onSignin: true })).toBe("/session");
  });

  test("optional sign-in keeps the existing routing", () => {
    expect(route({ onSignin: true })).toBe("/session");
    expect(route({ hasPreparedBootstrap: true })).toBe("/onboarding");
    expect(route({ onOnboarding: true })).toBe("/session");
    expect(route({ isSignedIn: true, orgSelectionPending: true })).toBe("/onboarding");
    expect(route({ isSignedIn: true })).toBeNull();
  });
});

/**
 * The gate must block, not redirect.
 *
 * A redirect leaves the requested route mounted while the navigation is
 * pending, so a deep link to a workspace screen rendered with no session. The
 * shell has to render only the sign-in surface; this is asserted against the
 * shell's own source, because that is where the guarantee lives.
 */
describe("the mandatory gate blocks the app instead of redirecting to it", () => {
  test("an unsigned session under a required sign-in blocks the routes", () => {
    expect(gateBlocksAppRoutes({ requireSignin: true, authChecking: false, isSignedIn: false })).toBe(true);
  });

  test("it never blocks while the session is still being checked", () => {
    // "Checking" is not "signed out": blocking here would flash the sign-in
    // page at every launch of a signed-in app.
    expect(gateBlocksAppRoutes({ requireSignin: true, authChecking: true, isSignedIn: false })).toBe(false);
  });

  test("it blocks only when sign-in is required, and never a signed-in session", () => {
    expect(gateBlocksAppRoutes({ requireSignin: false, authChecking: false, isSignedIn: false })).toBe(false);
    expect(gateBlocksAppRoutes({ requireSignin: true, authChecking: false, isSignedIn: true })).toBe(false);
    expect(gateBlocksAppRoutes({ requireSignin: false, authChecking: false, isSignedIn: true })).toBe(false);
  });

  test("the shell returns the sign-in surface before it ever returns the routes", () => {
    const source = readFileSync(resolve(import.meta.dir, "../src/react-app/shell/app-root.tsx"), "utf8");
    const blocked = source.indexOf("gateBlocksAppRoutes({");
    const routes = source.indexOf("{children}");

    expect(blocked).toBeGreaterThan(-1);
    expect(routes).toBeGreaterThan(-1);
    expect(blocked).toBeLessThan(routes);
  });
});
