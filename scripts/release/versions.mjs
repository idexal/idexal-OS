#!/usr/bin/env node
/**
 * Stable-version helpers for the tag-driven release flow.
 *
 * Git tags (vX.Y.Z) are the single source of truth for released versions —
 * package.json files hold a permanent "0.0.0-dev" placeholder and CI stamps
 * the real version into the workspace at build time (see stamp-version.mjs).
 *
 * CLI:
 *   node scripts/release/versions.mjs latest              # highest stable tag version
 *   node scripts/release/versions.mjs next --bump patch   # next version from tags
 */
import { execFileSync } from "node:child_process";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(fileURLToPath(new URL("../..", import.meta.url)));

export const STABLE_VERSION_PATTERN = /^(\d+)\.(\d+)\.(\d+)$/;

export function parseStableVersion(value) {
  const match = typeof value === "string" ? value.match(STABLE_VERSION_PATTERN) : null;
  return match ? match.slice(1).map(Number) : null;
}

export function compareStableVersions(left, right) {
  const leftParts = parseStableVersion(left);
  const rightParts = parseStableVersion(right);
  if (!leftParts || !rightParts) {
    throw new Error(`Cannot compare non-stable versions: ${left} vs ${right}`);
  }
  for (let index = 0; index < 3; index += 1) {
    const difference = leftParts[index] - rightParts[index];
    if (difference !== 0) return difference;
  }
  return 0;
}

export function stableVersionFromTag(tag) {
  const trimmed = typeof tag === "string" ? tag.trim() : "";
  if (!trimmed.startsWith("v")) return null;
  const version = trimmed.slice(1);
  return parseStableVersion(version) ? version : null;
}

export function highestStableVersion(versions) {
  let highest = null;
  for (const version of versions) {
    if (!parseStableVersion(version)) continue;
    if (highest === null || compareStableVersions(version, highest) > 0) {
      highest = version;
    }
  }
  return highest;
}

export function bumpStableVersion(version, bumpType) {
  const parts = parseStableVersion(version);
  if (!parts) throw new Error(`Invalid stable version: ${version}`);
  const [major, minor, patch] = parts;
  if (bumpType === "major") return `${major + 1}.0.0`;
  if (bumpType === "minor") return `${major}.${minor + 1}.0`;
  if (bumpType === "patch") return `${major}.${minor}.${patch + 1}`;
  throw new Error(`Unknown bump type: ${bumpType}`);
}

export function readStableTagVersions(cwd = root) {
  return execFileSync("git", ["tag", "--list", "v*"], { cwd, encoding: "utf8" })
    .split("\n")
    .map((tag) => stableVersionFromTag(tag))
    .filter((version) => version !== null);
}

function main() {
  const args = process.argv.slice(2);
  const command = args[0];
  const latest = highestStableVersion(readStableTagVersions());

  if (command === "latest") {
    if (!latest) throw new Error("No stable v* tags found (need a full clone: fetch-depth 0).");
    console.log(latest);
    return;
  }

  if (command === "next") {
    if (!latest) throw new Error("No stable v* tags found (need a full clone: fetch-depth 0).");
    const bumpIndex = args.indexOf("--bump");
    const bumpType = bumpIndex >= 0 ? args[bumpIndex + 1] : "patch";
    console.log(bumpStableVersion(latest, bumpType));
    return;
  }

  throw new Error("Usage: versions.mjs latest | next --bump patch|minor|major");
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    main();
  } catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    process.exit(1);
  }
}
