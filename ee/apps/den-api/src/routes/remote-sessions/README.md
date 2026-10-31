# Remote session routes

The signed-in member's own remote sessions, for idexal Os's apps:

- `POST /v1/remote-sessions/status`: compact, batched, versioned status of up to ten sessions, with an optional bounded wait.
- `POST /v1/remote-sessions/read`, `/send`, `/stop`: transcript pages, follow-ups and stop.

Each route runs the `remote-session:*` capability agents call through MCP
(`src/mcp/remote-session-capabilities.ts`), so apps and agents share the same
receipts and rules. Behind the `remoteSessionSubagents` feature, and not
exposed to MCP as separate tools (`x-mcp: false`).
