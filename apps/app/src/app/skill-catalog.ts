export type SuggestedSkill = {
  name: string;
  description: string;
  tags: string[];
  content: string;
};

/**
 * Curated workspace skills. Installing writes one SKILL.md under
 * .opencode/skills/<name>/; keep each entry self-contained.
 */
export const SUGGESTED_SKILLS: SuggestedSkill[] = [
  {
    name: "code-review",
    description: "Review a change for correctness, security and missing tests, and report findings by severity.",
    tags: ["review", "quality"],
    content: `---
name: code-review
description: Review a change for correctness, security and missing tests, and report findings by severity.
---

# Code review

Review the change, do not rewrite it unless the user asks.

1. Establish the boundary: run \`git status\` and \`git diff\` (or read the diff the user provided). List every touched file, including generated files.
2. Read the surrounding code, not only the changed lines. Identify the contract the change must preserve: callers, persisted data, wire formats, permissions.
3. Check, in order:
   - Correctness: wrong branch, off-by-one, unhandled null/empty, ordering, retries and partial failure.
   - Security: injection, path traversal, secret exposure, missing authorization, unsafe HTML or URLs.
   - Data: schema changes without a migration, migrations that do not move existing rows, lossy casts.
   - Tests: the behaviour is asserted, the assertion fails when the change is reverted, and the failure path is exercised.
4. Report one finding per line as \`path:line — severity — problem — concrete fix\`. Use blocker, major, minor. Do not pad with praise or restate the diff.
5. If a claim depends on runtime behaviour, say how you verified it; otherwise label it unverified.

Finish with the single most important risk and what you would test next.`,
  },
  {
    name: "regression-test",
    description: "Reproduce a bug with a failing test first, fix the cause, and prove the test turns green.",
    tags: ["testing", "debugging"],
    content: `---
name: regression-test
description: Reproduce a bug with a failing test first, fix the cause, and prove the test turns green.
---

# Regression test

1. Restate the bug as one observable behaviour: input, action, expected output.
2. Find the narrowest existing test surface that exercises that behaviour. Read its fixtures and helpers before adding a test.
3. Write a test that fails for the reported reason. Run it and read the failure. If it passes, the test does not reproduce the bug; change the test, not the code.
4. Find the cause. Trace the value from where it is produced to where it is consumed. Do not fix a symptom.
5. Make the smallest change that turns the test green. Re-run the test, then the surrounding suite.
6. Mutation check: revert the fix in your head or in a scratch copy and confirm the new test fails. A test that cannot fail proves nothing.
7. Report the failing output, the fix, and the passing output. If the suite is polluted by unrelated failures, say which ones and prove they are unrelated.`,
  },
  {
    name: "release-notes",
    description: "Turn a range of commits into clear, grouped release notes and call out breaking changes.",
    tags: ["release", "writing"],
    content: `---
name: release-notes
description: Turn a range of commits into clear, grouped release notes and call out breaking changes.
---

# Release notes

1. Establish the range: \`git log --oneline <from>..<to>\` and \`git diff --stat <from>..<to>\`. Confirm the target tag or version before writing.
2. Read every commit in the range, not only the subjects. Classify each as user-visible, internal, or generated.
3. Group user-visible changes under: New, Improved, Fixed, Security. Drop build noise, formatting and dependency bumps unless they change behaviour.
4. For each entry, write what changed for the user and why it matters. One sentence, no commit hashes in the body.
5. Call out breaking changes separately: removed options, changed defaults, required migrations, and the action the user must take.
6. Verify every named feature exists in the diff. Do not describe work that is only planned.
7. End with upgrade steps when the release needs them.

Keep the whole note short enough to read in one pass.`,
  },
];
