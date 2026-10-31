import assert from "node:assert/strict"
import { test } from "node:test"
import { currentTeam } from "../src/permissions/current-team.ts"

const removed = { teamId: "deleted-team", teamName: null, removedAt: new Date("2026-10-01") }
const sales = { teamId: "sales", teamName: "Sales", removedAt: null }
const engineering = { teamId: "engineering", teamName: "Engineering", removedAt: null }

test("a reusable role whose only team was deleted has no current team", () => {
  assert.equal(currentTeam([removed]), null)
  assert.equal(currentTeam([]), null)
  assert.equal(currentTeam(undefined), null)
})

test("deleting one team keeps a shared role on its remaining active team", () => {
  assert.deepEqual(currentTeam([removed, sales]), { id: "sales", name: "Sales" })
})

test("an orphan role can be reused without resurrecting its former team", () => {
  assert.deepEqual(currentTeam([engineering, removed]), { id: "engineering", name: "Engineering" })
})

test("the first team stays the oldest active link for compatibility", () => {
  assert.deepEqual(currentTeam([engineering, sales, removed]), { id: "sales", name: "Sales" })
})

test("only archived role details fall back to a historical team", () => {
  assert.deepEqual(currentTeam([removed], true), { id: "deleted-team", name: null })
  assert.deepEqual(currentTeam([engineering, removed], true), { id: "engineering", name: "Engineering" })
})
