import assert from "node:assert/strict"
import { afterEach, mock, test } from "node:test"
import { FeatureRolloutTable, OrganizationFeatureTable } from "@idexal-ee/den-db/schema"
import { createDenTypeId } from "@idexal-ee/utils/typeid"

process.env.DATABASE_URL ??= "mysql://test:test@127.0.0.1:3306/permission_cache_unit_test"
process.env.BETTER_AUTH_URL ??= "http://unit.test"
process.env.BETTER_AUTH_SECRET ??= "synthetic-permission-cache-unit-test-secret"
process.env.DEN_DB_ENCRYPTION_KEY ??= "synthetic-permission-cache-unit-test-key"
process.env.IDEXAL_DEV_MODE = "1"
process.env.DATABASE_REDIS_URL = ""
process.env.DEN_DEPLOYMENT = "self_hosted"
process.env.DEN_FEATURE_PERMISSIONS = ""
const { db } = await import("../src/db.js")
const { cache, setCacheDependenciesForTest } = await import("../src/cache.js")
const {
  getOrganizationFeatures, organizationFeatureEnabled, getFeaturesForOrganizations,
  deploymentFeatureEnabled, updateFeatureRollout, updateOrganizationFeatureOverrides,
  readOrganizationWithPermissions,
} = await import("../src/features.js")

const organizationId = createDenTypeId("organization")
afterEach(() => mock.restoreAll())

function setup() {
  const state: { enabled: boolean | null; killed: boolean; override: boolean | null; failDb: boolean } = {
    enabled: true, killed: false, override: null, failDb: false,
  }
  const rolloutKey = "cache:features:rollouts:old"
  const values = new Map([
    ["cache:features:generation:deployment", "old"],
    [rolloutKey, JSON.stringify({ permissions: { enabled: true, killed: false }, teamMembershipCache: { enabled: true, killed: false } })],
    [`cache:features:overrides:old:${organizationId}`, JSON.stringify({ permissions: true })],
  ])
  let failedBumps = 0
  const redis = {
    async get(key: string) { return values.get(key) ?? null },
    async set() { failedBumps++; throw new Error("OOM command not allowed when used memory > maxmemory") },
    async del(...keys: string[]) { keys.forEach((key) => values.delete(key)) },
  }
  const restore = setCacheDependenciesForTest({ redis })
  mock.method(console, "error", () => {})
  mock.method(db, "select", (fields: Record<string, unknown>) => {
    const rows = () => {
      if (state.failDb) throw new Error("authoritative permission state unavailable")
      if ("organization" in fields) return [{ organization: { id: organizationId }, rolloutEnabled: state.enabled, killed: state.killed, overrideEnabled: state.override }]
      if ("rolloutEnabled" in fields) return [{ organizationId, rolloutEnabled: state.enabled, killed: state.killed, overrideEnabled: state.override }]
      if ("metadata" in fields) return [{ metadata: {} }]
      if ("feature_key" in fields && "killed" in fields) return state.enabled === null ? [] : [{ feature_key: "permissions", enabled: state.enabled, killed: state.killed }]
      if ("feature_key" in fields) return state.override === null ? [] : [{ organization_id: organizationId, feature_key: "permissions", enabled: state.override }]
      if ("enabled" in fields && "killed" in fields) return state.enabled === null ? [] : [{ enabled: state.enabled, killed: state.killed }]
      throw new Error("unexpected permission-state selection")
    }
    const chain = {
      from: () => chain, leftJoin: () => chain, where: () => chain, limit: () => chain, for: () => chain,
      then: (resolve: (value: unknown[]) => unknown, reject: (error: unknown) => unknown) => Promise.resolve().then(rows).then(resolve, reject),
    }
    return chain
  })
  mock.method(db, "insert", (table: typeof FeatureRolloutTable | typeof OrganizationFeatureTable) => ({
    values(value: Record<string, unknown>) {
      return { async onDuplicateKeyUpdate() {
        assert.equal(typeof value.enabled, "boolean")
        if (typeof value.enabled !== "boolean") throw new Error("invalid fixture write")
        if (table === FeatureRolloutTable) {
          assert.equal(typeof value.killed, "boolean")
          if (typeof value.killed !== "boolean") throw new Error("invalid fixture kill switch")
          state.enabled = value.enabled
          state.killed = value.killed
        } else {
          assert.equal(table, OrganizationFeatureTable)
          state.override = value.enabled
        }
      } }
    },
  }))
  mock.method(db, "delete", () => ({ async where() { state.override = null } }))
  mock.method(db, "update", () => ({ set: () => ({ async where() {} }) }))
  mock.method(db, "transaction", async (callback: (database: typeof db) => Promise<unknown>) => callback(db))
  return { state, values, rolloutKey, restore, failedBumps: () => failedBumps }
}

