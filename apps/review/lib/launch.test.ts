import assert from "node:assert/strict";
import { test } from "node:test";
import type { EvidenceProviderId } from "@idexal/evidence-provider";
import { notReadyReviewProvider, type ReviewPreviewProvider } from "@idexal/evidence-provider/review";
import { launchHandlers } from "./launch.ts";
import type { PreviewProviderOption } from "./preview-options.ts";
import { previewProviderOptions } from "./provider-status.ts";

const sha = "a".repeat(40);
const context = { params: Promise.resolve({ id: "owned-report" }) };
const option = (id: EvidenceProviderId, connected = true): PreviewProviderOption => ({
  id, label: id === "daytona" ? "Daytona" : "Freestyle", connected, worlds: ["app-web"], reason: connected ? undefined : "The provider is not connected. The review app owner can configure it.",
});
const post = (body: unknown, origin = "https://review.example.test") => new Request("https://review.example.test/r/owned-report/launch", {
  method: "POST", headers: { origin, "content-type": "application/json" }, body: JSON.stringify(body),
});

function fixture(providerOptions?: () => Promise<PreviewProviderOption[]>) {
  const events: { provider: EvidenceProviderId; operation: string; source?: string; report?: string }[] = [];
  const scheduled: (() => Promise<void>)[] = [];
  let choices = [option("freestyle"), option("daytona")];
  let snapshot = true;
  let failure = false;
  let supportsPreviews = true;
  let optionsReads = 0;
  const providers = (id: EvidenceProviderId = "freestyle"): ReviewPreviewProvider => ({
    ...notReadyReviewProvider(id), configured: () => true, supportsPreviews,
    hasSnapshot: async (source) => { events.push({ provider: id, operation: "has", source }); return snapshot; },
    buildSnapshot: async (source) => { events.push({ provider: id, operation: "build", source }); },
    buildProgress: async () => { events.push({ provider: id, operation: "progress" }); return { building: true, steps: [] }; },
    launchPreview: async (input) => {
      events.push({ provider: id, operation: "launch", source: input.gitSha, report: input.reportId });
      if (failure) throw new Error("do-not-expose-provider-token");
      return { id: "owned-sandbox", ...input, expiresAt: new Date(Date.now() + 7200000).toISOString(), url: "https://8080-signed.daytonaproxy01.net/", outputs: {} };
    },
  });
  return {
    events, scheduled,
    setChoices: (value: PreviewProviderOption[]) => { choices = value; },
    setSnapshot: (value: boolean) => { snapshot = value; },
    setFailure: () => { failure = true; },
    disablePreviews: () => { supportsPreviews = false; },
    getOptionsReads: () => optionsReads,
    handlers: launchHandlers({ readReview: async () => ({ gitSha: sha }), provider: providers,
      options: async () => { optionsReads++; return providerOptions ? providerOptions() : choices; }, schedule: (task) => { scheduled.push(task); } }),
  };
}

test("dispatches explicit providers and uses only the immutable report commit and association", async () => {
  for (const id of ["freestyle", "daytona"]) {
    const f = fixture();
    const response = await f.handlers.POST(post({ world: "app-web", provider: id, gitSha: "b".repeat(40), reportId: "other-report" }), context);
    assert.equal(response.status, 201);
    assert.equal(response.headers.get("cache-control"), "private, no-store");
    assert.equal((await response.json()).provider, id);
    assert.deepEqual(f.events.at(-1), { provider: id, operation: "launch", source: sha, report: "owned-report" });
    assert.equal(f.getOptionsReads(), 2);
  }
});

test("Daytona-only configuration serves legacy requests without Freestyle fallback", async () => {
  const f = fixture();
  f.setChoices([option("freestyle", false), option("daytona")]);
  assert.equal((await f.handlers.POST(post({ world: "app-web" }), context)).status, 201);
  assert.ok(f.events.every((event) => event.provider === "daytona"));
  f.events.length = 0;
  assert.equal((await f.handlers.POST(post({ world: "app-web", provider: "freestyle" }), context)).status, 503);
  assert.deepEqual(f.events, []);
});

test("neither provider configured means no provider calls or scheduled spend", async () => {
  const f = fixture();
  f.setChoices([option("freestyle", false), option("daytona", false)]);
  const response = await f.handlers.POST(post({ provider: "daytona" }), context);
  assert.equal(response.status, 503);
  assert.match((await response.json()).error, /review app owner/);
  assert.deepEqual(f.events, []);
  assert.deepEqual(f.scheduled, []);
});

