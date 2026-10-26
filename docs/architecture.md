# Architecture

## The shape of the system

```text
┌────────────────────────────┐        ┌─────────────────────────────┐
│        Desktop app         │        │          Platform           │
│                            │        │                             │
│  workspace (local files)   │        │  inference gateway          │
│  chat / skills / browser   │◀──────▶│  entitlements & plans       │
│  automations               │  HTTPS │  organisation & membership  │
│  model router              │        │  extensions catalog         │
│  inventory cache           │        │  admin console              │
└────────────────────────────┘        └─────────────────────────────┘
                    ▲                              ▲
                    │                              │
                    └────────── MCP gateway ───────┘
                       (one URL for Codex, Claude Code,
                        Cursor, or any MCP client)
```

## Desktop app

- **Workspace** — a folder on disk. The agent reads and writes under it. Nothing
  is uploaded to make the app work.
- **Agent runtime** — built on OpenCode; the same capabilities the runtime
  exposes are available to the app even before a dedicated UI exists.
- **Model router** — resolves a requested model id to a provider. A *combo* is a
  first-class model id with several members beneath it.
- **Inventory cache** — the last successfully fetched set of providers, models,
  skills, plugins and entitlements, keyed by the inventory revision.

## Platform

- **Inference gateway** — holds upstream provider credentials and issues
  per-account credentials to desktops, so upstream keys never leave the server.
- **Entitlements** — plans, quotas, organisation credits, and which models a
  member may use.
- **Organisation & membership** — teams, roles, and desktop policies.
- **Extensions catalog** — the marketplace of connectors, skills and plugins.
- **Admin console** — where an operator changes all of the above.

## MCP gateway

One URL (`/mcp/agent`) that exposes an organisation's assigned capabilities to
any MCP client. A client calls `search_capabilities` to discover what it may use
and `execute_capability` to run one. The gateway does not require the idexal Os
desktop app.

## The boundary between the two big parts

The desktop and the platform exchange exactly three kinds of traffic:

1. **Inventory** — what the desktop is allowed to offer. Polled, and pushed over
   a server-sent event stream.
2. **Inference** — model calls, through the gateway or a locally configured
   provider.
3. **Identity** — sign-in, token refresh, device enrolment.

Everything else stays local. That boundary is specified in the
[Sync Contract](./sync-contract.md).

## Related reading

- [Sync Contract](./sync-contract.md)
- [Hybrid Combos](./hybrid-combos.md)
- [Extension Catalog](./extension-catalog.md)
- [Numerals Policy](./numerals-policy.md)
