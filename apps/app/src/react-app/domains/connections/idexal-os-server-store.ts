import { useSyncExternalStore } from "react";

import { t } from "../../../i18n";
import type { StartupPreference } from "../../../app/types";
import { isDesktopRuntime } from "../../../app/utils";
import {
  idexalOsServerInfo,
  idexalOsServerRestart,
  type IdexalOsServerInfo,
} from "../../../app/lib/desktop";
import {
  getIdexalOsGatewayOrigin,
  readIdexalOsGatewayDenToken,
} from "../../../app/lib/gateway-runtime";
import {
  clearIdexalOsServerSettings,
  createIdexalOsServerClient,
  isLoopbackIdexalOsServerUrl,
  normalizeIdexalOsServerUrl,
  readIdexalOsServerSettings,
  writeIdexalOsServerSettings,
  type IdexalOsAuditEntry,
  type IdexalOsServerCapabilities,
  type IdexalOsServerClient,
  type IdexalOsServerDiagnostics,
  type IdexalOsServerError,
  type IdexalOsServerSettings,
  type IdexalOsServerStatus,
} from "../../../app/lib/idexal-os-server";

type SetStateAction<T> = T | ((current: T) => T);

export type IdexalOsServerStoreSnapshot = {
  idexalOsServerSettings: IdexalOsServerSettings;
  idexalOsServerUrl: string;
  idexalOsServerBaseUrl: string;
  idexalOsServerAuth: { token?: string; hostToken?: string };
  idexalOsServerClient: IdexalOsServerClient | null;
  idexalOsServerStatus: IdexalOsServerStatus;
  idexalOsServerCapabilities: IdexalOsServerCapabilities | null;
  idexalOsServerReady: boolean;
  idexalOsServerWorkspaceReady: boolean;
  resolvedIdexalOsCapabilities: IdexalOsServerCapabilities | null;
  idexalOsServerCanWriteSkills: boolean;
  idexalOsServerCanWritePlugins: boolean;
  idexalOsServerHostInfo: IdexalOsServerInfo | null;
  idexalOsServerDiagnostics: IdexalOsServerDiagnostics | null;
  idexalOsReconnectBusy: boolean;
  idexalOsAuditEntries: IdexalOsAuditEntry[];
  idexalOsAuditStatus: "idle" | "loading" | "error";
  idexalOsAuditError: string | null;
  devtoolsWorkspaceId: string | null;
};

export type IdexalOsServerStore = ReturnType<typeof createIdexalOsServerStore>;

type CreateIdexalOsServerStoreOptions = {
  startupPreference: () => StartupPreference | null;
  documentVisible: () => boolean;
  developerMode: () => boolean;
  runtimeWorkspaceId: () => string | null;
};

type MutableState = {
  idexalOsServerSettings: IdexalOsServerSettings;
  idexalOsServerUrl: string;
  idexalOsServerStatus: IdexalOsServerStatus;
  idexalOsServerCapabilities: IdexalOsServerCapabilities | null;
  idexalOsServerCheckedAt: number | null;
  idexalOsServerHostInfo: IdexalOsServerInfo | null;
  idexalOsServerHostInfoReady: boolean;
  idexalOsServerDiagnostics: IdexalOsServerDiagnostics | null;
  idexalOsReconnectBusy: boolean;
  idexalOsAuditEntries: IdexalOsAuditEntry[];
  idexalOsAuditStatus: "idle" | "loading" | "error";
  idexalOsAuditError: string | null;
  devtoolsWorkspaceId: string | null;
};

const applyStateAction = <T,>(current: T, next: SetStateAction<T>) =>
  typeof next === "function" ? (next as (value: T) => T)(current) : next;

