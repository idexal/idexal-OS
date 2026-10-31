/** @jsxImportSource react */
import { GlobalRegistrator } from "@happy-dom/global-registrator";
import { afterAll, afterEach, beforeEach, expect, mock, spyOn, test } from "bun:test";
import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";

import type { EngineV2PreviewStatus } from "../src/app/lib/idexal-os-server";

const ownedDom = typeof window === "undefined";
if (ownedDom) GlobalRegistrator.register({ url: "http://localhost/" });
// Base UI chooses its layout-effect shim at import time; portals need the DOM first.
const { createDenClient } = await import("../src/app/lib/den");
const server = await import("../src/app/lib/idexal-os-server");
const { isDesktopRuntime } = await import("../src/app/lib/runtime-env");
const automations = await import("../src/react-app/domains/automations/use-automations");
const connection = await import("../src/react-app/shell/idexal-os-connection");
const { EngineMigrationOverlay, observeEngineMigration, resetEngineMigrationForTest, useEngineMigrationStore, continueEngineMigrationInBackground } = await import("../src/react-app/shell/engine-migration");
const previousAct = Reflect.get(globalThis, "IS_REACT_ACT_ENVIRONMENT");
Reflect.set(globalThis, "IS_REACT_ACT_ENVIRONMENT", true);
let root: Root | null = null;
let queryClient: QueryClient;
let previousBridge: unknown;

const status: EngineV2PreviewStatus = {
  enabled: false, running: false, chatRouting: false, v1HistoryAvailable: true,
  mirroredProviderIds: [], skippedProviderIds: [], catalogModelIds: [],
  migration: { state: "idle", imported: 0, skipped: 0, total: 2 },
};
const orgId = "org_test";

beforeEach(() => {
  previousBridge = window.__IDEXAL_ELECTRON__;
  Reflect.deleteProperty(window, "__IDEXAL_ELECTRON__");
  resetEngineMigrationForTest();
  queryClient = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: Infinity } } });
  // This is the same cache entry populated by Calendar and other org-feature consumers.
  queryClient.setQueryData(["den", "org-features", orgId], { engineV2Upgrade: true });
  spyOn(automations, "useAutomationsDenContext").mockReturnValue({
    client: createDenClient({ baseUrl: "https://den.example.test", token: "test-token" }),
    organizationId: orgId, ready: true, queryRoot: ["den", "automations", orgId],
  });
  spyOn(connection, "resolveIdexalOsConnection").mockResolvedValue({
    normalizedBaseUrl: "http://localhost:8787", resolvedToken: "test-token", resolvedHostToken: "test-host",
    hostInfo: null, source: "stored-settings",
  });
});

afterEach(async () => {
  if (root) await act(async () => root?.unmount());
  root = null;
  queryClient.clear();
  resetEngineMigrationForTest();
  Reflect.set(window, "__IDEXAL_ELECTRON__", previousBridge);
  document.body.innerHTML = "";
  mock.restore();
});

afterAll(async () => {
  Reflect.set(globalThis, "IS_REACT_ACT_ENVIRONMENT", previousAct);
  if (ownedDom) await GlobalRegistrator.unregister();
});

function engineClient() {
  const client = server.createIdexalOsServerClient({ baseUrl: "http://localhost:8787", token: "test-token" });
  spyOn(client, "getEngineV2PreviewStatus").mockResolvedValue(status);
  spyOn(client, "setEngineV2ForcePolicy").mockResolvedValue(status);
  spyOn(client, "migrateOpencodeHistory").mockResolvedValue(status);
  spyOn(client, "getEngineActivity").mockResolvedValue({
    v1: { verdict: "idle", busySessions: 0, waitingRequests: 0 }, v2: null,
  });
  spyOn(client, "switchOpencodeEngine").mockResolvedValue({ ...status, enabled: true, chatRouting: true });
  spyOn(server, "createIdexalOsServerClient").mockReturnValue(client);
  return client;
}

async function renderOverlay() {
  const host = document.createElement("div");
  document.body.append(host);
  root = createRoot(host);
  await act(async () => {
    root?.render(<QueryClientProvider client={queryClient}><EngineMigrationOverlay discoverRunningMigration={false} /></QueryClientProvider>);
  });
}

