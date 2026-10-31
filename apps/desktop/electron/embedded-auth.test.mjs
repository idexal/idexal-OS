import assert from "node:assert/strict";
import { test } from "node:test";

import { embeddedAuthHandoffUrl, embeddedAuthNavigationUrl } from "./embedded-auth.mjs";

const SCHEME = "idexalos";

test("returns the URL for this app's own den-auth grant", () => {
  const url = "idexalos://den-auth?grant=abc123&denBaseUrl=https%3A%2F%2Fos.idexal.com";
  assert.equal(embeddedAuthHandoffUrl(url, SCHEME), url);
});

test("ignores the platform's own pages while the user is signing in", () => {
  assert.equal(embeddedAuthHandoffUrl("https://os.idexal.com/login", SCHEME), null);
  assert.equal(embeddedAuthHandoffUrl("https://os.idexal.com/desktop/complete", SCHEME), null);
});

test("ignores another app's scheme", () => {
  assert.equal(embeddedAuthHandoffUrl("other://den-auth?grant=abc123", SCHEME), null);
});

test("ignores this app's scheme for a different path", () => {
  assert.equal(embeddedAuthHandoffUrl("idexalos://connect?token=abc123", SCHEME), null);
  assert.equal(embeddedAuthHandoffUrl("idexalos://den-auth", SCHEME), null);
});

test("refuses a den-auth link with no grant", () => {
  assert.equal(embeddedAuthHandoffUrl("idexalos://den-auth?denBaseUrl=https://x", SCHEME), null);
  assert.equal(embeddedAuthHandoffUrl("idexalos://den-auth?grant=", SCHEME), null);
});

test("ignores empty and unparseable input", () => {
  assert.equal(embeddedAuthHandoffUrl("", SCHEME), null);
  assert.equal(embeddedAuthHandoffUrl("   ", SCHEME), null);
  assert.equal(embeddedAuthHandoffUrl(undefined, SCHEME), null);
  assert.equal(embeddedAuthHandoffUrl(null, SCHEME), null);
  assert.equal(embeddedAuthHandoffUrl("not a url", SCHEME), null);
});

test("trims surrounding whitespace before matching", () => {
  const url = "idexalos://den-auth?grant=abc123";
  assert.equal(embeddedAuthHandoffUrl(`  ${url}\n`, SCHEME), url);
});

test("honours a custom scheme from a self-hosted platform", () => {
  const url = "vscode-idexal://den-auth?grant=abc123";
  assert.equal(embeddedAuthHandoffUrl(url, "vscode-idexal"), url);
  assert.equal(embeddedAuthHandoffUrl(url, SCHEME), null);
});

test("reads the navigation URL from the positional argument Electron still passes", () => {
  assert.equal(
    embeddedAuthNavigationUrl({ url: "https://os.idexal.com/login" }, "idexalos://den-auth?grant=abc123"),
    "idexalos://den-auth?grant=abc123",
  );
});

test("falls back to the event's own url on versions that drop the positional one", () => {
  assert.equal(
    embeddedAuthNavigationUrl({ url: "idexalos://den-auth?grant=abc123" }, undefined),
    "idexalos://den-auth?grant=abc123",
  );
  assert.equal(embeddedAuthNavigationUrl({ url: "https://os.idexal.com" }, null), "https://os.idexal.com");
});

test("returns an empty string when neither source has a URL", () => {
  assert.equal(embeddedAuthNavigationUrl({}, undefined), "");
  assert.equal(embeddedAuthNavigationUrl(undefined, undefined), "");
  assert.equal(embeddedAuthNavigationUrl(null, 42), "");
});
