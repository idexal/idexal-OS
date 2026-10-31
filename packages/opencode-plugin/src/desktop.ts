import { createHash } from "node:crypto"
import { fetchDesktopInventory, fetchDesktopSession } from "./desktop-credential-channel.ts"
import { DenAuthError, isAllowedApiBaseUrl, isRecord, normalizeBaseUrl, type DenSession, type Fetch } from "./den.ts"
import { subscribeSharedInventory } from "./inventory-events.ts"
import { inventoryRetry } from "./inventory-retry.ts"
import type { Json, PluginDefinition } from "./opencode.ts"
import { parseInventory, readOptions, snapshotFrom, type Inventory } from "./plugin.ts"
import { fetchGatewayInventory, type ProviderRecord } from "./providers.ts"

const CACHE_KEY = "desktop/provider-inventory"
const digest = (value: unknown) => createHash("sha256").update(JSON.stringify(value)).digest("hex")

/** Only a literal loopback endpoint may receive the process capability. No redirects. */
export function isDesktopSessionEndpoint(value: unknown): value is string {
  if (typeof value !== "string") return false
  try {
    const url = new URL(value)
    return url.protocol === "http:" && ["127.0.0.1", "[::1]"].includes(url.hostname)
      && !url.username && !url.password && !url.hash
  } catch { return false }
}

export interface DesktopSession {
  readonly apiBaseUrl: string
  readonly identity: string
  readonly orgId: string | null
  readonly inferenceOrigin: string
  readonly inferenceKey: string
}

// The shared inventory helpers accept a token-shaped identity key. In desktop
// mode this is ONLY an opaque hash, never a Den bearer, and the transport below
// replaces those helper headers with an identity-bound local inventory request.
function inventorySession(value: DesktopSession): DenSession {
  return { apiBaseUrl: value.apiBaseUrl, token: value.identity, orgId: value.orgId }
}

export async function readDesktopSession(fetcher: Fetch, endpoint: string, capability: string, signal?: AbortSignal): Promise<DesktopSession | null> {
  if (!isDesktopSessionEndpoint(endpoint) || !capability) return null
  const response = await fetcher(endpoint, {
    headers: { accept: "application/json", authorization: `Bearer ${capability}` },
    redirect: "error",
    signal: signal ? AbortSignal.any([signal, AbortSignal.timeout(1500)]) : AbortSignal.timeout(1500),
  })
  return parseDesktopSession(response, endpoint)
}

async function parseDesktopSession(response: Response | null, endpoint: string): Promise<DesktopSession | null> {
  if (!response) return null
  if (!response.ok) throw new Error("Desktop session unavailable")
  const value: unknown = await response.json()
  if (value === null) return null
  if (!isRecord(value) || typeof value.apiBaseUrl !== "string" || !isAllowedApiBaseUrl(value.apiBaseUrl)
    || "token" in value || typeof value.identity !== "string" || !/^[a-f0-9]{64}$/.test(value.identity)
    || !(value.orgId === null || typeof value.orgId === "string")
    || typeof value.inferenceOrigin !== "string" || value.inferenceOrigin !== new URL(endpoint).origin
    || typeof value.inferenceKey !== "string" || !/^ow_local_[a-f0-9]{64}$/.test(value.inferenceKey)) {
    throw new Error("Invalid desktop session")
  }
  return { apiBaseUrl: normalizeBaseUrl(value.apiBaseUrl), identity: value.identity, orgId: value.orgId,
    inferenceOrigin: value.inferenceOrigin, inferenceKey: value.inferenceKey }
}

