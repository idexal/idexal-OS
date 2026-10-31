#!/usr/bin/env node
// Offline cost proxy using the same pinned parser, scan policy and chunker as CI.
// Supply an isolated @sentry/warden install; never installs or runs PR code.
import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { pathToFileURL } from "node:url";

const packageRoot = process.env.WARDEN_PACKAGE_ROOT;
if (!packageRoot) throw new Error("Set WARDEN_PACKAGE_ROOT to an isolated @sentry/warden@0.43.0 installation");
assert.equal(JSON.parse(readFileSync(resolve(packageRoot, "package.json"), "utf8")).version, "0.43.0");
const load = (path) => import(pathToFileURL(resolve(packageRoot, "dist", path)).href);
const { loadWardenConfigFile } = await load("config/loader.js");
const { prepareFiles } = await load("sdk/prepare.js");
const { filterContextByPaths } = await load("triggers/matcher.js");
const config = loadWardenConfigFile(resolve("warden.toml"));
const coalesce = config.defaults.chunking.coalesce;
const git = (...args) => execFileSync("git", args, { encoding: "utf8", maxBuffer: 100 * 1024 * 1024 });
const base = git("rev-parse", "--verify", `${process.argv[2] ?? "HEAD"}^{commit}`).trim();
const commits = git("log", "-30", "--first-parent", "--format=%H", base).trim().split("\n");
const generated = (name) => name.startsWith("ee/packages/den-db/drizzle/meta/")
  || name.startsWith("packages/sdk/src/gen/") || name === "packages/docs/openapi.json";
const totals = { changedLines: 0, generatedLines: 0, beforeChunks: 0, afterChunks: 0, generatedBeforeChunks: 0, generatedAfterChunks: 0 };
const skipped = {};

for (const commit of commits) {
  const files = git("diff", "--no-renames", "--numstat", "-z", `${commit}^`, commit).split("\0").filter(Boolean).map((row) => {
    const [additions, deletions, filename] = row.split("\t");
    if (additions === "-") return null;
    return {
      filename, additions: Number(additions), deletions: Number(deletions), status: "modified",
      patch: git("diff", "--no-ext-diff", "--no-renames", "--unified=3", `${commit}^`, commit, "--", filename),
    };
  }).filter(Boolean);
  for (const file of files) {
    totals.changedLines += file.additions + file.deletions;
    if (generated(file.filename)) totals.generatedLines += file.additions + file.deletions;
  }
  const context = filterContextByPaths({ repoPath: process.cwd(), pullRequest: { files }, diffContextSource: { type: "git-ref", ref: commit } }, { ignorePaths: config.defaults.ignorePaths });
  const prepare = (maxChunkSize) => prepareFiles(context, {
    scan: config.defaults.scan, ignore: config.defaults.ignore,
    chunking: { coalesce: { ...coalesce, maxChunkSize } },
  });
  const before = prepare(8000);
  const after = prepare(coalesce.maxChunkSize);
  // Cost tuning must never add a skip, drop a file, or lose/reorder diff lines.
  assert.deepEqual(after.skippedFiles, before.skippedFiles);
  assert.deepEqual(after.files.map((file) => file.filename), before.files.map((file) => file.filename));
  for (let i = 0; i < before.files.length; i++) {
    const old = before.files[i];
    const next = after.files[i];
    assert.deepEqual(next.hunks.flatMap((hunk) => hunk.hunk.lines), old.hunks.flatMap((hunk) => hunk.hunk.lines));
    totals.beforeChunks += old.hunks.length;
    totals.afterChunks += next.hunks.length;
    if (generated(old.filename)) {
      totals.generatedBeforeChunks += old.hunks.length;
      totals.generatedAfterChunks += next.hunks.length;
    }
  }
  for (const file of before.skippedFiles) skipped[file.reason] = (skipped[file.reason] ?? 0) + 1;
}
console.log(JSON.stringify({ base, commits: commits.length, ...totals, existingSkips: skipped }, null, 2));
// This estimates initial analysis calls per skill, not billed tokens or time.
// Existing skips are reported explicitly, not claimed as reviewed coverage.
