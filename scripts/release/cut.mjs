#!/usr/bin/env node
/**
 * release:cut [patch|minor|major] [--version X.Y.Z] [--watch] [--dry-run]
 *
 * Cuts a release with zero local mutations: dispatches the Release App
 * workflow, which resolves the next version from existing v* tags, creates
 * the tag on origin/dev HEAD, stamps versions into the CI workspace, builds,
 * and publishes. Nothing is committed to the repo — anywhere.
 */
import { execSync } from "node:child_process";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(fileURLToPath(new URL("../..", import.meta.url)));
const args = process.argv.slice(2);

const REPO = "idexal/idexalOs";
const WORKFLOW = "Release App";

const dryRun = args.includes("--dry-run");
const watch = args.includes("--watch");
const versionIndex = args.indexOf("--version");
const explicitVersion = versionIndex >= 0 ? args[versionIndex + 1] : null;
const bumpType = args.find((arg) => ["patch", "minor", "major"].includes(arg)) ?? "patch";

const log = (msg) => console.log(`  ${msg}`);
const heading = (msg) => console.log(`\n▸ ${msg}`);
const success = (msg) => console.log(`  ✓ ${msg}`);
const fail = (msg) => {
  console.error(`  ✗ ${msg}`);
  process.exit(1);
};

const run = (cmd, opts = {}) => {
  if (dryRun && !opts.readOnly) {
    log(`[dry-run] ${cmd}`);
    return "";
  }
  try {
    return execSync(cmd, {
      cwd: root,
      encoding: "utf8",
      stdio: opts.inherit ? "inherit" : "pipe",
    }).trim();
  } catch (err) {
    if (opts.allowFail) return "";
    fail(`Command failed: ${cmd}\n${err.stderr || err.message}`);
  }
};

heading("Checking gh auth");
const authed = run("gh auth status", { readOnly: true, allowFail: true });
if (!authed) fail("gh is not authenticated (run: gh auth login)");
success("gh authenticated");

heading("Dispatching Release App");
if (explicitVersion) {
  if (!/^\d+\.\d+\.\d+$/.test(explicitVersion)) fail(`Invalid --version: ${explicitVersion} (expected X.Y.Z)`);
  run(`gh workflow run "${WORKFLOW}" --repo ${REPO} -f version=${explicitVersion}`);
  success(`Dispatched with version=${explicitVersion}`);
} else {
  run(`gh workflow run "${WORKFLOW}" --repo ${REPO} -f bump=${bumpType}`);
  success(`Dispatched with bump=${bumpType}`);
}

log(`Runs: https://github.com/${REPO}/actions/workflows/release-macos-aarch64.yml`);
log("The run resolves the version from v* tags and creates the tag on origin/dev HEAD.");

if (watch && !dryRun) {
  heading("Watching workflow run");
  execSync("sleep 10", { cwd: root });
  const runId = run(
    `gh run list --repo ${REPO} --workflow "${WORKFLOW}" --limit 1 --json databaseId -q ".[0].databaseId"`,
    { readOnly: true, allowFail: true },
  );
  if (runId) {
    run(`gh run watch ${runId} --repo ${REPO} --exit-status`, { inherit: true, allowFail: true });
  } else {
    log("Could not find the workflow run. Check the Actions tab manually.");
  }
}

console.log("\n" + "─".repeat(50));
console.log(`  Release dispatched${dryRun ? " (DRY RUN — nothing sent)" : ""}.`);
console.log("─".repeat(50) + "\n");
