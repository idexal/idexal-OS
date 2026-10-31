# Proofs

One-off evals an agent writes to prove one change on one PR:
`proofs/<name>.proof.test.ts`. They run on the PR that adds or changes them and
nowhere else. They follow the same lint as core journeys (`pnpm --dir evals
lint:specs`) except that they need no promise.

A proof is not maintained. If what it proves keeps mattering, fold its steps
into one of the core journeys listed in `../journeys.ts`.
