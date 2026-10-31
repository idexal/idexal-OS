import assert from "node:assert/strict"
import { test } from "node:test"
import { forgetRequestReadsOnWrite, requestRead, withRequestReads } from "../src/request-reads.ts"

function counter() {
  let loads = 0
  return { load: async () => { loads += 1; return loads }, loads: () => loads }
}

test("callers in one request share one read, including concurrent ones", async () => {
  const reads = counter()
  await withRequestReads(async () => {
    const [first, second] = await Promise.all([requestRead("k", reads.load), requestRead("k", reads.load)])
    assert.deepEqual([first, second, await requestRead("k", reads.load)], [1, 1, 1])
  })
  assert.equal(reads.loads(), 1)
})

test("different keys load separately", async () => {
  const reads = counter()
  await withRequestReads(async () => {
    assert.deepEqual([await requestRead("a", reads.load), await requestRead("b", reads.load)], [1, 2])
  })
})

test("a write statement drops shared reads; a read statement does not", async () => {
  const reads = counter()
  await withRequestReads(async () => {
    assert.equal(await requestRead("k", reads.load), 1)
    forgetRequestReadsOnWrite("select `id` from `member` where `member`.`id` = ?")
    assert.equal(await requestRead("k", reads.load), 1)
    forgetRequestReadsOnWrite("update `member` set `role` = ? where `member`.`id` = ?")
    assert.equal(await requestRead("k", reads.load), 2)
    for (const write of ["insert into `team` values (?)", "  delete from `team` where `id` = ?", "REPLACE INTO `team` VALUES (?)"]) {
      const before = reads.loads()
      forgetRequestReadsOnWrite(write)
      await requestRead("k", reads.load)
      assert.equal(reads.loads(), before + 1, write)
    }
  })
})

test("a failed read is not shared", async () => {
  let attempts = 0
  await withRequestReads(async () => {
    const failOnce = async () => {
      attempts += 1
      if (attempts === 1) throw new Error("database unavailable")
      return "rows"
    }
    await assert.rejects(requestRead("k", failOnce), /database unavailable/)
    assert.equal(await requestRead("k", failOnce), "rows")
  })
  assert.equal(attempts, 2)
})

test("outside a request every call loads, and each request has its own reads", async () => {
  const reads = counter()
  assert.equal(await requestRead("k", reads.load), 1)
  assert.equal(await requestRead("k", reads.load), 2)
  await withRequestReads(async () => {
    assert.equal(await requestRead("k", reads.load), 3)
    await withRequestReads(async () => {
      assert.equal(await requestRead("k", reads.load), 4, "a nested request does not see the outer one's reads")
    })
    assert.equal(await requestRead("k", reads.load), 3)
  })
  await withRequestReads(async () => {
    assert.equal(await requestRead("k", reads.load), 5, "nothing crosses requests")
  })
})

test("a write in a nested request also drops the outer request's shared reads", async () => {
  const reads = counter()
  await withRequestReads(async () => {
    assert.equal(await requestRead("k", reads.load), 1)
    await withRequestReads(async () => {
      forgetRequestReadsOnWrite("insert into `skill` values (?)")
    })
    assert.equal(await requestRead("k", reads.load), 2)
  })
})
