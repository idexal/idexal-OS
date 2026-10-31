#!/usr/bin/env node
/**
 * CI gate for release tags in the tag-driven release flow.
 *
 * Versions are no longer committed to package.json (the repo holds a
 * "0.0.0-dev" placeholder), so this no longer compares the tag against files.
 * It verifies the tag itself:
 *   - strict stable format (vX.Y.Z — prereleases never ship through Release App)
 *   - the tag exists in the clone
 *   - fresh releases are monotonic: strictly greater than every other stable
 *     tag (recovery reruns of an existing tag skip monotonicity)
 *
 * Usage: node scripts/release/verify-tag.mjs --tag vX.Y.Z [--mode fresh|recovery]
 */
import { execFileSync } from "node:child_process";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";
import {
  compareStableVersions,
  highestStableVersion,
  stableVersionFromTag,
} from "./versions.mjs";

const root = resolve(fileURLToPath(new URL("../..", import.meta.url)));

export const VERIFY_MODES = ["fresh", "recovery"];

export function verifyReleaseTag({ tag, mode, tags }) {
  const problems = [];

  if (!VERIFY_MODES.includes(mode)) {
    problems.push(`Unknown verify mode: ${mode} (expected ${VERIFY_MODES.join("|")})`);
  }

  const version = stableVersionFromTag(tag);
  if (!version) {
    problems.push(`Tag ${tag || "(empty)"} is not a stable release tag (expected vX.Y.Z).`);
    return { ok: false, version: null, problems };
  }

  if (mode === "fresh") {
    const otherVersions = tags
      .filter((existing) => existing.trim() !== tag.trim())
      .map((existing) => stableVersionFromTag(existing))
      .filter((existing) => existing !== null);
    const highest = highestStableVersion(otherVersions);
    if (highest && compareStableVersions(version, highest) <= 0) {
      problems.push(
        `Tag ${tag} must be greater than the highest existing stable tag v${highest}. ` +
          `Rerunning an already-released tag requires recovery mode (dispatch with tag=${tag}).`,
      );
    }
  }

  return { ok: problems.length === 0, version, problems };
}

function main() {
  const args = process.argv.slice(2);
  const tagIndex = args.indexOf("--tag");
  const tagArg = tagIndex >= 0 ? args[tagIndex + 1] : null;
  const tag = (tagArg || process.env.RELEASE_TAG || "").trim();
  const modeIndex = args.indexOf("--mode");
  const mode = ((modeIndex >= 0 ? args[modeIndex + 1] : null) || "fresh").trim();

  if (!tag) {
    throw new Error("Release tag missing. Provide --tag or set RELEASE_TAG.");
  }

  const tagExists = execFileSync("git", ["tag", "--list", tag], { cwd: root, encoding: "utf8" }).trim();
  if (!tagExists) {
    throw new Error(`Tag ${tag} not found in this clone (checkout needs fetch-depth: 0).`);
  }

  const tags = execFileSync("git", ["tag", "--list", "v*"], { cwd: root, encoding: "utf8" }).split("\n");
  const result = verifyReleaseTag({ tag, mode, tags });

  if (!result.ok) {
    console.error(`Release tag ${tag} failed verification (${mode}):`);
    for (const problem of result.problems) {
      console.error(`- ${problem}`);
    }
    process.exit(1);
  }

  console.log(`Release tag ${tag} verified (${mode}): version ${result.version}.`);
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    main();
  } catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    process.exit(1);
  }
}
