import assert from "node:assert/strict";
import { test } from "node:test";

import {
  CLOUD_DESKTOP_DISTRIBUTION,
  ENTERPRISE_DESKTOP_DISTRIBUTION,
  PUBLIC_DESKTOP_DISTRIBUTION,
  desktopActivationRequired,
  enterprisePreactivationCommandAllowed,
  resolveDesktopDistribution,
} from "../electron/desktop-distribution.mjs";

/**
 * The desktop app is a client of the platform, not a standalone tool: every
 * shipped flavor must require a platform sign-in before the shell opens. This
 * is the "no anonymous use" promise the product is built on, so it is asserted
 * at the build level rather than left to a runtime default.
 */
test("every distribution requires sign-in", () => {
  assert.equal(PUBLIC_DESKTOP_DISTRIBUTION.requireSignin, true);
  assert.equal(CLOUD_DESKTOP_DISTRIBUTION.requireSignin, true);
  assert.equal(ENTERPRISE_DESKTOP_DISTRIBUTION.requireSignin, true);
});

test("the public flavor is what an unflavoured build resolves to, and it requires sign-in", () => {
  const publicBuild = resolveDesktopDistribution({
    isPackaged: true,
    packageFlavor: undefined,
    environmentFlavor: undefined,
  });
  assert.equal(publicBuild.flavor, "public");
  assert.equal(publicBuild.requireSignin, true, "the public installer must never open without an account");

  const devNoFlavor = resolveDesktopDistribution({
    isPackaged: false,
    packageFlavor: undefined,
    environmentFlavor: undefined,
  });
  assert.equal(devNoFlavor.requireSignin, true);
});

test("only enterprise requires activation", () => {
  assert.equal(PUBLIC_DESKTOP_DISTRIBUTION.requireActivation, false);
  assert.equal(CLOUD_DESKTOP_DISTRIBUTION.requireActivation, false);
  assert.equal(ENTERPRISE_DESKTOP_DISTRIBUTION.requireActivation, true);
});

test("an unactivated enterprise install is held, and a stamp releases it", () => {
  assert.equal(desktopActivationRequired(ENTERPRISE_DESKTOP_DISTRIBUTION, {}), true);
  assert.equal(
    desktopActivationRequired(ENTERPRISE_DESKTOP_DISTRIBUTION, {
      enterpriseActivation: { activatedAt: "2026-10-29T00:00:00.000Z", denBaseUrl: "https://den.example.test" },
    }),
    false,
  );
});

test("the public and cloud flavors are never activation-gated", () => {
  assert.equal(desktopActivationRequired(PUBLIC_DESKTOP_DISTRIBUTION, {}), false);
  assert.equal(desktopActivationRequired(CLOUD_DESKTOP_DISTRIBUTION, {}), false);
});

test("the mandatory sign-in gate can open its window before any activation exists", () => {
  // The gate is the first thing a public install shows, so the command that
  // opens the platform inside the app has to be callable while the app is
  // still unactivated. A plain renderer command is not.
  assert.equal(enterprisePreactivationCommandAllowed("openEmbeddedAuth"), true);
  assert.equal(enterprisePreactivationCommandAllowed("connectLinkAccept"), true);
  assert.equal(enterprisePreactivationCommandAllowed("engineStart"), false);
});
