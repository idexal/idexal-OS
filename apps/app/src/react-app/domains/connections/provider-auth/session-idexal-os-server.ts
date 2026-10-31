// Session-route adapter for the provider-auth store's `idexalOsServer` slice.
//
// The settings route feeds the store the full idexal-os-server store, whose
// snapshot carries the server's real capabilities (including `providerSync`)
// and host-token auth. The session route used to fabricate a snapshot with
// hard-coded `{ config }` capabilities and no auth at all, so on the app's
// default surface `serverHandlesProviderSync()` was permanently false:
// PUT /den-session never fired after sign-in, the local server never learned
// the Den session, and server-side cloud provider sync never started (#3671).
//
// This adapter reports the truth for the endpoint it wraps:
// - loopback local endpoints (the desktop's own idexal Os server) advertise
//   `providerSync: true` — every idexal Os server does
//   (apps/server/src/types.ts `Capabilities.providerSync: true`) — and carry
//   the live host token so the store can PUT /den-session and
//   POST /cloud-provider-sync/run;
// - non-loopback local URL overrides stay config-only:
//   a local workspace label does not authorize forwarding desktop credentials.
import {
  createIdexalOsServerClient,
  isLoopbackIdexalOsServerUrl,
  readIdexalOsServerSettings,
  type IdexalOsServerClient,
} from "@/app/lib/idexal-os-server";
import type { ResolvedWorkspaceEndpoint } from "@/app/lib/workspace-endpoint";
import type { ProviderAuthIdexalOsServer } from "./store";

type SessionIdexalOsServerSnapshot = ReturnType<ProviderAuthIdexalOsServer["getSnapshot"]>;

/** The local server that owns the workspace, or the engine root before any workspace exists. */
export type SessionServerEndpoint = Pick<ResolvedWorkspaceEndpoint, "baseUrl" | "token" | "client">;

export type CreateSessionIdexalOsServerInput = {
  endpoint: () => SessionServerEndpoint | null;
  /** Live host token from the desktop runtime (idexalOsServerInfo). */
  hostToken?: () => string;
  generation?: () => number | null;
};

function resolveHostToken(endpoint: SessionServerEndpoint, live: string): string {
  // Fallback mirrors idexal-os-server-store's getAuth(): persisted settings may
  // hold the host token (ensureDesktopLocalIdexalOsConnection writes it), but
  // both live and stored host tokens must stay on loopback servers.
  if (!isLoopbackIdexalOsServerUrl(endpoint.baseUrl)) return "";
  if (live) return live;
  return readIdexalOsServerSettings().hostToken?.trim() ?? "";
}

export function createSessionIdexalOsServer(
  input: CreateSessionIdexalOsServerInput,
): ProviderAuthIdexalOsServer {
  let clientCacheKey = "";
  let clientCacheValue: IdexalOsServerClient | null = null;

  const hostAwareClient = (endpoint: SessionServerEndpoint, hostToken: string): IdexalOsServerClient => {
    if (!hostToken) return endpoint.client;
    const key = `${endpoint.baseUrl}\u001f${endpoint.token}\u001f${hostToken}`;
    if (key !== clientCacheKey || !clientCacheValue) {
      clientCacheKey = key;
      clientCacheValue = createIdexalOsServerClient({
        baseUrl: endpoint.baseUrl,
        token: endpoint.token || undefined,
        hostToken,
      });
    }
    return clientCacheValue;
  };

  return {
    getSnapshot: (): SessionIdexalOsServerSnapshot => {
      const endpoint = input.endpoint();
      if (!endpoint) {
        return {
          idexalOsServerStatus: "disconnected",
          idexalOsServerClient: null,
          idexalOsServerCapabilities: null,
        };
      }
      if (!isLoopbackIdexalOsServerUrl(endpoint.baseUrl)) {
        return {
          idexalOsServerStatus: "connected",
          idexalOsServerClient: endpoint.client,
          idexalOsServerCapabilities: { config: { read: true, write: true } },
        };
      }
      const hostToken = resolveHostToken(endpoint, input.hostToken?.().trim() ?? "");
      return {
        idexalOsServerStatus: "connected",
        idexalOsServerClient: hostAwareClient(endpoint, hostToken),
        idexalOsServerHostInfo: { generation: input.generation?.() ?? null },
        idexalOsServerAuth: {
          token: endpoint.token || undefined,
          hostToken: hostToken || undefined,
        },
        idexalOsServerCapabilities: {
          config: { read: true, write: true },
          providerSync: true,
        },
      };
    },
  };
}
