/**
 * Den as an OAuth client of MCP servers: a sign-in reply naming the wrong
 * issuer or carrying a forged state is refused before Den asks for a token, a
 * genuine reply works only once, a server declaring its protected resource on
 * another site is refused, and a member cannot replace an admin-provisioned
 * connector by its key. Ported from evals/core/den-manage-connectors*.e2e.test.ts
 * and evals/specs/mcp-oauth-response-issuer.test.ts, mcp-oauth-declared-resource.test.ts.
 */
import assert from "node:assert/strict"
import { after, before, test } from "node:test"
import { bootDen, field, list, startFakeMcpServer, startFakeOAuthMcpServer, summary, text, type Member } from "./harness.js"

const den = await bootDen()
const issuer = await startFakeOAuthMcpServer({ responseIssuer: true })
const foreign = await startFakeOAuthMcpServer({ protectedResource: "https://mcp.example.com/mcp" })
const plain = await startFakeMcpServer({ tools: [{ name: "read_notes", description: "Read notes", text: "notes" }] })
after(async () => {
  await Promise.all([issuer.close(), foreign.close(), plain.close()])
  await den.close()
})

let orgId = ""
let owner: Member
let sam: Member

before(async () => {
  const created = await den.createOrg(await den.signUp("Olivia Owner"), "Connectors org")
  orgId = created.orgId
  owner = created.owner
  sam = await den.newMember(owner, orgId, "Sam Member")
})

async function saveOAuthConnection(name: string, url: string, credentialMode: "shared" | "per_member"): Promise<string> {
  const created = await den.as(owner, orgId)("POST", "/v1/mcp-connections", { name, url, authType: "oauth", credentialMode, access: { orgWide: true } })
  assert.equal(created.status, 200, summary(created))
  return text(field(created.body, "id"), "connection id")
}

async function connected(connectionId: string): Promise<boolean> {
  const listed = await den.as(owner, orgId)("GET", "/v1/mcp-connections?scope=manageable")
  return field(list(field(listed.body, "connections")).find((row) => row.id === connectionId), "connected") === true
}

/** Owner clicks Sign in; the browser follows the provider's authorize redirect back toward Den. */
async function providerReply(connectionId: string): Promise<URL> {
  const started = await den.as(owner, orgId)("GET", `/v1/mcp-connections/${connectionId}/connect/start`)
  assert.equal(started.status, 200, summary(started))
  const authorizeUrl = text(field(started.body, "authorizeUrl"), "authorize URL")
  const redirect = await fetch(authorizeUrl, { redirect: "manual" })
  assert.equal(redirect.status, 302)
  return new URL(text(redirect.headers.get("location"), "provider redirect"))
}

/** The browser lands on Den's callback with whatever the reply carries. */
async function visit(callback: URL): Promise<{ status: number; tokenRequests: number }> {
  const before = issuer.tokenRequests()
  const reply = await den.request(null, "GET", `${callback.pathname}${callback.search}`)
  return { status: reply.status, tokenRequests: issuer.tokenRequests() - before }
}

test("a sign-in reply with the wrong issuer or a forged state is refused before Den asks for a token; the genuine reply works once", async () => {
  const id = await saveOAuthConnection("Issuer Notes", issuer.mcpUrl, "per_member")

  const wrong = await providerReply(id)
  assert.equal(wrong.searchParams.get("iss"), issuer.url, "the provider names itself")
  wrong.searchParams.set("iss", "https://other-issuer.example.test")
  const wrongIssuer = await visit(wrong)
  assert.equal(wrongIssuer.status, 400)
  assert.equal(wrongIssuer.tokenRequests, 0)
  assert.equal(await connected(id), false)

  const forged = await providerReply(id)
  forged.searchParams.set("state", "forged-state")
  const forgedState = await visit(forged)
  assert.equal(forgedState.status, 400)
  assert.equal(forgedState.tokenRequests, 0)
  assert.equal(await connected(id), false)

  const real = await providerReply(id)
  const genuine = await visit(real)
  assert.equal(genuine.status, 200)
  assert.equal(genuine.tokenRequests, 1)
  assert.equal(await connected(id), true)

  const replay = await visit(real)
  assert.equal(replay.status, 400)
  assert.equal(replay.tokenRequests, 0)
})

test("Den refuses to sign in to a server that declares its protected resource on another site", async () => {
  const id = await saveOAuthConnection("Lookalike Notes", foreign.mcpUrl, "shared")
  // The same fake without the foreign resource signs in fine (previous test), so the resource is the only difference.
  const started = await den.as(owner, orgId)("GET", `/v1/mcp-connections/${id}/connect/start`)
  assert.equal(started.status, 424, summary(started))
  assert.equal(field(started.body, "error"), "oauth_handshake_failed")
  assert.equal(field(started.body, "authorizeUrl"), undefined)
  assert.deepEqual(foreign.requests.filter((entry) => entry.path === "/authorize" || entry.path === "/token" || entry.path === "/register"), [])
  assert.equal(await connected(id), false)
})

test("a member cannot replace a connector an admin provisioned by its key, and it stays as the admin left it", async () => {
  const externalKey = `provisioned-notes-${den.stamp}`
  const input = { name: "Provisioned Notes", url: `${plain.url}/mcp`, authType: "none", credentialMode: "shared", access: { orgWide: true } }
  const created = await den.as(owner, orgId)("PUT", `/v1/mcp-connections/by-key/${externalKey}`, input)
  assert.equal(created.status, 201, summary(created))

  const denied = await den.as(sam, orgId)("PUT", `/v1/mcp-connections/by-key/${externalKey}`, { ...input, name: "Sam's Notes", url: "http://127.0.0.1:9/mcp" })
  assert.equal(denied.status, 403, summary(denied))

  const listed = await den.as(owner, orgId)("GET", "/v1/mcp-connections?scope=manageable")
  const rows = list(field(listed.body, "connections")).filter((row) => row.externalKey === externalKey)
  assert.equal(rows.length, 1)
  assert.equal(rows[0]?.name, "Provisioned Notes")
  assert.equal(rows[0]?.url, `${plain.url}/mcp`)
})
