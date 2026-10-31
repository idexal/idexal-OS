import { contextBridge, ipcRenderer, webFrame, webUtils } from "electron";
import { installBrowserShortcutFocusTracking } from "./browser-shortcut-focus.mjs";

const NATIVE_DEEP_LINK_EVENT = "idexalos:deep-link-native";
const NATIVE_MENU_OPEN_SETTINGS_EVENT = "idexalos:native-menu:open-settings";
const NATIVE_MENU_TOGGLE_SIDEBAR_EVENT = "idexalos:native-menu:toggle-sidebar";
const NATIVE_MENU_CHECK_UPDATES_EVENT = "idexalos:native-menu:check-updates";
const NATIVE_MENU_ZOOM_EVENT = "idexalos:native-menu:zoom";
const AUTOMATION_RUNNER_CREDENTIAL_REJECTED_EVENT = "idexalos:automation-runner:credential-rejected";
const BROWSER_BOUNDS_INVALIDATED_EVENT = "idexalos:browser:bounds-invalidated";

let lastBrowserGeometry = null;
let windowFullscreen = ipcRenderer.sendSync("idexalos:window-fullscreen-sync") === true;

async function sendBrowserGeometry(channel, bounds, ...args) {
  // Capture zoom in the same renderer turn as the CSS measurement, not after IPC.
  const geometry = { ...bounds, zoomFactor: webFrame.getZoomFactor() };
  if (channel === "idexalos:browser:bounds" && lastBrowserGeometry
    && ["x", "y", "width", "height", "zoomFactor"].every((key) => geometry[key] === lastBrowserGeometry[key])) {
    return true;
  }
  lastBrowserGeometry = geometry;
  try {
    const accepted = await ipcRenderer.invoke(channel, geometry, ...args);
    if (accepted === false && lastBrowserGeometry === geometry) lastBrowserGeometry = null;
    return accepted;
  } catch (error) {
    if (lastBrowserGeometry === geometry) lastBrowserGeometry = null;
    throw error;
  }
}

function normalizePlatform(value) {
  if (value === "darwin" || value === "linux") return value;
  if (value === "win32") return "windows";
  return "linux";
}

function applyShellDocumentMarkers() {
  try {
    const root = document?.documentElement;
    if (!root) return false;

    root.dataset.idexalOsShell = "electron";
    root.dataset.windowFullscreen = String(windowFullscreen);
    root.classList.add("idexal-os-electron");
    if (process.platform === "darwin") {
      root.classList.add("idexal-os-platform-mac");
    } else if (process.platform === "win32") {
      root.classList.add("idexal-os-platform-windows");
    } else if (process.platform === "linux") {
      root.classList.add("idexal-os-platform-linux");
    }
    return true;
  } catch {
    return false;
  }
}

function notifyMenuOverlayDismiss() {
  ipcRenderer.send("idexalos:menu-overlay:dismiss");
}

function installMenuOverlayDismissListeners() {
  try {
    const target = window;
    target.addEventListener("pointerdown", notifyMenuOverlayDismiss, { capture: true });
    target.addEventListener("wheel", notifyMenuOverlayDismiss, { capture: true, passive: true });
    target.addEventListener("keydown", notifyMenuOverlayDismiss, { capture: true });
    return true;
  } catch {
    return false;
  }
}

function linkOpenPreferences() {
  // Read at activation so Settings changes and reloads use the same saved
  // preference as the renderer, without a second main-process preference store.
  try {
    const prefs = JSON.parse(window.localStorage.getItem("idexal-os.preferences"));
    return { external: prefs?.linkOpenDestination === "external", ask: prefs?.askBeforeOpeningLinks !== false };
  } catch {
    return { external: false, ask: true };
  }
}

function openLink(url, sessionId) {
  ipcRenderer.send("idexalos:browser:linkClick", { url, sessionId, ...linkOpenPreferences() });
}