test("disabling permissions takes effect despite read-only Redis preserving the old enabled snapshot", async () => {
  const h = setup()
  try {
    assert.equal((await getOrganizationFeatures(organizationId)).permissions, true)
    await updateFeatureRollout({ key: "permissions", enabled: false, defaultEnabled: false })
    assert.equal(h.failedBumps(), 1)
    // Witness: Redis still serves the old enabled snapshot after the failed SET.
    assert.match(h.values.get(h.rolloutKey) ?? "", /"permissions":\{"enabled":true/)
    const features = await getOrganizationFeatures(organizationId)
    assert.equal(features.permissions, false)
    assert.equal(features.teamMembershipCache, true, "ordinary flags still use Redis")
    assert.equal(await organizationFeatureEnabled(organizationId, "permissions"), false)
    assert.equal(await deploymentFeatureEnabled("permissions"), false)
    assert.equal((await getFeaturesForOrganizations([organizationId])).get(organizationId)?.permissions, false)
  } finally { h.restore() }
})

test("a permissions kill switch overrides an organization grant after failed Redis invalidation", async () => {
  const h = setup()
  try {
    h.state.override = true
    await updateFeatureRollout({ key: "permissions", killed: true, defaultEnabled: false })
    assert.equal(h.failedBumps(), 1)
    assert.equal((await getOrganizationFeatures(organizationId)).permissions, false)
    assert.equal(await organizationFeatureEnabled(organizationId, "permissions"), false)
  } finally { h.restore() }
})

test("disabling an organization permissions override ignores its stale cached enabled value", async () => {
  const h = setup()
  try {
    h.state.override = true
    await updateOrganizationFeatureOverrides({ organizationId, changes: { permissions: false }, source: "platform" })
    assert.equal(h.failedBumps(), 1)
    assert.equal((await getOrganizationFeatures(organizationId)).permissions, false)
  } finally { h.restore() }
})

test("clearing an organization override uses the live deployment default, not stale Redis grants", async () => {
  const h = setup()
  try {
    h.state.enabled = false
    h.state.override = true
    await updateOrganizationFeatureOverrides({ organizationId, changes: { permissions: null }, source: "platform" })
    assert.equal(h.failedBumps(), 1)
    assert.equal((await getOrganizationFeatures(organizationId)).permissions, false)
  } finally { h.restore() }
})

test("permission authorization rejects DB errors even while Redis can serve old grants", async () => {
  const h = setup()
  try {
    assert.equal((await getOrganizationFeatures(organizationId)).permissions, true)
    h.state.failDb = true
    await assert.rejects(getOrganizationFeatures(organizationId), /authoritative permission state unavailable/)
    await assert.rejects(organizationFeatureEnabled(organizationId, "permissions"), /authoritative permission state unavailable/)
  } finally { h.restore() }
})

test("missing permission rows use registry defaults rather than stale cached grants", async () => {
  const h = setup()
  try {
    h.state.enabled = null
    h.state.override = null
    assert.equal((await getOrganizationFeatures(organizationId)).permissions, false)
    assert.equal(await deploymentFeatureEnabled("permissions"), false)
  } finally { h.restore() }
})

test("Redis rollback cannot resurrect permission state after the database disables it", async () => {
  const h = setup()
  try {
    const snapshot = new Map(h.values)
    h.state.enabled = false
    await cache.features.invalidate()
    h.values.clear()
    snapshot.forEach((value, key) => h.values.set(key, value))
    assert.equal((await getOrganizationFeatures(organizationId)).permissions, false)
  } finally { h.restore() }
})

test("the organization row read carries the live permissions flag, and features reuse it without another read", async () => {
  const h = setup()
  try {
    h.state.enabled = false
    const read = await readOrganizationWithPermissions(organizationId)
    assert.equal(read?.organization.id, organizationId)
    assert.equal(read?.permissions, false, "the database disabled permissions; stale Redis still says enabled")
    const selects = (db.select as unknown as { mock: { callCount(): number } }).mock
    const before = selects.callCount()
    const features = await getOrganizationFeatures(organizationId, { permissions: read?.permissions })
    assert.equal(features.permissions, false)
    assert.equal(features.teamMembershipCache, true, "ordinary flags still come from the cache")
    assert.equal(selects.callCount() - before, 0)
  } finally { h.restore() }
})

test("the organization row read resolves the kill switch and the override like the separate read", async () => {
  const h = setup()
  try {
    for (const [enabled, killed, override] of [[true, true, true], [false, false, true], [true, false, false], [null, false, null], [true, false, null]] as const) {
      Object.assign(h.state, { enabled, killed, override })
      const read = await readOrganizationWithPermissions(organizationId)
      assert.equal(read?.permissions, await organizationFeatureEnabled(organizationId, "permissions"), `enabled=${enabled} killed=${killed} override=${override}`)
    }
  } finally { h.restore() }
})
