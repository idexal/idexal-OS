import assert from "node:assert/strict"
import { test } from "node:test"
import { cloudHostingAvailable } from "../src/capability-sources/cloud-hosting.ts"

test("multi-org deployments always host Cloud", () => {
  assert.equal(cloudHostingAvailable({ orgMode: "multi_org", idexalOsWebEnabled: false }), true)
  assert.equal(cloudHostingAvailable({ orgMode: "multi_org", idexalOsWebEnabled: true }), true)
})

test("single-org installs host Cloud only when idexal Os Web is on", () => {
  assert.equal(cloudHostingAvailable({ orgMode: "single_org", idexalOsWebEnabled: false }), false)
  assert.equal(cloudHostingAvailable({ orgMode: "single_org", idexalOsWebEnabled: true }), true)
})
