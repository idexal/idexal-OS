import { ProxyStore } from "./proxy-store.mjs";
import { rotateProxyProfile, startGuardProxy, testProxyProfile } from "./egress-proxy.mjs";

const PROXY_ENV_KEYS = ["HTTP_PROXY", "HTTPS_PROXY", "ALL_PROXY", "NO_PROXY", "NODE_USE_ENV_PROXY"];
const LOWERCASE_PROXY_ENV_KEYS = ["http_proxy", "https_proxy", "all_proxy", "no_proxy"];

/**
 * Owns the encrypted profile store, the loopback guard proxy, and the mapping
 * from that guard to Electron sessions and child processes.
 */
export class ProxyManager {
  /**
   * @param {{
   *   app: import("electron").App,
   *   safeStorage: import("electron").SafeStorage,
   *   getBrowserPanel: () => any,
   *   onStateChange?: (state: import("@idexal/types/desktop-ipc").ProxyState) => void,
   *   onEgressChanged?: () => void | Promise<void>,
   * }} options
   */
  constructor({ app, safeStorage, getBrowserPanel, onStateChange = () => {}, onEgressChanged = () => {} }) {
    this.app = app;
    this.safeStorage = safeStorage;
    this.getBrowserPanel = getBrowserPanel;
    this.onStateChange = onStateChange;
    this.onEgressChanged = onEgressChanged;
    this.store = new ProxyStore({
      dirPath: app.getPath("userData"),
      safeStorage,
    });
    /** @type {Awaited<ReturnType<typeof startGuardProxy>> | null} */
    this.guard = null;
    /** @type {Map<string, string | undefined>} */
    this.previousEnv = new Map();
    this.applied = false;
    /** @type {number | null} */
    this.byteLimit = null;
  }

  async init() {
    this.store.load();
    if (this.store.enabled && this.store.activeProfile()) {
      await this.#applyWithRecovery().catch(() => undefined);
    }
    return this.state();
  }

  state() {
    const usage = this.store.usage();
    return this.store.publicState(this.guard
      ? {
          running: true,
          host: this.guard.host,
          port: this.guard.port,
          usedBytes: this.guard.usage(),
          limitBytes: this.guard.limitBytes(),
          month: usage.month,
        }
      : null);
  }

  /**
   * @param {number | null} gigabytes
   */
  setBandwidthLimit(gigabytes) {
    this.byteLimit = gigabytes === null || gigabytes === undefined
      ? null
      : Math.max(0, Number(gigabytes)) * 1024 ** 3;
    this.guard?.setLimitBytes(this.byteLimit);
    this.#publish();
    return this.state();
  }

  /**
   * @param {import("@idexal/types/desktop-ipc").ProxyProfileInput} input
   */
  async save(input) {
    const wasActive = Boolean(input?.id && this.store.enabled && this.store.activeId === input.id);
    this.store.save(input);
    if (wasActive && input?.id) {
      this.store.setActive(input.id);
    }
    await this.#applyWithRecovery();
    this.#publish();
    this.#notifyEgressChanged();
    return this.state();
  }

  /**
   * @param {string} id
   */
  async remove(id) {
    this.store.remove(id);
    await this.#applyWithRecovery();
    this.#publish();
    this.#notifyEgressChanged();
    return this.state();
  }

  /**
   * @param {string} id
   */
  async activate(id) {
    this.store.setActive(id);
    await this.#applyWithRecovery();
    this.#publish();
    this.#notifyEgressChanged();
    return this.state();
  }

  async disable() {
    this.store.disable();
    await this.#applyWithRecovery();
    this.#publish();
    this.#notifyEgressChanged();
    return this.state();
  }

  /**
   * @param {string} id
   */
  async test(id) {
    const profile = this.store.findSecret(id);
    if (!profile) throw new Error("Proxy profile not found.");
    return testProxyProfile(profile);
  }

  /**
   * @param {string} id
   */
  async rotate(id) {
    const profile = this.store.findSecret(id);
    if (!profile) throw new Error("Proxy profile not found.");
    if (!String(profile.rotationUrl ?? "").trim()) {
      throw new Error("Add a rotation URL to this profile first.");
    }
    return rotateProxyProfile(profile);
  }

  /**
   * @param {{ url?: string, ownerSessionId?: string, sessionId?: string }} [options]
   */
  async prewarm(options = {}) {
    const panel = this.getBrowserPanel();
    if (!panel?.prewarm) {
      return { ok: false, tabId: null, code: "browser_unavailable" };
    }
    return panel.prewarm(options);
  }

  /** Credentials the Electron login handler should answer with. */
  guardCredentials() {
    if (!this.guard) return null;
    return { username: this.guard.username, password: this.guard.password };
  }

  async shutdown() {
    await this.#stopGuard();
    this.#restoreEnv();
  }

