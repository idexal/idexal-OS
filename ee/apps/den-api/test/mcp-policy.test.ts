import assert from "node:assert/strict"
import { readFileSync } from "node:fs"
import { test } from "node:test"
import { isMcpOperationAllowed, type OpenApiOperation } from "../src/mcp/policy.ts"

type OpenApiDocument = { paths: Record<string, Record<string, OpenApiOperation>> }

function readSnapshot(): OpenApiDocument {
  return JSON.parse(readFileSync(new URL("../../../../packages/docs/openapi.json", import.meta.url), "utf8"))
}

function mcpAllowed(document: OpenApiDocument, method: string, path: string) {
  const operation = document.paths[path]?.[method]
  assert.ok(operation, `${method.toUpperCase()} ${path} is in the OpenAPI snapshot`)
  return isMcpOperationAllowed({ method, path, operation })
}

test("MCP agents can plan, prepare and follow managed deployments but never remove them or act as the installer", () => {
  const document = readSnapshot()
  assert.equal(mcpAllowed(document, "get", "/v1/managed-deployments/configuration"), true)
  assert.equal(mcpAllowed(document, "get", "/v1/managed-deployments"), true)
  assert.equal(mcpAllowed(document, "post", "/v1/managed-deployments"), true)
  assert.equal(mcpAllowed(document, "post", "/v1/managed-deployments/{deploymentId}/launch"), true)

  assert.equal(mcpAllowed(document, "delete", "/v1/managed-deployments/{deploymentId}"), false)
  assert.equal(mcpAllowed(document, "post", "/v1/managed-deployments/{deploymentId}/runs/{runId}/enroll"), false)
  assert.equal(mcpAllowed(document, "post", "/v1/managed-deployments/{deploymentId}/runs/{runId}/events"), false)
  assert.equal(mcpAllowed(document, "post", "/v1/managed-deployments/{deploymentId}/heartbeat"), false)
})

test("MCP agents can read permission sets but never create, change or delete them", () => {
  const document = readSnapshot()
  assert.equal(mcpAllowed(document, "post", "/v1/permissions/sets"), false)
  assert.equal(mcpAllowed(document, "put", "/v1/permissions/sets/{permissionSetId}/permissions"), false)
  assert.equal(mcpAllowed(document, "delete", "/v1/permissions/sets/{permissionSetId}"), false)

  assert.equal(mcpAllowed(document, "get", "/v1/permissions/catalog"), true)
  assert.equal(mcpAllowed(document, "get", "/v1/permissions/sets"), true)
  assert.equal(mcpAllowed(document, "get", "/v1/permissions/sets/{permissionSetId}"), true)
})
