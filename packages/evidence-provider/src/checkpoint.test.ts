import assert from "node:assert/strict";
import { test } from "node:test";
import { parseEvidenceCheckpoint } from "./checkpoint.ts";

const valid = {
  version: 1,
  provider: "freestyle",
  id: `ow-evidence-v1-${"a".repeat(32)}`,
  sourceSha: "b".repeat(40),
  imageHash: "c".repeat(64),
  capturedAt: "2026-10-12T00:00:00.000Z",
  expiresAt: "2026-10-12T12:00:00.000Z",
};

test("a Freestyle checkpoint recorded before this change still parses unchanged", () => {
  assert.deepEqual(parseEvidenceCheckpoint(valid), valid);
});

test("unknown fields are dropped", () => {
  assert.deepEqual(parseEvidenceCheckpoint({ ...valid, url: "https://example.test/?token=x" }), valid);
});

test("a Daytona checkpoint parses with its own ID format", () => {
  const daytona = { ...valid, provider: "daytona", id: `ow-dy-checkpoint-v1-${"d".repeat(32)}` };
  assert.deepEqual(parseEvidenceCheckpoint(daytona), daytona);
  // Each provider only accepts its own IDs.
  assert.throws(() => parseEvidenceCheckpoint({ ...valid, provider: "daytona" }), /Invalid checkpoint fields/);
  assert.throws(() => parseEvidenceCheckpoint({ ...daytona, provider: "freestyle" }), /Invalid checkpoint fields/);
});

test("unknown providers are rejected", () => {
  assert.throws(() => parseEvidenceCheckpoint({ ...valid, provider: "e2b" }), /Invalid checkpoint fields/);
  assert.throws(() => parseEvidenceCheckpoint({ ...valid, provider: undefined }), /Invalid checkpoint fields/);
});

test("IDs must match the provider's format", () => {
  assert.throws(() => parseEvidenceCheckpoint({ ...valid, id: "ow-evidence-v1-xyz" }), /Invalid checkpoint fields/);
});

test("lifetimes are positive and at most 24 hours", () => {
  assert.throws(() => parseEvidenceCheckpoint({ ...valid, expiresAt: valid.capturedAt }), /lifetime/);
  assert.throws(() => parseEvidenceCheckpoint({ ...valid, expiresAt: "2026-10-13T00:00:01.000Z" }), /lifetime/);
});
