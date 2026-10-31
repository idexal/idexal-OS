import assert from "node:assert/strict"
import { readFile } from "node:fs/promises"
import { afterEach, mock, test } from "node:test"
import { TeamMemberTable, TeamTable } from "@idexal-ee/den-db/schema"
import { createDenTypeId } from "@idexal-ee/utils/typeid"
import ts from "typescript"

process.env.DATABASE_URL ??= "mysql://test:test@127.0.0.1:3306/member_team_revocation_unit_test"
process.env.BETTER_AUTH_URL ??= "http://unit.test"
process.env.BETTER_AUTH_SECRET ??= "synthetic-member-team-revocation-unit-test-secret"
process.env.DEN_DB_ENCRYPTION_KEY ??= "synthetic-member-team-revocation-unit-test-key"
process.env.IDEXAL_DEV_MODE ??= "1"
process.env.DATABASE_REDIS_URL = ""
const { db } = await import("../src/db.js")
const { cache } = await import("../src/cache.js")

const { listTeamsForMember } = await import("../src/member-teams.js")
const source = ts.createSourceFile("member-teams.ts", await readFile(new URL("../src/member-teams.ts", import.meta.url), "utf8"), ts.ScriptTarget.Latest, true)
const declaration = source.statements.find((statement) => ts.isFunctionDeclaration(statement) && statement.name?.text === "listTeamsForMember")
assert.ok(declaration && ts.isFunctionDeclaration(declaration) && declaration.body)
const functionSource = declaration.getText(source)
type MemberTeamSummary = Pick<typeof TeamTable.$inferSelect, "id" | "name" | "organizationId" | "createdAt" | "updatedAt">
const organizationId = createDenTypeId("organization")
const memberId = createDenTypeId("member")
const input = { organizationId, memberId }
const team: MemberTeamSummary = {
  id: createDenTypeId("team"),
  name: "Regression team",
  organizationId,
  createdAt: new Date("2026-01-01T00:00:00.000Z"),
  updatedAt: new Date("2026-01-01T00:00:00.000Z"),
}
const selection = {
  id: TeamTable.id,
  name: TeamTable.name,
  organizationId: TeamTable.organizationId,
  createdAt: TeamTable.createdAt,
  updatedAt: TeamTable.updatedAt,
}

function stubReads(results: Array<MemberTeamSummary[] | Error>) {
  const reads: Array<{ sql: string; params: unknown[] }> = []
  const select = db.select.bind(db)
  const boundary = mock.method(db, "select", (fields: typeof selection) => {
    assert.deepEqual(fields, selection)
    const builder = select(fields)
    const from = builder.from.bind(builder)
    mock.method(builder, "from", (table: typeof TeamMemberTable) => {
      assert.equal(table, TeamMemberTable)
      const query = from(table)
      mock.method(query, "execute", async () => {
        reads.push(query.toSQL())
        const rows = results.shift()
        assert.notEqual(rows, undefined, "unexpected database read")
        if (rows instanceof Error) throw rows
        return rows
      })
      return query
    })
    return builder
  })
  return { reads, boundary }
}

function forbidCacheReads() {
  const fail = () => { throw new Error("team reads must not use any cache") }
  const members = mock.method(cache.org, "members", fail)
  const membership = mock.method(cache.org, "membership", fail)
  const rollouts = mock.method(cache.features, "rollouts", fail)
  const overrides = mock.method(cache.features, "overrides", fail)
  const state = mock.method(cache.features, "state", fail)
  return () => {
    for (const spy of [members, membership, rollouts, overrides, state]) assert.equal(spy.mock.callCount(), 0)
  }
}

