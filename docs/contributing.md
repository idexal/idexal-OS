# Contributing

Thank you for helping. This page is the short version; the repository's
`CONTRIBUTING` policy and the review of a concrete pull request are authoritative.

## Before you write code

1. **Search first.** Your bug may be fixed and your idea may be in the roadmap.
2. **Open an issue for behaviour changes.** A pull request that changes behaviour
   without a prior discussion is harder to review and more likely to stall.
3. **Keep the change small.** One concern per pull request.

## Ground rules

- **No secrets, ever.** No keys, tokens, or connection strings in code, tests,
  fixtures, or evidence. This repository is public.
- **No customer identities.** A branch name, commit message, or fixture must not
  identify a customer, prospect, partner or any outside person.
- **Match the surrounding code.** Structure, naming and style follow the files
  you are editing.
- **Strict types.** Do not widen a type to make a change compile; narrow the
  change instead.

## Testing

- Add a test that fails without your change and passes with it.
- Prefer a focused test over a broad one; state the behaviour, not the
  implementation.
- Run the narrowest set that covers the change before you open the pull request.

## Documentation

If your change alters a contract described in this wiki, update the wiki page in
the same pull request. A contract and its documentation drifting apart is the
failure mode this wiki exists to prevent.

## Reporting a defect

Include:

- What you expected and what happened.
- Your OS and the app version.
- The exact steps to reproduce.
- Relevant log lines, with secrets redacted.

## Proposing a feature

Open an **Ideas** discussion. Useful proposals state the problem before the
solution, and name the trade-off they are willing to accept.

## Where to talk

| Topic | Place |
| --- | --- |
| Bugs with a reproduction | [Issues](https://github.com/idexal/idexal-OS/issues) |
| Questions | [Q&A](https://github.com/idexal/idexal-OS/discussions/categories/q-a) |
| Feature ideas | [Ideas](https://github.com/idexal/idexal-OS/discussions/categories/ideas) |
| Catalog entries | [extensions-catalog](https://github.com/idexal/extensions-catalog) |
