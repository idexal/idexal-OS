import { afterAll, beforeAll, expect, mock, test } from "bun:test";
import { GlobalRegistrator } from "@happy-dom/global-registrator";

const liveInfo = {
  running: true,
  generation: 1,
  host: "127.0.0.1",
  port: 59897,
  baseUrl: "http://127.0.0.1:59897",
  clientToken: "client-token",
  ownerToken: "owner-token",
  hostToken: "host-token",
};
let infoCalls = 0;
const fetchCalls: string[] = [];

mock.module("../src/app/lib/desktop", () => ({
  idexalOsServerInfo: async () => {
    infoCalls += 1;
    if (infoCalls === 1) throw new Error("runtime is not ready yet");
    return liveInfo;
  },
  idexalOsServerRestart: async () => liveInfo,
  desktopFetch: (input: RequestInfo | URL, init?: RequestInit) => globalThis.fetch(input, init),
  desktopFetchViaMain: (input: RequestInfo | URL, init?: RequestInit) => globalThis.fetch(input, init),
  desktopFetchAgentContextDiagnostics: (input: RequestInfo | URL, init?: RequestInit) => globalThis.fetch(input, init),
}));

beforeAll(() => {
  GlobalRegistrator.register({ url: "http://localhost/" });
  Reflect.set(window, "__IDEXAL_ELECTRON__", {});

  const realSetTimeout = globalThis.setTimeout.bind(globalThis);
  const realSetInterval = globalThis.setInterval.bind(globalThis);
  Object.defineProperty(window, "setTimeout", {
    configurable: true,
    value: (fn: TimerHandler, delay = 0, ...args: unknown[]) => {
      const requested = Number(delay) || 0;
      const compressed = requested >= 5_000 ? 200 : requested >= 200 ? 20 : 0;
      return realSetTimeout(fn as (...values: unknown[]) => void, compressed, ...args);
    },
  });
  Object.defineProperty(window, "setInterval", {
    configurable: true,
    value: (fn: TimerHandler, delay = 0, ...args: unknown[]) =>
      realSetInterval(
        fn as (...values: unknown[]) => void,
        (Number(delay) || 0) >= 5_000 ? 300 : 40,
        ...args,
      ),
  });

  globalThis.fetch = (async (input: RequestInfo | URL) => {
    const url = typeof input === "string" ? input : input instanceof URL ? input.toString() : input.url;
    fetchCalls.push(url);
    const body = url.endsWith("/health")
      ? { ok: true, version: "2.1.0" }
      : {
          schemaVersion: 1,
          providerSync: true,
          skills: { read: true, write: true },
          plugins: { read: true, write: true },
          mcp: { read: true, write: true },
        };
    return new Response(JSON.stringify(body), {
      status: 200,
      headers: { "content-type": "application/json" },
    });
  }) as typeof fetch;
});

afterAll(() => {
  GlobalRegistrator.unregister();
});

test("the server store reconnects when host info arrives after the first probe", async () => {
  const { createIdexalOsServerStore } = await import(
    "../src/react-app/domains/connections/idexal-os-server-store"
  );
  const store = createIdexalOsServerStore({
    startupPreference: () => "local",
    documentVisible: () => true,
    developerMode: () => false,
    runtimeWorkspaceId: () => "ws_test",
  });

  try {
    store.start();
    await Bun.sleep(650);
    expect(store.getSnapshot().idexalOsServerStatus).toBe("connected");
    expect(fetchCalls.some((url) => url.endsWith("/capabilities"))).toBe(true);
  } finally {
    store.dispose();
  }
});
