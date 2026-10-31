import type { MiddlewareHandler } from "hono"
import { organizationFeatureEnabled } from "../features.js"
import type { AuthContextVariables } from "../session.js"
import type { OrganizationContextVariables } from "./organization-context.js"

export const SECURE_ENTRY_REQUIRED = {
  error: "secure_entry_required",
  message: "Do not send keys through the agent or an API key. Leave the key out; the person adds it in a private field in idexal Os.",
} as const

/** Whether a JSON body sets any of these top-level fields to a value. */
export function bodyCarriesSecret(body: unknown, fields: readonly string[]): boolean {
  if (typeof body !== "object" || body === null || Array.isArray(body)) return false
  return fields.some((field) => {
    const value: unknown = Object.getOwnPropertyDescriptor(body, field)?.value
    return value !== undefined && value !== null && value !== ""
  })
}

/**
 * Entering a secret is human-only. The agent (internal MCP principal) is
 * always refused; with secure keys in chat on, so are API keys. Mount after
 * the organization route so the rollout can be read; reads a clone of the
 * body, so validators downstream still see it.
 */
export function refuseMachineSecrets(fields: readonly string[]): MiddlewareHandler<{ Variables: Partial<AuthContextVariables & OrganizationContextVariables> }> {
  return async (c, next) => {
    const agent = c.get("session")?.id === "mcp_internal"
    if (!agent && !c.get("apiKey")) return next()
    const body: unknown = await c.req.raw.clone().json().catch(() => null)
    if (!bodyCarriesSecret(body, fields)) return next()
    const organizationId = c.get("organizationContext")?.organization.id
    if (agent || !organizationId || await organizationFeatureEnabled(organizationId, "secureSecretFields")) {
      return c.json(SECURE_ENTRY_REQUIRED, 400)
    }
    return next()
  }
}
