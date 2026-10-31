import { z } from "zod"

export const IDEXAL_AFFORDANCE_SCHEMA_VERSION = 1

export const idexalOsAffordanceKindSchema = z.enum(["query", "command", "guidance"])
export type IdexalOsAffordanceKind = z.infer<typeof idexalOsAffordanceKindSchema>

export const idexalOsProviderKindSchema = z.enum(["builtin", "extension", "mcp", "connect"])
export type IdexalOsProviderKind = z.infer<typeof idexalOsProviderKindSchema>

export const idexalOsProviderRefSchema = z.object({
  id: z.string().trim().min(1),
  kind: idexalOsProviderKindSchema,
})
export type IdexalOsProviderRef = z.infer<typeof idexalOsProviderRefSchema>

export const idexalOsAffordanceArgumentSchema = z.object({
  name: z.string().trim().min(1),
  type: z.enum(["string", "number", "boolean", "object", "array", "unknown"]),
  required: z.boolean(),
  description: z.string().trim().min(1).optional(),
})
export type IdexalOsAffordanceArgument = z.infer<typeof idexalOsAffordanceArgumentSchema>

export const idexalOsAffordanceEffectsSchema = z.object({
  data: z.enum(["none", "read", "write"]),
  ui: z.enum(["none", "focus", "navigate", "layout", "dialog"]),
  external: z.boolean(),
})
export type IdexalOsAffordanceEffects = z.infer<typeof idexalOsAffordanceEffectsSchema>

export const idexalOsAffordanceAvailabilitySchema = z.object({
  enabled: z.boolean(),
  reason: z.string().trim().min(1).optional(),
})
export type IdexalOsAffordanceAvailability = z.infer<typeof idexalOsAffordanceAvailabilitySchema>

export const idexalOsAffordanceExecutorSchema = z.discriminatedUnion("kind", [
  z.object({ kind: z.literal("idexalos") }),
  z.object({
    kind: z.literal("tool"),
    tool: z.string().trim().min(1),
  }),
])
export type IdexalOsAffordanceExecutor = z.infer<typeof idexalOsAffordanceExecutorSchema>

export const idexalOsAffordanceDescriptorSchema = z.object({
  id: z.string().trim().min(1),
  kind: idexalOsAffordanceKindSchema,
  title: z.string().trim().min(1),
  description: z.string().trim().min(1),
  provider: idexalOsProviderRefSchema,
  arguments: z.array(idexalOsAffordanceArgumentSchema),
  effects: idexalOsAffordanceEffectsSchema,
  confirmation: z.enum(["never", "destructive", "always"]),
  availability: idexalOsAffordanceAvailabilitySchema,
  executor: idexalOsAffordanceExecutorSchema,
})
export type IdexalOsAffordanceDescriptor = z.infer<typeof idexalOsAffordanceDescriptorSchema>

/**
 * The model a session is bound to, as agents pass it to `session.create`
 * (`model` argument) and read it back from `session.list_sessions` entries
 * and `session.read` results (`model` field). `variant` is the reasoning /
 * thinking effort the composer shows as its behavior pill (for example
 * `low`, `medium`, `high`); null means the provider default. The source is
 * the engine's session record: bound at creation, updated by every turn.
 * Results carry null instead of the object before any model is bound, and
 * the engine's literal "default" variant reads back as null.
 */
export const idexalOsSessionModelSchema = z.object({
  providerId: z.string().trim().min(1),
  modelId: z.string().trim().min(1),
  variant: z.string().trim().min(1).max(60).nullable(),
  displayName: z.string().optional(),
  providerName: z.string().optional(),
})
export type IdexalOsSessionModel = z.infer<typeof idexalOsSessionModelSchema>

export const idexalOsModelSelectorSchema = z.object({
  providerId: idexalOsSessionModelSchema.shape.providerId.optional(),
  modelId: idexalOsSessionModelSchema.shape.modelId.optional(),
  alias: z.string().trim().min(1).optional(),
  displayName: z.string().trim().min(1).optional(),
  variant: idexalOsSessionModelSchema.shape.variant.optional(),
}).superRefine((value, context) => {
  const names = [value.alias, value.displayName].filter((name) => name !== undefined)
  if (value.alias && (value.displayName || value.modelId)) {
    context.addIssue({ code: "custom", message: "Use exactly one of modelId, alias or displayName." })
  }
  if (!names.length && !value.modelId) {
    context.addIssue({ code: "custom", path: ["modelId"], message: "Provide providerId/modelId, alias or displayName." })
  }
  if (value.modelId && !value.providerId) {
    context.addIssue({ code: "custom", path: ["providerId"], message: "providerId is required with modelId." })
  }
})

export const idexalOsModelsListArgsSchema = z.object({ workspaceId: z.string().trim().min(1) })
export const idexalOsModelsListResultSchema = z.object({
  ok: z.literal(true),
  workspaceId: idexalOsModelsListArgsSchema.shape.workspaceId,
  models: z.array(idexalOsSessionModelSchema.omit({ variant: true }).extend({
    displayName: z.string(), providerName: z.string(), available: z.literal(true),
  })),
})
export type IdexalOsCatalogModel = Pick<IdexalOsSessionModel, "providerId" | "modelId"> & {
  displayName: string
  providerName: string
}

