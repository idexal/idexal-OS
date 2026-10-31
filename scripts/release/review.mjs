import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(fileURLToPath(new URL("../..", import.meta.url)));
const args = process.argv.slice(2);
const outputJson = args.includes("--json");
const strict = args.includes("--strict");

/**
 * Versions live in git tags, not files: release builds normally keep the
 * permanent dev placeholder and CI stamps the tag version into the workspace
 * at build time (scripts/release/stamp-version.mjs).
 *
 * idexal Os 2.0.0 is also committed as an explicit local commercial version so
 * a developer can package and inspect the same build users receive without
 * creating a tag. The tag-driven stamp still wins in CI. Any other committed
 * version is rejected.
 */
export const VERSION_PLACEHOLDER = "0.0.0-dev";
export const LOCAL_RELEASE_VERSION = "3.10.2";

const readJson = (path) => JSON.parse(readFileSync(path, "utf8"));

const appPkg = readJson(resolve(root, "apps", "app", "package.json"));
const desktopPkg = readJson(resolve(root, "apps", "desktop", "package.json"));
const serverPkg = readJson(resolve(root, "apps", "server", "package.json"));
const pinnedOpencodeVersion = String(
  readJson(resolve(root, "constants.json")).opencodeVersion ?? "",
)
  .trim()
  .replace(/^v/, "");

const versions = {
  app: appPkg.version ?? null,
  desktop: desktopPkg.version ?? null,
  server: serverPkg.version ?? null,
  opencode: pinnedOpencodeVersion || null,
};

const checks = [];
const warnings = [];
let ok = true;

const addCheck = (label, pass, details) => {
  checks.push({ label, ok: pass, details });
  if (!pass) ok = false;
};

const addWarning = (message) => warnings.push(message);

for (const [name, version] of [
  ["app", versions.app],
  ["desktop", versions.desktop],
  ["idexal-os-server", versions.server],
]) {
  addCheck(
    `${name} package.json holds a permitted version`,
    version === VERSION_PLACEHOLDER || version === LOCAL_RELEASE_VERSION,
    `${version ?? "?"} (expected ${VERSION_PLACEHOLDER} or ${LOCAL_RELEASE_VERSION}; releases stamp versions in CI from the tag)`,
  );
}

if (versions.opencode) {
  addCheck("OpenCode version pin exists", true, String(versions.opencode));
} else {
  addWarning("OpenCode version is not pinned in constants.json.");
}

const report = { ok, versions, checks, warnings };

if (outputJson) {
  console.log(JSON.stringify(report, null, 2));
} else {
  console.log("Release review");
  for (const check of checks) {
    const status = check.ok ? "ok" : "fail";
    console.log(`- ${status}: ${check.label} (${check.details})`);
  }
  if (warnings.length) {
    console.log("Warnings:");
    for (const warning of warnings) {
      console.log(`- ${warning}`);
    }
  }
}

if (strict && !ok) {
  process.exit(1);
}
