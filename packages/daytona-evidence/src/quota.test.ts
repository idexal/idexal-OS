import assert from "node:assert/strict";
import { test } from "node:test";
import { DaytonaApi, DaytonaApiError, type Sandbox, type Snapshot } from "./api.ts";
import { cleanupDaytonaEvidence } from "./cleanup.ts";
import { retrySnapshotQuota } from "./quota-retry.ts";
import { digest } from "@idexal/freestyle/cache";

const config = { apiKey: "mock", apiUrl: "https://mock.test/api", region: "mock" };
const quiet = () => {};

test("required save retries quota rejection, with bounded jitter, then succeeds", async () => {
  let calls = 0;
  const waits: number[] = [];
  const messages: string[] = [];
  const api = new DaytonaApi(config, async () => {
    calls++;
    return calls < 3 ? Response.json({ message: "Snapshot quota exceeded" }, { status: 400 }) : Response.json({});
  });
  await api.snapshotSandbox("builder", "required-world", true, { sleep: async (ms) => { waits.push(ms); }, random: () => 0.5, diagnostic: (message) => { messages.push(message); } });
  assert.equal(calls, 3);
  assert.deepEqual(waits, [67500, 67500]);
  assert.equal(messages.length, 2);
});

test("base image uses the same retry path; optional checkpoint reports original failure", async () => {
  let calls = 0;
  const api = new DaytonaApi(config, async () => {
    calls++;
    return calls === 1 ? Response.json({ message: "Snapshot quota exceeded" }, { status: 403 }) : Response.json({});
  });
  await api.createImageSnapshot({ name: "base", image: "ubuntu", cpu: 4, memory: 8, disk: 10 }, { sleep: async () => {}, diagnostic: quiet });
  assert.equal(calls, 2);
  const optional = new DaytonaApi(config, async () => Response.json({ message: "Snapshot quota exceeded" }, { status: 400 }));
  await assert.rejects(optional.snapshotSandbox("fork", "ow-dy-checkpoint-v1-fixture", true, { sleep: async () => { assert.fail("optional save must not wait"); } }), DaytonaApiError);
});

test("non-quota client errors are not retried", async () => {
  for (const message of ["Memory quota exceeded", "permission denied", "snapshot name already exists"]) {
    let calls = 0;
    const api = new DaytonaApi(config, async () => { calls++; return Response.json({ message }, { status: 400 }); });
    await assert.rejects(api.snapshotSandbox("builder", "required", true), DaytonaApiError);
    assert.equal(calls, 1);
  }
});

test("ambiguous failures never trigger quota replay", async () => {
  const original = new Error("connection reset");
  let calls = 0;
  await assert.rejects(retrySnapshotQuota(async () => { calls++; throw original; }, () => false), (error) => error === original);
  assert.equal(calls, 1);
});

test("exhaustion respects attempts and elapsed provider time, preserving the cause", async () => {
  const original = new Error("quota");
  let calls = 0;
  await assert.rejects(retrySnapshotQuota(async () => { calls++; throw original; }, () => true,
    { maxAttempts: 3, sleep: async () => {}, diagnostic: quiet }), (error: Error) => error.cause === original && /3 attempts/.test(error.message));
  assert.equal(calls, 3);
  let now = 0;
  calls = 0;
  await assert.rejects(retrySnapshotQuota(async () => { calls++; now += 1000; throw original; }, () => true,
    { now: () => now, timeoutMs: 500, sleep: async () => { assert.fail("no remaining budget"); } }), /1 attempts/);
  assert.equal(calls, 1);
  now = 0;
  await assert.rejects(retrySnapshotQuota(async () => { throw original; }, () => true,
    { now: () => now, timeoutMs: 500, sleep: async (ms) => { now += ms; }, diagnostic: quiet }), /deadline reached/);
});

test("both inventories follow advertised pages even when first page is short", async () => {
  for (const path of ["sandbox", "snapshots"]) {
    const urls: string[] = [];
    const api = new DaytonaApi(config, async (input) => {
      const url = String(input); urls.push(url);
      const page = new URL(url).searchParams.get("page");
      return Response.json({ items: [{ id: page, name: `item-${page}`, state: "active", snapshot: "source" }], totalPages: 2 });
    });
    const listed = path === "sandbox" ? await api.listSandboxes() : await api.listSnapshots();
    assert.equal(listed.length, 2);
    assert.equal(urls.length, 2);
  }
});

test("invalid, repeated and truncated pagination fail closed", async () => {
  for (const body of [{}, { items: [], totalPages: 51 }, { items: [], totalPages: 2 }, { items: [], totalPages: 1, total: 10 }, { items: [], page: 2 }, { items: [{ id: "same", name: "same", state: "active" }], totalPages: 2 }]) {
    const api = new DaytonaApi(config, async () => Response.json(body));
    await assert.rejects(api.listSnapshots(), /refusing partial inventory/);
    await assert.rejects(api.listSandboxes(), /refusing partial inventory/);
  }
});

