import {
  credentialPromptDestination,
  credentialPromptInstruction,
  credentialPromptSchema,
  type CredentialPrompt,
} from "@idexal/types/den/credential-prompt"
import { MICROSOFT_FOUNDRY_PROVIDER_ID, microsoftFoundryBaseUrl } from "@idexal-ee/utils/microsoft-foundry-catalog"
import { inferenceCredentialEnvNames } from "@idexal-ee/utils/inference-credentials"
import { isLiteLlmProviderId } from "@idexal-ee/utils/litellm-catalog"
import { env } from "./env.js"

function text(record: unknown, ...path: string[]): string | null {
  let value = record
  for (const key of path) {
    if (typeof value !== "object" || value === null) return null
    value = Object.getOwnPropertyDescriptor(value, key)?.value
  }
  return typeof value === "string" && value.trim() ? value.trim() : null
}

/** SDK defaults mirror ee/apps/gateway/src/protocols.ts; a parity test keeps them aligned. */
const GATEWAY_SDK_DEFAULTS: Record<string, string> = {
  "@ai-sdk/openai": "https://api.openai.com/v1",
  "@ai-sdk/anthropic": "https://api.anthropic.com/v1",
  "@ai-sdk/mistral": "https://api.mistral.ai/v1",
  "@ai-sdk/google": "https://generativelanguage.googleapis.com/v1beta",
}

/**
 * Where an AI Gateway provider sends its key, shown above the private field.
 * An explicit upstream wins; an unparseable one shows nothing rather than a
 * different, default destination.
 */
export function gatewayUpstreamDestination(providerId: string, providerConfig: unknown, settings: unknown): string | null {
  const explicit = text(settings, "upstreamBaseUrl") ?? text(providerConfig, "options", "baseURL") ?? text(providerConfig, "api")
  if (explicit) return credentialPromptDestination(explicit)
  const resourceName = text(settings, "resourceName") ?? text(providerConfig, "options", "resourceName")
  if (providerId === MICROSOFT_FOUNDRY_PROVIDER_ID) return credentialPromptDestination(microsoftFoundryBaseUrl(resourceName))
  const npm = text(providerConfig, "npm")
  if (npm === "@ai-sdk/azure" && resourceName && /^[a-zA-Z0-9][a-zA-Z0-9-]{0,62}$/.test(resourceName)) return `https://${resourceName.toLowerCase()}.openai.azure.com/openai`
  return npm ? GATEWAY_SDK_DEFAULTS[npm] ?? null : null
}

/**
 * Credential prompts are Den tool-result metadata that ask the person, in
 * idexal Os, for a key the agent must never see. They carry no value field.
 */
export function mcpConnectionKeyPrompt(connection: { id: string; name: string; url: string }): CredentialPrompt {
  return credentialPromptSchema.parse({
    schemaVersion: "1",
    kind: "credential_prompt",
    target: { type: "mcp_connection_key", connectionId: connection.id },
    name: connection.name,
    destination: credentialPromptDestination(connection.url),
  })
}

export function pluginConnectionKeyPrompt(input: { pluginId: string; configObjectId: string; serverName: string; connection: { id: string; name: string; url: string } }): CredentialPrompt {
  return credentialPromptSchema.parse({
    schemaVersion: "1",
    kind: "credential_prompt",
    target: { type: "plugin_connection_key", pluginId: input.pluginId, configObjectId: input.configObjectId, serverName: input.serverName, connectionId: input.connection.id },
    name: input.connection.name,
    destination: credentialPromptDestination(input.connection.url),
  })
}

/** What a person enters for one AI Gateway provider's shared credential, or null when chat can't collect it. */
export type GatewayCredentialShape =
  | { kind: "api_key" }
  | { kind: "aws_keys" }
  | { kind: "gcp_service_account" }
  | { kind: "api_key_map"; envNames: string[] }

export function gatewayCredentialShape(providerId: string, envNames: readonly string[]): GatewayCredentialShape | null {
  if (isLiteLlmProviderId(providerId)) return null
  if (providerId === "google-vertex" || providerId === "google-vertex-anthropic") return { kind: "gcp_service_account" }
  if (providerId === "amazon-bedrock" || providerId === "amazon-bedrock-mantle") return { kind: "aws_keys" }
  if (envNames.length === 1) return { kind: "api_key" }
  const keys = inferenceCredentialEnvNames(envNames)
  return keys.length >= 1 && keys.length <= 6 ? { kind: "api_key_map", envNames: keys } : null
}

export function gatewayProviderCredentialPrompt(input: { inferenceProviderId: string; credentialSetId: string; name: string; destination: string | null; shape: GatewayCredentialShape }): CredentialPrompt {
  if (input.shape.kind === "api_key") return gatewayProviderKeyPrompt(input)
  return credentialPromptSchema.parse({
    schemaVersion: "1",
    kind: "credential_prompt",
    target: {
      type: "gateway_provider_credential",
      inferenceProviderId: input.inferenceProviderId,
      credentialSetId: input.credentialSetId,
      credentialKind: input.shape.kind,
      ...(input.shape.kind === "api_key_map" ? { envNames: input.shape.envNames } : {}),
    },
    name: input.name,
    destination: credentialPromptDestination(input.destination),
  })
}

export function gatewayProviderKeyPrompt(input: { inferenceProviderId: string; credentialSetId: string; name: string; destination: string | null }): CredentialPrompt {
  return credentialPromptSchema.parse({
    schemaVersion: "1",
    kind: "credential_prompt",
    target: { type: "gateway_provider_key", inferenceProviderId: input.inferenceProviderId, credentialSetId: input.credentialSetId },
    name: input.name,
    destination: credentialPromptDestination(input.destination),
  })
}

/** The dashboard page where a person can add the same key outside idexal Os chat. */
export function credentialPromptManageUrl(prompt: CredentialPrompt): string | null {
  const path = prompt.target.type === "mcp_connection_key" || prompt.target.type === "plugin_connection_key"
    ? `/dashboard/mcp-connections/${encodeURIComponent(prompt.target.connectionId)}`
    : "/dashboard/ai-gateway?tab=ai-providers"
  try {
    return new URL(path, env.betterAuthUrl).toString()
  } catch {
    return null
  }
}

/** Fields added to a REST response so the agent and the host both read the prompt. */
export function credentialPromptResponseFields(prompt: CredentialPrompt) {
  const manageUrl = credentialPromptManageUrl(prompt)
  return { credentialPrompt: prompt, nextStep: credentialPromptInstruction(manageUrl) }
}
