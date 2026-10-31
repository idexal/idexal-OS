import assert from "node:assert/strict"
import { test } from "node:test"
import { buildDashboardActivity, newestDashboardActivity, pickSkillParents, type DashboardActivitySources } from "../src/dashboard-activity-rows.js"

const at = (day: number) => new Date(Date.UTC(2026, 9, day, 12))

function sources(overrides: Partial<DashboardActivitySources> = {}): DashboardActivitySources {
  return { connections: [], llmProviders: [], gatewayProviders: [], pluginAttachments: [], skillVersions: [], ...overrides }
}

test("merges every source into the newest five, newest first", () => {
  const entries = buildDashboardActivity(sources({
    connections: [{ id: "emc_1", name: "Docs", url: "https://docs.example/mcp", createdAt: at(1) }],
    llmProviders: [{ id: "lpr_1", name: "Team models", providerId: "example", createdAt: at(5) }],
    gatewayProviders: [{ id: "ipr_1", name: "Gateway models", providerId: "example", createdAt: at(4) }],
    pluginAttachments: [{ id: "mpp_1", createdAt: at(3), pluginName: "Research", marketplaceId: "mkt_1", marketplaceName: "Team", skillCount: 1 }],
    skillVersions: [
      { id: "cov_2", createdAt: at(6), skillId: "cob_1", skillTitle: "Research skill", pluginId: "plg_1", pluginName: "Research" },
      { id: "cov_1", createdAt: at(2), skillId: "cob_1", skillTitle: "Research skill", pluginId: "plg_1", pluginName: "Research" },
    ],
  }))
  assert.deepEqual(entries.map((entry) => entry.id), [
    "skill:cov_2", "provider:legacy:lpr_1", "provider:gateway:ipr_1", "plugin:mpp_1", "skill:cov_1",
  ])
  assert.deepEqual(entries[0], {
    id: "skill:cov_2",
    kind: "skill",
    title: "A new version of Research skill was published",
    detail: "Skill in Research",
    occurredAt: "2026-10-06T12:00:00.000Z",
    target: { type: "skill", pluginId: "plg_1", skillId: "cob_1" },
  })
  assert.deepEqual(entries[1]?.target, { type: "llm_provider", llmProviderId: "lpr_1" })
  assert.deepEqual(entries[1]?.logo, { name: "Team models", providerId: "example" })
  assert.deepEqual(entries[2]?.target, { type: "gateway_provider", inferenceProviderId: "ipr_1" })
  assert.equal(entries[3]?.title, "Research was added to the Team marketplace")
  assert.equal(entries[3]?.detail, "Plugin with 1 skill")
  assert.deepEqual(entries[3]?.target, { type: "marketplace", marketplaceId: "mkt_1" })
})

test("connections keep their stored date, url logo, and pluralized plugin details", () => {
  const entries = buildDashboardActivity(sources({
    connections: [{ id: "emc_1", name: "Docs", url: "https://docs.example/mcp", createdAt: at(1) }],
    pluginAttachments: [{ id: "mpp_1", createdAt: at(1), pluginName: "Empty", marketplaceId: "mkt_1", marketplaceName: "Team", skillCount: 0 }],
  }))
  const connection = entries.find((entry) => entry.kind === "connection")
  assert.equal(connection?.title, "Docs was added")
  assert.equal(connection?.detail, "Connection")
  assert.equal(connection?.occurredAt, "2026-10-01T12:00:00.000Z")
  assert.deepEqual(connection?.logo, { name: "Docs", url: "https://docs.example/mcp" })
  assert.equal(entries.find((entry) => entry.kind === "plugin")?.detail, "Plugin with 0 skills")
})

test("ties sort by event id, duplicates collapse, and invalid dates never contribute", () => {
  const entries = buildDashboardActivity(sources({
    connections: [
      { id: "emc_b", name: "B", url: null, createdAt: at(1) },
      { id: "emc_a", name: "A", url: null, createdAt: at(1) },
      { id: "emc_a", name: "A", url: null, createdAt: at(1) },
      { id: "emc_bad", name: "Bad", url: null, createdAt: new Date(Number.NaN) },
    ],
  }))
  assert.deepEqual(entries.map((entry) => entry.id), ["connection:emc_a", "connection:emc_b"])
  assert.deepEqual(entries[0]?.logo, { name: "A" })
})

test("the merged result never exceeds five", () => {
  const connections = Array.from({ length: 7 }, (_, index) => ({ id: `emc_${index}`, name: `C${index}`, url: null, createdAt: at(index + 1) }))
  const entries = buildDashboardActivity(sources({ connections }))
  assert.deepEqual(entries.map((entry) => entry.id), ["connection:emc_6", "connection:emc_5", "connection:emc_4", "connection:emc_3", "connection:emc_2"])
  assert.equal(newestDashboardActivity([]).length, 0)
})

test("one parent per skill, the first in plugin order", () => {
  const parents = pickSkillParents([
    { skillId: "cob_1", pluginId: "plg_new" },
    { skillId: "cob_1", pluginId: "plg_old" },
    { skillId: "cob_2", pluginId: "plg_old" },
  ])
  assert.equal(parents.get("cob_1")?.pluginId, "plg_new")
  assert.equal(parents.get("cob_2")?.pluginId, "plg_old")
})
