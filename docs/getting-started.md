# Getting Started

## 1. Install the desktop app

Download the installer for your platform from the
[download page](https://os.idexal.com/download) or from
[Releases](https://github.com/idexal/idexal-OS/releases). Verify it before you
run it:

```bash
sha256sum -c SHA256SUMS
```

The installer ships with its checksums. On Windows the updater manifest
(`latest.yml`) is attached to the same release.

## 2. Choose a mode

| Mode | Where your files live | Where credentials live |
| --- | --- | --- |
| **Desktop only** | your machine | your machine (custom providers) |
| **Desktop + platform** | your machine | the platform (a derived gateway credential) |

Most people run the second. Point the app at a platform base URL
(`https://os.idexal.com` for the hosted service, or your own deployment) and sign
in. The platform holds the upstream provider keys; the desktop receives a
credential scoped to your account and organisation.

## 3. Add at least one model

If you run against a platform, models arrive with your entitlements — open the
model picker and they are already there. If you run desktop-only, add a provider
in settings and supply the key locally.

A **hybrid combo** counts as one model in the picker. It is a single public id
that routes to several members underneath; see [Hybrid Combos](./hybrid-combos.md).

## 4. Point the agent at your work

Open a workspace folder. The agent reads and writes files under that folder. Ask
it to summarise a module, draft a document, or run a task across several files.

## 5. Add skills and connections (optional)

Skills are task-scoped instructions the agent can load; connections are MCP
servers and integrations. Both come from the
[extension catalog](./extension-catalog.md), and an organisation can assign them
centrally so every member sees the same set.

## 6. Confirm it is healthy

| Check | Expected |
| --- | --- |
| Platform `/up` | `200` |
| Platform `/v1/app-version` | the current stable version |
| Desktop sign-in | succeeds and the picker is populated |

## Next

- [Sync Contract](./sync-contract.md) — when and why the desktop re-reads its
  inventory.
- [Self-Hosting](./self-hosting.md) — run the platform on your own infrastructure.
- [FAQ](./faq.md) — the questions we are asked most.