test("same-origin boundary precedes provider authorization", async () => {
  const f = fixture();
  assert.equal((await f.handlers.POST(post({ provider: "daytona" }, "https://other.example.test"), context)).status, 403);
  assert.equal(f.getOptionsReads(), 0);
  assert.deepEqual(f.events, []);
});

test("rejects unknown providers and unsupported worlds before allocation", async () => {
  const f = fixture();
  for (const body of [{ provider: "unknown" }, { provider: "daytona", world: "desktop" }, { provider: "daytona", world: "unknown" }]) {
    assert.equal((await f.handlers.POST(post(body), context)).status, 400);
  }
  assert.deepEqual(f.events, []);
});

test("configured credentials cannot authorize a provider without preview capabilities", async () => {
  const f = fixture();
  f.disablePreviews();
  assert.equal((await f.handlers.POST(post({ provider: "daytona" }), context)).status, 503);
  assert.deepEqual(f.events, []);
  assert.deepEqual(f.scheduled, []);
});

test("202 build is pinned to its provider and rechecks configuration when scheduled", async () => {
  const f = fixture();
  f.setSnapshot(false);
  const response = await f.handlers.POST(post({ provider: "daytona" }), context);
  assert.equal(response.status, 202);
  assert.equal(f.scheduled.length, 1);
  f.setChoices([option("freestyle"), option("daytona", false)]);
  await f.scheduled[0]();
  assert.ok(!f.events.some((event) => event.operation === "build"));
  const fresh = fixture(); fresh.setSnapshot(false);
  await fresh.handlers.POST(post({ provider: "daytona" }), context);
  await fresh.scheduled[0]();
  assert.deepEqual(fresh.events.at(-1), { provider: "daytona", operation: "build", source: sha });
});

test("polling dispatches the chosen provider and stops when it is no longer configured", async () => {
  const f = fixture(); f.setSnapshot(false);
  const get = () => new Request("https://review.example.test/r/owned-report/launch?world=app-web&provider=daytona");
  assert.equal((await f.handlers.GET(get(), context)).status, 200);
  assert.ok(f.events.every((event) => event.provider === "daytona"));
  f.events.length = 0;
  f.setChoices([option("daytona", false)]);
  assert.equal((await f.handlers.GET(get(), context)).status, 503);
  assert.deepEqual(f.events, []);
});

test("production configuration drives launch, preparation and polling without a Cloud request", async () => {
  const previous = { ...process.env };
  const previousFetch = globalThis.fetch;
  let cloudRequests = 0;
  try {
    process.env.DAYTONA_API_KEY = "synthetic-review-infrastructure-key";
    process.env.FREESTYLE_API_KEY = "";
    process.env.IDEXAL_EVIDENCE_PROVIDER = "daytona";
    process.env.DEN_API_BASE = "https://unavailable-authority.invalid";
    process.env.DEN_DEPLOYMENT = "self_hosted";
    globalThis.fetch = async () => { cloudRequests++; throw new Error("No Cloud authority is available"); };
    const f = fixture(previewProviderOptions);
    f.setSnapshot(false);
    assert.equal((await f.handlers.POST(post({ provider: "daytona" }), context)).status, 202);
    const poll = () => f.handlers.GET(new Request("https://review.example.test/r/owned-report/launch?world=app-web&provider=daytona"), context);
    assert.equal((await poll()).status, 200);
    await f.scheduled[0]();
    assert.deepEqual(f.events.at(-1), { provider: "daytona", operation: "build", source: sha });
    f.setSnapshot(true);
    assert.equal((await f.handlers.POST(post({ provider: "daytona" }), context)).status, 201);
    assert.deepEqual(f.events.at(-1), { provider: "daytona", operation: "launch", source: sha, report: "owned-report" });
    delete process.env.DAYTONA_API_KEY;
    const before = f.events.length;
    assert.equal((await poll()).status, 503);
    assert.equal((await f.handlers.POST(post({ provider: "daytona" }), context)).status, 503);
    assert.equal(f.events.length, before);
    assert.equal(cloudRequests, 0);
  } finally {
    globalThis.fetch = previousFetch;
    for (const key of ["DAYTONA_API_KEY", "FREESTYLE_API_KEY", "IDEXAL_EVIDENCE_PROVIDER", "DEN_API_BASE", "DEN_DEPLOYMENT"]) {
      if (previous[key] === undefined) delete process.env[key]; else process.env[key] = previous[key];
    }
  }
});

test("provider failures are actionable and never return raw provider errors", async () => {
  const f = fixture(); f.setFailure();
  const response = await f.handlers.POST(post({ provider: "daytona" }), context);
  assert.equal(response.status, 502);
  assert.ok(!(await response.text()).includes("do-not-expose-provider-token"));
});
