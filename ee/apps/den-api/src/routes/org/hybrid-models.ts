/**
 * Hybrid Models API - Build custom AI model bundles from multiple providers
 * 
 * Features:
 * - Combine multiple models from different providers into a single hybrid model
 * - Round-robin distribution among equivalent models
 * - Intelligent task-based routing (code, search, image generation)
 * - Admin-controlled aggregation and distribution strategies
 */

import { and, desc, eq, inArray, sql } from "@idexal-ee/den-db/drizzle"
import { HybridModelTable, HybridModelGroupTable, HybridModelProviderAccessTable, LlmProviderTable, MemberTable, TeamTable } from "@idexal-ee/den-db/schema"
import { createDenTypeId } from "@idexal-ee/utils/typeid"
import type { Hono } from "hono"
import { describeRoute, type DescribeRouteOptions } from "hono-openapi"
import { z } from "zod"
import { jsonValidator, orgMemberRoute, paramValidator } from "../../middleware/index.js"
import { db } from "../../db.js"
import { denTypeIdSchema, invalidRequestSchema, jsonResponse, unauthorizedSchema } from "../../openapi.js"
import type { OrgRouteVariables } from "./shared.js"

type HybridModelId = typeof HybridModelTable.$inferSelect.id
type HybridModelGroupId = typeof HybridModelGroupTable.$inferSelect.id

// Schema definitions for hybrid models
const hybridModelBaseSchema = z.object({
  name: z.string().trim().min(1).max(255),
  description: z.string().optional(),
  groupIds: z.array(denTypeIdSchema("hybridModelGroup")).min(1),
  strategy: z.enum(["round-robin", "task-routing", "weighted", "fallback"]),
  config: z.unknown().optional(),
})

const hybridModelCreateSchema = z.object({
  ...hybridModelBaseSchema.shape,
  organizationId: denTypeIdSchema("organization"),
})

const hybridModelUpdateSchema = hybridModelBaseSchema.partial()

const hybridModelGroupSchema = z.object({
  name: z.string().trim().min(1).max(255),
  modelIds: z.array(z.string().trim().min(1).max(255)).min(1),
  providerIds: z.array(z.string().trim().min(1).max(255)).min(1),
  weights: z.record(z.number().int().positive()).optional(), // For weighted strategy
  role: z.enum(["coding", "search", "image", "text", "multimodal"]).optional(), // For task-routing
})

const hybridModelListQuerySchema = z.object({
  scope: z.enum(["usable", "manageable"]).optional().default("usable"),
})

// Route descriptions
const describeHybridRoute = (options: Omit<DescribeRouteOptions, "x-mcp">) => 
  describeRoute({ ...options, "x-mcp": false })

