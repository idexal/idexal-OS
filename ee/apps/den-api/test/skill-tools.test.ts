import assert from "node:assert/strict"
import { test } from "node:test"
import { CLIENT_CAPABILITIES_META_KEY, CLIENT_INFO_META_KEY, McpServer, PROTOCOL_VERSION_META_KEY } from "@modelcontextprotocol/server"
import { z } from "zod"
import { createAgentMcpHttpHandler } from "../src/mcp/agent-http.js"
import { registerAgentSkillCatalogTools } from "../src/mcp/skill-tools.js"
import { registerAgentSkillTools } from "../src/mcp/skill-created-app.js"

const jsonSchema = z.looseObject({
  properties: z.record(z.string(), z.looseObject({ description: z.string().optional() })),
})
const toolSchema = z.object({
  name: z.string(),
  description: z.string(),
  annotations: z.object({ readOnlyHint: z.boolean(), destructiveHint: z.boolean(), idempotentHint: z.boolean(), openWorldHint: z.boolean() }),
  inputSchema: jsonSchema,
  outputSchema: jsonSchema,
})

function fixture() {
  const handler = createAgentMcpHttpHandler(() => {
    const server = new McpServer({ name: "skill-metadata-test", version: "1" })
    registerAgentSkillCatalogTools({ server, listSkills: async () => [], readSkill: async () => null })
    registerAgentSkillTools({
      server,
      create: async () => ({ ok: false, error: "test_failure", message: "Not saved." }),
      update: async () => ({ ok: false, error: "test_failure", message: "Not saved." }),
    })
    return server
  })
  return async (method: string, params: object) => {
    const response = await handler.fetch(new Request("https://example.test/mcp/agent", {
      method: "POST",
      headers: { "content-type": "application/json", accept: "application/json, text/event-stream", "mcp-protocol-version": "2026-07-28", "mcp-method": method,
        ...("name" in params && typeof params.name === "string" ? { "mcp-name": params.name } : {}),
      },
      body: JSON.stringify({ jsonrpc: "2.0", id: 1, method, params: { ...params, _meta: {
        [PROTOCOL_VERSION_META_KEY]: "2026-07-28", [CLIENT_INFO_META_KEY]: { name: "skill-test-client", version: "1" }, [CLIENT_CAPABILITIES_META_KEY]: {},
      } } }),
    }))
    assert.equal(response.status, 200)
    return response.json()
  }
}

test("skill tool metadata describes outputs, prerequisites, and mutation semantics without changing schemas", async () => {
  const call = fixture()
  const { result: { tools } } = z.object({ result: z.object({ tools: z.array(toolSchema) }) }).parse(await call("tools/list", {}))
  assert.deepEqual(tools.map((tool) => tool.name), ["list_skills", "get_skill", "create_skill", "update_skill"])
  for (const tool of tools) {
    for (const [name, field] of Object.entries(tool.outputSchema.properties)) {
      assert.ok(field.description, `${tool.name}.${name} needs output documentation`)
    }
    assert.equal(tool.annotations.openWorldHint, false)
  }
  const list = tools.find((tool) => tool.name === "list_skills")
  const get = tools.find((tool) => tool.name === "get_skill")
  const create = tools.find((tool) => tool.name === "create_skill")
  const update = tools.find((tool) => tool.name === "update_skill")
  assert.ok(list && get && create && update)
  assert.match(list.description, /total.*before limit/)
  assert.match(list.inputSchema.properties.query?.description ?? "", /every search token/)
  assert.match(list.inputSchema.properties.limit?.description ?? "", /total still counts all matches/)
  assert.match(get.description, /skill_needs_setup.*status and action/)
  assert.match(create.description, /mcp:write.*permission to create/)
  assert.match(create.inputSchema.properties.skillMarkdown?.description ?? "", /YAML frontmatter with name and description/)
  assert.match(update.description, /Publish a new immutable version/)
  assert.match(update.description, /mcp:write.*editor access/)
  assert.match(update.description, /cannot overwrite or erase.*undo.*publish another version/)
  assert.match(update.inputSchema.properties.reason?.description ?? "", /source revision reference/)
  assert.deepEqual(list.annotations, { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false })
  assert.deepEqual(get.annotations, list.annotations)
  assert.deepEqual(create.annotations, { readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false })
  assert.deepEqual(update.annotations, { readOnlyHint: false, destructiveHint: true, idempotentHint: false, openWorldHint: false })
  assert.equal(list.inputSchema.properties.query?.maxLength, 200)
  assert.equal(list.inputSchema.properties.limit?.maximum, 200)
  assert.equal(create.inputSchema.properties.skillMarkdown?.maxLength, 1_048_576)
  assert.equal(update.inputSchema.properties.reason?.maxLength, 255)
})

test("skill tool output and error response shapes remain unchanged", async () => {
  const call = fixture()
  const list = z.object({ result: z.object({ structuredContent: z.unknown() }) }).parse(await call("tools/call", { name: "list_skills", arguments: {} }))
  assert.deepEqual(list.result.structuredContent, { skills: [], total: 0, hint: "No skills are available to this member yet." })
  const errorResult = z.object({ result: z.object({ isError: z.literal(true), content: z.array(z.object({ type: z.literal("text"), text: z.string() })) }) })
  const missing = errorResult.parse(await call("tools/call", { name: "get_skill", arguments: { name: "missing" } }))
  assert.equal(JSON.parse(missing.result.content[0]?.text ?? "{}").error, "unknown_skill")
  for (const [name, args] of [
    ["create_skill", { pluginName: "Test", skillMarkdown: "Test instructions" }],
    ["update_skill", { skillId: "cob_test", skillMarkdown: "Test instructions", reason: "Clarify instructions" }],
  ]) {
    const saved = errorResult.parse(await call("tools/call", { name, arguments: args }))
    assert.deepEqual(JSON.parse(saved.result.content[0]?.text ?? "{}"), { error: "test_failure", message: "Not saved." })
  }
})