test("web never offers an engine upgrade from another consumer's enabled feature cache", async () => {
  const client = engineClient();
  expect(isDesktopRuntime()).toBe(false);
  await renderOverlay();
  expect(queryClient.getQueryData(["den", "org-features", orgId])).toEqual({ engineV2Upgrade: true });
  expect(document.querySelector('[data-testid="engine-upgrade-notice"]') === null).toBe(true);
  expect(document.querySelector('[data-testid="engine-migration-consent"]')).toBeNull();
  // A render-only guard is insufficient: the feature hook must not start engine reads on web either.
  expect(connection.resolveIdexalOsConnection).not.toHaveBeenCalled();
  expect(client.getEngineV2PreviewStatus).not.toHaveBeenCalled();
  expect(client.setEngineV2ForcePolicy).not.toHaveBeenCalled();
});

test("desktop still offers the cached upgrade and opens its consent", async () => {
  Reflect.set(window, "__IDEXAL_ELECTRON__", {});
  engineClient();
  await renderOverlay();
  const notice = document.querySelector('[data-testid="engine-upgrade-notice"]');
  expect(notice).not.toBeNull();
  const upgrade = notice?.querySelectorAll("button")[1];
  if (!(upgrade instanceof HTMLButtonElement)) throw new Error("Missing upgrade action");
  await act(async () => upgrade.click());
  expect(document.querySelector('[data-testid="engine-migration-consent"]')?.getAttribute("data-mode")).toBe("upgrade");
});

test("web hides consent and engine-switch prompts already in the shared store", async () => {
  const client = engineClient();
  useEngineMigrationStore.setState({
    consent: { client, mode: "upgrade", runningTasks: 0, submitting: false, error: null },
    switchPrompt: { engine: "v2", from: "v1", runningTasks: 1, resolve: () => {} },
  });
  await renderOverlay();
  expect(document.querySelector('[data-testid="engine-migration-consent"]')).toBeNull();
  expect(document.querySelector('[data-testid="engine-switch-prompt"]')).toBeNull();
});

for (const view of ["dialog", "banner"] satisfies Array<"dialog" | "banner">) {
  test(`web hides stored migration ${view} and does not poll it`, async () => {
    const client = engineClient();
    useEngineMigrationStore.setState({ progress: {
      client, migration: { state: "running", phase: "copying", imported: 1, skipped: 0, total: 2 },
      selected: "v1", view, retrying: false, runningTasks: 0, switchWhenDone: true,
    } });
    await renderOverlay();
    expect(document.querySelector('[data-testid="engine-migration-progress"]')).toBeNull();
    expect(document.querySelector('[data-testid="engine-migration-banner"]')).toBeNull();
    await act(async () => { await new Promise((resolve) => setTimeout(resolve, 1_100)); });
    expect(client.getEngineV2PreviewStatus).not.toHaveBeenCalled();
  });
}

test("web does not finish an upgrade from stored completed progress", async () => {
  const client = engineClient();
  useEngineMigrationStore.setState({ progress: {
    client, migration: { state: "completed", imported: 2, skipped: 0, total: 2 },
    selected: "v1", view: "banner", retrying: false, runningTasks: 0, switchWhenDone: true,
  } });
  await renderOverlay();
  expect(client.switchOpencodeEngine).not.toHaveBeenCalled();
  expect(client.getEngineActivity).not.toHaveBeenCalled();
});

async function setFeatures(upgrade: boolean, offer = true) {
  await act(async () => {
    queryClient.setQueryData(["den", "org-features", orgId], {
      engineV2Upgrade: offer, engineV2ForceUpgrade: upgrade,
    });
    // TanStack observer notifications are scheduled, not synchronous with cache writes.
    await new Promise((resolve) => setTimeout(resolve, 0));
  });
}

test("desktop forwards the off force flag without starting a migration or invitation", async () => {
  Reflect.set(window, "__IDEXAL_ELECTRON__", {});
  const client = engineClient();
  await setFeatures(false, false);
  await renderOverlay();
  expect(client.setEngineV2ForcePolicy).toHaveBeenCalledWith({ upgrade: false });
  expect(client.migrateOpencodeHistory).not.toHaveBeenCalled();
  expect(client.switchOpencodeEngine).not.toHaveBeenCalled();
  expect(document.querySelector('[data-testid="engine-upgrade-notice"]')).toBeNull();
});

