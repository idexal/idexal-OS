import { z } from "zod"

import {
  idexalOsAffordanceDescriptorSchema,
  idexalOsProviderRefSchema,
} from "./idexal-os-affordance.js"
import { idexalOsFeatureContributionSchema } from "./idexal-os-provider.js"

export const IDEXAL_CONTEXT_SCHEMA_VERSION = 1

export const idexalOsSessionRefSchema = z.object({
  workspaceId: z.string().trim().min(1),
  sessionId: z.string().trim().min(1),
  title: z.string().optional(),
})
export type IdexalOsSessionRef = z.infer<typeof idexalOsSessionRefSchema>

export const idexalOsScreenSchema = z.discriminatedUnion("kind", [
  z.object({
    kind: z.literal("conversation"),
    route: z.string(),
    workspaceId: z.string().optional(),
    sessionId: z.string().optional(),
  }),
  z.object({
    kind: z.literal("settings"),
    route: z.string(),
    workspaceId: z.string().optional(),
    panel: z.string(),
  }),
  z.object({
    kind: z.literal("other"),
    route: z.string(),
  }),
])
export type IdexalOsScreen = z.infer<typeof idexalOsScreenSchema>

export const idexalOsConversationLayoutSchema = z.discriminatedUnion("kind", [
  z.object({ kind: z.literal("empty") }),
  z.object({
    kind: z.literal("single"),
    sessionId: z.string(),
    workspaceId: z.string().optional(),
  }),
  z.object({
    kind: z.literal("split"),
    primarySessionId: z.string(),
    primaryWorkspaceId: z.string().optional(),
    secondarySessionId: z.string(),
    secondaryWorkspaceId: z.string().optional(),
    focused: z.enum(["primary", "secondary"]),
  }),
])
export type IdexalOsConversationLayout = z.infer<typeof idexalOsConversationLayoutSchema>

export const idexalOsPanelTabSchema = z.object({
  id: z.string(),
  kind: z.enum(["browser", "artifact"]),
  label: z.string(),
  url: z.string().optional(),
  status: z.enum(["loading", "ready", "suspending", "suspended", "restoring"]).optional(),
})
export type IdexalOsPanelTab = z.infer<typeof idexalOsPanelTabSchema>

export const idexalOsResourceDescriptorSchema = z.object({
  ref: z.string().trim().min(1),
  kind: z.enum(["workspace", "session", "screen", "side-panel", "settings"]),
  title: z.string(),
  provider: idexalOsProviderRefSchema,
  state: z.record(z.string(), z.unknown()),
})
export type IdexalOsResourceDescriptor = z.infer<typeof idexalOsResourceDescriptorSchema>

export const idexalOsContextSnapshotSchema = z.object({
  schemaVersion: z.literal(IDEXAL_CONTEXT_SCHEMA_VERSION),
  revision: z.number().int().nonnegative(),
  capturedAt: z.string(),
  features: z.object({
    connectionQuestions: z.boolean().optional(),
  }).optional(),
  screen: idexalOsScreenSchema,
  conversations: z.object({
    tabs: z.array(idexalOsSessionRefSchema),
    layout: idexalOsConversationLayoutSchema,
    pinnedSessionIds: z.array(z.string()),
  }),
  chrome: z.object({
    sidebarOpen: z.boolean(),
    applicationMenuVisible: z.boolean(),
    rightSidebarExpanded: z.boolean(),
  }),
  execution: z.object({
    queries: z.literal("parallel"),
    commands: z.literal("serialized"),
    busyCommandId: z.string().nullable(),
    busyActor: z.string().nullable(),
  }),
  sidePanel: z.object({
    open: z.boolean(),
    ownerSessionId: z.string().nullable(),
    kind: z.enum(["panel", "extensions"]).nullable(),
    tabs: z.array(idexalOsPanelTabSchema),
    activeTabId: z.string().nullable(),
  }),
  resources: z.array(idexalOsResourceDescriptorSchema),
  availableAffordances: z.array(idexalOsAffordanceDescriptorSchema),
  contributions: z.array(idexalOsFeatureContributionSchema),
})
export type IdexalOsContextSnapshot = z.infer<typeof idexalOsContextSnapshotSchema>
