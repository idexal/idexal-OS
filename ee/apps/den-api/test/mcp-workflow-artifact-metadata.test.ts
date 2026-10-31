import assert from "node:assert/strict"
import { test } from "node:test"
import { McpServer } from "@modelcontextprotocol/server"
import { Client } from "@modelcontextprotocol/sdk/client/index.js"
import { InMemoryTransport } from "@modelcontextprotocol/sdk/inMemory.js"
import { IDEXAL_MCP_APP_DOMAIN } from "../src/mcp/mcp-app-metadata.ts"
import {
  registerAgentWorkflowArtifactApp,
  WORKFLOW_ARTIFACT_APP_RESOURCE_URI,
  type WorkflowArtifactLoadResult,
} from "../src/mcp/workflow-artifact-app.ts"

function record(value: unknown): Record<string, unknown> {
  assert.ok(value !== null && typeof value === "object" && !Array.isArray(value))
  return Object.fromEntries(Object.entries(value))
}

async function connect(load: Parameters<typeof registerAgentWorkflowArtifactApp>[0]["load"]) {
  const server = new McpServer({ name: "Artifact metadata test", version: "1.0.0" })
  registerAgentWorkflowArtifactApp({ server, load })
  const client = new Client({ name: "Artifact metadata client", version: "1.0.0" })
  const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair()
  await server.connect(serverTransport)
  await client.connect(clientTransport)
  return { client, close: async () => { await client.close(); await server.close() } }
}

const missing: WorkflowArtifactLoadResult = {
  ok: false, error: "workflow_snapshot_not_found", message: "Run this Workflow before viewing its result.",
}

test("the artifact view advertises supported resource metadata on list and read without changing its URI or MIME", async () => {
  const connection = await connect(async () => missing)
  try {
    const resources = await connection.client.listResources()
    const listed = resources.resources.find(resource => resource.uri === WORKFLOW_ARTIFACT_APP_RESOURCE_URI)
    assert.ok(listed)
    const read = await connection.client.readResource({ uri: WORKFLOW_ARTIFACT_APP_RESOURCE_URI })
    assert.equal(read.contents.length, 1)
    const content = read.contents[0]
    assert.equal(content.uri, "ui://idexalos/workflow-artifact/v1/view.html")
    assert.equal(listed.mimeType, "text/html;profile=mcp-app")
    assert.equal(content.mimeType, "text/html;profile=mcp-app")
    assert.deepEqual(content._meta, listed._meta)
    const meta = record(content._meta)
    const ui = record(meta.ui)
    assert.equal(ui.domain, IDEXAL_MCP_APP_DOMAIN)
    const origin = new URL(IDEXAL_MCP_APP_DOMAIN)
    assert.equal(origin.protocol, "https:")
    assert.equal(IDEXAL_MCP_APP_DOMAIN, origin.origin)
    assert.match(String(meta["openai/widgetDescription"]), /Preview, Data, and Lineage/)
    assert.deepEqual(record(ui.csp), { connectDomains: [], resourceDomains: [], frameDomains: [], baseUriDomains: [] })
    assert.equal(ui.prefersBorder, true)
  } finally { await connection.close() }
})

test("artifact tool discovery documents inputs and output while keeping custom host metadata out of annotations", async () => {
  const connection = await connect(async () => missing)
  try {
    const { tools } = await connection.client.listTools()
    const tool = tools.find(tool => tool.name === "render_workflow_artifact")
    assert.ok(tool)
    assert.equal(tool.title, "View Workflow result")
    assert.match(tool.description ?? "", /never runs or refreshes/)
    assert.deepEqual(tool.annotations, { readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false })
    const properties = record(tool.inputSchema.properties)
    for (const field of ["configObjectId", "receiptId", "maxAgeMs"]) {
      assert.ok(String(record(properties[field]).description).length > 30)
    }
    assert.match(String(record(properties.maxAgeMs).description), /Does not filter snapshots/)
    assert.match(String(tool.outputSchema?.description), /immutable receipt lineage/)
    const meta = record(tool._meta)
    for (const key of ["openai/toolInvocation/invoking", "openai/toolInvocation/invoked"]) {
      assert.equal(typeof meta[key], "string")
      assert.ok(String(meta[key]).length <= 64)
    }
    assert.notEqual(meta["openai/toolInvocation/invoking"], meta["openai/toolInvocation/invoked"])
    assert.equal(record(meta.ui).resourceUri, WORKFLOW_ARTIFACT_APP_RESOURCE_URI)
    assert.deepEqual(record(meta.ui).visibility, ["model", "app"])
  } finally { await connection.close() }
})

test("artifact calls return the documented structured result and Markdown fallback for a retained snapshot", async () => {
  const loaded: WorkflowArtifactLoadResult = {
    ok: true,
    markdown: "Total: 42",
    payload: {
      schemaVersion: "1",
      artifact: {
        title: "Weekly total", description: null, pluginId: "plugin-id", configObjectId: "workflow-id",
        configObjectVersionId: "version-id", receiptId: "receipt-id", automationRunId: null,
        source: "manual", generatedAt: "2026-10-15T00:00:00.000Z", resultDigest: `sha256:${"a".repeat(64)}`,
        rendererVersion: "codemode-markdown-v1", freshness: { state: "stale", ageMs: 120_000, maxAgeMs: 60_000 },
      },
      data: { total: 42 },
    },
  }
  const connection = await connect(async () => loaded)
  try {
    const result = await connection.client.callTool({ name: "render_workflow_artifact", arguments: { configObjectId: "workflow-id" } })
    assert.notEqual(result.isError, true)
    assert.deepEqual(result.structuredContent, loaded.payload)
    assert.ok(Array.isArray(result.content))
    const text = record(result.content[0]).text
    assert.equal(typeof text, "string")
    assert.match(String(text), /Freshness: stale/)
    assert.match(String(text), /Receipt: receipt-id/)
    assert.match(String(text), /Total: 42/)
    assert.equal(record(result._meta).receiptId, "receipt-id")
  } finally { await connection.close() }
})

test("artifact calls preserve the documented error fallback and freshness argument bounds", async () => {
  const requests: Array<{ configObjectId: string; receiptId?: string; maxAgeMs?: number }> = []
  const connection = await connect(async request => { requests.push(request); return missing })
  try {
    const result = await connection.client.callTool({
      name: "render_workflow_artifact", arguments: { configObjectId: "workflow-id", receiptId: "receipt-id", maxAgeMs: 60_000 },
    })
    assert.equal(result.isError, true)
    assert.deepEqual(result.content, [{ type: "text", text: JSON.stringify({ error: missing.error, message: missing.message }) }])
    assert.deepEqual(requests, [{ configObjectId: "workflow-id", receiptId: "receipt-id", maxAgeMs: 60_000 }])
    const invalid = await connection.client.callTool({
      name: "render_workflow_artifact", arguments: { configObjectId: "workflow-id", maxAgeMs: 59_999 },
    })
    assert.equal(invalid.isError, true)
    assert.equal(requests.length, 1)
  } finally { await connection.close() }
})