function sameIdexalOsServerSnapshot(
  current: IdexalOsServerStoreSnapshot,
  next: IdexalOsServerStoreSnapshot,
): boolean {
  return (
    current.idexalOsServerSettings === next.idexalOsServerSettings &&
    current.idexalOsServerUrl === next.idexalOsServerUrl &&
    current.idexalOsServerBaseUrl === next.idexalOsServerBaseUrl &&
    current.idexalOsServerAuth.token === next.idexalOsServerAuth.token &&
    current.idexalOsServerAuth.hostToken === next.idexalOsServerAuth.hostToken &&
    current.idexalOsServerClient === next.idexalOsServerClient &&
    current.idexalOsServerStatus === next.idexalOsServerStatus &&
    current.idexalOsServerCapabilities === next.idexalOsServerCapabilities &&
    current.idexalOsServerReady === next.idexalOsServerReady &&
    current.idexalOsServerWorkspaceReady === next.idexalOsServerWorkspaceReady &&
    current.resolvedIdexalOsCapabilities === next.resolvedIdexalOsCapabilities &&
    current.idexalOsServerCanWriteSkills === next.idexalOsServerCanWriteSkills &&
    current.idexalOsServerCanWritePlugins === next.idexalOsServerCanWritePlugins &&
    current.idexalOsServerHostInfo === next.idexalOsServerHostInfo &&
    current.idexalOsServerDiagnostics === next.idexalOsServerDiagnostics &&
    current.idexalOsReconnectBusy === next.idexalOsReconnectBusy &&
    current.idexalOsAuditEntries === next.idexalOsAuditEntries &&
    current.idexalOsAuditStatus === next.idexalOsAuditStatus &&
    current.idexalOsAuditError === next.idexalOsAuditError &&
    current.devtoolsWorkspaceId === next.devtoolsWorkspaceId
  );
}

