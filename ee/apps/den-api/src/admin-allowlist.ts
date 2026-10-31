import { sql } from "@idexal-ee/den-db/drizzle"
import { AdminAllowlistTable } from "@idexal-ee/den-db/schema"
import { createDenTypeId } from "@idexal-ee/utils/typeid"
import { db } from "./db.js"
import { env } from "./env.js"

const BOOTSTRAP_ADMIN_NOTE = "Seeded bootstrap admin"

let ensureAdminAllowlistSeededPromise: Promise<void> | null = null

async function seedAdminAllowlist() {
  for (const email of env.bootstrapAdminEmails) {
    await db
      .insert(AdminAllowlistTable)
      .values({
        id: createDenTypeId("adminAllowlist"),
        email,
        note: BOOTSTRAP_ADMIN_NOTE,
      })
      .onDuplicateKeyUpdate({
        set: {
          note: BOOTSTRAP_ADMIN_NOTE,
          updated_at: sql`CURRENT_TIMESTAMP(3)`,
        },
      })
  }
}

export async function ensureAdminAllowlistSeeded() {
  if (!ensureAdminAllowlistSeededPromise) {
    ensureAdminAllowlistSeededPromise = seedAdminAllowlist().catch((error) => {
      ensureAdminAllowlistSeededPromise = null
      throw error
    })
  }

  await ensureAdminAllowlistSeededPromise
}
