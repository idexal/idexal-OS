import assert from "node:assert/strict";
import { test } from "node:test";
import { DaytonaApi } from "./api.ts";
import { DaytonaPreviewLaunchError, buildDaytonaPreview, daytonaFreshPreviewMethods, launchDaytonaPreview, previewDescriptor } from "./previews.ts";

const sha = "a".repeat(40);
const reportId = "b".repeat(32);

test("unsupported worlds, malformed SHA/report and pending snapshots never allocate a preview", async () => {
  const calls: string[] = [];
  const api = new DaytonaApi({ apiKey: "host-key", apiUrl: "https://provider.test/api", region: "r" }, async (input, init) => {
    calls.push(init?.method ?? "GET");
    assert.equal(init?.method, "GET");
    return Response.json({ id: "pending", name: "pending", state: "snapshotting" });
  });
  await assert.rejects(launchDaytonaPreview({ gitSha: sha, reportId, world: "desktop" }, api), /web only/);
  await assert.rejects(launchDaytonaPreview({ gitSha: "dev", reportId, world: "app-web" }, api), /full pushed/);
  await assert.rejects(launchDaytonaPreview({ gitSha: sha, reportId: "invalid", world: "app-web" }, api), /report ID/);
  assert.deepEqual(calls, []);
  await assert.rejects(launchDaytonaPreview({ gitSha: sha, reportId, world: "app-web" }, api), /no ready/);
  assert.deepEqual(calls, ["GET"]);
});

test("unfetchable source never allocates a builder", async () => {
  const api = new DaytonaApi({ apiKey: "host-key", apiUrl: "https://provider.test/api", region: "r" }, async (_input, init) => {
    assert.equal(init?.method, "GET");
    return Response.json({}, { status: 404 });
  });
  await assert.rejects(buildDaytonaPreview(sha, "app-web", api, { sourceFetch: async () => new Response("missing", { status: 404 }) }), /HTTP 404/);
});

test("changed ownership during restore is never inspected, assigned access or deleted", async () => {
  const descriptor = await previewDescriptor(sha, "app-web");
  const createdId = "12345678-1234-1234-1234-123456789abc";
  const writes: string[] = [];
  const api = new DaytonaApi({ apiKey: "host-key", apiUrl: "https://provider.test/api", region: "r" }, async (input, init) => {
    const url = new URL(String(input));
    if (url.pathname.includes("/snapshots/")) return Response.json({ id: "snapshot", name: descriptor.name, state: "active" });
    if (url.pathname === "/api/sandbox" && init?.method === "POST") {
      assert.equal(typeof init.body, "string");
      if (typeof init.body !== "string") throw new Error("Missing create payload");
      const body = JSON.parse(init.body);
      return Response.json({ id: createdId, public: false, state: "starting", labels: body.labels });
    }
    if (url.pathname === `/api/sandbox/${createdId}` && init?.method === "GET") return Response.json({ id: createdId, state: "started", labels: { kind: "unrelated" } });
    writes.push(`${init?.method} ${url.pathname}`);
    throw new Error("Must not touch an unrelated resource");
  });
  let failure: unknown;
  try { await launchDaytonaPreview({ gitSha: sha, reportId, world: "app-web" }, api); }
  catch (error) { failure = error; }
  assert.ok(failure instanceof Error);
  assert.deepEqual(writes, []);
  assert.deepEqual(daytonaFreshPreviewMethods(() => api).launchFailureDetails(failure), { stage: "restore", vmId: createdId });
  assert.deepEqual(daytonaFreshPreviewMethods(() => api).launchFailureDetails(new Error("private host-key")), {});
  assert.deepEqual(daytonaFreshPreviewMethods(() => api).launchFailureDetails(new DaytonaPreviewLaunchError("restore", "private-host-key")), { stage: "restore" });
});