/** Provider-only host path: never registers login methods, revokes Den sessions, or touches MCP. */
export function createDesktopProviderPlugin(deps: { fetch?: Fetch; now?: () => number; capability?: () => string | undefined; sessionIntervalMs?: number; retryMs?: number } = {}): PluginDefinition {
  const fetcher = deps.fetch ?? fetch
  const now = deps.now ?? Date.now
  return {
    id: "idexalos",
    async setup(ctx) {
      // The server supplies the resolved registry value, not a new environment flag.
      if (ctx.options.liveProviderSync !== true) return
      const endpoint = ctx.options.desktopSession
      const capability = deps.capability?.()
      if (!isDesktopSessionEndpoint(endpoint)) return
      if (typeof ctx.provider?.transform !== "function" || typeof ctx.provider?.reload !== "function" || typeof ctx.storage?.get !== "function") {
        throw new Error("idexal Os provider sync needs OpenCode V2 provider and storage APIs")
      }
      const options = { ...readOptions(ctx.options), mcp: false }
      const inventoryFetch = (source: DesktopSession): Fetch => async (input, init) => {
        const base = normalizeBaseUrl(source.apiBaseUrl)
        if (!input.startsWith(`${base}/`) || (init?.method ?? "GET") !== "GET" || init?.body != null) return new Response(null, { status: 404 })
        const path = input.slice(base.length)
        if (path !== "/v1/inference-providers?scope=usable" && path !== "/v1/inference-providers/inventory/events"
          && !/^\/v1\/inference-providers\/ipr_[a-z0-9_]{1,128}\/connect$/.test(path)) return new Response(null, { status: 404 })
        const signal = init?.signal ?? undefined
        const response = capability
          ? await fetcher(new URL(endpoint).origin + path, {
            method: "GET", redirect: "error", signal,
            headers: { accept: path.endsWith("/inventory/events") ? "text/event-stream" : "application/json",
              authorization: `Bearer ${capability}`, "x-idexal-os-provider-identity": source.identity },
          })
          : await fetchDesktopInventory(fetcher, endpoint, path, source.identity, signal)
        return response ?? new Response(null, { status: 503 })
      }
      const lifetime = new AbortController()
      let session: DesktopSession | null = null
      let key = ""
      let epoch = 0
      let stream: AbortController | null = null
      let refreshController = new AbortController()
      let providers: readonly ProviderRecord[] = []
      let applied = digest(providers)
      let polling = false
      let running: Promise<void> | null = null
      let queued = false
      let inventory: Inventory | null = null
      const retry = inventoryRetry(() => { void refresh() }, deps.retryMs)

      await ctx.provider.transform(editor => {
        for (const provider of providers) editor.add(provider)
      })
      const publish = async (next: readonly ProviderRecord[]) => {
        providers = next
        const fingerprint = digest(next)
        if (fingerprint === applied) return
        await ctx.provider.reload()
        if (providers === next) applied = fingerprint // Failed/stale reloads remain retryable.
      }
      const safeHeaders = (headers: Record<string, string> | undefined) => headers ? Object.fromEntries(Object.entries(headers)
        .filter(([name]) => !["authorization", "x-api-key", "api-key", "x-goog-api-key", "cookie", "host"].includes(name.toLowerCase()))) : undefined
      const records = (value: Inventory | null): readonly ProviderRecord[] => {
        if (!session || value?.gateway?.apiKey !== "ow_local_catalog") return []
        // Cached catalog definitions stay portable/public. Only publication binds
        // them to this engine's current loopback origin and identity-scoped key.
        const source = session
        return snapshotFrom(value, options, { type: "env", name: "idexal-os-desktop" }).providers.map(provider => {
          const baseURL = `${source.inferenceOrigin}/api/v1/providers/${provider.info.id}`
          return {
            info: { ...provider.info, settings: { ...provider.info.settings, baseURL, apiKey: source.inferenceKey }, headers: safeHeaders(provider.info.headers) },
            models: provider.models.map(model => ({ ...model, settings: { ...model.settings, baseURL, apiKey: source.inferenceKey }, headers: safeHeaders(model.headers) })),
          }
        })
      }
      const revoked = () => {
        epoch++
        retry.reset()
        refreshController.abort()
        refreshController = new AbortController()
        inventory = null
        queued = false
        // Keep the session/stream: membership can return without a new token.
        void ctx.storage.remove(CACHE_KEY).catch(() => {})
        void publish([]).catch(() => {})
      }
      const unauthorized = () => {
        epoch++
        retry.reset()
        refreshController.abort()
        stream?.abort()
        stream = null
        // Keep the rejected identity key so local session checks cannot reopen
        // it. Only a changed handoff (or engine restart) can authorize it again.
        session = null
        inventory = null
        queued = false
        void ctx.storage.remove(CACHE_KEY).catch(() => {})
        void publish([]).catch(() => {})
      }
      const refresh = (): Promise<void> => {
        if (lifetime.signal.aborted || !session) return Promise.resolve()
        if (running) { queued = true; return running }
        retry.cancel()
        const source = session
        const accountKey = key
        const generation = epoch
        const current = () => !lifetime.signal.aborted && generation === epoch
        running = (async () => {
          try {
            // Deliberately no 30s freshness shortcut: every event/reconnect is authoritative.
            const gateway = await fetchGatewayInventory(inventoryFetch(source), inventorySession(source), refreshController.signal, "desktop")
            if (!current()) return
            const next: Inventory = { version: 1, connectionKey: accountKey, apiBaseUrl: source.apiBaseUrl, fetchedAt: now(), gateway, mcp: null }
            inventory = next
            // Public definitions + a dummy credential only. Never persist raw
            // reusable credentials or engine-specific origin/ephemeral keys.
            const json: Json = JSON.parse(JSON.stringify(next))
            await ctx.storage.set(CACHE_KEY, json).catch(() => {})
            if (!current()) return
            await publish(records(next))
            if (current()) retry.reset()
          } catch (error) {
            if (!current()) return
            if (error instanceof DenAuthError) {
              if (error.status === 403) revoked()
              else unauthorized()
            } else {
              retry.schedule()
              await publish(records(inventory))
            }
          }
        })().catch(() => {}).finally(() => {
          running = null
          if (queued) { queued = false; void refresh() }
        })
        return running
      }
      const pollSession = async () => {
        if (polling || lifetime.signal.aborted) return
        polling = true
        try {
          // This local-only check detects sign-out/account/org/token changes without polling Den inventory.
          const next = await (capability
            ? readDesktopSession(fetcher, endpoint, capability, lifetime.signal)
            : fetchDesktopSession(fetcher, endpoint, lifetime.signal).then(response => parseDesktopSession(response, endpoint))).catch(() => null)
          if (lifetime.signal.aborted) return
          const nextKey = next ? digest({ apiBaseUrl: next.apiBaseUrl, identity: next.identity, orgId: next.orgId }) : ""
          if (nextKey === key) {
            if (session) session = next
            // A failed reload is independent of the network subscription and remains retryable.
            await publish(records(inventory)).catch(() => {})
            return
          }
          epoch++
          retry.reset()
          refreshController.abort()
          refreshController = new AbortController()
          stream?.abort()
          stream = null
          session = next
          key = nextKey
          inventory = null
          // Drop stale providers before reading cache or issuing any new-account request.
          await publish([]).catch(() => {})
          if (!next) {
            await ctx.storage.remove(CACHE_KEY).catch(() => {})
            return
          }
          const cached = parseInventory(await ctx.storage.get(CACHE_KEY).catch(() => undefined))
          if (cached?.connectionKey === key && cached.gateway?.apiKey === "ow_local_catalog" && !lifetime.signal.aborted) {
            inventory = cached
            await publish(records(cached)).catch(() => {})
          }
          if (lifetime.signal.aborted) return
          const controller = new AbortController()
          stream = controller
          // Re-resolve the handoff on reconnect, rather than retaining an expired token.
          void subscribeSharedInventory({
            identity: inventorySession(next),
            unauthorized,
            revoked,
            fetch: inventoryFetch(next),
            signal: AbortSignal.any([lifetime.signal, controller.signal]),
            session: async () => {
              await pollSession()
              return controller.signal.aborted || !session ? null : inventorySession(session)
            },
            changed: () => { void refresh() },
          })
          void refresh()
        } finally { polling = false }
      }
      void pollSession()
      const timer = setInterval(() => { void pollSession().catch(() => {}) }, deps.sessionIntervalMs ?? 1000)
      timer.unref?.()
      return () => {
        epoch++
        retry.reset()
        lifetime.abort()
        refreshController.abort()
        stream?.abort()
        clearInterval(timer)
      }
    },
  }
}

export default createDesktopProviderPlugin()
