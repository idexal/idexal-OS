import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { test } from "node:test";
import { fileURLToPath } from "node:url";

/**
 * Installer-branding guard.
 *
 * The Windows wizard art is baked into its own files, so when the product's
 * mark and palette changed it kept shipping the previous logo and a hardcoded
 * version — a build that looked abandoned before it was even run. The art is
 * regenerated from the committed brand mark (`scripts/generate-installer-branding.py`),
 * and this test is what stops it drifting out of date again: the wizard must
 * carry the current lime accent, must not carry the retired teal, and the
 * licence and welcome copy must not promise anonymous use the app no longer
 * offers.
 */

const desktopRoot = resolve(dirname(fileURLToPath(import.meta.url)), "..");

// Brand tokens from the site stylesheet.
const LIME = [0x7d, 0xff, 0x00]; // --color-lime #7dff00
// The palette this product retired; its presence means an old asset shipped.
const RETIRED_TEAL = [
  [0x1b, 0x76, 0x62], // old sidebar rule
  [0x23, 0xd3, 0xa6], // old header rule
];

function readAsset(name) {
  return readFileSync(resolve(desktopRoot, "build", name));
}

/** BMP stores width/height as little-endian int32 at offsets 18 and 22. */
function bmpSize(buffer) {
  return { width: buffer.readInt32LE(18), height: buffer.readInt32LE(22) };
}

function containsSequence(buffer, bytes) {
  outer: for (let i = 0; i + bytes.length <= buffer.length; i += 1) {
    for (let j = 0; j < bytes.length; j += 1) {
      if (buffer[i + j] !== bytes[j]) continue outer;
    }
    return true;
  }
  return false;
}

test("the installer sidebar is the standard MUI2 size and carries the brand accent", () => {
  const sidebar = readAsset("installerSidebar.bmp");
  assert.deepEqual(bmpSize(sidebar), { width: 164, height: 314 });
  assert.ok(containsSequence(sidebar, LIME), "the sidebar must carry the idexal Os lime accent");
});

test("the installer header is the standard MUI2 size and carries the brand accent", () => {
  const header = readAsset("installerHeader.bmp");
  assert.deepEqual(bmpSize(header), { width: 150, height: 57 });
  assert.ok(containsSequence(header, LIME), "the header must carry the idexal Os lime accent");
});

test("no installer art still ships the retired palette", () => {
  for (const name of ["installerSidebar.bmp", "installerHeader.bmp"]) {
    const buffer = readAsset(name);
    for (const teal of RETIRED_TEAL) {
      assert.ok(
        !containsSequence(buffer, teal),
        `${name} still contains the retired teal (${teal.map((v) => v.toString(16)).join("")})`,
      );
    }
  }
});

test("the licence does not pin a version that will go stale", () => {
  const licence = readFileSync(resolve(desktopRoot, "build", "license.txt"), "utf8");
  assert.ok(
    !/\bversion\s+\d+\.\d+\.\d+/i.test(licence),
    "license.txt must not hardcode an app version; the wizard title shows ${VERSION}",
  );
});

test("the welcome text does not promise anonymous use", () => {
  const nsis = readFileSync(resolve(desktopRoot, "build", "installer.nsh"), "utf8");
  assert.ok(
    !/run without an account/i.test(nsis),
    "the wizard must not claim the app can run without an account",
  );
  assert.match(
    nsis,
    /sign in|sign-in|account/i,
    "the wizard should say what first launch asks for",
  );
});