for (const upgrade of [false, true]) {
  test(`web ignores the cached force flag (upgrade=${upgrade})`, async () => {
    const client = engineClient();
    await setFeatures(upgrade);
    await renderOverlay();
    expect(connection.resolveIdexalOsConnection).not.toHaveBeenCalled();
    expect(client.setEngineV2ForcePolicy).not.toHaveBeenCalled();
    expect(document.body.textContent).toBe("");
  });
}

test("pending force upgrade shows only a banner before ownership moves, without renderer migration or switching", async () => {
  Reflect.set(window, "__IDEXAL_ELECTRON__", {});
  const client = engineClient();
  const forced: EngineV2PreviewStatus = {
    ...status, engineOrigin: "manual", engineForced: false, forceUpgradePending: true,
    migration: { state: "running", phase: "copying", imported: 1, skipped: 0, total: 2 },
  };
  spyOn(client, "setEngineV2ForcePolicy").mockResolvedValue(forced);
  spyOn(client, "getEngineV2PreviewStatus").mockResolvedValue(forced);
  await setFeatures(true);
  await renderOverlay();
  expect(client.setEngineV2ForcePolicy).toHaveBeenCalledWith({ upgrade: true });
  expect(document.querySelector('[data-testid="engine-migration-banner"]')).not.toBeNull();
  expect(document.querySelector('[data-testid="engine-migration-consent"]')).toBeNull();
  expect(document.querySelector('[data-testid="engine-migration-progress"]')).toBeNull();
  expect(document.querySelector('[data-testid="engine-upgrade-notice"]')).toBeNull();
  expect(useEngineMigrationStore.getState().progress?.serverManaged).toBe(true);
  expect(client.migrateOpencodeHistory).not.toHaveBeenCalled();
  expect(client.switchOpencodeEngine).not.toHaveBeenCalled();
});

for (const origin of ["forced", "manual"] satisfies Array<"forced" | "manual">) {
  test(`off policy ${origin === "forced" ? "observes the host's forced-only revert" : "leaves manual engine selection alone"}`, async () => {
    Reflect.set(window, "__IDEXAL_ELECTRON__", {});
    const client = engineClient();
    spyOn(client, "setEngineV2ForcePolicy").mockResolvedValue({
      ...status, enabled: true, running: true, chatRouting: true,
      engineOrigin: origin, engineForced: origin === "forced",
    });
    spyOn(client, "getEngineV2PreviewStatus").mockResolvedValue({
      ...status, engineOrigin: "reverted", engineForced: false,
    });
    const changed = mock(() => {});
    window.addEventListener("idexal-os-engine-changed", changed);
    try {
      await setFeatures(false, false);
      await renderOverlay();
      expect(client.setEngineV2ForcePolicy).toHaveBeenCalledWith({ upgrade: false });
      await act(async () => { await new Promise((resolve) => setTimeout(resolve, 1_100)); });
      expect(client.getEngineV2PreviewStatus).toHaveBeenCalledTimes(origin === "forced" ? 1 : 0);
      expect(changed).toHaveBeenCalledTimes(origin === "forced" ? 2 : 0);
      expect(client.switchOpencodeEngine).not.toHaveBeenCalled();
      expect(client.migrateOpencodeHistory).not.toHaveBeenCalled();
      expect(document.body.textContent).toBe("");
    } finally {
      window.removeEventListener("idexal-os-engine-changed", changed);
    }
  });
}

test("force turning off is forwarded for host-owned revert and suppresses optional offers while on", async () => {
  Reflect.set(window, "__IDEXAL_ELECTRON__", {});
  const client = engineClient();
  await setFeatures(true, true);
  await renderOverlay();
  expect(client.setEngineV2ForcePolicy).toHaveBeenLastCalledWith({ upgrade: true });
  expect(document.querySelector('[data-testid="engine-upgrade-notice"]')).toBeNull();
  await setFeatures(false, false);
  expect(client.setEngineV2ForcePolicy).toHaveBeenLastCalledWith({ upgrade: false });
  expect(client.switchOpencodeEngine).not.toHaveBeenCalled();
  expect(client.migrateOpencodeHistory).not.toHaveBeenCalled();
});

