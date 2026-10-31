import assert from "node:assert/strict";
import { mkdtempSync, readFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import path from "node:path";
import { test } from "node:test";

import { ProxyStore, normalizeProfileInput, splitProxyAddress } from "../electron/proxy-store.mjs";

function encryptedStorage() {
  return {
    isEncryptionAvailable: () => true,
    encryptString: (value) => Buffer.from(`enc:${value}`, "utf8"),
    decryptString: (buffer) => buffer.toString("utf8").replace(/^enc:/, ""),
  };
}

function withStore(run, { safeStorage = encryptedStorage(), now } = {}) {
  const dirPath = mkdtempSync(path.join(tmpdir(), "idexal-proxy-store-"));
  try {
    const store = new ProxyStore({ dirPath, safeStorage, now }).load();
    return run(store, dirPath);
  } finally {
    rmSync(dirPath, { recursive: true, force: true });
  }
}

test("saving a full proxy URL fills host, port, protocol, and credentials", () => {
  withStore((store, dirPath) => {
    const profile = store.save({
      name: "Webshare static",
      kind: "static_residential",
      host: "http://alice:s3cret@proxy.example.test:9000",
      port: 8080,
      protocol: "socks5",
      country: "us",
      applyToApp: true,
      alignIdentity: true,
    });

    assert.equal(profile.host, "proxy.example.test");
    assert.equal(profile.port, 9000);
    assert.equal(profile.protocol, "http");
    assert.equal(profile.username, "alice");
    assert.equal(profile.hasPassword, true);

    const secret = store.findSecret(profile.id);
    assert.equal(secret.password, "s3cret");

    const persisted = readFileSync(path.join(dirPath, "proxy-manager.json"), "utf8");
    assert.match(persisted, /"passwordCipher": "enc:v1:/);
    assert.doesNotMatch(persisted, /s3cret/);
    assert.equal(store.publicState().secretsUnavailable, false);
  });
});

test("editing a profile without a password keeps the saved password", () => {
  withStore((store) => {
    const created = store.save({
      name: "Static",
      kind: "static_residential",
      host: "proxy.example.test",
      port: 8080,
      username: "alice",
      password: "keep-me",
      protocol: "http",
      applyToApp: true,
      alignIdentity: true,
    });

    store.save({
      ...created,
      name: "Static renamed",
      password: "",
    });

    const secret = store.findSecret(created.id);
    assert.equal(secret.name, "Static renamed");
    assert.equal(secret.password, "keep-me");

    store.save({
      ...created,
      host: "https://bob:new-pass@proxy.example.test:9443",
      password: "",
    });

    const updated = store.findSecret(created.id);
    assert.equal(updated.host, "proxy.example.test");
    assert.equal(updated.port, 9443);
    assert.equal(updated.protocol, "https");
    assert.equal(updated.username, "bob");
    assert.equal(updated.password, "new-pass");
  });
});

test("loading restores encrypted profiles and active state", () => {
  withStore((store, dirPath) => {
    const saved = store.save({
      name: "Static",
      kind: "static_residential",
      host: "proxy.example.test",
      port: 8080,
      username: "alice",
      password: "secret",
      protocol: "http",
      applyToApp: false,
      alignIdentity: false,
    });
    store.setActive(saved.id);

    const reloaded = new ProxyStore({ dirPath, safeStorage: encryptedStorage() }).load();

    assert.equal(reloaded.enabled, true);
    assert.equal(reloaded.activeId, saved.id);
    assert.equal(reloaded.findSecret(saved.id).password, "secret");
    assert.equal(reloaded.publicState().profiles[0].hasPassword, true);
    assert.equal(reloaded.publicState().profiles[0].applyToApp, false);
  });
});

test("missing OS encryption falls back to an explicit raw format and reports it", () => {
  withStore((store) => {
    const profile = store.save({
      name: "No keychain",
      kind: "custom",
      host: "proxy.example.test",
      port: 1080,
      username: "alice",
      password: "plain",
      protocol: "socks5",
      applyToApp: true,
      alignIdentity: true,
    });

    const state = store.publicState();
    assert.equal(state.secretsUnavailable, true);
    assert.equal(store.findSecret(profile.id).password, "plain");
  }, { safeStorage: null });
});

test("validation rejects bad hosts, ports, duplicate names, and countries", () => {
  withStore((store) => {
    store.save({
      name: "One",
      kind: "custom",
      host: "proxy.example.test",
      port: 8080,
      protocol: "http",
      applyToApp: true,
      alignIdentity: true,
    });

    assert.throws(() => store.save({ name: "One", kind: "custom", host: "other.example.test", port: 8080 }), /already exists/);
    assert.throws(() => store.save({ name: "Bad port", kind: "custom", host: "proxy.example.test", port: 70000 }), /between 1 and 65535/);
    assert.throws(() => store.save({ name: "Bad host", kind: "custom", host: "", port: 8080 }), /proxy host/);
    assert.throws(() => store.save({ name: "Bad country", kind: "custom", host: "proxy.example.test", port: 8080, country: "usa!" }), /country code/);
  });
});

test("removing the active profile disables the proxy", () => {
  withStore((store) => {
    const profile = store.save({
      name: "Static",
      kind: "static_residential",
      host: "proxy.example.test",
      port: 8080,
      protocol: "http",
      applyToApp: true,
      alignIdentity: true,
    });
    store.setActive(profile.id);
    store.remove(profile.id);

    assert.equal(store.publicState().enabled, false);
    assert.equal(store.publicState().activeId, null);
    assert.deepEqual(store.publicState().profiles, []);
  });
});

test("splitProxyAddress handles bare hosts, host:port, IPv6, and credentials", () => {
  assert.deepEqual(splitProxyAddress("proxy.example.test"), { host: "proxy.example.test", port: null });
  assert.deepEqual(splitProxyAddress("proxy.example.test:8080"), { host: "proxy.example.test", port: 8080 });
  assert.deepEqual(splitProxyAddress("[2001:db8::1]:1080"), { host: "2001:db8::1", port: 1080 });

  const parsed = splitProxyAddress("socks5://alice:p%40ss@proxy.example.test:1080");
  assert.equal(parsed.host, "proxy.example.test");
  assert.equal(parsed.port, 1080);
  assert.equal(parsed.username, "alice");
  assert.equal(parsed.password, "p@ss");
  assert.equal(parsed.protocol, "socks5");
});

test("usage persists within a month and resets on month change", () => {
  const dirPath = mkdtempSync(path.join(tmpdir(), "idexal-proxy-usage-"));
  try {
    const october = () => new Date("2026-10-05T00:00:00Z");
    const store = new ProxyStore({ dirPath, safeStorage: encryptedStorage(), now: october }).load();
    store.setUsage(1536);

    const reloaded = new ProxyStore({ dirPath, safeStorage: encryptedStorage(), now: october }).load();
    assert.equal(reloaded.usage().bytes, 1536);

    const november = new ProxyStore({
      dirPath,
      safeStorage: encryptedStorage(),
      now: () => new Date("2026-11-01T00:00:00Z"),
    }).load();
    assert.equal(november.usage().bytes, 0);
    assert.equal(JSON.parse(readFileSync(path.join(dirPath, "proxy-usage.json"), "utf8")).month, "2026-11");
  } finally {
    rmSync(dirPath, { recursive: true, force: true });
  }
});

test("normalizeProfileInput accepts a known timezone and rejects an unknown one", () => {
  const profile = normalizeProfileInput({
    name: "London",
    kind: "static_residential",
    host: "proxy.example.test",
    port: 8080,
    country: "gb",
    timezone: "Europe/London",
    locale: "en_GB",
    latitude: 51.5074,
    longitude: -0.1278,
  });

  assert.equal(profile.timezone, "Europe/London");
  assert.equal(profile.locale, "en-GB");
  assert.equal(profile.latitude, 51.5074);
  assert.throws(
    () => normalizeProfileInput({ name: "Bad", kind: "custom", host: "proxy.example.test", port: 8080, timezone: "Mars/Olympus" }),
    /not recognized/,
  );
});
