# Roadmap

The live roadmap is [idexal Os â€” Engineering Roadmap](https://github.com/idexal/idexal-OS/projects).
It mirrors the community **Ideas** discussions and is updated as items are
accepted, started and shipped.

Nothing on the roadmap is a release commitment until it ships.

## Themes

### Model routing

- **Routing profiles** â€” resolve a logical model per workspace, so the same model
  id behaves differently by intent without duplicating combos.
- **Spend ceilings for combos** â€” a window and a ceiling, with graceful
  degradation when it is reached.

### Sync and reliability

- **Audit and close remaining silent write paths** â€” every admin mutation that
  can change inventory must announce itself.
- **Offline hardening** â€” deeper local inventory caching so a flaky network
  degrades features instead of blocking a session.
- **Long-lived inventory channel** â€” carry entitlements and marketplace updates
  over the existing event stream so polling becomes a fallback.

### Marketplace

- **Signed entries with provenance** â€” who published an entry, from which commit,
  and a signature over its content.
- **Skill versioning and deprecation** â€” content changes ship as versions;
  consumers can pin one.
- **Connector permission scopes and an audit trail** â€” declared scope up front,
  recorded use afterwards.
- **Schema validation in CI** â€” reject an invalid or identity-changing entry
  before a reviewer sees it.

### Platform and operations

- **Health and revision diagnostics** â€” the current revision, the last announced
  change, and the subscriber count, visible in the admin console.
- **Single source of truth for the released version** â€” manifests, the download
  page and the API must never disagree.

### Release coverage

- **macOS and Linux assets in the stable channel** â€” signing, notarisation and
  updater manifests for every supported platform.

## How to influence it

1. Open an **Ideas** discussion describing the problem.
2. Comment on an existing draft item in the project.
3. Vote in a **Polls** discussion when asked.

## Related reading

- [Sync Contract](./sync-contract.md)
- [Hybrid Combos](./hybrid-combos.md)
- [Release Process](./release-process.md)
