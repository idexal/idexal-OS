import assert from "node:assert/strict"
import { test } from "node:test"
import { bumpGeneration, readManyThroughGeneration, readThroughGeneration, type GenerationCacheRedis } from "../src/generation-cache.ts"

function fakeRedis(options: { failGet?: boolean } = {}) {
  const store = new Map<string, string>()
  const redis: GenerationCacheRedis = {
    async get(key) {
      if (options.failGet) throw new Error("redis down")
      return store.get(key) ?? null
    },
    async set(key, value) {
      store.set(key, value)
      return "OK"
    },
  }
  return { redis, store }
}

function parseNumberList(raw: string | null) {
  if (!raw) return null
  const parsed: unknown = JSON.parse(raw)
  return Array.isArray(parsed) && parsed.every((value) => typeof value === "number") ? parsed : null
}

function reader(redis: GenerationCacheRedis | null, load: () => Promise<number[]>) {
  return readThroughGeneration({
    redis,
    generationKey: "gen:org_1",
    entryKey: (generation) => `entry:org_1:${generation}:member_1`,
    ttlSeconds: 60,
    parse: parseNumberList,
    load,
  })
}

test("without redis every read goes to the loader", async () => {
  let loads = 0
  const load = async () => { loads += 1; return [1] }
  assert.deepEqual(await reader(null, load), [1])
  assert.deepEqual(await reader(null, load), [1])
  assert.equal(loads, 2)
})

test("a second read is served from the cache, including an empty list", async () => {
  const { redis } = fakeRedis()
  let loads = 0
  const load = async () => { loads += 1; return [] }
  assert.deepEqual(await reader(redis, load), [])
  assert.deepEqual(await reader(redis, load), [])
  assert.equal(loads, 1)
})

test("bumping the generation makes the next read load fresh data", async () => {
  const { redis } = fakeRedis()
  let value = [1]
  const load = async () => value
  assert.deepEqual(await reader(redis, load), [1])
  value = [1, 2]
  assert.deepEqual(await reader(redis, load), [1], "still cached before the bump")
  await bumpGeneration(redis, "gen:org_1")
  assert.deepEqual(await reader(redis, load), [1, 2])
})

test("a loader that read before a committed write cannot repopulate stale data", async () => {
  const { redis } = fakeRedis()
  // The loader reads the old rows, then the write commits and bumps the
  // generation before the loader stores its result.
  const staleRead = reader(redis, async () => {
    await bumpGeneration(redis, "gen:org_1")
    return [1]
  })
  assert.deepEqual(await staleRead, [1])
  assert.deepEqual(await reader(redis, async () => [1, 2]), [1, 2])
})

test("a redis read failure falls back to the loader and writes nothing", async () => {
  const { redis, store } = fakeRedis({ failGet: true })
  assert.deepEqual(await reader(redis, async () => [3]), [3])
  assert.equal(store.size, 0)
})

test("an unparseable entry is reloaded", async () => {
  const { redis, store } = fakeRedis()
  store.set("entry:org_1:0:member_1", "{not json")
  // JSON.parse throws inside parse; treated like a read failure, then reloaded.
  assert.deepEqual(await reader(redis, async () => [4]), [4])
})

function fakeMgetRedis(options: { failMget?: boolean } = {}) {
  const store = new Map<string, string>()
  const calls = { get: 0, mget: 0 }
  const redis: GenerationCacheRedis = {
    async get(key) {
      calls.get += 1
      return store.get(key) ?? null
    },
    async set(key, value) {
      store.set(key, value)
      return "OK"
    },
    async mget(...keys) {
      calls.mget += 1
      if (options.failMget) throw new Error("redis down")
      return keys.map((key) => store.get(key) ?? null)
    },
  }
  return { redis, store, calls }
}

// Each test uses its own scope: the last generation read is kept per process.
function manyReader(redis: GenerationCacheRedis, scope: string, loads: { a: () => Promise<number[]>; b: () => Promise<number[]> }) {
  return readManyThroughGeneration<[number[], number[]]>({
    redis,
    generationKey: `gen:${scope}`,
    entries: [
      { entryKey: (generation) => `entry:${scope}:${generation}:a`, parse: parseNumberList, load: loads.a },
      { entryKey: (generation) => `entry:${scope}:${generation}:b`, parse: parseNumberList, load: loads.b },
    ],
    ttlSeconds: 60,
  })
}

test("with MGET, an unchanged generation serves the generation and every entry in one round trip", async () => {
  const { redis, calls } = fakeMgetRedis()
  let loads = 0
  const load = async () => { loads += 1; return [loads] }
  assert.deepEqual(await manyReader(redis, "steady", { a: load, b: load }), [[1], [2]])
  const before = { ...calls }
  assert.deepEqual(await manyReader(redis, "steady", { a: load, b: load }), [[1], [2]])
  assert.equal(loads, 2)
  assert.equal(calls.mget - before.mget, 1)
  assert.equal(calls.get - before.get, 0)
})

test("with MGET, a bump makes the next read fetch under the new generation and reload", async () => {
  const { redis, calls } = fakeMgetRedis()
  let value = [1]
  const load = async () => value
  await manyReader(redis, "bumped", { a: load, b: load })
  value = [1, 2]
  assert.deepEqual(await manyReader(redis, "bumped", { a: load, b: load }), [[1], [1]], "still cached before the bump")
  await bumpGeneration(redis, "gen:bumped")
  const before = calls.mget
  assert.deepEqual(await manyReader(redis, "bumped", { a: load, b: load }), [[1, 2], [1, 2]])
  assert.equal(calls.mget - before, 2, "the stale guess costs one extra round trip")
  const settled = calls.mget
  assert.deepEqual(await manyReader(redis, "bumped", { a: load, b: load }), [[1, 2], [1, 2]])
  assert.equal(calls.mget - settled, 1)
})

test("with MGET, a loader that read before a committed write cannot repopulate stale data", async () => {
  const { redis } = fakeMgetRedis()
  const staleRead = manyReader(redis, "race", {
    a: async () => {
      await bumpGeneration(redis, "gen:race")
      return [1]
    },
    b: async () => [1],
  })
  assert.deepEqual(await staleRead, [[1], [1]])
  assert.deepEqual(await manyReader(redis, "race", { a: async () => [1, 2], b: async () => [1, 2] }), [[1, 2], [1, 2]])
})

test("an MGET failure loads every entry and writes nothing", async () => {
  const { redis, store } = fakeMgetRedis({ failMget: true })
  assert.deepEqual(await manyReader(redis, "down", { a: async () => [5], b: async () => [6] }), [[5], [6]])
  assert.equal(store.size, 0)
})

test("an unparseable entry reloads only that entry", async () => {
  const { redis, store } = fakeMgetRedis()
  store.set("entry:partial:0:a", "[7]")
  store.set("entry:partial:0:b", "{not json")
  let aLoads = 0
  const result = await manyReader(redis, "partial", { a: async () => { aLoads += 1; return [0] }, b: async () => [8] })
  assert.deepEqual(result, [[7], [8]])
  assert.equal(aLoads, 0)
  assert.equal(store.get("entry:partial:0:b"), "[8]")
})