class Inventory extends DaytonaApi {
  snapshots: Snapshot[] = [];
  sandboxes: Sandbox[] = [];
  deleted: string[] = [];
  constructor() { super(config, async () => { throw new Error("mock must not call provider"); }); }
  override async listSnapshots() { return this.snapshots; }
  override async listSandboxes() { return this.sandboxes; }
  override async getSnapshot(name: string) { return this.snapshots.find((s) => s.name === name) ?? null; }
  override async getSandbox(id: string) { return this.sandboxes.find((s) => s.id === id) ?? null; }
  override async deleteSnapshot(id: string) { this.deleted.push(id); }
  override async deleteSandbox(id: string) { this.deleted.push(id); }
}
test("cleanup aborts before deletion when account inventory is unavailable", async () => {
  class Unavailable extends Inventory {
    override async listSandboxes(): Promise<Sandbox[]> { throw new Error("inventory unavailable"); }
  }
  const api = new Unavailable();
  api.snapshots = [{ id: "expired", name: "ow-dy-checkpoint-v1-expired", state: "active", createdAt: "2026-01-01T00:00:00Z" }];
  await assert.rejects(cleanupDaytonaEvidence(api), /inventory unavailable/);
  assert.deepEqual(api.deleted, []);
});

const old = "2026-01-01T00:00:00Z";
const now = Date.parse("2026-10-13T12:00:00Z");

test("cleanup protects pending, needed, referenced and unrelated snapshots; active sandboxes survive", async () => {
  const api = new Inventory();
  api.snapshots = ["old", "needed", "referenced", "pending", "newest"].map((id) => ({ id, name: `ow-dy-evidence-web-v1-${id}`, state: id === "pending" ? "snapshotting" : "active", createdAt: id === "newest" ? "2026-10-13T11:00:00Z" : old }));
  api.snapshots.push({ id: "dev", name: "dev-release-template", state: "active", createdAt: old });
  api.sandboxes = [{ id: "live", name: "live", state: "started", snapshot: "referenced", labels: { kind: "idexal-os-daytona-evidence-v1" }, createdAt: old }];
  await cleanupDaytonaEvidence(api, { now, protectedSnapshots: ["ow-dy-evidence-web-v1-needed"] });
  assert.deepEqual(api.deleted, ["old"]);
});

test("builders and capture forks block reclamation even when old or stopped for a cold save", async () => {
  for (const kind of ["idexal-os-daytona-builder-v1", "idexal-os-daytona-checkpoint-capture-v1"]) for (const state of ["started", "stopped", "snapshotting"]) {
    const api = new Inventory();
    api.snapshots = [{ id: "expired", name: "ow-dy-checkpoint-v1-expired", state: "active", createdAt: old }];
    api.sandboxes = [{ id: "active", name: "active", state, labels: { kind }, createdAt: old }];
    await cleanupDaytonaEvidence(api, { now });
    assert.deepEqual(api.deleted, []);
  }
});

test("a labelled inflight builder protects source and target without blocking unrelated quota reclamation", async () => {
  const api = new Inventory();
  const target = "ow-dy-evidence-web-v1-target";
  api.snapshots = ["source", "target", "expired"].map((id) => ({ id, name: `ow-dy-evidence-web-v1-${id}`, state: "active", createdAt: old }));
  api.sandboxes = [{ id: "builder", name: "builder", state: "stopped", snapshot: "source", labels: { kind: "idexal-os-daytona-builder-v1", layer: digest(target).slice(0, 24) }, createdAt: old }];
  await cleanupDaytonaEvidence(api, { now });
  assert.deepEqual(api.deleted, ["expired"]);
});

test("cleanup rechecks newly used snapshots and newly active sandboxes before deleting", async () => {
  class Changed extends Inventory {
    override async getSnapshot(name: string) { const snapshot = await super.getSnapshot(name); return snapshot ? { ...snapshot, lastUsedAt: new Date(now).toISOString() } : null; }
    override async getSandbox(id: string) { const sandbox = await super.getSandbox(id); return sandbox ? { ...sandbox, state: "started" } : null; }
  }
  const api = new Changed();
  api.snapshots = [{ id: "expired", name: "ow-dy-checkpoint-v1-expired", state: "active", createdAt: old }];
  api.sandboxes = [{ id: "stopped", name: "stopped", state: "stopped", labels: { kind: "idexal-os-daytona-evidence-v1" }, createdAt: old }];
  await cleanupDaytonaEvidence(api, { now });
  assert.deepEqual(api.deleted, []);
});
