import { and, eq, gte, isNull, lt, lte, sql } from "drizzle-orm"
import type { createDenDb } from "./client"
import { FreeAutoLimitEventTable as Limit } from "./schema/free-auto-behavior"
import { AnonymousInferenceRateBucketTable as Rate } from "./schema/inference"
import { freeInferenceDigest } from "@idexal-ee/utils/free-inference-digest"

type Database = ReturnType<typeof createDenDb>["db"]
export const FREE_AUTO_CONVERSION_WINDOW_DAYS = 30
const WINDOW_MS = FREE_AUTO_CONVERSION_WINDOW_DAYS * 86_400_000
/** Analytics only: shared networks can record several journeys, but rotating public headers cannot grow storage indefinitely. */
export const FREE_AUTO_GUEST_OBSERVATION_DAILY_LIMIT = 100

/** A missing/malformed correlation header disables guest attribution without affecting access or accounting. */
export function freeAutoJourneyHash(value: string | null | undefined): string | null {
  return value && /^[a-f0-9]{64}$/.test(value) ? freeInferenceDigest("free-auto-journey", value) : null
}

export async function recordFreeAutoLimit(database: Database, input: {
  audience: "guest" | "member"; subjectHash: string; userId?: typeof Limit.$inferInsert.user_id;
  organizationId?: typeof Limit.$inferInsert.organization_id; windowStart: Date; now: Date;
  /** Server-derived keyed network hash; never supplied by the client. Required for guest observations. */
  observationRateHash?: string;
}) {
  const id = freeInferenceDigest("free-auto-limit", `${input.audience}:${input.subjectHash}:${input.windowStart.toISOString()}`)
  const row = { id, audience: input.audience, subject_hash: input.subjectHash,
    user_id: input.userId, organization_id: input.organizationId, window_start_at: input.windowStart, exhausted_at: input.now }
  if (input.audience === "member") {
    await database.insert(Limit).values(row).onDuplicateKeyUpdate({ set: { id: sql`${Limit.id}` } })
    return
  }
  if (!input.observationRateHash) return
  const day = Math.floor(input.now.getTime() / 86_400_000)
  const rateId = freeInferenceDigest("free-auto-observation-rate", `${input.observationRateHash}:${day}`)
  // This existing expiry-indexed table is independent of allowance accounting.
  await database.delete(Rate).where(lte(Rate.expires_at, input.now)).limit(100)
  await database.transaction(async (tx) => {
    // Insert before locking, avoiding a gap lock on an absent counter. The row lock bounds all Gateway instances together.
    await tx.insert(Rate).values({ id: rateId, used_amount: 0, expires_at: new Date((day + 1) * 86_400_000) })
      .onDuplicateKeyUpdate({ set: { id: sql`${Rate.id}` } })
    const [rate] = await tx.select({ used: Rate.used_amount }).from(Rate).where(eq(Rate.id, rateId)).limit(1).for("update")
    if (!rate || rate.used >= FREE_AUTO_GUEST_OBSERVATION_DAILY_LIMIT) return
    const [existing] = await tx.select({ id: Limit.id }).from(Limit).where(eq(Limit.id, id)).limit(1)
    if (existing) return
    await tx.insert(Limit).values(row).onDuplicateKeyUpdate({ set: { id: sql`${Limit.id}` } })
    await tx.update(Rate).set({ used_amount: sql`${Rate.used_amount} + 1` }).where(eq(Rate.id, rateId))
  })
}

/** First authenticated Auto request with the same reported journey; no IP or device fingerprint join. */
export async function linkFreeAutoGuest(database: Database, input: {
  journeyHash: string; userId: NonNullable<typeof Limit.$inferInsert.user_id>;
  organizationId: NonNullable<typeof Limit.$inferInsert.organization_id>; now: Date;
}) {
  await database.update(Limit).set({ user_id: input.userId, organization_id: input.organizationId, authenticated_at: input.now })
    .where(and(eq(Limit.audience, "guest"), eq(Limit.subject_hash, input.journeyHash), isNull(Limit.user_id),
      lt(Limit.exhausted_at, input.now), gte(Limit.exhausted_at, new Date(input.now.getTime() - WINDOW_MS))))
}

/** Durable sign-in evidence, independent of session retention or optional organization audit capture. */
export async function recordFreeAutoSignIn(database: Database, input: { userId: NonNullable<typeof Limit.$inferInsert.user_id>; now: Date }) {
  await database.update(Limit).set({ authenticated_at: input.now }).where(and(eq(Limit.audience, "member"),
    eq(Limit.user_id, input.userId), isNull(Limit.authenticated_at), gte(Limit.exhausted_at, new Date(input.now.getTime() - WINDOW_MS)), lt(Limit.exhausted_at, input.now)))
}