for (const state of ["completed", "error"] satisfies Array<"completed" | "error">) {
  test(`forced ${state} never opens recovery consent or switches from the renderer`, async () => {
    Reflect.set(window, "__IDEXAL_ELECTRON__", {});
    const client = engineClient();
    await setFeatures(true);
    useEngineMigrationStore.setState({ progress: {
      client, migration: { state: "running", imported: 1, skipped: 0, total: 2 },
      selected: "v1", view: "dialog", retrying: false, runningTasks: 0, switchWhenDone: true,
    } });
    observeEngineMigration(client, {
      ...status, engineOrigin: "manual", engineForced: false, forceUpgradePending: true,
      migration: { state, imported: state === "completed" ? 2 : 1, skipped: 0, total: 2 },
    });
    expect(useEngineMigrationStore.getState().progress).toMatchObject({
      serverManaged: true, switchWhenDone: false, view: "banner",
    });
    await renderOverlay();
    expect(document.querySelector('[data-testid="engine-migration-progress"]')).toBeNull();
    expect(document.querySelector('[data-testid="engine-migration-consent"]')).toBeNull();
    expect(client.switchOpencodeEngine).not.toHaveBeenCalled();
    expect(client.migrateOpencodeHistory).not.toHaveBeenCalled();
    expect(useEngineMigrationStore.getState().progress).toBeNull();
  });
}

test("a fresh forced v2 status refreshes routing without any migration UI or renderer switch", async () => {
  Reflect.set(window, "__IDEXAL_ELECTRON__", {});
  const client = engineClient();
  spyOn(client, "setEngineV2ForcePolicy").mockResolvedValue({
    ...status, enabled: true, running: true, chatRouting: true,
    v1HistoryAvailable: false, engineOrigin: "forced", engineForced: true,
  });
  const changed = mock(() => {});
  window.addEventListener("idexal-os-engine-changed", changed);
  try {
    await setFeatures(true);
    await renderOverlay();
    expect(changed).toHaveBeenCalledTimes(1);
    expect(document.body.textContent).toBe("");
    expect(client.migrateOpencodeHistory).not.toHaveBeenCalled();
    expect(client.switchOpencodeEngine).not.toHaveBeenCalled();
  } finally {
    window.removeEventListener("idexal-os-engine-changed", changed);
  }
});

test("signed-out desktop cannot activate cached force flags", async () => {
  Reflect.set(window, "__IDEXAL_ELECTRON__", {});
  const client = engineClient();
  spyOn(automations, "useAutomationsDenContext").mockReturnValue({
    client: null, organizationId: orgId, ready: false, queryRoot: ["den", "automations", orgId],
  });
  await setFeatures(true, true);
  await renderOverlay();
  expect(client.setEngineV2ForcePolicy).not.toHaveBeenCalled();
  expect(document.querySelector('[data-testid="engine-upgrade-notice"]')).toBeNull();
  expect(client.migrateOpencodeHistory).not.toHaveBeenCalled();
});

test("a failed force request never falls back to a renderer migration, switch, or consent", async () => {
  Reflect.set(window, "__IDEXAL_ELECTRON__", {});
  const client = engineClient();
  spyOn(client, "setEngineV2ForcePolicy").mockRejectedValue(new Error("Host unavailable"));
  await setFeatures(true);
  await renderOverlay();
  expect(client.setEngineV2ForcePolicy).toHaveBeenCalledTimes(1);
  expect(client.migrateOpencodeHistory).not.toHaveBeenCalled();
  expect(client.switchOpencodeEngine).not.toHaveBeenCalled();
  expect(document.body.textContent).toBe("");
});

