# Local managed MCP OAuth

idexal Os desktop can own OAuth for a custom remote MCP and expose its tools to
the bundled OpenCode engine through an authenticated loopback MCP gateway. This
provides a compatibility path for providers whose OAuth flow works through
idexal Os's enterprise MCP client but not through OpenCode's direct MCP client.

## User flow

1. In a local desktop workspace, add a remote MCP and expand **idexal Os-managed
   OAuth**.
2. Optionally enter a pre-registered client ID, client secret, and scopes. If
   the provider supports dynamic client registration, those fields may remain
   empty.
3. idexal Os performs OAuth discovery, DCR when needed, PKCE authorization, the
   loopback callback, token exchange, and an authenticated `tools/list` check.
4. OpenCode receives a remote MCP entry pointing at the idexal Os loopback
   gateway with `oauth: false`. It sees the provider tools but never receives
   the provider access token, refresh token, or OAuth client secret.

Existing direct remote MCPs and local command MCPs keep their current paths.
The managed path is opt-in per connection and is currently desktop-only.

## Persistence and lifecycle

- Provider credentials, OAuth registrations, discovery state, and PKCE
  transactions are encrypted with AES-256-GCM in idexal Os's runtime storage.
- idexal Os Desktop keeps the encryption key behind the operating system's
  secure-storage service and persists only the protected key blob, separately
  from the encrypted vault. A standalone server must set
  `IDEXAL_ENCRYPTION_KEY`; there is no plaintext key-file fallback.
- The gateway bearer is scoped to a workspace and connection, generated from a
  process-only secret, and rotated on every idexal Os server restart.
- Startup reconciliation rewrites managed runtime MCP entries with the current
  loopback port and bearer while keeping the encrypted provider credential.
- Disconnect deletes the stored credential and disables the gateway entry.

## Network boundary

Outside explicit local development, managed providers must use HTTPS and
resolve only to public addresses. Redirects are revalidated, HTTPS downgrade is
blocked, and credential-bearing request bodies cannot cross origins. This
prevents a configured MCP URL or redirect from turning the desktop gateway into
an internal-network request proxy.

## Verification

Focused server coverage proves discovery and DCR, PKCE authorization, encrypted
credential persistence, OpenCode runtime registration, gateway tool listing and
invocation, refresh-token recovery, restart reconciliation with bearer
rotation, disconnect, and the outbound URL guard. The desktop and server
TypeScript projects and the compiled embedded server build are also checked.
