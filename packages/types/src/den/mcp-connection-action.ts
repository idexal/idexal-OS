import { z } from "zod"

export const IDEXAL_CLOUD_MCP_CONNECTION_ACTION_VERSION = 1 as const
export const IDEXAL_CLOUD_MCP_CONNECTION_ACTION_KIND = "connection_action" as const
export const IDEXAL_CLOUD_MCP_CONNECTION_ACTION_SOURCE = "idexal-os-cloud" as const

export const idexalOsCloudMcpConnectionActionSchema = z.object({
  version: z.literal(IDEXAL_CLOUD_MCP_CONNECTION_ACTION_VERSION),
  kind: z.literal(IDEXAL_CLOUD_MCP_CONNECTION_ACTION_KIND),
  source: z.literal(IDEXAL_CLOUD_MCP_CONNECTION_ACTION_SOURCE),
  connectionId: z.string().min(1),
  connectionName: z.string().min(1),
  authType: z.enum(["oauth", "apikey", "none"]),
  credentialMode: z.enum(["shared", "per_member"]),
  state: z.enum(["needs_connection", "reauth_required", "provider_error"]),
  actor: z.enum([
    "member",
    "organization_admin",
    "provider_admin",
    "network_admin",
    "idexalos",
  ]),
  action: z.object({
    type: z.enum([
      "connect",
      "reconnect",
      "update_credentials",
      "inspect_connection",
      "fix_provider",
      "fix_network",
      "contact_idexalos",
    ]),
    surface: z.enum([
      "idexal_os_your_connections",
      "idexal_os_organization_connections",
      "provider_admin_console",
      "network_infrastructure",
      "idexal_os_support",
    ]),
    retry: z.literal("search_capabilities"),
  }),
})

/**
 * The only connection action that chat may execute directly. Shared OAuth,
 * API-key, provider-admin, and support actions remain descriptive because the
 * current member may not own the credential or have permission to repair it.
 */
export const idexalOsCloudMcpInlineReconnectSchema = idexalOsCloudMcpConnectionActionSchema.extend({
  authType: z.literal("oauth"),
  credentialMode: z.literal("per_member"),
  state: z.literal("reauth_required"),
  actor: z.literal("member"),
  action: z.object({
    type: z.literal("reconnect"),
    surface: z.literal("idexal_os_your_connections"),
    retry: z.literal("search_capabilities"),
  }),
})

export type IdexalOsCloudMcpConnectionAction = z.infer<typeof idexalOsCloudMcpConnectionActionSchema>
export type IdexalOsCloudMcpInlineReconnect = z.infer<typeof idexalOsCloudMcpInlineReconnectSchema>