export function registerOrgHybridModelsRoutes<T extends { Variables: OrgRouteVariables }>(app: Hono<T>) {
  
  // List all hybrid models in organization
  app.get(
    "/v1/hybrid-models",
    describeHybridRoute({
      tags: ["Hybrid Models"],
      summary: "List hybrid models",
      description: "Lists usable or manageable hybrid models. Hybrid models combine multiple AI models from different providers into a single intelligent bundle.",
      responses: {
        200: jsonResponse("Hybrid models list.", z.object({ hybridModels: z.array(z.object({}).passthrough()) })),
        401: jsonResponse("Unauthorized.", unauthorizedSchema),
      },
    }),
    orgMemberRoute(),
    async (c) => {
      const payload = c.get("organizationContext")
      
      const models = await db.select().from(HybridModelTable)
        .where(eq(HybridModelTable.organizationId, payload.organization.id))
        .orderBy(desc(HybridModelTable.createdAt))
        .limit(100)
      
      return c.json({ hybridModels: models })
    },
  )

  // Create a new hybrid model
  app.post(
    "/v1/hybrid-models",
    describeHybridRoute({
      tags: ["Hybrid Models"],
      summary: "Create hybrid model",
      description: "Creates a new hybrid model by combining multiple models from different providers. Supports round-robin, task-based routing, weighted distribution, and fallback strategies.",
      responses: {
        201: jsonResponse("Hybrid model created.", z.object({ hybridModel: z.object({}).passthrough() })),
        400: jsonResponse("Invalid request.", invalidRequestSchema),
        401: jsonResponse("Unauthorized.", unauthorizedSchema),
      },
    }),
    orgMemberRoute(),
    jsonValidator(hybridModelCreateSchema),
    async (c) => {
      const input = c.req.valid("json")
      const payload = c.get("organizationContext")
      
      const now = new Date()
      const hybridModelId = createDenTypeId("hybridModel")
      
      await db.insert(HybridModelTable).values({
        id: hybridModelId,
        organizationId: payload.organization.id,
        createdByOrgMembershipId: payload.currentMember.id,
        ...input,
        createdAt: now,
        updatedAt: now,
      })
      
      // Create groups for each entry in groupIds
      for (const groupId of input.groupIds) {
        await db.insert(HybridModelGroupTable).values({
          id: createDenTypeId("hybridModelGroup"),
          hybridModelId,
          name: `Group ${groupId}`,
          status: "active",
          createdAt: now,
          updatedAt: now,
        })
      }
      
      return c.json({ 
        hybridModel: {
          id: hybridModelId,
          ...input,
        } 
      }, 201)
    },
  )

  // Get a specific hybrid model
  app.get(
    "/v1/hybrid-models/:modelId",
    describeHybridRoute({
      tags: ["Hybrid Models"],
      summary: "Get hybrid model details",
      description: "Returns detailed information about a specific hybrid model including its groups and routing configuration.",
      responses: {
        200: jsonResponse("Hybrid model details.", z.object({ hybridModel: z.object({}).passthrough() })),
        404: jsonResponse("Not found.", z.object({ error: z.string() })),
        401: jsonResponse("Unauthorized.", unauthorizedSchema),
      },
    }),
    orgMemberRoute(),
    paramValidator(z.object({ modelId: denTypeIdSchema("hybridModel") })),
    async (c) => {
      const params = c.req.valid("param")
      const payload = c.get("organizationContext")
      
      const [model] = await db.select().from(HybridModelTable)
        .where(and(
          eq(HybridModelTable.id, params.modelId),
          eq(HybridModelTable.organizationId, payload.organization.id)
        ))
      
      if (!model) {
        return c.json({ error: "hybrid_model_not_found" }, 404)
      }
      
      // Get groups
      const groups = await db.select().from(HybridModelGroupTable)
        .where(eq(HybridModelGroupTable.hybridModelId, model.id))
      
      return c.json({ 
        hybridModel: {
          ...model,
          groups,
        } 
      })
    },
  )

  // Update hybrid model
  app.patch(
    "/v1/hybrid-models/:modelId",
    describeHybridRoute({
      tags: ["Hybrid Models"],
      summary: "Update hybrid model",
      description: "Updates an existing hybrid model's configuration.",
      responses: {
        200: jsonResponse("Hybrid model updated.", z.object({ hybridModel: z.object({}).passthrough() })),
        404: jsonResponse("Not found.", z.object({ error: z.string() })),
        401: jsonResponse("Unauthorized.", unauthorizedSchema),
      },
    }),
    orgMemberRoute(),
    paramValidator(z.object({ modelId: denTypeIdSchema("hybridModel") })),
    jsonValidator(hybridModelUpdateSchema),
    async (c) => {
      const params = c.req.valid("param")
      const input = c.req.valid("json")
      const payload = c.get("organizationContext")
      
      const [model] = await db.select().from(HybridModelTable)
        .where(and(
          eq(HybridModelTable.id, params.modelId),
          eq(HybridModelTable.organizationId, payload.organization.id)
        ))
      
      if (!model) {
        return c.json({ error: "hybrid_model_not_found" }, 404)
      }
      
      const now = new Date()
      const updated = { ...model, ...input, updatedAt: now }
      
      await db.update(HybridModelTable).set(updated).where(eq(HybridModelTable.id, model.id))
      
      return c.json({ hybridModel: updated })
    },
  )

  // Delete hybrid model
  app.delete(
    "/v1/hybrid-models/:modelId",
    describeHybridRoute({
      tags: ["Hybrid Models"],
      summary: "Delete hybrid model",
      description: "Deletes a hybrid model and its associated groups.",
      responses: {
        204: jsonResponse("Deleted successfully."),
        404: jsonResponse("Not found.", z.object({ error: z.string() })),
        401: jsonResponse("Unauthorized.", unauthorizedSchema),
      },
    }),
    orgMemberRoute(),
    paramValidator(z.object({ modelId: denTypeIdSchema("hybridModel") })),
    async (c) => {
      const params = c.req.valid("param")
      const payload = c.get("organizationContext")
      
      const [model] = await db.select().from(HybridModelTable)
        .where(and(
          eq(HybridModelTable.id, params.modelId),
          eq(HybridModelTable.organizationId, payload.organization.id)
        ))
      
      if (!model) {
        return c.json({ error: "hybrid_model_not_found" }, 404)
      }
      
      await db.delete(HybridModelTable).where(eq(HybridModelTable.id, model.id))
      return c.body(null, 204)
    },
  )

  // List hybrid model groups
  app.get(
    "/v1/hybrid-models/:modelId/groups",
    describeHybridRoute({
      tags: ["Hybrid Models"],
      summary: "List hybrid model groups",
      description: "Returns all groups within a hybrid model.",
      responses: {
        200: jsonResponse("Groups list.", z.object({ groups: z.array(z.object({}).passthrough()) })),
        404: jsonResponse("Not found.", z.object({ error: z.string() })),
      },
    }),
    orgMemberRoute(),
    paramValidator(z.object({ modelId: denTypeIdSchema("hybridModel") })),
    async (c) => {
      const params = c.req.valid("param")
      const payload = c.get("organizationContext")
      
      const groups = await db.select().from(HybridModelGroupTable)
        .where(eq(HybridModelGroupTable.hybridModelId, params.modelId))
        .orderBy(desc(HybridModelGroupTable.createdAt))
      
      return c.json({ groups })
    },
  )

  // Create hybrid model group
  app.post(
    "/v1/hybrid-models/:modelId/groups",
    describeHybridRoute({
      tags: ["Hybrid Models"],
      summary: "Create hybrid model group",
      description: "Creates a new group within a hybrid model. Groups contain models from the same provider family or version for rotation.",
      responses: {
        201: jsonResponse("Group created.", z.object({ group: z.object({}).passthrough() })),
        400: jsonResponse("Invalid request.", invalidRequestSchema),
        404: jsonResponse("Not found.", z.object({ error: z.string() })),
      },
    }),
    orgMemberRoute(),
    paramValidator(z.object({ modelId: denTypeIdSchema("hybridModel") })),
    jsonValidator(hybridModelGroupSchema),
    async (c) => {
      const params = c.req.valid("param")
      const input = c.req.valid("json")
      const payload = c.get("organizationContext")
      
      const [model] = await db.select().from(HybridModelTable)
        .where(eq(HybridModelTable.id, params.modelId))
      
      if (!model) {
        return c.json({ error: "hybrid_model_not_found" }, 404)
      }
      
      const now = new Date()
      const groupId = createDenTypeId("hybridModelGroup")
      
      const group = {
        id: groupId,
        hybridModelId: model.id,
        ...input,
        createdAt: now,
        updatedAt: now,
      }
      
      await db.insert(HybridModelGroupTable).values(group)
      
      return c.json({ group }, 201)
    },
  )

  // Test hybrid model routing (DEV ONLY)
  app.post(
    "/v1/hybrid-models/test-routing",
    describeHybridRoute({
      tags: ["Hybrid Models"],
      summary: "Test hybrid model routing",
      description: "Tests how a hybrid model would route a given task. Returns which models would be selected based on the strategy.",
      responses: {
        200: jsonResponse("Routing test result.", z.object({ 
          taskType: z.string(),
          selectedModels: z.array(z.object({})),
          strategyUsed: z.string()
        })),
        400: jsonResponse("Invalid request.", invalidRequestSchema),
      },
    }),
    orgMemberRoute(),
    jsonValidator(z.object({
      hybridModelId: denTypeIdSchema("hybridModel"),
      taskType: z.string().min(1).max(255),
    })),
    async (c) => {
      const input = c.req.valid("json")
      
      // This would implement actual routing logic
      // For now, return a demo response
      return c.json({
        taskType: input.taskType,
        selectedModels: [
          { id: "glm-5", weight: 0.3 },
          { id: "deepseek-v4", weight: 0.3 },
          { id: "mimo-6", weight: 0.2 },
          { id: "qwen-8", weight: 0.2 },
        ],
        strategyUsed: "task-routing",
      })
    },
  )
}