if (process.isMainFrame) {
  installBrowserShortcutFocusTracking(window, (tabId) => {
    ipcRenderer.send("idexalos:browser:shortcut-focus", tabId);
  });
  window.addEventListener("click", (event) => {
    if (!event.isTrusted || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const anchor = event.composedPath().find((node) => node instanceof HTMLAnchorElement);
    if (!anchor || anchor.isContentEditable || anchor.hasAttribute("download")) return;
    if (!/^(https?:)?\/\//i.test(anchor.getAttribute("href") ?? "")) return;
    let url;
    try { url = new URL(anchor.href); } catch { return; }
    if (!["http:", "https:"].includes(url.protocol)) return;
    if (url.origin === location.origin && url.pathname === location.pathname && url.search === location.search) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    openLink(url.href, anchor.closest("[data-session-surface-id]")?.getAttribute("data-session-surface-id") ?? null);
  }, { capture: true });
}

// Selected text and ordinary editors use Chromium's native context-menu event.
// Explicit editor action menus compose their own editing + formatting menu.
window.addEventListener("contextmenu", (event) => {
  const eventPath = event.composedPath();
  const composedEditor = eventPath.some((node) => node instanceof HTMLElement && node.hasAttribute("data-native-context-menu-editable"));
  const editable = eventPath.some((node) => node instanceof HTMLElement && (
    node.isContentEditable || node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement
  ));
  const selection = window.getSelection();
  const selectedTarget = event.target instanceof Node && selection?.toString() && selection.containsNode(event.target, true);
  if (!composedEditor && (editable || selectedTarget)) {
    event.stopImmediatePropagation();
    return;
  }
  if (composedEditor) return;
  // Preserve Chromium's image hit-test and pixel clipboard operation, including
  // linked images. Do not let surrounding message/link menus swallow it.
  if (eventPath.some((node) => node instanceof HTMLImageElement)) {
    event.stopImmediatePropagation();
    return;
  }
  const anchor = event.composedPath().find((node) => node instanceof HTMLAnchorElement);
  if (!anchor || anchor.isContentEditable || anchor.hasAttribute("download")) return;
  const href = anchor.getAttribute("href") ?? "";
  if (!/^(https?:)?\/\//i.test(href)) return;
  let url;
  try { url = new URL(anchor.href); } catch { return; }
  if (!["http:", "https:"].includes(url.protocol)) return;
  if (url.origin === location.origin && url.pathname === location.pathname && url.search === location.search) return;
  event.preventDefault();
  event.stopImmediatePropagation();
  ipcRenderer.send("idexalos:browser:linkContextMenu", {
    url: url.href,
    point: { x: event.clientX, y: event.clientY },
    sessionId: anchor.closest("[data-session-surface-id]")?.getAttribute("data-session-surface-id") ?? null,
  });
}, { capture: true });

let desktopBootstrap = null;
let desktopDistribution = null;
try {
  desktopBootstrap = ipcRenderer.sendSync("idexalos:desktop-bootstrap-sync");
  desktopDistribution = ipcRenderer.sendSync("idexalos:desktop-distribution-sync");
} catch {
  desktopBootstrap = null;
  desktopDistribution = null;
}

contextBridge.exposeInMainWorld("__IDEXAL_ELECTRON__", {
  invokeDesktop(command, ...args) {
    return ipcRenderer.invoke("idexalos:desktop", command, ...args);
  },
  automationRunner: {
    onCredentialRejected(callback) {
      const handler = () => callback();
      ipcRenderer.on(AUTOMATION_RUNNER_CREDENTIAL_REJECTED_EVENT, handler);
      return () => ipcRenderer.removeListener(AUTOMATION_RUNNER_CREDENTIAL_REJECTED_EVENT, handler);
    },
  },
  fileSystem: {
    getPathForFile(file) {
      return webUtils.getPathForFile(file);
    },
  },
  shell: {
    openExternal(url) {
      return ipcRenderer.invoke("idexalos:shell:openExternal", url);
    },
    relaunch() {
      return ipcRenderer.invoke("idexalos:shell:relaunch");
    },
  },
  system: {
    getArchitectureInfo() {
      return ipcRenderer.invoke("idexalos:system:architecture");
    },
  },
  migration: {
    readSnapshot() {
      return ipcRenderer.invoke("idexalos:migration:read");
    },
    ackSnapshot() {
      return ipcRenderer.invoke("idexalos:migration:ack");
    },
  },
  brandIcon: {
    apply(url) {
      return ipcRenderer.invoke("idexalos:desktop", "__applyBrandIcon", url ?? null);
    },
    getState() {
      return ipcRenderer.invoke("idexalos:desktop", "__getBrandIconState");
    },
  },
  dev: {
    evalRelaunch() {
      return ipcRenderer.invoke("idexalos:desktop", "__evalRelaunch");
    },
  },
  updater: {
    getChannel() {
      return ipcRenderer.invoke("idexalos:updater:getChannel");
    },
    setChannel(channel) {
      return ipcRenderer.invoke("idexalos:updater:setChannel", channel);
    },
    check(channel, targetVersion, options) {
      return ipcRenderer.invoke("idexalos:updater:check", channel, targetVersion, options);
    },
    download() {
      return ipcRenderer.invoke("idexalos:updater:download");
    },
    installAndRestart() {
      return ipcRenderer.invoke("idexalos:updater:installAndRestart");
    },
    /** Subscribe to incremental download progress from electron-updater. */
    onDownloadProgress(callback) {
      const handler = (_event, data) => callback(data);
      ipcRenderer.on("idexalos:updater:download-progress", handler);
      return () => {
        ipcRenderer.removeListener("idexalos:updater:download-progress", handler);
      };
    },
  },
  recovery: {
    recordHealthy() {
      return ipcRenderer.invoke("idexalos:recovery:recordHealthy");
    },
    list(policy) {
      return ipcRenderer.invoke("idexalos:recovery:list", policy);
    },
    restorePrevious() {
      return ipcRenderer.invoke("idexalos:recovery:restorePrevious");
    },
    use(id) {
      return ipcRenderer.invoke("idexalos:recovery:use", id);
    },
  },
  browser: {
    openLink,
    chooseLinkDestination(id, destination) { return ipcRenderer.invoke("idexalos:browser:chooseLinkDestination", id, destination); },
    onLinkOpenRequest(callback) {
      const handler = (_event, request) => callback(request);
      ipcRenderer.on("idexalos:browser:link-open-request", handler);
      return () => ipcRenderer.removeListener("idexalos:browser:link-open-request", handler);
    },
    show(bounds, sessionId) { return sendBrowserGeometry("idexalos:browser:show", bounds, sessionId); },
    hide(options) {
      lastBrowserGeometry = null;
      return ipcRenderer.invoke("idexalos:browser:hide", options);
    },
    openUrl(url, provider, options) { return ipcRenderer.invoke("idexalos:browser:openUrl", url, provider, options); },
    setVisibleSession(sessionId) { return ipcRenderer.invoke("idexalos:browser:setVisibleSession", sessionId); },
    navigate(url) { return ipcRenderer.invoke("idexalos:browser:navigate", url); },
    back() { return ipcRenderer.invoke("idexalos:browser:back"); },
    forward() { return ipcRenderer.invoke("idexalos:browser:forward"); },
    reload() { return ipcRenderer.invoke("idexalos:browser:reload"); },
    setBounds(bounds) { return sendBrowserGeometry("idexalos:browser:bounds", bounds); },
    getState() { return ipcRenderer.invoke("idexalos:browser:state"); },
    createTab(url, sessionId) { return ipcRenderer.invoke("idexalos:browser:createTab", url, sessionId); },
    closeTab(tabId) { return ipcRenderer.invoke("idexalos:browser:closeTab", tabId); },
    suspendTab(tabId) { return ipcRenderer.invoke("idexalos:browser:suspendTab", tabId); },
    restoreTab(tabId, sessionId) { return ipcRenderer.invoke("idexalos:browser:restoreTab", tabId, sessionId); },
    releaseTab(tabId, sessionId) { return ipcRenderer.invoke("idexalos:browser:releaseTab", tabId, sessionId); },
    closeSessionTabs(sessionId) { return ipcRenderer.invoke("idexalos:browser:closeSessionTabs", sessionId); },
    selectTab(tabId) { return ipcRenderer.invoke("idexalos:browser:selectTab", tabId); },
    reorderTabs(tabIds) { return ipcRenderer.invoke("idexalos:browser:reorderTabs", tabIds); },
    taskControl(tabId, action) { return ipcRenderer.invoke("idexalos:browser:taskControl", tabId, action); },
    setProxy(proxy) { return ipcRenderer.invoke("idexalos:browser:setProxy", proxy); },
    setControlEnabled(enabled) { return ipcRenderer.invoke("idexalos:browser:setControlEnabled", enabled); },
    setAgentPermissionRules(rules) { return ipcRenderer.invoke("idexalos:browser:setAgentPermissionRules", rules); },
    showTabContextMenu(tabId, point) { return ipcRenderer.invoke("idexalos:browser:tabContextMenu", tabId, point); },
    destroy() {
      lastBrowserGeometry = null;
      return ipcRenderer.invoke("idexalos:browser:destroy");
    },
    onStateChange(callback) {
      const handler = (_event, state) => callback(state);
      ipcRenderer.on("idexalos:browser:state", handler);
      return () => ipcRenderer.removeListener("idexalos:browser:state", handler);
    },
    onPanelOpened(callback) {
      const handler = (_event, payload) => callback(payload);
      ipcRenderer.on("idexalos:browser:panel-opened", handler);
      return () => ipcRenderer.removeListener("idexalos:browser:panel-opened", handler);
    },
    onPanelClosed(callback) {
      const handler = (_event, payload) => callback(payload);
      ipcRenderer.on("idexalos:browser:panel-closed", handler);
      return () => ipcRenderer.removeListener("idexalos:browser:panel-closed", handler);
    },
  },
  // Development-only observation of native popup menus; main registers no handler otherwise.
  ...(process.env.IDEXAL_DEV_MODE === "1" ? {
    contextMenu: {
      inspect() { return ipcRenderer.invoke("idexalos:context-menu:inspect"); },
      choose(id) { return ipcRenderer.invoke("idexalos:context-menu:choose", id); },
      dismiss() { return ipcRenderer.invoke("idexalos:context-menu:dismiss"); },
    },
  } : {}),
  terminal: {
    create(options) { return ipcRenderer.invoke("idexalos:terminal:create", options); },
    write(terminalId, data) { return ipcRenderer.invoke("idexalos:terminal:write", terminalId, data); },
    resize(terminalId, cols, rows) { return ipcRenderer.invoke("idexalos:terminal:resize", terminalId, cols, rows); },
    kill(terminalId) { return ipcRenderer.invoke("idexalos:terminal:kill", terminalId); },
    onData(callback) {
      const handler = (_event, payload) => callback(payload);
      ipcRenderer.on("idexalos:terminal:data", handler);
      return () => ipcRenderer.removeListener("idexalos:terminal:data", handler);
    },
    onExit(callback) {
      const handler = (_event, payload) => callback(payload);
      ipcRenderer.on("idexalos:terminal:exit", handler);
      return () => ipcRenderer.removeListener("idexalos:terminal:exit", handler);
    },
  },
  meta: {
    desktopBootstrap,
    distribution: desktopDistribution,
    initialDeepLinks: [],
    platform: normalizePlatform(process.platform),
    version: process.versions.electron,
    evalFatalBootstrapFailure: process.env.IDEXAL_EVAL_FATAL_DESKTOP_BOOTSTRAP_FAILURE ?? null,
  },
});

if (
  process.env.IDEXAL_EVAL_FATAL_DESKTOP_BOOTSTRAP_FAILURE
  && (process.env.IDEXAL_EVAL_RECOVERY_CANDIDATES || process.env.IDEXAL_EVAL_RECOVERY_RELEASES)
) {
  contextBridge.exposeInMainWorld("__idexalOsRecoveryControl", {
    snapshot() {
      return ipcRenderer.invoke("idexalos:recovery:evalSnapshot");
    },
    select(id) {
      return ipcRenderer.invoke("idexalos:recovery:use", id);
    },
  });
}

ipcRenderer.on(NATIVE_DEEP_LINK_EVENT, (_event, urls) => {
  if (typeof window === "undefined") return;
  window.dispatchEvent(new CustomEvent(NATIVE_DEEP_LINK_EVENT, { detail: urls }));
});

ipcRenderer.on(NATIVE_MENU_OPEN_SETTINGS_EVENT, () => {
  if (typeof window === "undefined") return;
  window.dispatchEvent(new Event(NATIVE_MENU_OPEN_SETTINGS_EVENT));
});

ipcRenderer.on(NATIVE_MENU_TOGGLE_SIDEBAR_EVENT, () => {
  if (typeof window === "undefined") return;
  window.dispatchEvent(new Event(NATIVE_MENU_TOGGLE_SIDEBAR_EVENT));
});

ipcRenderer.on(NATIVE_MENU_CHECK_UPDATES_EVENT, () => {
  if (typeof window === "undefined") return;
  window.dispatchEvent(new Event(NATIVE_MENU_CHECK_UPDATES_EVENT));
});

ipcRenderer.on(NATIVE_MENU_ZOOM_EVENT, (_event, action) => {
  if (typeof window === "undefined") return;
  window.dispatchEvent(new CustomEvent(NATIVE_MENU_ZOOM_EVENT, { detail: action }));
});

ipcRenderer.on(BROWSER_BOUNDS_INVALIDATED_EVENT, () => {
  lastBrowserGeometry = null;
  window.dispatchEvent(new Event(BROWSER_BOUNDS_INVALIDATED_EVENT));
});

ipcRenderer.on("idexalos:window-fullscreen", (_event, fullscreen) => {
  windowFullscreen = fullscreen === true;
  applyShellDocumentMarkers();
});

if (!applyShellDocumentMarkers() && typeof document !== "undefined") {
  document.addEventListener("DOMContentLoaded", applyShellDocumentMarkers, { once: true });
}

if (!installMenuOverlayDismissListeners() && typeof document !== "undefined") {
  document.addEventListener("DOMContentLoaded", installMenuOverlayDismissListeners, { once: true });
}
