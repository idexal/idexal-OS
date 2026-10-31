import assert from "node:assert/strict"
import { test } from "node:test"
import { emptyInventoryWrites, inventoryRevisionsToBump, noteInventoryWrite, trackInventoryWrites } from "../src/inventory-writes.ts"

const ORG = "org_01m4xbdvxxeh585vrbxydzqq58"
const OTHER_ORG = "org_01m4xbdvwaejpvw05s0dd97wzt"

async function bumpsFor(statements: Array<[string, unknown[]]>, requestOrganizationId: string | null = ORG) {
  const writes = emptyInventoryWrites()
  await trackInventoryWrites(writes, async () => {
    for (const [query, params] of statements) noteInventoryWrite(query, params)
  })
  return inventoryRevisionsToBump(writes, requestOrganizationId)
}

test("reads never bump", async () => {
  assert.deepEqual(await bumpsFor([["select `id` from `plugin` where `plugin`.`organization_id` = ?", [ORG]]]), [])
})

test("a write to an inventory table bumps the request's organization", async () => {
  assert.deepEqual(await bumpsFor([["update `plugin` set `name` = ? where `plugin`.`id` = ?", ["Docs", "plg_1"]]]), [ORG])
  assert.deepEqual(await bumpsFor([["delete from `team_member` where `team_member`.`id` = ?", ["tm_1"]]]), [ORG])
  assert.deepEqual(await bumpsFor([["insert into `marketplace_access_grant` (`id`) values (?)", ["mag_1"]]]), [ORG])
})

test("bookkeeping writes do not bump", async () => {
  assert.deepEqual(await bumpsFor([
    ["update `automation_runner` set `last_seen_at` = ? where `automation_runner`.`id` = ?", [new Date(), "run_1"]],
    ["insert into `capability_usage_event` (`id`, `organization_id`) values (?, ?)", ["cue_1", ORG]],
    ["update `session` set `expires_at` = ? where `session`.`id` = ?", [new Date(), "ses_1"]],
  ]), [])
})

test("a write in a request without an organization bumps the organizations in its parameters", async () => {
  assert.deepEqual(
    await bumpsFor([["insert into `connected_account` (`id`, `organization_id`) values (?, ?)", ["ca_1", OTHER_ORG]]], null),
    [OTHER_ORG],
  )
  assert.deepEqual(await bumpsFor([["update `connected_account` set `access_token` = ? where `id` = ?", ["secret", "ca_1"]]], null), [])
})

test("writes outside a tracked request are ignored", () => {
  assert.doesNotThrow(() => noteInventoryWrite("update `plugin` set `name` = ?", ["Docs"]))
})
