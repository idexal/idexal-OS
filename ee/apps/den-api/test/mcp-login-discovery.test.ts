import assert from "node:assert/strict"
import { test } from "node:test"
import { createOAuthLoginDiscoveryFetch } from "../../../../packages/enterprise-mcp-client/src/oauth-login-discovery.js"
import { createEnterpriseMcpClient } from "../../../../packages/enterprise-mcp-client/src/enterprise-mcp-client.js"
import type { EnterpriseMcpFetch, EnterpriseMcpOAuthPersistence } from "../../../../packages/enterprise-mcp-client/src/contracts.js"

const serverUrl = new URL("https://resource.example/mcp/team")
const pathMetadata = "https://resource.example/.well-known/oauth-protected-resource/mcp/team"
const rootMetadata = "https://resource.example/.well-known/oauth-protected-resource"
const post = { method: "POST", body: JSON.stringify({ jsonrpc: "2.0", id: 1, method: "initialize" }) }
const json = (value: unknown, status = 200) => new Response(JSON.stringify(value), { status, headers: { "content-type": "application/json" } })

function fixture(input: { status?: number; resource?: string; missing?: boolean; rootOnly?: boolean; issuerMismatch?: boolean } = {}) {
  const requests: { url: string; init?: RequestInit }[] = []
  const fetch: EnterpriseMcpFetch = async (url, init) => {
    requests.push({ url: String(url), init })
    if (String(url) === serverUrl.href) assert.equal(init?.redirect, "manual")
    if (String(url) === serverUrl.href) return new Response("<html>Sign in</html>", { status: input.status ?? 302, headers: { location: "https://login.example/secret-target", "content-type": "text/html" } })
    if (String(url).includes("oauth-protected-resource")) {
      assert.equal(new Headers(init?.headers).has("authorization"), false)
      assert.equal(new Headers(init?.headers).has("cookie"), false)
      if (input.status !== 401) assert.equal(init?.credentials, "omit")
      if (input.missing || (input.rootOnly && String(url) === pathMetadata)) return json({}, 404)
      return json({ resource: input.resource ?? serverUrl.href, authorization_servers: [serverUrl.href] })
    }
    if (String(url).includes("oauth-authorization-server")) return json({ issuer: input.issuerMismatch ? serverUrl.origin : serverUrl.href, authorization_endpoint: `${serverUrl.origin}/authorize`, token_endpoint: `${serverUrl.origin}/token`, response_types_supported: ["code"], code_challenge_methods_supported: ["S256"] })
    throw new Error(`Unexpected request: ${url}`)
  }
  return { fetch, requests }
}

for (const status of [302, 200]) {
  test(`${status} HTML requires valid PRM before producing an OAuth challenge`, async () => {
    const mock = fixture({ status })
    const fetch = createOAuthLoginDiscoveryFetch({ fetch: mock.fetch, serverUrl })
    const response = await fetch(serverUrl, post)
    assert.equal(response.status, 401)
    assert.equal(response.headers.get("www-authenticate"), `Bearer resource_metadata="${pathMetadata}"`)
    const metadata = await fetch(pathMetadata)
    assert.equal((await metadata.json()).resource, serverUrl.href)
    assert.deepEqual(mock.requests.map((r) => r.url), [serverUrl.href, pathMetadata])
  })
}

for (const invalid of [{ missing: true }, { resource: "https://resource.example/other" }, { resource: serverUrl.origin }]) {
  test(`redirect with ${JSON.stringify(invalid)} fails closed with an actionable error`, async () => {
    const mock = fixture(invalid)
    const fetch = createOAuthLoginDiscoveryFetch({ fetch: mock.fetch, serverUrl })
    await assert.rejects(fetch(serverUrl, post), { code: "MCP_OAUTH_SIGN_IN_METADATA_MISSING", message: /Configure the connection manually or ask the server administrator/ })
    assert.deepEqual(mock.requests.map((r) => r.url), [serverUrl.href, pathMetadata, rootMetadata])
  })
}

test("path-suffixed metadata is tried before root metadata", async () => {
  const mock = fixture({ rootOnly: true })
  const fetch = createOAuthLoginDiscoveryFetch({ fetch: mock.fetch, serverUrl })
  assert.equal((await fetch(serverUrl, post)).status, 401)
  assert.deepEqual(mock.requests.map((r) => r.url), [serverUrl.href, pathMetadata, rootMetadata])
})