  async #applyWithRecovery() {
    try {
      await this.#apply();
    } catch (error) {
      this.store.disable();
      this.store.setError(error instanceof Error ? error.message : "Could not activate the proxy.");
      await this.#apply().catch(() => undefined);
      this.#publish();
      throw error;
    }
  }

  async #apply() {
    const profile = this.store.enabled ? this.store.activeProfile() : null;
    await this.#stopGuard();
    if (!profile) {
      await this.#applyDirect();
      this.#restoreEnv();
      this.#publish();
      return;
    }

    let guard;
    try {
      guard = await startGuardProxy({
        profile,
        usageStartBytes: this.store.usage().bytes,
        byteLimit: this.byteLimit,
      });
      this.guard = guard;
      console.info("[proxy] guard listening", { port: guard.port, applyToApp: profile.applyToApp, kind: profile.kind });
      const browserPanel = this.getBrowserPanel();
      // Chromium proxy rules do not carry credentials. The app-wide login
      // handler answers the guard's Basic challenge with the per-start secret.
      const guardRules = `http://${guard.host}:${guard.port}`;
      if (profile.applyToApp) {
        await applySessionProxy(guardRules);
        console.info("[proxy] default session now uses the guard");
        this.#applyEnv(guard.url);
      } else {
        await applySessionProxy(null);
        this.#restoreEnv();
      }
      if (browserPanel?.setProxy) {
        await browserPanel.setProxy(guard.url);
        console.info("[proxy] browser session now uses the guard");
      }
      if (browserPanel?.setIdentity) {
        await browserPanel.setIdentity(profile.alignIdentity ? {
          userAgent: profile.userAgent,
          timezone: profile.timezone,
          locale: profile.locale,
        } : null);
        console.info("[proxy] browser identity applied");
      }
      this.store.setError("");
      this.applied = true;
    } catch (error) {
      await guard?.close().catch(() => undefined);
      this.guard = null;
      await this.#applyDirect();
      this.#restoreEnv();
      this.store.setError(error instanceof Error ? error.message : "Could not start the proxy.");
      throw error;
    }
  }

  async #applyDirect() {
    await applySessionProxy(null);
    const browserPanel = this.getBrowserPanel();
    if (browserPanel?.setProxy) {
      await browserPanel.setProxy(null).catch(() => undefined);
    }
    if (browserPanel?.setIdentity) {
      await browserPanel.setIdentity(null).catch(() => undefined);
    }
  }

  async #stopGuard() {
    if (!this.guard) return;
    const guard = this.guard;
    this.guard = null;
    this.store.setUsage(guard.usage());
    await guard.close().catch(() => undefined);
  }

  /**
   * @param {string} url
   */
  #applyEnv(url) {
    const values = {
      HTTP_PROXY: url,
      HTTPS_PROXY: url,
      ALL_PROXY: url,
      NO_PROXY: "localhost,127.0.0.1,::1",
      NODE_USE_ENV_PROXY: "1",
      http_proxy: url,
      https_proxy: url,
      all_proxy: url,
      no_proxy: "localhost,127.0.0.1,::1",
    };
    for (const key of [...PROXY_ENV_KEYS, ...LOWERCASE_PROXY_ENV_KEYS]) {
      if (!this.previousEnv.has(key)) {
        this.previousEnv.set(key, process.env[key]);
      }
      process.env[key] = values[key];
    }
  }

  #restoreEnv() {
    for (const [key, value] of this.previousEnv) {
      if (value === undefined) delete process.env[key];
      else process.env[key] = value;
    }
    this.previousEnv.clear();
  }

  #publish() {
    try {
      this.onStateChange(this.state());
    } catch {
      // The UI can always ask again with proxyState.
    }
  }

  #notifyEgressChanged() {
    // Child processes inherit env at spawn time. The managed engine must be
    // restarted after a proxy change or it keeps the pre-proxy egress path.
    void Promise.resolve(this.onEgressChanged()).catch(() => undefined);
  }
}

/** A stuck upstream connection must not hold the activation open forever. */
function closeConnectionsWithin(session, timeoutMs = 5_000) {
  return Promise.race([
    Promise.resolve(session.closeAllConnections()).catch(() => undefined),
    new Promise((resolve) => setTimeout(resolve, timeoutMs)),
  ]);
}

/**
 * @param {string | null} proxyRules
 */
async function applySessionProxy(proxyRules) {
  const { session } = await import("electron");
  const browserSession = session.defaultSession;
  if (!proxyRules) {
    await browserSession.setProxy({ mode: "system" });
  } else {
    await browserSession.setProxy({
      proxyRules,
      proxyBypassRules: "<local>,localhost,127.0.0.1,::1",
    });
  }
  await closeConnectionsWithin(browserSession);
}
