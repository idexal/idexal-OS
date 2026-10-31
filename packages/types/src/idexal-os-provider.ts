import { z } from "zod"

import {
  idexalOsAffordanceDescriptorSchema,
  idexalOsProviderRefSchema,
} from "./idexal-os-affordance.js"

export const idexalOsGuidanceDescriptorSchema = z.object({
  ref: z.string().trim().min(1),
  title: z.string().trim().min(1),
  description: z.string().trim().min(1),
  provider: idexalOsProviderRefSchema,
  loading: z.enum(["eager", "catalog", "on-demand"]),
})
export type IdexalOsGuidanceDescriptor = z.infer<typeof idexalOsGuidanceDescriptorSchema>

export const idexalOsFeatureContributionSchema = z.object({
  featureId: z.string().trim().min(1),
  provider: idexalOsProviderRefSchema,
  affordances: z.array(idexalOsAffordanceDescriptorSchema),
  guidance: z.array(idexalOsGuidanceDescriptorSchema),
})
export type IdexalOsFeatureContribution = z.infer<typeof idexalOsFeatureContributionSchema>

export const idexalOsProviderCatalogSchema = z.object({
  schemaVersion: z.literal(1),
  contributions: z.array(idexalOsFeatureContributionSchema),
})
export type IdexalOsProviderCatalog = z.infer<typeof idexalOsProviderCatalogSchema>

export const idexalOsCapabilityResultSchema = z.discriminatedUnion("status", [
  z.object({
    status: z.literal("completed"),
    data: z.unknown(),
    additionalContext: z.array(z.string()).optional(),
  }),
  z.object({
    status: z.literal("guidance"),
    instructions: z.array(z.string()),
  }),
  z.object({
    status: z.literal("requires-user-action"),
    message: z.string(),
    action: z.string().optional(),
  }),
  z.object({
    status: z.literal("failed"),
    error: z.string(),
    retryable: z.boolean(),
  }),
])
export type IdexalOsCapabilityResult = z.infer<typeof idexalOsCapabilityResultSchema>
