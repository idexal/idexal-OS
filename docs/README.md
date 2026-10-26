# idexal Os Wiki

**idexal Os** is a free, open-source desktop app for macOS, Windows and Linux for
doing work with AI agents on your own files — an open-source alternative to
Claude Cowork and Codex, built on OpenCode, and able to run any model from 50+
providers.

Desktop mode keeps your files local. Cloud is optional and only provides what
genuinely lives on a server: the inference gateway, your organisation's
entitlements, assigned skills, connections, and the extension marketplace.

This wiki is the field manual for the platform's contracts — the parts of the
system an integrator, an operator, or a contributor needs to know exactly.
Product announcements and community conversation live in
[Discussions](https://github.com/idexal/idexal-OS/discussions); the roadmap lives
in [Projects](https://github.com/idexal/idexal-OS/projects).

---

## Start here

| If you want to… | Read |
| --- | --- |
| Install the app and connect it to a platform | [Getting Started](./getting-started.md) |
| Understand how the pieces fit together | [Architecture](./architecture.md) |
| Know exactly when a desktop re-reads its inventory | [Sync Contract](./sync-contract.md) |
| Understand hybrid (combo) models | [Hybrid Combos](./hybrid-combos.md) |
| Know why numbers look the way they do | [Numerals Policy](./numerals-policy.md) |
| Publish a skill, connector or plugin | [Extension Catalog](./extension-catalog.md) |
| Run the platform yourself | [Self-Hosting](./self-hosting.md) |
| Understand how we ship | [Release Process](./release-process.md) |
| Get an answer to a common question | [FAQ](./faq.md) |

---

## The three surfaces

The project ships three surfaces that share one contract:

1. **Desktop app** — the local-first agent workspace. Chat on your files, skills,
   browser automation, scheduled automations, Anthropic-compatible plugins.
2. **Platform** — the control plane the desktop consumes. Inference gateway,
   entitlements, organisation and membership, marketplace catalog, admin console.
3. **MCP gateway** — one URL that brings organisation-assigned skills, plugins and
   connections into Codex, Claude Code, Cursor, or any MCP client through
   `search_capabilities` / `execute_capability`.

The desktop and the platform are not two products with a shared login; they are
one system with a published boundary. That boundary is the subject of the
[Sync Contract](./sync-contract.md).

---

## Principles

- **Local first.** Files stay on the machine that owns them. The network is asked
  for entitlements and inference, not for the workspace.
- **One source of truth.** A catalog identity, an inventory revision, a release
  version — each has exactly one authority. Parallel hand-maintained lists are
  treated as defects.
- **Fail visibly.** A change that cannot be attributed still reaches the client.
  A write path that used to be silent is a bug, not a feature.
- **Plain digits.** Numbers render as ASCII digits with Latin separators in every
  language. The rest of the number follows the viewer's locale.

---

## Getting help

| Kind of question | Where |
| --- | --- |
| "How do I…?" | [Q&A discussions](https://github.com/idexal/idexal-OS/discussions/categories/q-a) |
| "This looks broken, here is a reproduction" | [Issues](https://github.com/idexal/idexal-OS/issues) |
| "We should change the design" | [Ideas discussions](https://github.com/idexal/idexal-OS/discussions/categories/ideas) |
| "What shipped in this version?" | [Releases](https://github.com/idexal/idexal-OS/releases) |

Do not paste secrets, API keys or connection strings into any public thread.