function assertQueries(reads: Array<{ sql: string; params: unknown[] }>, count: number) {
  assert.equal(reads.length, count)
  for (const query of reads) {
    assert.match(query.sql, /from `team_member` inner join `team`/)
    assert.match(query.sql, /inner join `member` on/)
    assert.match(query.sql, /`member`\.`id` = `team_member`\.`org_membership_id`/)
    assert.match(query.sql, /`member`\.`organization_id` = `team`\.`organization_id`/)
    assert.match(query.sql, /`member`\.`removed_at` is null/)
    assert.match(query.sql, /where \(`team`\.`organization_id` = \? and `team_member`\.`org_membership_id` = \?\)/)
    assert.match(query.sql, /order by `team`\.`created_at` asc/)
    assert.deepEqual(query.params, [organizationId, memberId])
  }
}

afterEach(() => mock.restoreAll())

test("listTeamsForMember uses the dedicated revocation-safe cache boundary", () => {
  assert.match(functionSource, /cache\.org\.memberTeams/)
  assert.match(functionSource, /OrganizationTable\.teamMembershipRevision/)
  assert.match(functionSource, /isNull\(MemberTable\.removedAt\)/)
})

test("without Redis, repeated reads query the database and revoked memberships disappear without invalidation", async () => {
  const { reads, boundary } = stubReads([[team], [team], [], []])
  const assertNoCacheReads = forbidCacheReads()
  const invalidation = mock.method(cache.features, "invalidate", async () => { throw new Error("feature invalidation unavailable") })
  assert.deepEqual(await listTeamsForMember(input), [team])
  assert.deepEqual(await listTeamsForMember(input), [team])
  assert.deepEqual(await listTeamsForMember(input), [])
  assert.deepEqual(await listTeamsForMember(input), [])
  assert.equal(boundary.mock.callCount(), 4)
  assert.equal(invalidation.mock.callCount(), 0)
  assertNoCacheReads()
  assertQueries(reads, 4)
})

test("revoked memberships remain empty immediately after feature cache invalidation throws", async () => {
  const { reads } = stubReads([[team], [team], [], []])
  const assertNoCacheReads = forbidCacheReads()
  const failure = new Error("feature invalidation Redis failure")
  const invalidation = mock.method(cache.features, "invalidate", async () => { throw failure })
  assert.deepEqual(await listTeamsForMember(input), [team])
  assert.deepEqual(await listTeamsForMember(input), [team])
  await assert.rejects(cache.features.invalidate(), (error: unknown) => error === failure)
  assert.deepEqual(await listTeamsForMember(input), [])
  assert.deepEqual(await listTeamsForMember(input), [])
  assert.equal(invalidation.mock.callCount(), 1)
  assertNoCacheReads()
  assertQueries(reads, 4)
})

test("removed organization members stay excluded after repeated reads and an unrelated Redis-backed cache failure", async () => {
  const { reads } = stubReads([[team], [team], [], []])
  const assertNoCacheReads = forbidCacheReads()
  const failure = new Error("unrelated Redis connection unavailable")
  const grant = mock.method(cache.auth, "grant", async () => { throw failure })
  assert.deepEqual(await listTeamsForMember(input), [team])
  assert.deepEqual(await listTeamsForMember(input), [team])
  await assert.rejects(cache.auth.grant(createDenTypeId("oauthConsent")), (error: unknown) => error === failure)
  assert.deepEqual(await listTeamsForMember(input), [])
  assert.deepEqual(await listTeamsForMember(input), [])
  assert.equal(grant.mock.callCount(), 1)
  assertNoCacheReads()
  assertQueries(reads, 4)
})

test("a database failure after populated reads rejects instead of returning stale teams", async () => {
  const failure = new Error("authoritative membership read failed")
  const { reads } = stubReads([[team], [team], failure, []])
  const assertNoCacheReads = forbidCacheReads()
  assert.deepEqual(await listTeamsForMember(input), [team])
  assert.deepEqual(await listTeamsForMember(input), [team])
  await assert.rejects(listTeamsForMember(input), (error: unknown) => error === failure)
  assert.deepEqual(await listTeamsForMember(input), [])
  assertNoCacheReads()
  assertQueries(reads, 4)
})