export function createIdexalOsServerStore(options: CreateIdexalOsServerStoreOptions) {
  const bootStartedAt = Date.now();
  const listeners = new Set<() => void>();
  const intervals = new Map<string, number>();

  let clientCacheKey = "";
  let clientCacheValue: IdexalOsServerClient | null = null;
  let started = false;
  let disposed = false;
  let healthTimeoutId: number | null = null;
  let healthBusy = false;
  let healthDelayMs = 10_000;
  let consecutiveHealthFailures = 0;
  let visibilityChangeHandler: (() => void) | null = null;
  let snapshot: IdexalOsServerStoreSnapshot | undefined;

  let state: MutableState = {
    idexalOsServerSettings: readIdexalOsServerSettings(),
    idexalOsServerUrl: "",
    idexalOsServerStatus: "disconnected",
    idexalOsServerCapabilities: null,
    idexalOsServerCheckedAt: null,
    idexalOsServerHostInfo: null,
    idexalOsServerHostInfoReady: !isDesktopRuntime(),
    idexalOsServerDiagnostics: null,
    idexalOsReconnectBusy: false,
    idexalOsAuditEntries: [],
    idexalOsAuditStatus: "idle",
    idexalOsAuditError: null,
    devtoolsWorkspaceId: null,
  };

  const emitChange = () => {
    for (const listener of listeners) listener();
  };

  const getBaseUrl = () => {
    const gatewayOrigin = getIdexalOsGatewayOrigin();
    if (gatewayOrigin) return normalizeIdexalOsServerUrl(gatewayOrigin) ?? "";

    const pref = options.startupPreference();
    const hostInfo = state.idexalOsServerHostInfo;
    const settingsUrl = normalizeIdexalOsServerUrl(state.idexalOsServerSettings.urlOverride ?? "") ?? "";

    if (pref === "local") return hostInfo?.baseUrl ?? "";
    if (pref === "server" && settingsUrl && isLoopbackIdexalOsServerUrl(settingsUrl) && hostInfo?.baseUrl) {
      return hostInfo.baseUrl;
    }
    if (pref === "server") return settingsUrl;
    return hostInfo?.baseUrl ?? settingsUrl;
  };

  const getAuth = () => {
    const gatewayOrigin = getIdexalOsGatewayOrigin();
    if (gatewayOrigin) {
      const token = readIdexalOsGatewayDenToken().trim();
      return { token: token || undefined, hostToken: undefined };
    }

    const pref = options.startupPreference();
    const hostInfo = state.idexalOsServerHostInfo;
    const settingsUrl = normalizeIdexalOsServerUrl(state.idexalOsServerSettings.urlOverride ?? "") ?? "";
    const settingsToken = state.idexalOsServerSettings.token?.trim() ?? "";
    const settingsHostToken = state.idexalOsServerSettings.hostToken?.trim() ?? "";
    const clientToken = hostInfo?.clientToken?.trim() ?? "";
    const hostToken = hostInfo?.hostToken?.trim() ?? "";

    if (pref === "local") {
      return { token: clientToken || undefined, hostToken: hostToken || undefined };
    }
    if (pref === "server" && settingsUrl && isLoopbackIdexalOsServerUrl(settingsUrl) && hostInfo?.baseUrl) {
      return {
        token: clientToken || settingsToken || undefined,
        hostToken: hostToken || settingsHostToken || undefined,
      };
    }
    if (pref === "server") {
      return {
        token: settingsToken || undefined,
        hostToken: settingsUrl && isLoopbackIdexalOsServerUrl(settingsUrl) ? settingsHostToken || undefined : undefined,
      };
    }
    if (hostInfo?.baseUrl) {
      return { token: clientToken || undefined, hostToken: hostToken || undefined };
    }
    return {
      token: settingsToken || undefined,
      hostToken: settingsUrl && isLoopbackIdexalOsServerUrl(settingsUrl) ? settingsHostToken || undefined : undefined,
    };
  };

  const getClient = () => {
    const baseUrl = getBaseUrl().trim();
    if (!baseUrl) {
      clientCacheKey = "";
      clientCacheValue = null;
      return null;
    }

    const auth = getAuth();
    const key = `${baseUrl}::${auth.token ?? ""}::${auth.hostToken ?? ""}`;
    if (key !== clientCacheKey) {
      clientCacheKey = key;
      clientCacheValue = createIdexalOsServerClient({
        baseUrl,
        token: auth.token,
        hostToken: auth.hostToken,
      });
    }
    return clientCacheValue;
  };

  const refreshSnapshot = (): boolean => {
    const idexalOsServerBaseUrl = getBaseUrl().trim();
    const idexalOsServerAuth = getAuth();
    const idexalOsServerClient = getClient();
    const idexalOsServerReady = state.idexalOsServerStatus === "connected";
    const idexalOsServerWorkspaceReady = Boolean(options.runtimeWorkspaceId());
    const resolvedIdexalOsCapabilities = state.idexalOsServerCapabilities;

    const pref = options.startupPreference();
    const info = state.idexalOsServerHostInfo;
    const hostUrl = info?.baseUrl ?? "";
    const settingsUrl = normalizeIdexalOsServerUrl(state.idexalOsServerSettings.urlOverride ?? "") ?? "";

    let idexalOsServerUrl = hostUrl || settingsUrl;
    if (pref === "local") idexalOsServerUrl = hostUrl;
    if (pref === "server") idexalOsServerUrl = settingsUrl;
    state.idexalOsServerUrl = idexalOsServerUrl;

    const nextSnapshot: IdexalOsServerStoreSnapshot = {
      idexalOsServerSettings: state.idexalOsServerSettings,
      idexalOsServerUrl,
      idexalOsServerBaseUrl,
      idexalOsServerAuth,
      idexalOsServerClient,
      idexalOsServerStatus: state.idexalOsServerStatus,
      idexalOsServerCapabilities: state.idexalOsServerCapabilities,
      idexalOsServerReady,
      idexalOsServerWorkspaceReady,
      resolvedIdexalOsCapabilities,
      idexalOsServerCanWriteSkills:
        idexalOsServerReady &&
        (resolvedIdexalOsCapabilities?.skills?.write ?? false),
      idexalOsServerCanWritePlugins:
        idexalOsServerReady &&
        (resolvedIdexalOsCapabilities?.plugins?.write ?? false),
      idexalOsServerHostInfo: state.idexalOsServerHostInfo,
      idexalOsServerDiagnostics: state.idexalOsServerDiagnostics,
      idexalOsReconnectBusy: state.idexalOsReconnectBusy,
      idexalOsAuditEntries: state.idexalOsAuditEntries,
      idexalOsAuditStatus: state.idexalOsAuditStatus,
      idexalOsAuditError: state.idexalOsAuditError,
      devtoolsWorkspaceId: state.devtoolsWorkspaceId,
    };
    if (snapshot && sameIdexalOsServerSnapshot(snapshot, nextSnapshot)) return false;
    snapshot = nextSnapshot;
    return true;
  };

  const mutateState = (updater: (current: MutableState) => MutableState) => {
    state = updater(state);
    if (refreshSnapshot()) emitChange();
  };

  const setStateField = <K extends keyof MutableState>(key: K, value: MutableState[K]) => {
    if (Object.is(state[key], value)) return;
    mutateState((current) => ({ ...current, [key]: value }));
  };

  const setIdexalOsServerSettings = (next: SetStateAction<IdexalOsServerSettings>) => {
    const resolved = applyStateAction(state.idexalOsServerSettings, next);
    mutateState((current) => ({ ...current, idexalOsServerSettings: resolved }));
    queueHealthCheck(0);
  };

  const updateIdexalOsServerSettings = (next: IdexalOsServerSettings) => {
    const stored = writeIdexalOsServerSettings(next);
    mutateState((current) => ({ ...current, idexalOsServerSettings: stored }));
    queueHealthCheck(0);
  };

  const resetIdexalOsServerSettings = () => {
    clearIdexalOsServerSettings();
    mutateState((current) => ({ ...current, idexalOsServerSettings: {} }));
    queueHealthCheck(0);
  };

  const shouldWaitForLocalHostInfo = () =>
    isDesktopRuntime() &&
    options.startupPreference() !== "server" &&
    !state.idexalOsServerHostInfoReady;

  const shouldRetryStartupCheck = (status: IdexalOsServerStatus) =>
    status !== "connected" &&
    isDesktopRuntime() &&
    options.startupPreference() !== "server" &&
    Date.now() - bootStartedAt < 5_000;

  const checkIdexalOsServer = async (url: string, token?: string, hostToken?: string) => {
    const client = createIdexalOsServerClient({ baseUrl: url, token, hostToken });
    try {
      await client.health();
    } catch (error) {
      const resolved = error as IdexalOsServerError | Error;
      if ("status" in resolved && (resolved.status === 401 || resolved.status === 403)) {
        return { status: "limited" as IdexalOsServerStatus, capabilities: null };
      }
      return { status: "disconnected" as IdexalOsServerStatus, capabilities: null };
    }

    if (!token) {
      return { status: "limited" as IdexalOsServerStatus, capabilities: null };
    }

    try {
      const capabilities = await client.capabilities();
      return { status: "connected" as IdexalOsServerStatus, capabilities };
    } catch (error) {
      const resolved = error as IdexalOsServerError | Error;
      if ("status" in resolved && (resolved.status === 401 || resolved.status === 403)) {
        return { status: "limited" as IdexalOsServerStatus, capabilities: null };
      }
      return { status: "disconnected" as IdexalOsServerStatus, capabilities: null };
    }
  };

  const clearHealthTimeout = () => {
    if (healthTimeoutId !== null) {
      window.clearTimeout(healthTimeoutId);
      healthTimeoutId = null;
    }
  };

  const queueHealthCheck = (delayMs: number) => {
    if (disposed || typeof window === "undefined") return;
    clearHealthTimeout();
    healthTimeoutId = window.setTimeout(() => {
      healthTimeoutId = null;
      void runHealthCheck();
    }, Math.max(0, delayMs));
  };

  const runHealthCheck = async () => {
    if (disposed || typeof window === "undefined") return;
    if (!options.documentVisible()) {
      queueHealthCheck(healthDelayMs);
      return;
    }
    if (shouldWaitForLocalHostInfo()) {
      queueHealthCheck(250);
      return;
    }
    if (healthBusy) return;

    const url = getBaseUrl().trim();
    const auth = getAuth();
    if (!url) {
      consecutiveHealthFailures = 0;
      mutateState((current) => ({
        ...current,
        idexalOsServerStatus: "disconnected",
        idexalOsServerCapabilities: null,
        idexalOsServerCheckedAt: Date.now(),
      }));
      // The local runtime can publish hostInfo after the first probe. Keep a
      // slow recheck armed instead of parking the store on "disconnected"
      // until the next full reload.
      queueHealthCheck(healthDelayMs);
      return;
    }

    healthBusy = true;
    try {
      let result = await checkIdexalOsServer(url, auth.token, auth.hostToken);

      if (shouldRetryStartupCheck(result.status)) {
        await new Promise<void>((resolve) => window.setTimeout(resolve, 250));
        if (disposed) return;

        try {
          const info = await idexalOsServerInfo() as IdexalOsServerInfo;
          if (disposed) return;

          mutateState((current) => ({
            ...current,
            idexalOsServerHostInfo: info,
            idexalOsServerHostInfoReady: true,
          }));

          const retryUrl = info.baseUrl?.trim() ?? "";
          const retryToken = info.clientToken?.trim() || undefined;
          const retryHostToken = info.hostToken?.trim() || undefined;
          if (retryUrl) {
            result = await checkIdexalOsServer(retryUrl, retryToken, retryHostToken);
          }
        } catch {
          // Preserve the original check result when the retry probe fails.
        }
      }

      if (disposed) return;
      const previousStatus = state.idexalOsServerStatus;
      const previousCapabilities = state.idexalOsServerCapabilities;
      const healthy = result.status === "connected" || result.status === "limited";
      if (healthy) {
        consecutiveHealthFailures = 0;
        healthDelayMs = 10_000;
      } else {
        consecutiveHealthFailures += 1;
        healthDelayMs = Math.min(healthDelayMs * 2, 60_000);
      }

      const preservePrevious =
        !healthy &&
        consecutiveHealthFailures < 3 &&
        (previousStatus === "connected" || previousStatus === "limited");

      mutateState((current) => ({
        ...current,
        idexalOsServerStatus: preservePrevious ? previousStatus : result.status,
        idexalOsServerCapabilities: preservePrevious ? previousCapabilities : result.capabilities,
        idexalOsServerCheckedAt: Date.now(),
      }));
    } catch {
      healthDelayMs = Math.min(healthDelayMs * 2, 60_000);
      mutateState((current) => ({
        ...current,
        idexalOsServerCheckedAt: Date.now(),
      }));
    } finally {
      healthBusy = false;
      if (!disposed) queueHealthCheck(healthDelayMs);
    }
  };

  const syncFromOptions = () => {
    if (refreshSnapshot()) emitChange();

    if (!isDesktopRuntime()) return;
    const port = state.idexalOsServerHostInfo?.port;
    if (!port) return;
    if (state.idexalOsServerSettings.portOverride === port) return;

    updateIdexalOsServerSettings({
      ...state.idexalOsServerSettings,
      portOverride: port,
    });
  };

  const startInterval = (key: string, fn: () => void, ms: number) => {
    if (typeof window === "undefined") return;
    if (intervals.has(key)) return;
    intervals.set(key, window.setInterval(fn, ms));
  };

  const stopInterval = (key: string) => {
    const id = intervals.get(key);
    if (id === undefined) return;
    window.clearInterval(id);
    intervals.delete(key);
  };

  const start = () => {
    if (typeof window === "undefined") return;
    if (started) return;
    // Allow restart after a prior dispose() (React 18 StrictMode double-mounts
    // each effect in dev: mount → dispose → re-mount). If we early-return when
    // `disposed` is true, the real mount never arms polling and the UI stays
    // on stale/empty state forever.
    disposed = false;
    started = true;

    syncFromOptions();
    queueHealthCheck(0);
    visibilityChangeHandler = () => {
      if (!options.documentVisible()) return;
      consecutiveHealthFailures = 0;
      queueHealthCheck(0);
    };
    window.addEventListener("visibilitychange", visibilityChangeHandler);

    const refreshHostInfo = () => {
      if (!isDesktopRuntime()) return;
      if (!options.documentVisible()) return;
      void (async () => {
        try {
          const info = await idexalOsServerInfo() as IdexalOsServerInfo;
          if (disposed) return;
          const previousBaseUrl = state.idexalOsServerHostInfo?.baseUrl ?? "";
          mutateState((current) => ({
            ...current,
            idexalOsServerHostInfo: info,
            idexalOsServerHostInfoReady: true,
          }));
          if ((info?.baseUrl ?? "") !== previousBaseUrl) {
            consecutiveHealthFailures = 0;
            healthDelayMs = 10_000;
            queueHealthCheck(0);
          }
        } catch {
          if (disposed) return;
          mutateState((current) => ({
            ...current,
            idexalOsServerHostInfo: null,
            idexalOsServerHostInfoReady: true,
          }));
          queueHealthCheck(healthDelayMs);
        }
      })();
    };
    refreshHostInfo();
    startInterval("hostInfo", refreshHostInfo, 10_000);

    const refreshDiagnostics = () => {
      if (!options.documentVisible()) return;
      if (!options.developerMode()) {
        setStateField("idexalOsServerDiagnostics", null);
        return;
      }

      const client = getClient();
      if (!client || state.idexalOsServerStatus === "disconnected") {
        setStateField("idexalOsServerDiagnostics", null);
        return;
      }

      void (async () => {
        try {
          const status = await client.status();
          if (!disposed) setStateField("idexalOsServerDiagnostics", status);
        } catch {
          if (!disposed) setStateField("idexalOsServerDiagnostics", null);
        }
      })();
    };
    refreshDiagnostics();
    startInterval("diagnostics", refreshDiagnostics, 10_000);

    const refreshDevtoolsWorkspace = () => {
      if (!options.documentVisible()) return;
      if (!options.developerMode()) {
        setStateField("devtoolsWorkspaceId", null);
        return;
      }

      const client = getClient();
      if (!client) {
        setStateField("devtoolsWorkspaceId", null);
        return;
      }

      void (async () => {
        try {
          const response = await client.listWorkspaces();
          if (disposed) return;
          const items = Array.isArray(response.items) ? response.items : [];
          const activeMatch = response.activeId
            ? items.find((item) => item.id === response.activeId)
            : null;
          setStateField("devtoolsWorkspaceId", activeMatch?.id ?? items[0]?.id ?? null);
        } catch {
          if (!disposed) setStateField("devtoolsWorkspaceId", null);
        }
      })();
    };
    refreshDevtoolsWorkspace();
    startInterval("devtoolsWorkspace", refreshDevtoolsWorkspace, 20_000);

    const refreshAudit = () => {
      if (!options.documentVisible()) return;
      if (!options.developerMode()) {
        mutateState((current) => ({
          ...current,
          idexalOsAuditEntries: [],
          idexalOsAuditStatus: "idle",
          idexalOsAuditError: null,
        }));
        return;
      }

      const client = getClient();
      const workspaceId = state.devtoolsWorkspaceId;
      if (!client || !workspaceId) {
        mutateState((current) => ({
          ...current,
          idexalOsAuditEntries: [],
          idexalOsAuditStatus: "idle",
          idexalOsAuditError: null,
        }));
        return;
      }

      mutateState((current) => ({
        ...current,
        idexalOsAuditStatus: "loading",
        idexalOsAuditError: null,
      }));

      void (async () => {
        try {
          const result = await client.listAudit(workspaceId, 50);
          if (disposed) return;
          mutateState((current) => ({
            ...current,
            idexalOsAuditEntries: Array.isArray(result.items) ? result.items : [],
            idexalOsAuditStatus: "idle",
          }));
        } catch (error) {
          if (disposed) return;
          mutateState((current) => ({
            ...current,
            idexalOsAuditEntries: [],
            idexalOsAuditStatus: "error",
            idexalOsAuditError:
              error instanceof Error
                ? error.message
                : t("app.error_audit_load"),
          }));
        }
      })();
    };
    refreshAudit();
    startInterval("audit", refreshAudit, 15_000);
  };

  const dispose = () => {
    disposed = true;
    started = false;
    clearHealthTimeout();
    if (visibilityChangeHandler && typeof window !== "undefined") {
      window.removeEventListener("visibilitychange", visibilityChangeHandler);
      visibilityChangeHandler = null;
    }
    for (const key of [...intervals.keys()]) stopInterval(key);
  };

  const testIdexalOsServerConnection = async (next: IdexalOsServerSettings) => {
    const derived = normalizeIdexalOsServerUrl(next.urlOverride ?? "");
    if (!derived) {
      mutateState((current) => ({
        ...current,
        idexalOsServerStatus: "disconnected",
        idexalOsServerCapabilities: null,
        idexalOsServerCheckedAt: Date.now(),
      }));
      return false;
    }

    const result = await checkIdexalOsServer(derived, next.token);
    consecutiveHealthFailures = result.status === "disconnected" ? consecutiveHealthFailures + 1 : 0;
    mutateState((current) => ({
      ...current,
      idexalOsServerStatus: result.status,
      idexalOsServerCapabilities: result.capabilities,
      idexalOsServerCheckedAt: Date.now(),
    }));

    return result.status === "connected" || result.status === "limited";
  };

  const reconnectIdexalOsServer = async () => {
    if (state.idexalOsReconnectBusy) return false;
    setStateField("idexalOsReconnectBusy", true);

    try {
      let hostInfo = state.idexalOsServerHostInfo;
      if (isDesktopRuntime()) {
        try {
          hostInfo = await idexalOsServerInfo() as IdexalOsServerInfo;
          mutateState((current) => ({ ...current, idexalOsServerHostInfo: hostInfo }));
        } catch {
          hostInfo = null;
          setStateField("idexalOsServerHostInfo", null);
        }
      }

      if (hostInfo?.clientToken?.trim() && options.startupPreference() !== "server") {
        const liveToken = hostInfo.clientToken.trim();
        const liveHostToken = hostInfo.hostToken?.trim() ?? "";
        const settings = state.idexalOsServerSettings;
        if (
          (settings.token?.trim() ?? "") !== liveToken ||
          (settings.hostToken?.trim() ?? "") !== liveHostToken
        ) {
          updateIdexalOsServerSettings({
            ...settings,
            token: liveToken,
            hostToken: liveHostToken || undefined,
          });
        }
      }

      const url = getBaseUrl().trim();
      const auth = getAuth();
      if (!url) {
        mutateState((current) => ({
          ...current,
          idexalOsServerStatus: "disconnected",
          idexalOsServerCapabilities: null,
          idexalOsServerCheckedAt: Date.now(),
        }));
        return false;
      }

      const result = await checkIdexalOsServer(url, auth.token, auth.hostToken);
      mutateState((current) => ({
        ...current,
        idexalOsServerStatus: result.status,
        idexalOsServerCapabilities: result.capabilities,
        idexalOsServerCheckedAt: Date.now(),
      }));
      return result.status === "connected" || result.status === "limited";
    } finally {
      setStateField("idexalOsReconnectBusy", false);
    }
  };

  async function ensureLocalIdexalOsServerClient(): Promise<IdexalOsServerClient | null> {
    const healthyClientFromInfo = async (
      info: IdexalOsServerInfo | null,
    ): Promise<IdexalOsServerClient | null> => {
      const baseUrl = info?.baseUrl?.trim() ?? "";
      const token = info?.clientToken?.trim() ?? "";
      if (!baseUrl || !token) return null;
      const candidate = createIdexalOsServerClient({
        baseUrl,
        token,
        hostToken: info?.hostToken?.trim() || undefined,
      });
      try {
        await candidate.health();
      } catch {
        return null;
      }
      return candidate;
    };

    const cached = await healthyClientFromInfo(state.idexalOsServerHostInfo);
    if (cached) {
      if (options.startupPreference() !== "server") {
        await reconnectIdexalOsServer();
      }
      return cached;
    }

    if (!isDesktopRuntime()) return null;

    // A store that has not observed the server yet (a fresh route mount)
    // must not treat it as dead: the restart below tears down the embedded
    // server AND its managed engine, killing every live run. Ask the desktop
    // bridge for the live server first and restart only when that running
    // server is genuinely unreachable.
    let hostInfo: IdexalOsServerInfo | null = null;
    try {
      hostInfo = await idexalOsServerInfo() as IdexalOsServerInfo;
      mutateState((current) => ({
        ...current,
        idexalOsServerHostInfo: hostInfo,
        idexalOsServerHostInfoReady: true,
      }));
    } catch {
      hostInfo = null;
    }
    const live = await healthyClientFromInfo(hostInfo);
    if (live) {
      if (options.startupPreference() !== "server") {
        await reconnectIdexalOsServer();
      }
      return live;
    }

    try {
      hostInfo = await idexalOsServerRestart() as IdexalOsServerInfo;
      mutateState((current) => ({ ...current, idexalOsServerHostInfo: hostInfo }));
    } catch {
      return null;
    }

    const baseUrl = hostInfo?.baseUrl?.trim() ?? "";
    const token = hostInfo?.clientToken?.trim() ?? "";
    const hostToken = hostInfo?.hostToken?.trim() ?? "";
    if (!baseUrl || !token) return null;

    if (options.startupPreference() !== "server") {
      await reconnectIdexalOsServer();
    }

    return createIdexalOsServerClient({
      baseUrl,
      token,
      hostToken: hostToken || undefined,
    });
  }

  refreshSnapshot();

  const subscribe = (listener: () => void) => {
    listeners.add(listener);
    return () => {
      listeners.delete(listener);
    };
  };

  const getSnapshot = () => {
    if (!snapshot) throw new Error("idexal Os server snapshot was not initialized.");
    return snapshot;
  };

  return {
    subscribe,
    getSnapshot,
    start,
    dispose,
    syncFromOptions,
    setIdexalOsServerSettings,
    updateIdexalOsServerSettings,
    resetIdexalOsServerSettings,
    checkIdexalOsServer,
    testIdexalOsServerConnection,
    reconnectIdexalOsServer,
    ensureLocalIdexalOsServerClient,
  };
}

export function useIdexalOsServerStoreSnapshot(store: IdexalOsServerStore) {
  return useSyncExternalStore(store.subscribe, store.getSnapshot, store.getSnapshot);
}
