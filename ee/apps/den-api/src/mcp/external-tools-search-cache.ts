import type { listExternalMcpTools } from "../capability-sources/external-mcp-client-runtime.js"

const SUCCESS_TTL_MS = 10 * 60_000
const FAILURE_TTL_MS = 60_000
export const EXTERNAL_TOOLS_SEARCH_CACHE_MAX_ENTRIES = 500
const SHARED_CREDENTIAL_IDENTITY = "shared"

export type ExternalToolsSearchCacheKey = {
  organizationId: string
  connectionId: string
  updatedAt: Date
  credentialMode: "shared" | "per_member"
  orgMembershipId?: string
}

export type ExternalToolsSearchProbeResult =
  | { outcome: "success"; tools: Awaited<ReturnType<typeof listExternalMcpTools>> }
  | { outcome: "failure"; error: unknown }

type CacheEntry = {
  expiresAt: number
  result: ExternalToolsSearchProbeResult
}

const entries = new Map<string, CacheEntry>()

function cacheKey(input: ExternalToolsSearchCacheKey): string {
  const identity = input.credentialMode === "per_member"
    ? input.orgMembershipId
    : SHARED_CREDENTIAL_IDENTITY
  if (!identity) throw new Error("Per-member external MCP search cache keys require an organization membership id.")
  return JSON.stringify([input.organizationId, input.connectionId, input.updatedAt.getTime(), identity])
}

export function getExternalToolsSearchCache(
  key: ExternalToolsSearchCacheKey,
  now: () => number = Date.now,
): ExternalToolsSearchProbeResult | undefined {
  const serializedKey = cacheKey(key)
  const entry = entries.get(serializedKey)
  if (!entry) return undefined
  if (entry.expiresAt <= now()) {
    entries.delete(serializedKey)
    return undefined
  }
  return entry.result
}

export function setExternalToolsSearchCache(
  key: ExternalToolsSearchCacheKey,
  result: ExternalToolsSearchProbeResult,
  now: () => number = Date.now,
): void {
  const serializedKey = cacheKey(key)
  entries.delete(serializedKey)
  entries.set(serializedKey, {
    expiresAt: now() + (result.outcome === "success" ? SUCCESS_TTL_MS : FAILURE_TTL_MS),
    result,
  })
  while (entries.size > EXTERNAL_TOOLS_SEARCH_CACHE_MAX_ENTRIES) {
    const oldestKey = entries.keys().next().value
    if (oldestKey === undefined) break
    entries.delete(oldestKey)
  }
}

export function clearExternalToolsSearchCache(): void {
  entries.clear()
}