test("a newer off policy waits for an in-flight upgrade policy and discards its stale progress", async () => {
  Reflect.set(window, "__IDEXAL_ELECTRON__", {});
  const client = engineClient();
  const pending = Promise.withResolvers<EngineV2PreviewStatus>();
  const policy = spyOn(client, "setEngineV2ForcePolicy")
    .mockImplementationOnce(() => pending.promise)
    .mockResolvedValue(status);
  await setFeatures(true);
  await renderOverlay();
  expect(policy).toHaveBeenCalledTimes(1);
  await setFeatures(false, false);
  expect(policy).toHaveBeenCalledTimes(1);
  await act(async () => pending.resolve({
    ...status, engineOrigin: "forced", engineForced: true,
    migration: { state: "running", imported: 1, skipped: 0, total: 2 },
  }));
  expect(policy).toHaveBeenLastCalledWith({ upgrade: false });
  expect(policy).toHaveBeenCalledTimes(2);
  expect(document.querySelector('[data-testid="engine-migration-banner"]')).toBeNull();
});

test("enabling force removes an optional upgrade consent without confirming it", async () => {
  Reflect.set(window, "__IDEXAL_ELECTRON__", {});
  const client = engineClient();
  useEngineMigrationStore.setState({ consent: {
    client, mode: "upgrade", runningTasks: 0, submitting: false, error: null,
  } });
  await setFeatures(true);
  await renderOverlay();
  expect(useEngineMigrationStore.getState().consent).toBeNull();
  expect(document.querySelector('[data-testid="engine-migration-consent"]')).toBeNull();
  expect(client.migrateOpencodeHistory).not.toHaveBeenCalled();
});

test("force API sends host authentication and parses ownership fields without inferring missing markers", async () => {
  const fetch = spyOn(globalThis, "fetch").mockResolvedValue(new Response(JSON.stringify({
    ...status, engineOrigin: "manual", engineForced: false, forceUpgradePending: true,
  }), { status: 200, headers: { "Content-Type": "application/json" } }));
  const client = server.createIdexalOsServerClient({ baseUrl: "http://localhost:8787", token: "test-token", hostToken: "test-host" });
  const result = await client.setEngineV2ForcePolicy({ upgrade: true });
  expect(result.engineOrigin).toBe("manual");
  expect(result.engineForced).toBe(false);
  expect(result.forceUpgradePending).toBe(true);
  const [url, options] = fetch.mock.calls[0] ?? [];
  expect(url).toBe("http://localhost:8787/experimental/engine-v2-preview/force");
  expect(options?.method).toBe("POST");
  expect(new Headers(options?.headers).get("X-Idexal-Os-Host-Token")).toBe("test-host");
  expect(options?.body).toBe(JSON.stringify({ upgrade: true }));
  fetch.mockResolvedValue(new Response(JSON.stringify(status), { status: 200 }));
  const legacy = await client.getEngineV2PreviewStatus();
  expect(legacy.engineOrigin).toBeUndefined();
  expect(legacy.engineForced).toBeUndefined();
  expect(legacy.forceUpgradePending).toBeUndefined();
});

/**
 * A copy keeps running on the computer after the app closes, and a fresh
 * renderer has no memory of the choice — so the same still-running copy was
 * presented as the blocking dialog again at every launch. That is the window
 * that "keeps appearing", and it is what this pins down.
 */
test("a running migration sent to the background stays in the background after a relaunch", () => {
  resetEngineMigrationForTest();
  const client = engineClient();
  const running = {
    ...status,
    engineOrigin: "manual",
    engineForced: false,
    forceUpgradePending: false,
    migration: { state: "running" as const, phase: "copying", imported: 1, skipped: 0, total: 2 },
  };

  observeEngineMigration(client, running);
  expect(useEngineMigrationStore.getState().progress?.view).toBe("dialog");

  continueEngineMigrationInBackground();
  expect(useEngineMigrationStore.getState().progress?.view).toBe("banner");

  // What a relaunch looks like: the renderer forgot everything, the copy did not stop.
  useEngineMigrationStore.setState({ progress: null });
  observeEngineMigration(client, running);
  expect(
    useEngineMigrationStore.getState().progress?.view,
    "the relaunch must not put the blocking dialog back for a copy already in the background",
  ).toBe("banner");

  // Once the copy is no longer running the marker is cleared, so a later
  // migration of the same shape asks again.
  observeEngineMigration(client, {
    ...running,
    migration: { state: "completed" as const, imported: 2, skipped: 0, total: 2 },
  });
  useEngineMigrationStore.setState({ progress: null });
  observeEngineMigration(client, running);
  expect(useEngineMigrationStore.getState().progress?.view).toBe("dialog");
});
