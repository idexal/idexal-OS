# REA integration

[REA — Reverse Engineer Anything](https://github.com/morluto/rea) is an MIT
licensed, local MCP server that gives an agent tools to inspect native binaries,
JavaScript/Electron apps, .NET assemblies, websites and saved captures. Results
come with evidence and stated limitations, so an agent can explain how a
feature works and then build a version for the member's own project.

This document defines how idexal Os adopts REA without turning the desktop app
into a binary-analysis distribution or weakening the agent permission model.

## Why it fits

- **MCP first.** idexal Os already brokers local MCP servers through
  `opencode.json`. REA exposes an MCP server; no engine fork is needed.
- **Local by design.** REA analyzes targets on the member's computer. idexal Os
  adds no upload path and keeps the existing "nothing leaves the device unless
  the member or their organization configured it" rule.
- **Evidence matches our agent contract.** idexal Os agents are expected to
  show their work and verify outcomes. REA returns evidence ids and explicit
  unknowns, which the agent can cite instead of guessing.
- **A useful commercial differentiator.** Reverse engineering is a concrete,
  high-value task that complements the built-in browser, file workspace and
  agent desktop control. It can be plan-gated like other extensions.

## What ships in phase 1 (this change)

- A built-in extension manifest (`rea`) in `apps/app/src/app/extensions.ts`.
- One-click installation as the local MCP server `rea`, started with:

  ```json
  {
    "mcp": {
      "rea": {
        "type": "local",
        "command": ["npx", "-y", "rea-agents@6.7.0", "mcp"]
      }
    }
  }
  ```

- A composer prompt and setup instructions that state the Node.js requirement
  and the optional native-analysis engines.
- The extension is disabled until the member installs it. It then inherits the
  existing `mcp` agent permission and `manage_extensions` plan feature, so an
  administrator can block local MCP servers or hide extension management for a
  plan without a new enforcement path.

Phase 1 does not bundle REA, install Node.js, or install Ghidra/Hopper/IDA. It
does not claim that a binary can be analyzed when the required provider is
missing.

## Phase 2 — preflight and observability

1. **Preflight action** on the extension detail page:
   - Node.js version check (REA requires 22.19+, 24.11+ or 26+).
   - `npx` availability and first-run download status.
   - Optional provider detection for Ghidra, Hopper and IDA, reported as
     available/absent, never auto-installed.
2. **MCP status** already shows connected/error. Add a "Test REA" action that
   runs a static JavaScript analysis on a tiny fixture and reports the tool
   round-trip, not just process startup.
3. **Audit**: record extension install/remove and each REA session start in the
   existing extension audit trail. Do not record target file contents.

## Phase 3 — workspace skill and evidence UX

- Package a small `reverse-engineer-anything` skill that teaches the agent:
  - when to use REA (feature reconstruction, interoperability, security
    research on software the member is authorized to inspect);
  - to name the target and the evidence id for every load-bearing claim;
  - to distinguish static evidence from runtime observation and to state
    unknowns;
  - to stop and ask before touching third-party license terms.
- Add an evidence pane that renders REA evidence bundles next to the
  conversation. Evidence stays local and is linked from the workspace, not
  copied into chat logs.
- Add snapshots/export so a member can reproduce an investigation later.

## Phase 4 — plan gating and packaging

- Add a `rea` feature flag to the Laravel plan catalogue and expose it in
  `/v1/me/desktop-config` as part of `features`, with an explicit backfill for
  existing plans.
- Suggested commercial shape:
  - **Free**: static JavaScript/Electron and .NET inspection, local only.
  - **Pro**: native analysis providers, evidence export, larger sessions.
  - **Enterprise**: organization policy, audit retention and shared evidence
    bundles.
- Add the plan row to the Filament admin plan editor and to the feature
  registry before the code that consumes it. Turning it off must leave the
  extension hidden and its MCP entry uninstalled.
- Keep the existing agent permission families: `mcp` controls whether any local
  MCP server may run. REA does not get a bypass.

## Security and legal constraints

- REA is for lawful research, interoperability, software preservation and
  analysis of software the member owns or is authorized to examine. The skill
  and setup copy state this; the product must not advertise DRM or license
  circumvention.
- Never bundle, download or activate IDA, Hopper or Ghidra on the member's
  behalf. A commercial tool's license stays with the member.
- Never upload target files, evidence or session logs to idexal Os Cloud by
  default. Any future sync requires an explicit organization policy and a
  visible consent surface.
- REA runs with the member's OS permissions. It is a local process, not a
  sandbox. The agent permission model, website rules and organization policy
  still apply to everything the agent does with the results.
- Treat REA output as untrusted data. A string inside a binary or a webpage is
  not an instruction.

## Rollout and verification

1. Feature flag off in the registry, extension visible only in preview builds.
2. Static JavaScript analysis against a fixture in CI; assert the MCP tool
   round-trip and that no network egress occurs.
3. Packaged-desktop journey: install the extension, restart the workspace,
   inspect a fixture, and assert evidence ids are returned.
4. Only then enable the plan feature and publish. A green unit test is not
   evidence that `npx` resolves on a clean Windows machine.

## Open questions

- Should evidence bundles live under `.rea/` in the workspace or under the
  app's user data? Workspace storage makes them portable but risks accidental
  commits; user data keeps them private but harder to share. Default: user data,
  with an explicit export.
- Which plan gets native analysis first? This is a pricing decision, not an
  engineering one.
- Whether to mirror the REA skill from the upstream repository or maintain a
  smaller idexal Os-specific skill. Upstream updates frequently; mirroring
  requires a review cadence.
