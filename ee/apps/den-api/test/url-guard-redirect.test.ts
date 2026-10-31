import assert from "node:assert/strict"
import { test } from "node:test"
import { createRealmSafeFetch } from "../src/capability-sources/url-guard.js"

for (const location of ["https://resource.example/login", "https://login.example/sso"]) {
  test(`manual redirect mode never requests ${location}`, async () => {
    const requested: string[] = []
    const fetch = createRealmSafeFetch(async (url, init) => {
      requested.push(String(url))
      assert.equal(init?.redirect, "manual")
      return new Response("<html>sign in</html>", { status: 302, headers: { location } })
    })
    const response = await fetch("https://resource.example/mcp", {
      method: "POST", body: "{}", redirect: "manual",
      headers: { authorization: "Bearer exp-mcpredirect-token", cookie: "exp-mcpredirect-cookie=synthetic" },
    })
    assert.equal(response.status, 302)
    assert.deepEqual(requested, ["https://resource.example/mcp"])
    await response.body?.cancel()
  })
}

test("default redirects retain cross-origin body rejection", async () => {
  const requested: string[] = []
  const fetch = createRealmSafeFetch(async (url) => {
    requested.push(String(url))
    return new Response(null, { status: 307, headers: { location: "https://login.example/sso" } })
  })
  await assert.rejects(fetch("https://resource.example/mcp", { method: "POST", body: "{}" }), /request body cannot be redirected to another origin/)
  assert.deepEqual(requested, ["https://resource.example/mcp"])
})

test("redirect:error still rejects without requesting the destination", async () => {
  const requested: string[] = []
  const fetch = createRealmSafeFetch(async (url) => {
    requested.push(String(url))
    return new Response(null, { status: 302, headers: { location: "/login" } })
  })
  await assert.rejects(fetch("https://resource.example/mcp", { redirect: "error" }), /refused a redirect/)
  assert.deepEqual(requested, ["https://resource.example/mcp"])
})