for (const candidate of ["redirect", "html", "invalid-json", "wrong-status", "invalid-schema"]) {
  test(`invalid ${candidate} metadata never produces an OAuth challenge`, async () => {
    const requested: string[] = []
    const fetch = createOAuthLoginDiscoveryFetch({ serverUrl, fetch: async (url, init) => {
      requested.push(String(url))
      assert.equal(init?.redirect, "manual")
      if (String(url) === serverUrl.href || candidate === "redirect") return new Response(null, { status: 302, headers: { location: "https://login.example/metadata" } })
      if (candidate === "html") return new Response("<html>login</html>", { headers: { "content-type": "text/html" } })
      if (candidate === "invalid-json") return new Response("{", { headers: { "content-type": "application/json" } })
      if (candidate === "wrong-status") return json({ resource: serverUrl.href }, 201)
      return json({ resource: serverUrl.href, authorization_servers: "not-an-array" })
    } })
    await assert.rejects(fetch(serverUrl, post), { code: "MCP_OAUTH_SIGN_IN_METADATA_MISSING" })
    assert.deepEqual(requested, [serverUrl.href, pathMetadata, rootMetadata])
  })
}

test("401 is unchanged and does not trigger the compatibility probe", async () => {
  const challenge = new Response(null, { status: 401, headers: { "www-authenticate": "Bearer" } })
  let calls = 0
  const fetch = createOAuthLoginDiscoveryFetch({ serverUrl, fetch: async () => { calls++; return challenge } })
  assert.equal(await fetch(serverUrl, post), challenge)
  assert.equal(calls, 1)
})

test("authenticated redirects are not translated or followed", async () => {
  const mock = fixture()
  const fetch = createOAuthLoginDiscoveryFetch({ fetch: mock.fetch, serverUrl })
  assert.equal((await fetch(serverUrl, { ...post, headers: { authorization: "Bearer exp-mcpredirect-token" } })).status, 302)
  assert.deepEqual(mock.requests.map((r) => r.url), [serverUrl.href])
})

function persistence(): EnterpriseMcpOAuthPersistence {
  return {
    clientRegistrations: {
      load: async () => ({ clientInformation: { client_id: "exp-mcpredirect-client" }, revision: "1", source: "pre-registered" }),
      save: async () => { throw new Error("No registration expected") }, invalidate: async () => {},
    },
    credentials: { load: async () => undefined, save: async () => { throw new Error("No tokens expected") }, invalidate: async () => {} },
    authorizations: { begin: async () => {}, load: async () => undefined, invalidate: async () => {} },
  }
}

test("a redirected resource still rejects an RFC 8414 issuer mismatch", async () => {
  const mock = fixture({ issuerMismatch: true })
  const client = createEnterpriseMcpClient({ fetch: mock.fetch })
  try {
    await client.connect({ connection: { id: "exp-mcpredirect-unit", serverUrl: serverUrl.href, authorization: { type: "oauth", persistence: persistence() } }, redirectUri: "https://client.example/callback", authorizationId: "exp-mcpredirect-state" })
    assert.fail("An inconsistent issuer must not start OAuth")
  } catch (error) {
    assert.ok(error instanceof Error)
    assert.ok(error.cause instanceof Error)
    assert.match(error.cause.message, /Issuer mismatch in authorization server metadata \(RFC 8414/)
  }
  assert.equal(mock.requests.some((r) => new URL(r.url).origin === "https://login.example"), false)
  assert.equal(mock.requests.some((r) => r.url.endsWith("/token")), false)
})

for (const status of [302, 200, 401]) {
  test(`${status} starts normal OAuth only after resource validation`, async () => {
    const mock = fixture({ status })
    const client = createEnterpriseMcpClient({ fetch: mock.fetch })
    const result = await client.connect({ connection: { id: "exp-mcpredirect-unit", serverUrl: serverUrl.href, authorization: { type: "oauth", persistence: persistence() } }, redirectUri: "https://client.example/callback", authorizationId: "exp-mcpredirect-state" })
    assert.equal(result.status, "needs_auth")
    if (result.status !== "needs_auth") throw new Error("OAuth did not start")
    assert.equal(new URL(result.authorizeUrl).pathname, "/authorize")
    assert.equal(new URL(result.authorizeUrl).searchParams.get("code_challenge_method"), "S256")
    assert.equal(mock.requests.some((r) => new URL(r.url).origin === "https://login.example"), false)
  })
}