export const idexalOsEngineProviderCatalogSchema = z.object({
  connected: z.array(z.string()),
  all: z.array(z.object({
    id: z.string(),
    name: z.string(),
    models: z.record(z.string(), z.object({ name: z.string().optional() })),
  })),
})

export function idexalOsCatalogModels(value: z.infer<typeof idexalOsEngineProviderCatalogSchema>): IdexalOsCatalogModel[] {
  return value.all.filter((provider) => value.connected.includes(provider.id)).flatMap((provider) =>
    Object.entries(provider.models).map(([modelId, model]) => ({
      providerId: provider.id, modelId, displayName: model.name || modelId, providerName: provider.name,
    })),
  )
}

export function labelIdexalOsSessionModel(model: IdexalOsSessionModel | null, catalog: readonly IdexalOsCatalogModel[]): IdexalOsSessionModel | null {
  if (!model) return null
  const entry = catalog.find((entry) => entry.providerId === model.providerId && entry.modelId === model.modelId)
  return entry ? { ...model, displayName: entry.displayName, providerName: entry.providerName } : model
}

export function resolveIdexalOsModel(selector: z.infer<typeof idexalOsModelSelectorSchema>, catalog: readonly IdexalOsCatalogModel[]): IdexalOsSessionModel {
  const name = selector.modelId ? undefined : selector.alias ?? selector.displayName
  const matches = catalog.filter((entry) => name !== undefined
    ? entry.displayName.toLowerCase() === name.toLowerCase() && (!selector.providerId || entry.providerId.toLowerCase() === selector.providerId.toLowerCase())
    : entry.providerId === selector.providerId && entry.modelId === selector.modelId)
  const match = matches[0]
  if (matches.length !== 1 || !match) {
    throw new Error(`${matches.length ? "Ambiguous" : "Unavailable"} model: ${name ?? `${selector.providerId}/${selector.modelId}`}. Use models.list with this workspaceId${matches.length ? " and qualify the name with providerId" : ""}.`)
  }
  return { providerId: match.providerId, modelId: match.modelId, displayName: match.displayName, providerName: match.providerName,
    variant: selector.variant && selector.variant !== "default" ? selector.variant : null }
}

export const idexalOsSessionActivityInventorySchema = z.object({
  working: z.boolean(),
  descendantActivity: z.object({
    busy: z.number().int().nonnegative(),
    waiting: z.number().int().nonnegative(),
    unknown: z.number().int().nonnegative(),
  }),
  inventoryComplete: z.boolean(),
})
export type IdexalOsSessionActivityInventory = z.infer<typeof idexalOsSessionActivityInventorySchema>

/**
 * Where a request came from: the conversation (session) whose agent issued
 * it. Set by the idexal Os bridge, never by the agent, so UI commands such as
 * opening a browser tab can act for the requesting conversation instead of
 * whichever one happens to be on screen.
 */
export const idexalOsAffordanceOriginSchema = z.object({
  sessionId: z.string().trim().min(1),
  workspaceId: z.string().trim().min(1).optional(),
})
export type IdexalOsAffordanceOrigin = z.infer<typeof idexalOsAffordanceOriginSchema>

export const idexalOsAffordanceRequestSchema = z.object({
  id: z.string().trim().min(1),
  args: z.record(z.string(), z.unknown()).optional(),
  expectedRevision: z.number().int().nonnegative().optional(),
  actor: z.string().trim().min(1).optional(),
  origin: idexalOsAffordanceOriginSchema.optional(),
})
export type IdexalOsAffordanceRequest = z.infer<typeof idexalOsAffordanceRequestSchema>

const idexalOsAffordanceSuccessSchema = z.object({
  ok: z.literal(true),
  id: z.string(),
  result: z.unknown().optional(),
  revision: z.number().int().nonnegative().optional(),
  effects: idexalOsAffordanceEffectsSchema,
})

/**
 * Structured outcomes an action can report so the agent can decide instead of
 * retrying a transport-looking error. Warnings travel back through the channel
 * the request came from: an agent never gets a dialog, it gets one of these.
 * - `target_working`: the target session is still working; ask the person to
 *   stop it if they want it closed, otherwise leave it running.
 * - `self_archive_while_working`: a session asked to archive itself (or its
 *   parent) from inside its own running turn; finish the turn, the reviewer
 *   archives.
 * - `verification_failed`: archive safety checks could not finish; no archive
 *   mutation was sent by this attempt.
 * - `archive_outcome_unknown`: the archive mutation was sent but its outcome
 *   could not be confirmed; read the session before considering another attempt.
 */
export const idexalOsAffordanceFailureCodeSchema = z.enum([
  "unavailable",
  "invalid-args",
  "conflict",
  "failed",
  "target_working",
  "self_archive_while_working",
  "verification_failed",
  "archive_outcome_unknown",
])
export type IdexalOsAffordanceFailureCode = z.infer<typeof idexalOsAffordanceFailureCodeSchema>

const idexalOsAffordanceFailureSchema = z.object({
  ok: z.literal(false),
  id: z.string(),
  error: z.string(),
  code: idexalOsAffordanceFailureCodeSchema,
  hint: z.string().optional(),
  revision: z.number().int().nonnegative().optional(),
})

export const idexalOsAffordanceResultSchema = z.discriminatedUnion("ok", [
  idexalOsAffordanceSuccessSchema,
  idexalOsAffordanceFailureSchema,
])
export type IdexalOsAffordanceResult = z.infer<typeof idexalOsAffordanceResultSchema>
