import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { test } from "node:test";
import { pathToFileURL } from "node:url";

const root = process.env.WARDEN_PACKAGE_ROOT;
if (!root) throw new Error("Set WARDEN_PACKAGE_ROOT to an isolated @sentry/warden@0.43.0 installation");
assert.equal(JSON.parse(readFileSync(resolve(root, "package.json"), "utf8")).version, "0.43.0");
const load = (path) => import(pathToFileURL(resolve(root, "dist", path)).href);
const { loadWardenConfigFile } = await load("config/loader.js");
const { parsePatch } = await load("diff/parser.js");
const { coalesceHunks, splitLargeHunks } = await load("diff/coalesce.js");
const config = loadWardenConfigFile(new URL("../../warden.toml", import.meta.url).pathname);
const options = config.defaults.chunking.coalesce;
const chunk = (hunks) => coalesceHunks(splitLargeHunks(hunks, options), options);

// Fictional data only: exercise JSON, generated TypeScript, SQL and removed lines.
const lines = Array.from({ length: 800 }, (_, index) => `${index % 2 ? "+" : "-"}  "field${index}": "${"x".repeat(40)}",`);
const hunks = parsePatch(`@@ -1,400 +1,400 @@\n${lines.join("\n")}`);

test("the pinned CLI accepts the root policy and the intended chunk settings", () => {
  assert.deepEqual(options, { enabled: true, maxGapLines: 30, maxChunkSize: 12000 });
  assert.deepEqual(config.skills.map((skill) => skill.name), ["diff-security-review", "confidentiality-review"]);
});

test("large hunks require fewer analyses without dropping or reordering diff lines", () => {
  const before = coalesceHunks(splitLargeHunks(hunks, { maxChunkSize: 8000 }));
  const after = chunk(hunks);
  assert.ok(after.length < before.length);
  assert.deepEqual(after.flatMap((hunk) => hunk.lines), hunks.flatMap((hunk) => hunk.lines));
});

test("small nearby hunks merge, but distant schema and SQL scopes stay separate", () => {
  const nearby = parsePatch("@@ -1,1 +1,1 @@ schema\n-old\n+new\n@@ -25,1 +25,1 @@ migration\n-SELECT 1;\n+SELECT 2;");
  assert.equal(chunk(nearby).length, 1);
  assert.deepEqual(chunk(nearby).flatMap((hunk) => hunk.lines), nearby.flatMap((hunk) => hunk.lines));
  const distant = parsePatch("@@ -1,1 +1,1 @@ schema\n-old\n+new\n@@ -100,1 +100,1 @@ migration\n-SELECT 1;\n+SELECT 2;");
  assert.equal(chunk(distant).length, 2);
});
