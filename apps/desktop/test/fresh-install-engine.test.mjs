import assert from "node:assert/strict";
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { test } from "node:test";
import { idexalOsInstallMarkerPaths, resolveFreshInstallEngine } from "../electron/runtime.mjs";

function withProfile(run) {
  const dir = mkdtempSync(join(tmpdir(), "idexal-os-fresh-install-"));
  try {
    const markerPaths = idexalOsInstallMarkerPaths({
      userDataDir: join(dir, "userData"),
      serverConfigPath: join(dir, "config", "idexalos", "server.json"),
      env: {},
    });
    run({ dir, markerPaths });
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

test("a profile with no earlier idexal Os state is a new install and keeps the stable engine", () => {
  withProfile(({ markerPaths }) => {
    assert.equal(resolveFreshInstallEngine({ markerPaths, env: {} }), undefined);
  });
});

test("any file an earlier idexal Os run left behind marks an update, which keeps its engine", () => {
  withProfile(({ markerPaths }) => {
    for (const marker of markerPaths) {
      assert.equal(resolveFreshInstallEngine({ markerPaths, env: {}, exists: (filePath) => filePath === marker }), undefined, marker);
    }
  });
});

test("an updated install with a server config stays on its engine", () => {
  withProfile(({ dir, markerPaths }) => {
    const serverConfig = join(dir, "config", "idexalos", "server.json");
    assert.ok(markerPaths.includes(serverConfig));
    mkdirSync(join(dir, "config", "idexalos"), { recursive: true });
    writeFileSync(serverConfig, "{}");
    assert.equal(resolveFreshInstallEngine({ markerPaths, env: {} }), undefined);
  });
});

test("an explicit IDEXAL_ENGINE_V2_PREVIEW always wins over the new-install default", () => {
  withProfile(({ markerPaths }) => {
    assert.equal(resolveFreshInstallEngine({ markerPaths, env: { IDEXAL_ENGINE_V2_PREVIEW: "0" } }), undefined);
  });
});

test("only a host that explicitly opts in sends a fresh install to v2", () => {
  withProfile(({ markerPaths }) => {
    assert.equal(
      resolveFreshInstallEngine({ markerPaths, env: { IDEXAL_FRESH_INSTALL_ENGINE: "v2" } }),
      "v2",
    );
    // The opt-in never overrides an existing profile or an explicit preview.
    const serverConfig = markerPaths[0];
    assert.equal(
      resolveFreshInstallEngine({
        markerPaths,
        env: { IDEXAL_FRESH_INSTALL_ENGINE: "v2" },
        exists: (filePath) => filePath === serverConfig,
      }),
      undefined,
    );
    assert.equal(
      resolveFreshInstallEngine({
        markerPaths,
        env: { IDEXAL_FRESH_INSTALL_ENGINE: "v2", IDEXAL_ENGINE_V2_PREVIEW: "1" },
      }),
      undefined,
    );
  });
});
