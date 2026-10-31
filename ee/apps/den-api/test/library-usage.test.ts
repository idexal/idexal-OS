import assert from "node:assert/strict"
import { test } from "node:test"
import { buildLibraryUsageRows, libraryUsageWindow } from "../src/capability-usage-rows.js"

test("the window is the last N UTC calendar days, today included, matching the daily rollup buckets", () => {
  const window = libraryUsageWindow(7, new Date("2026-10-13T06:30:00Z"))
  assert.equal(window.from.toISOString(), "2026-10-07T00:00:00.000Z")
  assert.equal(window.fromDate, "2026-10-07")
  assert.equal(window.toDate, "2026-10-13")
  assert.equal(libraryUsageWindow(1, new Date("2026-10-13T23:59:59Z")).fromDate, "2026-10-13")
})

test("unused items are listed with zeros and totals for removed items are ignored, most used first", () => {
  const items = [
    { id: "a", name: "Alpha", detail: "Sales", pluginId: "p1", tracksFailures: false },
    { id: "b", name: "Beta", detail: "Sales", pluginId: "p1", tracksFailures: false },
    { id: "c", name: "Gamma", detail: "Support", pluginId: "p2", tracksFailures: false },
  ]
  const rows = buildLibraryUsageRows(items, [
    { itemId: "c", uses: 9, people: 2, failures: 0, lastUsedAt: new Date("2026-10-07T10:00:00Z") },
    { itemId: "a", uses: 2, people: 1, failures: 0, lastUsedAt: new Date("2026-10-01T10:00:00Z") },
    { itemId: "gone", uses: 50, people: 3, failures: 0, lastUsedAt: new Date("2026-10-07T10:00:00Z") },
  ])
  assert.deepEqual(rows.map((row) => [row.name, row.uses, row.people, row.failures, row.lastUsedAt]), [
    ["Gamma", 9, 2, null, "2026-10-07T10:00:00.000Z"],
    ["Alpha", 2, 1, null, "2026-10-01T10:00:00.000Z"],
    ["Beta", 0, 0, null, null],
  ])
})

test("failures are kept for plugins and connectors, and ties sort by people then name", () => {
  const rows = buildLibraryUsageRows(
    [{ id: "p1", name: "Support kit", detail: "2 skills", pluginId: null, tracksFailures: true },
      { id: "p2", name: "Sales kit", detail: null, pluginId: null, tracksFailures: true },
      { id: "p3", name: "Admin kit", detail: null, pluginId: null, tracksFailures: true }],
    [
      { itemId: "p1", uses: 7, people: 1, failures: 2, lastUsedAt: new Date("2026-10-08T10:00:00Z") },
      { itemId: "p3", uses: 7, people: 3, failures: 0, lastUsedAt: new Date("2026-10-06T10:00:00Z") },
    ],
  )
  assert.deepEqual(rows.map((row) => [row.name, row.uses, row.people, row.failures]), [
    ["Admin kit", 7, 3, 0],
    ["Support kit", 7, 1, 2],
    ["Sales kit", 0, 0, 0],
  ])
})
