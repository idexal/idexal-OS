import assert from "node:assert/strict";
import { test } from "node:test";
import { previewProviderOptions } from "./provider-status.ts";
import { previewAccessUrl } from "./preview-options.ts";
import { parsePreviewOutputs } from "@idexal/freestyle/outputs";

test("server-derived choices cover neither, Freestyle-only, Daytona-only and both credentials", async () => {
  for (const freestyle of [false, true]) for (const daytona of [false, true]) {
    const options = await previewProviderOptions({
      configured: { freestyle: () => freestyle, daytona: () => daytona },
      preferred: () => "freestyle",
    });
    assert.deepEqual(options.filter((option) => option.connected).map((option) => option.id), [
      ...(freestyle ? ["freestyle"] : []), ...(daytona ? ["daytona"] : []),
    ]);
    assert.ok(!JSON.stringify(options).includes("apiKey"));
  }
});

test("a configured preferred provider stays available without disabling its configured alternative", async () => {
  const options = await previewProviderOptions({ configured: { freestyle: () => true, daytona: () => true }, preferred: () => "daytona" });
  assert.equal(options[0].id, "daytona");
  assert.equal(options[0].connected, true);
  assert.equal(options[0].reason, undefined);
  assert.equal(options[1].connected, true);
});

test("bad provider configuration fails closed instead of silently selecting another", async () => {
  const options = await previewProviderOptions({ configured: { freestyle: () => true, daytona: () => true },
    preferred: () => { throw new Error("bad config"); } });
  assert.ok(options.every((option) => !option.connected && option.reason?.includes("misconfigured")));
});

test("configured reviewer infrastructure is independent of Cloud authority and deployment", async () => {
  const previousFetch = globalThis.fetch;
  const previous = { ...process.env };
  let requests = 0;
  try {
    process.env.DAYTONA_API_KEY = "synthetic-review-infrastructure-key";
    process.env.FREESTYLE_API_KEY = "";
    process.env.IDEXAL_EVIDENCE_PROVIDER = "daytona";
    process.env.DEN_API_BASE = "https://unavailable-authority.invalid";
    globalThis.fetch = async () => { requests++; throw new Error("Reviewer infrastructure must not fetch Cloud rollout authority"); };
    for (const deployment of ["self_hosted", "cloud"]) {
      process.env.DEN_DEPLOYMENT = deployment;
      process.env.DEN_FEATURE_REVIEWER_DAYTONA_PREVIEWS = "false";
      const options = await previewProviderOptions();
      assert.equal(options[0].id, "daytona");
      assert.equal(options[0].connected, true);
      assert.deepEqual(options[0].worlds, ["app-web"]);
      assert.equal(options[0].reason, undefined);
    }
    assert.equal(requests, 0);
  } finally {
    globalThis.fetch = previousFetch;
    for (const key of ["DAYTONA_API_KEY", "FREESTYLE_API_KEY", "IDEXAL_EVIDENCE_PROVIDER", "DEN_API_BASE", "DEN_DEPLOYMENT", "DEN_FEATURE_REVIEWER_DAYTONA_PREVIEWS"]) {
      if (previous[key] === undefined) delete process.env[key]; else process.env[key] = previous[key];
    }
  }
});

test("provider-specific links reject cross-provider and untrusted access hosts", () => {
  const freestyle = `https://ow-${"a".repeat(32)}.preview.idexal.com/`;
  const desktop = `https://desktop-${"a".repeat(32)}.preview.idexal.com/`;
  const daytona = "https://8080-signed.daytonaproxy01.net/__idexal_os_launch?token=fixture";
  assert.equal(previewAccessUrl("freestyle", freestyle), true);
  assert.equal(previewAccessUrl("freestyle", desktop, true), true);
  assert.equal(previewAccessUrl("daytona", daytona), true);
  for (const provider of ["freestyle", "daytona"]) {
    // The public predicate accepts only a known provider union.
    for (const bad of ["javascript:alert(1)", "http://example.test", "https://example.test", "https://user:pass@8080-signed.daytonaproxy01.net/", "https://8080-signed.daytonaproxy01.net:444/"]) {
      assert.equal(previewAccessUrl(provider === "freestyle" ? "freestyle" : "daytona", bad), false);
    }
  }
  assert.equal(previewAccessUrl("daytona", freestyle), false);
  assert.equal(previewAccessUrl("freestyle", daytona), false);
  const services = { webUrl: { value: daytona, group: "Services", secret: true } };
  assert.equal(parsePreviewOutputs(services, "daytona").webUrl.value, daytona);
  assert.throws(() => parsePreviewOutputs(services, "freestyle"), /Invalid private service link/);
  assert.throws(() => parsePreviewOutputs({ webUrl: { value: "https://example.test/", group: "Services" } }, "daytona"), /Invalid private service link/);
});
