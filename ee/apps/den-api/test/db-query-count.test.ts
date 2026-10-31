import assert from "node:assert/strict"
import { test } from "node:test"
import { countDbQueries, dbQueryCounter } from "../src/observability/db-query-count.ts"

test("statements issued while a request runs are counted for that request only", async () => {
  const outer = { count: 0 }
  const inner = { count: 0 }
  dbQueryCounter.logQuery("select 1", [])
  await countDbQueries(outer, async () => {
    dbQueryCounter.logQuery("select 1", [])
    await Promise.resolve()
    dbQueryCounter.logQuery("select 2", ["secret"])
    // A nested app.fetch counts in its own request log.
    await countDbQueries(inner, async () => {
      dbQueryCounter.logQuery("select 3", [])
    })
    dbQueryCounter.logQuery("select 4", [])
  })
  dbQueryCounter.logQuery("select 5", [])
  assert.equal(outer.count, 3)
  assert.equal(inner.count, 1)
})
