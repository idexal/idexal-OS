---
name: connect-idexal-os-mcp
description: Connect the idexal Os MCP Gateway (https://api.idexal.com/mcp/agent) to Claude Code, Codex, Gemini CLI, Cursor, VS Code, Claude Desktop, or ChatGPT so the agent can use the user's idexal Os organization skills, plugins, and connections.
---

# Connect the idexal Os MCP Gateway

Server URL: `https://api.idexal.com/mcp/agent`
Transport: Streamable HTTP. Auth: OAuth (PKCE, dynamic client registration); the client opens a browser for sign-in. No API key is needed.

The user needs an idexal Os Cloud account. If they don't have one, send them to https://os.idexal.com?mode=sign-up first.

## 1. Add the server

Use the command for the client you are running in. If an `idexalos` entry already exists, don't add a duplicate; sign in with the existing one.

Claude Code:

```sh
claude mcp add --transport http idexalos https://api.idexal.com/mcp/agent
# add `-s user` to make it available in every project
```

Codex:

```sh
codex mcp add idexalos --url https://api.idexal.com/mcp/agent
codex mcp login idexalos
```

Gemini CLI:

```sh
gemini mcp add --transport http idexalos https://api.idexal.com/mcp/agent
```

OpenCode:

```sh
opencode mcp add idexalos --url https://api.idexal.com/mcp/agent
opencode mcp auth idexalos
```

Cursor, VS Code, Claude Desktop, ChatGPT, Windsurf, Zed: add `https://api.idexal.com/mcp/agent` as a remote MCP server. Per-client steps: https://os.idexal.com/docs/start-here/connect-idexal-os-mcp

## 2. Sign in

- Claude Code: run `/mcp`, select `idexalos`, and authenticate.
- Codex: `codex mcp login idexalos` opens the browser.
- Gemini CLI: run `/mcp auth idexalos`.
- OpenCode: `opencode mcp auth idexalos` opens the browser.

The user signs in and picks their organization. The organization is pinned to the token; to switch, log out of the `idexalos` server and sign in again.

## 3. Verify

1. `claude mcp list` (or the client's equivalent) shows `idexalos`.
2. Restart the agent session so the new tools load.
3. The agent has the `search_capabilities` and `execute_capability` tools.
4. Ask: "Which idexal Os organization am I connected to?"

Don't claim the connection works until step 3 passes.

## If it fails

- 401 or `invalid_grant`: log out of `idexalos` in the client and sign in again.
- No person can sign in yet (headless agent): see https://os.idexal.com/auth.md for anonymous workspaces a person claims later.
- Self-hosted idexal Os: use your own Den API origin, for example `https://api.<your-den-web-host>/mcp/agent`.
- Reference: https://os.idexal.com/docs/cloud/run-in-the-cloud/cloud-mcp
