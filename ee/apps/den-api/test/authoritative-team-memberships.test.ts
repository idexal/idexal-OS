import assert from "node:assert/strict"
import { afterEach, mock, test } from "node:test"
import { createDenTypeId } from "@idexal-ee/utils/typeid"

// No database is contacted: each test replaces the query boundary (db.select)
// with canned rows, in the order the function issues its reads.
process.env.DATABASE_URL ??= "mysql://test:test@127.0.0.1:3306/authoritative_team_memberships_unit_test"
process.env.BETTER_AUTH_URL ??= "http://unit.test"
process.env.BETTER_AUTH_SECRET ??= "synthetic-authoritative-team-memberships-unit-test-secret"
process.env.DEN_DB_ENCRYPTION_KEY ??= "synthetic-authoritative-team-memberships-unit-test-key"
process.env.IDEXAL_DEV_MODE ??= "1"
const { db } = await import("../src/db.js")
const { listAuthoritativeTeamMemberships } = await import("@idexal-ee/den-db/permissions")

type Read = { fields: string[]; limit?: number; lock?: string }

function stubReads(results: unknown[][]) {
  const reads: Read[] = []
  mock.method(db, "select", (fields: Record<string, unknown>) => {
    const read: Read = { fields: Object.keys(fields) }
    reads.push(read)
    const rows = results.shift() ?? []
    const chain = {
      from: () => chain,
      innerJoin: () => chain,
      leftJoin: () => chain,
      where: () => chain,
      limit: (count: number) => {
        read.limit = count
        return chain
      },
      for: (lock: string) => {
        read.lock = lock
        return Promise.resolve(rows)
      },
      then: (resolve: (value: unknown[]) => unknown, reject: (reason: unknown) => unknown) => Promise.resolve(rows).then(resolve, reject),
    }
    return chain
  })
  return reads
}

afterEach(() => mock.restoreAll())

const organizationId = createDenTypeId("organization")
const memberId = createDenTypeId("member")
const teamId = createDenTypeId("team")
const teamMemberId = createDenTypeId("teamMember")

const membershipRow = {
  teamMemberId,
  memberId,
  teamId,
  teamName: "Admins",
  grantsOrganizationAdmin: true,
  scimGroupId: null,
  groupMappingMode: null,
  listedMemberId: null,
  projectionLinkId: null,
  projectionGroupTeamId: null,
  projectionProviderId: null,
}

test("a member in no matching team is answered by the memberships read alone, without the SCIM joins", async () => {
  const reads = stubReads([[]])
  const result = await listAuthoritativeTeamMemberships(db, { organizationId, memberId, adminTeamsOnly: true })
  assert.deepEqual(result, [])
  assert.equal(reads.length, 1)
  assert.deepEqual(reads[0].fields, ["teamMemberId"])
  assert.equal(reads[0].limit, 1)
})

test("a hand-managed Admin team still grants authority once the SCIM read runs", async () => {
  const reads = stubReads([[{ teamMemberId }], [membershipRow]])
  const result = await listAuthoritativeTeamMemberships(db, { organizationId, memberId, adminTeamsOnly: true })
  assert.deepEqual(result, [{ memberId, teamId, teamName: "Admins", grantsOrganizationAdmin: true }])
  assert.equal(reads.length, 2)
  assert.ok(reads[1].fields.includes("groupMappingMode"))
})

test("SCIM still drops memberships the identity provider no longer lists, or whose projection is orphaned", async () => {
  stubReads([[{ teamMemberId }], [{ ...membershipRow, scimGroupId: createDenTypeId("scimGroup"), groupMappingMode: "create_teams" }]])
  assert.deepEqual(await listAuthoritativeTeamMemberships(db, { organizationId, memberId, adminTeamsOnly: true }), [])

  stubReads([[{ teamMemberId }], [{ ...membershipRow, projectionLinkId: createDenTypeId("scimGroupMember"), projectionGroupTeamId: null }]])
  assert.deepEqual(await listAuthoritativeTeamMemberships(db, { organizationId, memberId, adminTeamsOnly: true }), [])
})

test("a locked read inside a transaction skips the unlocked pre-check", async () => {
  const reads = stubReads([[membershipRow]])
  const result = await listAuthoritativeTeamMemberships(db, { organizationId, memberId, adminTeamsOnly: true, lock: "share" })
  assert.equal(result.length, 1)
  assert.equal(reads.length, 1)
  assert.equal(reads[0].lock, "share")
})
