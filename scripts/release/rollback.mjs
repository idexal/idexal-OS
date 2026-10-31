#!/usr/bin/env node
import { execFileSync } from "node:child_process";
import { resolve } from "node:path";
import { fileURLToPath } from "node:url";

export const REPO = "idexal/idexalOs";
const MAX_BUFFER = 128 * 1024 * 1024;

const log = (message) => console.log(`  ${message}`);
const heading = (message) => console.log(`\n▸ ${message}`);

export function parseArgs(args) {
  const options = {
    bad: null,
    to: null,
    execute: false,
    keepBadListed: false,
  };

  for (let index = 0; index < args.length; index += 1) {
    const arg = args[index];
    if (arg === "--execute") {
      options.execute = true;
      continue;
    }
    if (arg === "--keep-bad-listed") {
      options.keepBadListed = true;
      continue;
    }
    if (["--bad", "--to"].includes(arg)) {
      const value = args[index + 1];
      if (!value || value.startsWith("--")) throw new Error(`${arg} requires a value.`);
      options[arg.slice(2)] = value;
      index += 1;
      continue;
    }
    throw new Error(`Unknown argument: ${arg}`);
  }

  return options;
}

export function parseSemver(tag) {
  const match = tag.match(/^v(\d+)\.(\d+)\.(\d+)$/);
  return match ? match.slice(1).map(Number) : null;
}

export function compareSemver(leftTag, rightTag) {
  const left = parseSemver(leftTag);
  const right = parseSemver(rightTag);
  if (!left) throw new Error(`Release tag '${leftTag}' is not valid semver (expected vX.Y.Z).`);
  if (!right) throw new Error(`Release tag '${rightTag}' is not valid semver (expected vX.Y.Z).`);

  for (let index = 0; index < left.length; index += 1) {
    if (left[index] !== right[index]) return left[index] - right[index];
  }
  return 0;
}

function releaseTag(release) {
  return release.tag_name;
}

function findRelease(releases, tag) {
  return releases.find((release) => releaseTag(release) === tag) ?? null;
}

function validateBadRelease(releases, badTag) {
  const bad = findRelease(releases, badTag);
  if (!bad) throw new Error(`Bad release not found: ${badTag}.`);
  if (bad.draft) throw new Error(`Bad release ${badTag} is a draft, not a published release.`);
  if (!parseSemver(badTag)) {
    throw new Error(`Bad release tag '${badTag}' is not valid semver (expected vX.Y.Z).`);
  }
  return bad;
}

export function selectRollbackTarget(releases, badTag) {
  validateBadRelease(releases, badTag);
  const candidates = releases.filter((release) => {
    const tag = releaseTag(release);
    return !release.draft
      && !release.prerelease
      && parseSemver(tag)
      && compareSemver(tag, badTag) < 0;
  });
  candidates.sort((left, right) => compareSemver(releaseTag(right), releaseTag(left)));

  if (!candidates[0]) {
    throw new Error(`No published, non-prerelease release exists below ${badTag}.`);
  }
  return candidates[0];
}

export function resolveRollback(releases, badTag, requestedTargetTag = null) {
  const bad = validateBadRelease(releases, badTag);
  const targetTag = requestedTargetTag ?? releaseTag(selectRollbackTarget(releases, badTag));
  const target = findRelease(releases, targetTag);

  if (!target) throw new Error(`Target release not found: ${targetTag}.`);
  if (badTag === targetTag) throw new Error(`Bad and target releases must differ (${badTag}).`);
  if (target.draft) throw new Error(`Target release ${targetTag} is a draft, not a published release.`);
  if (target.prerelease) throw new Error(`Target release ${targetTag} is a prerelease, not a stable published release.`);
  if (!parseSemver(targetTag)) {
    throw new Error(`Target release tag '${targetTag}' is not valid semver (expected vX.Y.Z).`);
  }
  if (compareSemver(targetTag, badTag) >= 0) {
    throw new Error(`Target release ${targetTag} must be lower than bad release ${badTag}.`);
  }

  return { bad, target };
}

export function hasUpdaterManifest(release) {
  return (release.assets ?? []).some(({ name }) => (
    /^latest.*\.yml$/i.test(name) || name.toLowerCase() === "latest.json"
  ));
}

