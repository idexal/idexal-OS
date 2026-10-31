# Design review

Every evidence screenshot gets an advisory design review, so a layout problem
does not depend on the spec author noticing it. It never changes a test's
verdict.

Two passes, because each catches what the other misses:

| Pass | Reads | Catches | Needs |
| --- | --- | --- | --- |
| Measured | `NN-caption.layout.json`, written beside each screenshot by `screenshot()` | Text over text, a page wider than the window, row values drifting away from their names, columns a few pixels off, faint or tiny text | Nothing |
| Judged | The PNG, `rubric.md` and `DESIGN.md` | Rules a DOM cannot answer: header rows on plain lists, tinted status panels, dark selection outlines, internals in the viewport, copy rules | `OPENAI_API_KEY` or `ANTHROPIC_API_KEY` |

`rubric.md` packs the rules from the team's **idexal Os Design** plugin
(`idexal-os-paper-design`, `paper-design-consistency-audit`,
`idexal-os-ui-source-map`, plus the craft skills) that a screenshot can show.
`DESIGN.md` is read as it is in the checkout, so a rule change applies to the
next review. When a plugin skill changes, update its section in `rubric.md` in
the same PR.

## Run it

```sh
pnpm evals:e2e <slug> --local                                # records screenshots with layout
pnpm --dir evals design:review -- --test-run latest          # both passes when a key is set
pnpm --dir evals design:review -- --test-run latest --no-vision
```

It writes `design-review.json` beside `test-run.json` and prints one line per
note. Fix `medium` notes or say in the PR why the screen is right as it is.

## For agents

The notes ride in the PR's **Evidence preview** check, next to the evidence
they are about: one summary line, and the check's text lists each note with
its rule, the spec step that took the screenshot, where in the code to look
(the DOM hook and class list of the boxes involved) and the command that
reproduces it, plus the same notes as JSON. The images stay in the report;
nothing is posted twice and the verdict never changes. `pnpm --dir evals design:review -- --test-run latest --json` gives the
same entries locally. The `fix-design-notes` skill is the loop: read the
check, reproduce, fix, re-run, push.

## In CI

The trusted `judge-vision` job in `pr-proof.yml` runs
`evals/scripts/design-review-journeys.mjs` after judging screenshot claims.
Notes go to the job summary and into the proof record. The trusted publisher
adds them to the Evidence preview check; the review report shows them on each screenshot (count in the gallery,
outlined regions, notes and code hooks in the viewer). The step is
`continue-on-error`: a finding or a provider error never fails the job.
`run-advisory-design-review.mjs` gives the entire advisory pass a **120-second
wall-clock budget**, then kills its process group (including stuck model work
and subprocesses). A three-minute step timeout is a runner-level backstop.
Completed notes and measured findings are checkpointed atomically between
screenshots and before model calls; completed run summaries are appended as
runs finish. On timeout, these partial notes are uploaded with the judged
records and a warning says remaining screenshots were not reviewed. Screenshot
assertion judgments run first, outside this budget; their records and verdicts
are never changed by the advisory fallback. Local design review has no cap.
