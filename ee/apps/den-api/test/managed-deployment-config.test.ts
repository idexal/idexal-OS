import assert from "node:assert/strict"
import { test } from "node:test"
import { releaseReadiness } from "../src/managed-deployments/config.js"

function environment(provider: "AWS" | "GCP"): NodeJS.ProcessEnv {
  return {
    DEN_API_PUBLIC_URL: "https://api.example.test",
    [`DEN_MANAGED_DEPLOYMENT_${provider}_VERSION`]: "0.18.58",
    [`DEN_MANAGED_DEPLOYMENT_${provider}_TEMPLATE_URL`]: provider === "GCP" ? "https://storage.googleapis.com/test-release/tutorial.md?generation=1" : "https://releases.example.test/template.json",
    [`DEN_MANAGED_DEPLOYMENT_${provider}_BUNDLE_URL`]: provider === "GCP" ? "https://storage.googleapis.com/test-release/bundle.tar.gz?generation=2" : "https://releases.example.test/bundle.tar.gz",
    [`DEN_MANAGED_DEPLOYMENT_${provider}_BUNDLE_SHA256`]: "a".repeat(64),
  }
}
test("missing publication reports field names without exposing configuration values", () => {
  const result = releaseReadiness("aws", { DEN_MANAGED_DEPLOYMENT_AWS_BUNDLE_URL: "private-invalid-value" })
  assert.equal(result.available, false)
  assert.equal(result.reason, "not_configured")
  assert.ok(result.missingFields.includes("DEN_MANAGED_DEPLOYMENT_AWS_VERSION"))
  assert.ok(result.missingFields.includes("DEN_MANAGED_DEPLOYMENT_AWS_BUNDLE_URL"))
  assert.ok(!JSON.stringify(result).includes("private-invalid-value"))
})
for (const provider of ["aws", "gcp"] satisfies Array<"aws" | "gcp">) {
  test(`${provider} valid release is configured independently`, () => {
    const env = environment(provider === "aws" ? "AWS" : "GCP")
    assert.equal(releaseReadiness(provider, env).reason, "ready")
    assert.equal(releaseReadiness(provider === "aws" ? "gcp" : "aws", env).available, false)
  })
}
test("mutable GCP artifact URLs cannot advertise readiness", () => {
  const env = { ...environment("GCP"), DEN_MANAGED_DEPLOYMENT_GCP_BUNDLE_URL: "https://storage.googleapis.com/test-release/bundle.tar.gz" }
  assert.equal(releaseReadiness("gcp", env).available, false)
  assert.deepEqual(releaseReadiness("gcp", env).missingFields, ["DEN_MANAGED_DEPLOYMENT_GCP_BUNDLE_URL"])
})
test("invalid origin and embedded URL credentials never enable launch", () => {
  for (const origin of ["https://api.example.test/path", "https://user:password@api.example.test", "http://api.example.test"]) {
    const env = { ...environment("AWS"), DEN_API_PUBLIC_URL: origin }
    assert.equal(releaseReadiness("aws", env).reason, "invalid_configuration")
    assert.deepEqual(releaseReadiness("aws", env).missingFields, ["DEN_API_PUBLIC_URL"])
  }
  const env = { ...environment("AWS"), DEN_MANAGED_DEPLOYMENT_AWS_BUNDLE_URL: "https://secret@example.test/bundle" }
  assert.equal(releaseReadiness("aws", env).available, false)
})