export function rollbackBanner(targetTag, now = new Date()) {
  const date = now.toISOString().slice(0, 10);
  return `> ⚠️ Rolled back on ${date}. Do not install; use ${targetTag} instead.`;
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

export function hasRollbackBanner(body, targetTag) {
  const target = escapeRegExp(targetTag);
  return new RegExp(
    `^> ⚠️ Rolled back on \\d{4}-\\d{2}-\\d{2}\\. Do not install; use ${target} instead\\.$`,
    "m",
  ).test(body ?? "");
}

export function buildRollbackBody(body, targetTag, now = new Date()) {
  const existingBody = body ?? "";
  if (hasRollbackBanner(existingBody, targetTag)) return existingBody;
  const banner = rollbackBanner(targetTag, now);
  return existingBody ? `${banner}\n\n${existingBody}` : banner;
}

export function buildRollbackPlan({
  bad,
  target,
  latestTag,
  keepBadListed = false,
  now = new Date(),
}) {
  const badTag = releaseTag(bad);
  const targetTag = releaseTag(target);
  const nextBody = buildRollbackBody(bad.body, targetTag, now);

  return [
    {
      name: `Re-point Latest from ${latestTag} to ${targetTag}`,
      skip: latestTag === targetTag,
      skipReason: `${targetTag} is already Latest`,
      args: ["release", "edit", targetTag, "--repo", REPO, "--latest"],
    },
    {
      name: `Demote ${badTag} to prerelease (assets and tag stay intact)`,
      skip: keepBadListed || bad.prerelease,
      skipReason: keepBadListed
        ? "--keep-bad-listed was supplied"
        : `${badTag} is already a prerelease`,
      args: ["release", "edit", badTag, "--repo", REPO, "--prerelease"],
    },
    {
      name: `Prepend the rollback banner to ${badTag} release notes`,
      skip: nextBody === (bad.body ?? ""),
      skipReason: "rollback banner is already present",
      args: [
        "api",
        "--method",
        "PATCH",
        `repos/${REPO}/releases/${bad.id}`,
        "-f",
        `body=${nextBody}`,
      ],
    },
  ];
}

function parseReleasePages(output) {
  const parsed = JSON.parse(output);
  if (!Array.isArray(parsed)) throw new Error("Expected a JSON array from gh api.");
  if (parsed.every(Array.isArray)) return parsed.flat();
  if (parsed.every((release) => !Array.isArray(release))) return parsed;
  throw new Error("Expected gh api pagination output to contain release arrays.");
}

function defaultGh(args) {
  return execFileSync("gh", args, {
    encoding: "utf8",
    maxBuffer: MAX_BUFFER,
  }).trim();
}

function loadReleases(gh) {
  return parseReleasePages(gh([
    "api",
    "--paginate",
    "--slurp",
    `repos/${REPO}/releases?per_page=100`,
  ]));
}

function loadLatestRelease(gh) {
  return JSON.parse(gh(["api", `repos/${REPO}/releases/latest`]));
}

function nextPatch(tag) {
  const [major, minor, patch] = parseSemver(tag);
  return `${major}.${minor}.${patch + 1}`;
}

function printGuidance(badTag, targetTag) {
  const badVersion = badTag.slice(1);
  const nextVersion = nextPatch(badTag);

  heading("Next steps — reissue from last-good code (not automated)");
  log("Updaters never downgrade. Reissue the last-good code at a version higher than the bad release.");
  log("Versions live in tags only — tag the last-good commit with the higher version (no commits needed):");
  console.log("");
  console.log(`    git tag v${nextVersion} ${targetTag}   # or any version higher than ${badTag}`);
  console.log(`    git push origin v${nextVersion}        # admins only; triggers Release App`);
  console.log("");
  log(`After npm publish: npm deprecate idexal-os-server@${badVersion} "rolled back — use ${nextVersion}"`);
  log("The org install door follows published GitHub releases at runtime: demoting the bad release to");
  log("prerelease removes it from den-api's published list; the reissue becomes latest once published.");
}

export function runRollback(args, dependencies = {}) {
  const gh = dependencies.gh ?? defaultGh;
  const now = dependencies.now ?? new Date();
  const options = parseArgs(args);
  const releases = loadReleases(gh);
  const latest = loadLatestRelease(gh);
  const badTag = options.bad ?? releaseTag(latest);
  const { bad, target } = resolveRollback(releases, badTag, options.to);
  const targetTag = releaseTag(target);
  const plan = buildRollbackPlan({
    bad,
    target,
    latestTag: releaseTag(latest),
    keepBadListed: options.keepBadListed,
    now,
  });

  console.log("Release rollback");
  log(`Repository: ${REPO}`);
  log(`Bad release: ${badTag}`);
  log(`Last good: ${targetTag}`);
  log(`Mode: ${options.execute ? "EXECUTE" : "DRY RUN"}`);

  if (!hasUpdaterManifest(target)) {
    console.error(`\n  ⚠ WARNING: ${targetTag} is missing updater manifests (latest*.yml or latest.json).`);
    console.error("  ⚠ Re-pointing Latest may leave desktop auto-update downloads unavailable.");
  }

  heading("Stop-the-bleed plan");
  for (let index = 0; index < plan.length; index += 1) {
    const step = plan[index];
    log(`${index + 1}. ${step.name}${step.skip ? ` — skip: ${step.skipReason}` : ""}`);
  }

  if (options.execute) {
    heading("Executing rollback");
    for (let index = 0; index < plan.length; index += 1) {
      const step = plan[index];
      if (step.skip) {
        log(`${index + 1}. Skipped — ${step.skipReason}.`);
        continue;
      }
      log(`${index + 1}. ${step.name}`);
      gh(step.args);
      log(`✓ Step ${index + 1} complete`);
    }
  }

  printGuidance(badTag, targetTag);

  if (options.execute) {
    console.log("\n  ✓ Stop-the-bleed rollback complete. Reissue is still required for already-updated clients.");
  } else {
    console.log(`\nDRY RUN — no changes made. Re-run with --bad ${badTag} --execute.`);
  }

  return { bad, target, plan, execute: options.execute };
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    runRollback(process.argv.slice(2));
  } catch (error) {
    console.error(`  ✗ ${error instanceof Error ? error.message : String(error)}`);
    process.exit(1);
  }
}
