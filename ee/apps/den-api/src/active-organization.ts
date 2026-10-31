import { and, asc, eq, isNull } from "@idexal-ee/den-db/drizzle"
import { MemberTable } from "@idexal-ee/den-db/schema"
import { normalizeDenTypeId } from "@idexal-ee/utils/typeid"
import { db } from "./db.js"
import { env } from "./env.js"
import { ensureUserOrgAccess } from "./orgs.js"

export async function getInitialActiveOrganizationIdForUser(userId: string) {
  const normalizedUserId = normalizeDenTypeId("user", userId)

  if (env.orgMode === "single_org") {
    return ensureUserOrgAccess({ userId: normalizedUserId })
  }

  const rows = await db
    .select({
      organizationId: MemberTable.organizationId,
    })
    .from(MemberTable)
    .where(and(eq(MemberTable.userId, normalizedUserId), isNull(MemberTable.removedAt)))
    .orderBy(asc(MemberTable.createdAt))
    .limit(2)

  return rows.length === 1 ? rows[0].organizationId : null
}
