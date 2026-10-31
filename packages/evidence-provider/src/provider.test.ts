import assert from "node:assert/strict";
import { test } from "node:test";
import { evidenceProviderFromEnv, EvidenceProviderNotReady } from "./provider.ts";
import { notReadyReviewProvider } from "./review.ts";

test("unset or blank selects Freestyle, today's behavior", () => {
  assert.equal(evidenceProviderFromEnv({}), "freestyle");
  assert.equal(evidenceProviderFromEnv({ IDEXAL_EVIDENCE_PROVIDER: "  " }), "freestyle");
});

test("known providers are selected case-insensitively", () => {
  assert.equal(evidenceProviderFromEnv({ IDEXAL_EVIDENCE_PROVIDER: "daytona" }), "daytona");
  assert.equal(evidenceProviderFromEnv({ IDEXAL_EVIDENCE_PROVIDER: " Freestyle " }), "freestyle");
});

test("an unknown provider is an error, not a silent fallback", () => {
  assert.throws(() => evidenceProviderFromEnv({ IDEXAL_EVIDENCE_PROVIDER: "e2b" }), /must be one of freestyle, daytona/);
});

test("a provider without review support reports itself as not configured and refuses work", async () => {
  const provider = notReadyReviewProvider("daytona");
  assert.equal(provider.label, "Daytona");
  assert.equal(provider.configured(), false);
  await assert.rejects(provider.launchPreview({ gitSha: "a".repeat(40), reportId: "b".repeat(32), world: "app-web" }), EvidenceProviderNotReady);
});
