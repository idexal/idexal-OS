import * as React from "react";

import { desktopRestrictionNotice, type DesktopAppRestrictionChecker } from "../../../../app/cloud/desktop-app-restrictions";

import { applyEdits, modify } from "jsonc-parser";

import { t } from "../../../../i18n";
import type {
  Client,
  PluginScope,
  ReloadReason,
  ReloadTrigger,
  SkillCard,
} from "../../../../app/types";
import { addOpencodeCacheHint, isDesktopRuntime, normalizeDirectoryPath } from "../../../../app/utils";
import skillCreatorTemplate from "../../../../app/data/skill-creator.md?raw";
import {
  isPluginInstalled,
  loadPluginsFromConfig as loadPluginsFromConfigHelpers,
  parsePluginListFromContent,
  stripPluginVersion,
} from "../../../../app/utils/plugins";
import {
  importSkill,
  installSkillTemplate,
  joinDesktopPath,
  listLocalSkills,
  openDesktopPath,
  pickDirectory,
  readLocalSkill,
  readOpencodeConfig,
  revealDesktopItemInDir,
  uninstallSkill as uninstallSkillCommand,
  workspaceIdexalOsRead,
  workspaceIdexalOsWrite,
  writeLocalSkill,
  writeOpencodeConfig,
  type OpencodeConfigFile,
} from "../../../../app/lib/desktop";
import type {
  IdexalOsServerCapabilities,
  IdexalOsServerClient,
  IdexalOsServerStatus,
} from "../../../../app/lib/idexal-os-server";
import {
  DenApiError,
  createDenClient,
  readDenSettings,
  type DenOrgMarketplaceResolved,
} from "../../../../app/lib/den";
import {
  readWorkspaceCloudImports,
  withWorkspaceCloudImports,
  type CloudImportedMarketplace,
  type CloudImportedPlugin,
} from "../../../../app/cloud/import-state";
import {
  derivePendingCloudPluginChanges,
  readPendingCloudSyncChanges,
  refreshDesktopCloudSync,
  type PendingCloudPluginChange,
} from "../../../../app/cloud/desktop-cloud-sync";
import { requestMemberActivityRefresh } from "@/react-app/kernel/activity-types";
import type { IdexalOsServerStore } from "../../connections/idexal-os-server-store";
import { clearCloudInventoryCache } from "../../connections/cloud-inventory-cache";
import {
  denLibraryPluginCreateRequest,
  waitForListedLibraryPlugin,
  type CreateLibraryItemInput,
  type LibraryAuthorableKind,
} from "../library";

const OPENCODE_MCP_NAME_RE = /^[A-Za-z0-9_][A-Za-z0-9_-]*$/;
const OPENCODE_MCP_IMPORT_PATH_PREFIX = "opencode.jsonc#mcp.";

type SetStateAction<T> = T | ((current: T) => T);

type PluginListEntry = {
  name: string;
  source: "config" | "dir.project" | "dir.global";
  removable: boolean;
};

export type ExtensionsStoreSnapshot = {
  workspaceContextKey: string;
  skills: SkillCard[];
  skillsStatus: string | null;
  cloudOrgMarketplaces: DenOrgMarketplaceResolved[];
  cloudOrgMarketplacesStatus: string | null;
  importedCloudMarketplaces: Record<string, CloudImportedMarketplace>;
  importedCloudPlugins: Record<string, CloudImportedPlugin>;
  pendingCloudPluginChanges: Record<string, PendingCloudPluginChange>;
  pluginScope: PluginScope;
  pluginConfig: OpencodeConfigFile | null;
  pluginConfigPath: string | null;
  pluginList: PluginListEntry[];
  pluginInput: string;
  pluginStatus: string | null;
  activePluginGuide: string | null;
  sidebarPluginList: string[];
  sidebarPluginStatus: string | null;
  skillsStale: boolean;
  pluginsStale: boolean;
};

type MutableState = {
  skillsContextKey: string;
  pluginsContextKey: string;
  skills: SkillCard[];
  skillsStatus: string | null;
  cloudOrgMarketplaces: DenOrgMarketplaceResolved[];
  cloudOrgMarketplacesStatus: string | null;
  importedCloudMarketplaces: Record<string, CloudImportedMarketplace>;
  importedCloudPlugins: Record<string, CloudImportedPlugin>;
  pendingCloudPluginChanges: Record<string, PendingCloudPluginChange>;
  pluginScope: PluginScope;
  pluginConfig: OpencodeConfigFile | null;
  pluginConfigPath: string | null;
  pluginList: PluginListEntry[];
  pluginInput: string;
  pluginStatus: string | null;
  activePluginGuide: string | null;
  sidebarPluginList: string[];
  sidebarPluginStatus: string | null;
};

export type ExtensionsStore = ReturnType<typeof createExtensionsStore>;

function cloudPluginMcpNameFromPath(path: string): string | null {
  if (!path.startsWith(OPENCODE_MCP_IMPORT_PATH_PREFIX)) return null;
  const name = path.slice(OPENCODE_MCP_IMPORT_PATH_PREFIX.length).trim();
  return OPENCODE_MCP_NAME_RE.test(name) ? name : null;
}

function toConfigPluginListEntries(names: string[]): PluginListEntry[] {
  const next: PluginListEntry[] = [];
  const seen = new Set<string>();
  for (const rawName of names) {
    const name = rawName.trim();
    if (!name || seen.has(name)) continue;
    seen.add(name);
    next.push({ name, source: "config", removable: true });
  }
  return next;
}

function toProjectPluginListEntries(
  items: Array<{ spec: string; source: string }>,
): PluginListEntry[] {
  const byName = new Map<string, PluginListEntry>();
  for (const item of items) {
    const name = item.spec.trim();
    if (!name) continue;
    const source: PluginListEntry["source"] =
      item.source === "dir.project" || item.source === "dir.global"
        ? item.source
        : "config";
    const entry: PluginListEntry = {
      name,
      source,
      removable: source === "config",
    };
    const existing = byName.get(name);
    if (!existing || (entry.removable && !existing.removable)) {
      byName.set(name, entry);
    }
  }
  return [...byName.values()];
}

export function createExtensionsStore(options: {
  checkDesktopAppRestriction: DesktopAppRestrictionChecker;
  client: () => Client | null;
  projectDir: () => string;
  selectedWorkspaceId: () => string;
  selectedWorkspaceRoot: () => string;
  workspaceType: () => "local";
  idexalOsServer: IdexalOsServerStore;
  idexalOsServerConnection?: () => {
    idexalOsServerClient: IdexalOsServerClient | null;
    idexalOsServerStatus: IdexalOsServerStatus;
    idexalOsServerCapabilities: IdexalOsServerCapabilities | null;
  };
  runtimeWorkspaceId: () => string | null;
  ensureRuntimeWorkspaceId?: () => Promise<string | null | undefined>;
  setBusy: (value: boolean) => void;
  setBusyLabel: (value: string | null) => void;
  setBusyStartedAt: (value: number | null) => void;
  setError: (value: string | null) => void;
  markReloadRequired?: (reason: ReloadReason, trigger?: ReloadTrigger) => void;
}) {
  const listeners = new Set<() => void>();

  let disposed = false;
  let started = false;
  let stopIdexalOsSubscription: (() => void) | null = null;
  let stopDenSessionListener: (() => void) | null = null;
  let lastWorkspaceContextKey = "";
  let snapshot: ExtensionsStoreSnapshot;

  let refreshSkillsInFlight = false;
  let refreshPluginsInFlight = false;
  let refreshCloudOrgMarketplacesInFlight = false;
  let refreshCloudOrgMarketplacesInFlightKey = "";
  let refreshSkillsAborted = false;
  let refreshPluginsAborted = false;
  let refreshCloudOrgMarketplacesAborted = false;
  let skillsLoaded = false;
  let cloudOrgMarketplacesLoaded = false;
  let skillsRoot = "";
  let cloudOrgMarketplacesLoadKey = "";
  /** Plugin IDs the user has already been notified about. Prevents repeated
   *  "new extension available" notifications across sync cycles. */

  let state: MutableState = {
    skillsContextKey: "",
    pluginsContextKey: "",
    skills: [],
    skillsStatus: null,
    cloudOrgMarketplaces: [],
    cloudOrgMarketplacesStatus: null,
    importedCloudMarketplaces: {},
    importedCloudPlugins: {},
    pendingCloudPluginChanges: {},
    pluginScope: "project",
    pluginConfig: null,
    pluginConfigPath: null,
    pluginList: [],
    pluginInput: "",
    pluginStatus: null,
    activePluginGuide: null,
    sidebarPluginList: [],
    sidebarPluginStatus: null,
  };

  const emitChange = () => {
    for (const listener of listeners) listener();
  };

  const getWorkspaceContextKey = () => {
    const workspaceId = options.selectedWorkspaceId().trim();
    const root = normalizeDirectoryPath(options.selectedWorkspaceRoot().trim());
    const runtimeWorkspaceId = (options.runtimeWorkspaceId() ?? "").trim();
    const workspaceType = options.workspaceType();
    return `${workspaceType}:${workspaceId}:${root}:${runtimeWorkspaceId}`;
  };

  const getIdexalOsServerSnapshot = () => {
    const snapshot = options.idexalOsServer.getSnapshot();
    const connection = options.idexalOsServerConnection?.();
    if (!connection?.idexalOsServerClient) return snapshot;
    return {
      ...snapshot,
      idexalOsServerClient: connection.idexalOsServerClient,
      idexalOsServerStatus: connection.idexalOsServerStatus,
      idexalOsServerCapabilities: connection.idexalOsServerCapabilities,
    };
  };

  const resolveWorkspaceServerTarget = async () => {
    const idexalOsSnapshot = getIdexalOsServerSnapshot();
    const idexalOsClient = idexalOsSnapshot.idexalOsServerClient;
    let idexalOsWorkspaceId = options.runtimeWorkspaceId()?.trim() || null;
    if (!idexalOsWorkspaceId && idexalOsSnapshot.idexalOsServerStatus === "connected" && idexalOsClient) {
      idexalOsWorkspaceId = (await options.ensureRuntimeWorkspaceId?.())?.trim() || null;
    }
    const hasIdexalOsTarget =
      idexalOsSnapshot.idexalOsServerStatus === "connected" &&
      Boolean(idexalOsClient && idexalOsWorkspaceId);
    return {
      idexalOsSnapshot,
      idexalOsClient,
      idexalOsWorkspaceId,
      hasIdexalOsTarget,
    };
  };

  const refreshSnapshot = () => {
    const workspaceContextKey = getWorkspaceContextKey();
    snapshot = {
      workspaceContextKey,
      skills: state.skills,
      skillsStatus: state.skillsStatus,
      cloudOrgMarketplaces: state.cloudOrgMarketplaces,
      cloudOrgMarketplacesStatus: state.cloudOrgMarketplacesStatus,
      importedCloudMarketplaces: state.importedCloudMarketplaces,
      importedCloudPlugins: state.importedCloudPlugins,
      pendingCloudPluginChanges: state.pendingCloudPluginChanges,
      pluginScope: state.pluginScope,
      pluginConfig: state.pluginConfig,
      pluginConfigPath: state.pluginConfigPath,
      pluginList: state.pluginList,
      pluginInput: state.pluginInput,
      pluginStatus: state.pluginStatus,
      activePluginGuide: state.activePluginGuide,
      sidebarPluginList: state.sidebarPluginList,
      sidebarPluginStatus: state.sidebarPluginStatus,
      skillsStale: state.skillsContextKey !== workspaceContextKey,
      pluginsStale: state.pluginsContextKey !== workspaceContextKey,
    };
  };

  const mutateState = (updater: (current: MutableState) => MutableState) => {
    state = updater(state);
    refreshSnapshot();
    emitChange();
  };

  const setStateField = <K extends keyof MutableState>(key: K, value: MutableState[K]) => {
    if (Object.is(state[key], value)) return;
    mutateState((current) => ({ ...current, [key]: value }));
  };

  const applyStateAction = <T,>(current: T, next: SetStateAction<T>) =>
    typeof next === "function" ? (next as (value: T) => T)(current) : next;

  const formatSkillPath = (location: string) => location.replace(/[/\\]SKILL\.md$/i, "");

  const readWorkspaceIdexalOsConfigRecord = async (): Promise<Record<string, unknown>> => {
    const root = options.selectedWorkspaceRoot().trim();
    const isLocalWorkspace = options.workspaceType() === "local";
    const { idexalOsSnapshot, idexalOsClient, idexalOsWorkspaceId, hasIdexalOsTarget } =
      await resolveWorkspaceServerTarget();
    const canUseIdexalOsServer =
      hasIdexalOsTarget &&
      idexalOsSnapshot.idexalOsServerCapabilities?.config?.read !== false;

    if (canUseIdexalOsServer && idexalOsClient && idexalOsWorkspaceId) {
      const config = await idexalOsClient.getConfig(idexalOsWorkspaceId);
      return config.idexalos ?? {};
    }

    if (hasIdexalOsTarget) {
      return {};
    }

    if (isLocalWorkspace && isDesktopRuntime() && root) {
      return await workspaceIdexalOsRead({ workspacePath: root }) as unknown as Record<string, unknown>;
    }

    return {};
  };

  const writeWorkspaceIdexalOsConfigRecord = async (config: Record<string, unknown>) => {
    const root = options.selectedWorkspaceRoot().trim();
    const isLocalWorkspace = options.workspaceType() === "local";
    const { idexalOsSnapshot, idexalOsClient, idexalOsWorkspaceId, hasIdexalOsTarget } =
      await resolveWorkspaceServerTarget();
    const canUseIdexalOsServer =
      hasIdexalOsTarget &&
      idexalOsSnapshot.idexalOsServerCapabilities?.config?.write !== false;

    if (canUseIdexalOsServer && idexalOsClient && idexalOsWorkspaceId) {
      await idexalOsClient.patchConfig(idexalOsWorkspaceId, { idexalos: config });
      return true;
    }

    if (hasIdexalOsTarget) {
      return false;
    }

    if (isLocalWorkspace && isDesktopRuntime() && root) {
      const result = (await workspaceIdexalOsWrite({
        workspacePath: root,
        config: config as never,
      })) as { ok: boolean; stderr?: string; stdout?: string };
      if (!result.ok) {
        throw new Error(result.stderr || result.stdout || "Failed to write .opencode/idexal-os.json");
      }
      return true;
    }

    return false;
  };

  const refreshPendingCloudPluginChanges = async (installedPlugins?: Record<string, CloudImportedPlugin>) => {
    try {
      const target = await resolveWorkspaceServerTarget();
      if (!target.idexalOsClient || !target.idexalOsWorkspaceId) {
        setStateField("pendingCloudPluginChanges", {});
        return;
      }
      const syncResult = await refreshDesktopCloudSync({
        idexalOsClient: target.idexalOsClient,
        workspaceId: target.idexalOsWorkspaceId,
      }).catch(() => null);
      const changes = syncResult
        ? syncResult.changes
        : readPendingCloudSyncChanges(await target.idexalOsClient.getDesktopCloudSync(target.idexalOsWorkspaceId));
      const pending = derivePendingCloudPluginChanges({
        changes,
        installedPlugins: installedPlugins ?? snapshot.importedCloudPlugins,
      });
      const previousPending = snapshot.pendingCloudPluginChanges;
      setStateField("pendingCloudPluginChanges", pending);

      // Installation drift is not activity history or evidence of an admin
      // removal. Ask the member-scoped observer to verify usable resources.
      if (Object.entries(pending).some(([id, change]) => previousPending[id] !== change)) {
        requestMemberActivityRefresh();
      }
    } catch {
      // keep previous pending state on failure
    }
  };

  const refreshImportedCloudPlugins = async () => {
    try {
      const target = await resolveWorkspaceServerTarget();
      if (target.idexalOsClient && target.idexalOsWorkspaceId) {
        const result = await target.idexalOsClient.listCloudPlugins(target.idexalOsWorkspaceId);
        setStateField("importedCloudMarketplaces", result.marketplaces);
        setStateField("importedCloudPlugins", result.plugins);
        void refreshPendingCloudPluginChanges(result.plugins);
        return result.plugins;
      }
      const config = await readWorkspaceIdexalOsConfigRecord();
      const cloudImports = readWorkspaceCloudImports(config);
      setStateField("importedCloudMarketplaces", cloudImports.marketplaces);
      setStateField("importedCloudPlugins", cloudImports.plugins);
      return cloudImports.plugins;
    } catch {
      setStateField("importedCloudMarketplaces", {});
      setStateField("importedCloudPlugins", {});
      setStateField("pendingCloudPluginChanges", {});
      return {};
    }
  };

  const persistImportedCloudPlugins = async (nextPlugins: Record<string, CloudImportedPlugin>) => {
    const config = await readWorkspaceIdexalOsConfigRecord();
    const cloudImports = readWorkspaceCloudImports(config);
    const nextCloudImports = {
      ...cloudImports,
      plugins: nextPlugins,
    };
    const nextConfig = withWorkspaceCloudImports(config, nextCloudImports);
    const persisted = await writeWorkspaceIdexalOsConfigRecord(nextConfig);
    if (!persisted) {
      throw new Error("idexal Os server unavailable. Connect to manage imported cloud plugins.");
    }
    setStateField("importedCloudPlugins", nextPlugins);
    void refreshPendingCloudPluginChanges(nextPlugins);
  };

  const deleteWorkspaceSkill = async (name: string) => {
    const isLocalWorkspace = options.workspaceType() === "local";
    const root = options.selectedWorkspaceRoot().trim();
    const { idexalOsSnapshot, idexalOsClient, idexalOsWorkspaceId, hasIdexalOsTarget } =
      await resolveWorkspaceServerTarget();
    const canUseIdexalOsServer =
      hasIdexalOsTarget &&
      idexalOsSnapshot.idexalOsServerCapabilities?.skills?.write !== false;

    if (canUseIdexalOsServer && idexalOsClient && idexalOsWorkspaceId) {
      await idexalOsClient.deleteSkill(idexalOsWorkspaceId, name);
      return;
    }

    if (hasIdexalOsTarget) {
      throw new Error("idexal Os server cannot remove skills for this workspace.");
    }


    if (!isDesktopRuntime()) {
      throw new Error(t("skills.desktop_required"));
    }

    if (!isLocalWorkspace || !root) {
      throw new Error(t("skills.pick_workspace_first"));
    }

    const result = (await uninstallSkillCommand(root, name)) as { ok: boolean; stderr?: string; stdout?: string };
    if (!result.ok) {
      throw new Error(result.stderr || result.stdout || t("skills.uninstall_failed"));
    }
  };

  const deletePluginMcpConfig = async (name: string) => {
    const idexalOsSnapshot = getIdexalOsServerSnapshot();
    const idexalOsClient = idexalOsSnapshot.idexalOsServerClient;
    const idexalOsWorkspaceId = options.runtimeWorkspaceId();
    if (
      idexalOsSnapshot.idexalOsServerStatus === "connected" &&
      idexalOsClient &&
      idexalOsWorkspaceId &&
      idexalOsSnapshot.idexalOsServerCapabilities?.mcp?.write
    ) {
      await idexalOsClient.removeMcp(idexalOsWorkspaceId, name);
      return;
    }
    throw new Error("idexal Os server unavailable. Connect to remove imported MCP servers from this workspace.");
  };

  const deletePluginWorkspaceFiles = async (files: Array<{ path: string; recursive?: boolean }>) => {
    if (files.length === 0) return;
    const { idexalOsSnapshot, idexalOsClient, idexalOsWorkspaceId, hasIdexalOsTarget } =
      await resolveWorkspaceServerTarget();
    if (
      hasIdexalOsTarget &&
      idexalOsClient &&
      idexalOsWorkspaceId &&
      idexalOsSnapshot.idexalOsServerCapabilities?.config?.write !== false &&
      typeof idexalOsClient.deleteWorkspaceFiles === "function"
    ) {
      const results = await idexalOsClient.deleteWorkspaceFiles(idexalOsWorkspaceId, files);
      const failed = results.filter((result) => !result.ok && result.code !== "file_not_found");
      if (failed.length > 0) {
        throw new Error(
          `Failed to remove ${failed.length} imported plugin file${failed.length === 1 ? "" : "s"} from the workspace.`,
        );
      }
      return;
    }
    throw new Error("idexal Os server unavailable. Connect to remove imported plugin files from this workspace.");
  };

  const invalidateWorkspaceCaches = () => {
    skillsLoaded = false;
    cloudOrgMarketplacesLoaded = false;
    skillsRoot = "";
    cloudOrgMarketplacesLoadKey = "";
  };

  const getCurrentCloudOrgLoadKey = () => {
    const orgId = readDenSettings().activeOrgId?.trim() ?? "";
    return `${getWorkspaceContextKey()}::${orgId}`;
  };

  const touch = () => {
    refreshSnapshot();
    emitChange();
  };

  async function refreshCloudOrgMarketplaces(optionsOverride?: { force?: boolean }) {
    const wk = getWorkspaceContextKey();
    const settings = readDenSettings();
    const token = settings.authToken?.trim() ?? "";
    const orgId = settings.activeOrgId?.trim() ?? "";
    const loadKey = `${wk}::${orgId}`;

    if (loadKey !== cloudOrgMarketplacesLoadKey) {
      cloudOrgMarketplacesLoaded = false;
    }

    if (!optionsOverride?.force && cloudOrgMarketplacesLoaded) {
      await refreshImportedCloudPlugins();
      return;
    }
    if (refreshCloudOrgMarketplacesInFlight && refreshCloudOrgMarketplacesInFlightKey === loadKey) return;

    refreshCloudOrgMarketplacesInFlight = true;
    refreshCloudOrgMarketplacesInFlightKey = loadKey;
    refreshCloudOrgMarketplacesAborted = false;

    try {
      setStateField("cloudOrgMarketplacesStatus", null);

      if (!token || !orgId) {
        mutateState((current) => ({
          ...current,
          cloudOrgMarketplaces: [],
          cloudOrgMarketplacesStatus: null,
        }));
        cloudOrgMarketplacesLoaded = true;
        cloudOrgMarketplacesLoadKey = loadKey;
        await refreshImportedCloudPlugins();
        return;
      }

      const client = createDenClient({ baseUrl: settings.baseUrl, token });
      const marketplaces = await client.listOrgMarketplaces(orgId);
      const resolved = await Promise.all(
        marketplaces.map((marketplace) => client.getOrgMarketplaceResolved(orgId, marketplace.id)),
      );
      if (refreshCloudOrgMarketplacesAborted || getCurrentCloudOrgLoadKey() !== loadKey) return;
      mutateState((current) => ({
        ...current,
        cloudOrgMarketplaces: resolved,
        cloudOrgMarketplacesStatus: null,
      }));

      // Activity uses grant-scoped inventories, not this broader marketplace
      // management view (which can include admin-only resources).
      requestMemberActivityRefresh();

      cloudOrgMarketplacesLoaded = true;
      cloudOrgMarketplacesLoadKey = loadKey;
      await refreshImportedCloudPlugins();
    } catch (error) {
      if (refreshCloudOrgMarketplacesAborted || getCurrentCloudOrgLoadKey() !== loadKey) return;
      mutateState((current) => ({
        ...current,
        cloudOrgMarketplaces: [],
        cloudOrgMarketplacesStatus:
          error instanceof Error ? error.message : "Failed to load organization marketplaces.",
      }));
    } finally {
      if (refreshCloudOrgMarketplacesInFlightKey === loadKey) {
        refreshCloudOrgMarketplacesInFlight = false;
        refreshCloudOrgMarketplacesInFlightKey = "";
      }
    }
  }

  // Only manual local changes enter these handlers. Assigned Cloud inventory
  // continues to reconcile through its separate refresh/sync paths.
  function extensionMutationDenied() {
    if (!options.checkDesktopAppRestriction({ restriction: "allowManageExtensions" })) return false;
    options.setError(desktopRestrictionNotice("allowManageExtensions"));
    return true;
  }

  async function removeCloudOrgPlugin(pluginId: string): Promise<{ ok: boolean; message: string }> {
    if (extensionMutationDenied()) return { ok: false, message: desktopRestrictionNotice("allowManageExtensions") };
    options.setBusy(true);
    options.setError(null);
    setStateField("cloudOrgMarketplacesStatus", null);

    try {
      const target = await resolveWorkspaceServerTarget();
      if (target.idexalOsClient && target.idexalOsWorkspaceId) {
        const result = await target.idexalOsClient.removeCloudPlugin(target.idexalOsWorkspaceId, pluginId);
        await refreshSkills({ force: true });
        await refreshCloudOrgMarketplaces({ force: true });
        void refreshPendingCloudPluginChanges();
        return {
          ok: true,
          message: `Removed ${result.item.name}.`,
        };
      }

      const imported = snapshot.importedCloudPlugins[pluginId];
      if (!imported) throw new Error("Marketplace package is not installed in this workspace.");

      const removedMcpNames: string[] = [];
      const fileDeletes: Array<{ path: string; recursive?: boolean }> = [];
      for (const file of imported.files) {
        const mcpName = file.objectType === "mcp" ? cloudPluginMcpNameFromPath(file.path) : null;
        if (mcpName) {
          removedMcpNames.push(mcpName);
          continue;
        }
        if (!file.path.startsWith(".opencode/")) continue;
        const skillDir = file.path.match(/^(\.opencode\/skills\/[^/]+\/[^/]+)\/SKILL\.md$/)?.[1];
        fileDeletes.push(skillDir ? { path: skillDir, recursive: true } : { path: file.path });
      }
      await Promise.all(removedMcpNames.map((name) => deletePluginMcpConfig(name)));
      await deletePluginWorkspaceFiles(fileDeletes);

      const nextPlugins = { ...snapshot.importedCloudPlugins };
      delete nextPlugins[pluginId];
      await persistImportedCloudPlugins(nextPlugins);

      if (removedMcpNames.length > 0) {
        options.markReloadRequired?.("mcp", { type: "mcp", name: imported.name, action: "removed" });
      }
      if (fileDeletes.length > 0) {
        options.markReloadRequired?.("config", { type: "config", name: imported.name, action: "removed" });
      }
      await Promise.all([
        refreshSkills({ force: true }),
        refreshCloudOrgMarketplaces({ force: true }),
      ]);

      return { ok: true, message: `Removed ${imported.name}.` };
    } catch (error) {
      const message = error instanceof Error ? error.message : t("skills.unknown_error");
      options.setError(addOpencodeCacheHint(message));
      return { ok: false, message };
    } finally {
      options.setBusy(false);
    }
  }

  const isPluginInstalledByName = (pluginName: string, aliases: string[] = []) =>
    isPluginInstalled(snapshot.pluginList.map((entry) => entry.name), pluginName, aliases);

  const loadPluginsFromConfig = (config: OpencodeConfigFile | null) => {
    const nextPluginNames: string[] = [];
    let nextPluginStatus: string | null = null;
    loadPluginsFromConfigHelpers(
      config,
      (value) => {
        nextPluginNames.splice(0, nextPluginNames.length, ...applyStateAction(nextPluginNames, value));
      },
      (message) => {
        nextPluginStatus = message;
      },
    );
    mutateState((current) => ({
      ...current,
      pluginList: toConfigPluginListEntries(nextPluginNames),
      pluginStatus: nextPluginStatus,
    }));
  };

  async function refreshSkills(optionsOverride?: { force?: boolean }) {
    const root = options.selectedWorkspaceRoot().trim();
    const isLocalWorkspace = options.workspaceType() === "local";
    const { idexalOsSnapshot, idexalOsClient, idexalOsWorkspaceId, hasIdexalOsTarget } =
      await resolveWorkspaceServerTarget();
    const canUseIdexalOsServer =
      hasIdexalOsTarget &&
      idexalOsSnapshot.idexalOsServerCapabilities?.skills?.read !== false;

    if (!root && !hasIdexalOsTarget) {
      mutateState((current) => ({
        ...current,
        skills: [],
        skillsStatus: t("skills.pick_workspace_first"),
      }));
      return;
    }

    if (canUseIdexalOsServer && idexalOsClient && idexalOsWorkspaceId) {
      const skillCacheKey = root || idexalOsWorkspaceId;
      if (skillCacheKey !== skillsRoot) skillsLoaded = false;
      if (!optionsOverride?.force && skillsLoaded) return;
      if (refreshSkillsInFlight) return;

      refreshSkillsInFlight = true;
      refreshSkillsAborted = false;
      try {
        setStateField("skillsStatus", null);
        const response = await idexalOsClient.listSkills(idexalOsWorkspaceId, { includeGlobal: isLocalWorkspace });
        if (refreshSkillsAborted) return;
        const next: SkillCard[] = Array.isArray(response.items)
          ? response.items.map((entry) => ({
              name: entry.name,
              description: entry.description,
              path: entry.path,
              trigger: entry.trigger,
            }))
          : [];
        mutateState((current) => ({
          ...current,
          skills: next,
          skillsStatus: next.length ? null : t("skills.no_skills_found"),
          skillsContextKey: getWorkspaceContextKey(),
        }));
        skillsLoaded = true;
        skillsRoot = skillCacheKey;
      } catch (error) {
        if (refreshSkillsAborted) return;
        mutateState((current) => ({
          ...current,
          skills: [],
          skillsStatus: error instanceof Error ? error.message : t("skills.failed_to_load"),
        }));
      } finally {
        refreshSkillsInFlight = false;
      }
      return;
    }

    if (hasIdexalOsTarget) {
      mutateState((current) => ({
        ...current,
        skills: [],
        skillsStatus: "idexal Os server cannot read skills for this workspace.",
      }));
      return;
    }

    if (isLocalWorkspace && isDesktopRuntime()) {
      if (root !== skillsRoot) skillsLoaded = false;
      if (!optionsOverride?.force && skillsLoaded) return;
      if (refreshSkillsInFlight) return;

      refreshSkillsInFlight = true;
      refreshSkillsAborted = false;
      try {
        setStateField("skillsStatus", null);
        const local = await listLocalSkills(root);
        if (refreshSkillsAborted) return;
        const next: SkillCard[] = Array.isArray(local)
          ? local.map((entry) => ({
              name: entry.name,
              description: entry.description,
              path: entry.path,
              trigger: entry.trigger,
            }))
          : [];
        mutateState((current) => ({
          ...current,
          skills: next,
          skillsStatus: next.length ? null : t("skills.no_skills_found"),
          skillsContextKey: getWorkspaceContextKey(),
        }));
        skillsLoaded = true;
        skillsRoot = root;
      } catch (error) {
        if (refreshSkillsAborted) return;
        mutateState((current) => ({
          ...current,
          skills: [],
          skillsStatus: error instanceof Error ? error.message : t("skills.failed_to_load"),
        }));
      } finally {
        refreshSkillsInFlight = false;
      }
      return;
    }

    const client = options.client();
    if (!client) {
      mutateState((current) => ({
        ...current,
        skills: [],
        skillsStatus: "idexal Os server unavailable. Connect to load skills.",
      }));
      return;
    }

    if (root !== skillsRoot) skillsLoaded = false;
    if (!optionsOverride?.force && skillsLoaded) return;
    if (refreshSkillsInFlight) return;

    refreshSkillsInFlight = true;
    refreshSkillsAborted = false;
    try {
      setStateField("skillsStatus", null);
      const rawClient = client as unknown as { _client?: { get: (input: { url: string }) => Promise<unknown> } };
      if (!rawClient._client) throw new Error("idexal Os client unavailable.");
      const result = await rawClient._client.get({ url: "/skill" }) as {
        data?: Array<{ name: string; description: string; location: string }>;
        error?: unknown;
      };
      if (result?.data === undefined) {
        const err = result?.error;
        const message = err instanceof Error ? err.message : typeof err === "string" ? err : t("skills.failed_to_load");
        throw new Error(message);
      }
      if (refreshSkillsAborted) return;
      const next: SkillCard[] = Array.isArray(result.data)
        ? result.data.map((entry) => ({
            name: entry.name,
            description: entry.description,
            path: formatSkillPath(entry.location),
          }))
        : [];
      mutateState((current) => ({
        ...current,
        skills: next,
        skillsStatus: next.length ? null : t("skills.no_skills_found"),
        skillsContextKey: getWorkspaceContextKey(),
      }));
      skillsLoaded = true;
      skillsRoot = root;
    } catch (error) {
      if (refreshSkillsAborted) return;
      mutateState((current) => ({
        ...current,
        skills: [],
        skillsStatus: error instanceof Error ? error.message : t("skills.failed_to_load"),
      }));
    } finally {
      refreshSkillsInFlight = false;
    }
  }

  async function refreshPlugins(scopeOverride?: PluginScope) {
    const isLocalWorkspace = options.workspaceType() === "local";
    const { idexalOsSnapshot, idexalOsClient, idexalOsWorkspaceId, hasIdexalOsTarget } =
      await resolveWorkspaceServerTarget();
    const canUseIdexalOsServer =
      hasIdexalOsTarget &&
      idexalOsSnapshot.idexalOsServerCapabilities?.plugins?.read !== false;

    if (refreshPluginsInFlight) return;
    refreshPluginsInFlight = true;
    refreshPluginsAborted = false;

    const scope = scopeOverride ?? snapshot.pluginScope;
    const targetDir = options.projectDir().trim();

    if (scope !== "project" && !isLocalWorkspace) {
      mutateState((current) => ({
        ...current,
        pluginStatus: "Global plugins are only available for local workers.",
        pluginList: [],
        sidebarPluginStatus: "Global plugins require a local worker.",
        sidebarPluginList: [],
      }));
      refreshPluginsInFlight = false;
      return;
    }

    if (scope === "project" && canUseIdexalOsServer && idexalOsClient && idexalOsWorkspaceId) {
      mutateState((current) => ({
        ...current,
        pluginConfig: null,
        pluginConfigPath: "opencode.json (idexalos server)",
      }));

      try {
        mutateState((current) => ({ ...current, pluginStatus: null, sidebarPluginStatus: null }));
        if (refreshPluginsAborted) return;
        const result = await idexalOsClient.listPlugins(idexalOsWorkspaceId, { includeGlobal: false });
        if (refreshPluginsAborted) return;
        const projectItems = result.items.filter((item) => item.scope === "project");
        const list = toProjectPluginListEntries(projectItems);
        mutateState((current) => ({
          ...current,
          pluginList: list,
          sidebarPluginList: list.map((entry) => entry.name),
          pluginStatus: null,
          sidebarPluginStatus: null,
          pluginsContextKey: getWorkspaceContextKey(),
        }));
      } catch (error) {
        if (refreshPluginsAborted) return;
        mutateState((current) => ({
          ...current,
          pluginList: [],
          sidebarPluginList: [],
          sidebarPluginStatus: "Failed to load plugins.",
          pluginStatus: error instanceof Error ? error.message : "Failed to load plugins.",
        }));
      } finally {
        refreshPluginsInFlight = false;
      }
      return;
    }

    if (scope === "project" && hasIdexalOsTarget) {
      mutateState((current) => ({
        ...current,
        pluginStatus: "idexal Os server cannot read plugins for this workspace.",
        pluginList: [],
        sidebarPluginStatus: "idexal Os server cannot read plugins for this workspace.",
        sidebarPluginList: [],
      }));
      refreshPluginsInFlight = false;
      return;
    }

    if (!isDesktopRuntime()) {
      mutateState((current) => ({
        ...current,
        pluginStatus: t("skills.plugin_management_host_only"),
        pluginList: [],
        sidebarPluginStatus: t("skills.plugins_host_only"),
        sidebarPluginList: [],
      }));
      refreshPluginsInFlight = false;
      return;
    }

    if (!isLocalWorkspace && !canUseIdexalOsServer) {
      mutateState((current) => ({
        ...current,
        pluginStatus: "idexal Os server unavailable. Connect to manage plugins.",
        pluginList: [],
        sidebarPluginStatus: "Connect an idexal Os server to load plugins.",
        sidebarPluginList: [],
      }));
      refreshPluginsInFlight = false;
      return;
    }

    if (scope === "project" && !targetDir) {
      mutateState((current) => ({
        ...current,
        pluginStatus: t("skills.pick_project_for_plugins"),
        pluginList: [],
        sidebarPluginStatus: t("skills.pick_project_for_active"),
        sidebarPluginList: [],
      }));
      refreshPluginsInFlight = false;
      return;
    }

    try {
      mutateState((current) => ({ ...current, pluginStatus: null, sidebarPluginStatus: null }));
      if (refreshPluginsAborted) return;
      const config = (await readOpencodeConfig(scope, targetDir)) as OpencodeConfigFile;
      if (refreshPluginsAborted) return;
      mutateState((current) => ({ ...current, pluginConfig: (config as OpencodeConfigFile | null), pluginConfigPath: config.path ?? null }));

      if (!config.exists) {
        mutateState((current) => ({
          ...current,
          pluginList: [],
          pluginStatus: t("skills.no_opencode_found"),
          sidebarPluginList: [],
          sidebarPluginStatus: t("skills.no_opencode_workspace"),
        }));
        return;
      }

      let nextSidebarPluginList: string[] = [];
      let nextSidebarPluginStatus: string | null = null;
      try {
        nextSidebarPluginList = parsePluginListFromContent(config.content ?? "");
      } catch {
        nextSidebarPluginList = [];
        nextSidebarPluginStatus = t("skills.failed_parse_opencode");
      }

      const nextPluginNames: string[] = [];
      let nextPluginStatus: string | null = null;
      loadPluginsFromConfigHelpers(
        config as never,
        (value) => {
          nextPluginNames.splice(0, nextPluginNames.length, ...applyStateAction(nextPluginNames, value));
        },
        (message) => {
          nextPluginStatus = message;
        },
      );

      mutateState((current) => ({
        ...current,
        pluginList: toConfigPluginListEntries(nextPluginNames),
        pluginStatus: nextPluginStatus,
        sidebarPluginList: nextSidebarPluginList,
        sidebarPluginStatus: nextSidebarPluginStatus,
        pluginsContextKey: getWorkspaceContextKey(),
      }));
    } catch (error) {
      if (refreshPluginsAborted) return;
      mutateState((current) => ({
        ...current,
        pluginConfig: null,
        pluginConfigPath: null,
        pluginList: [],
        pluginStatus: error instanceof Error ? error.message : t("skills.failed_load_opencode"),
        sidebarPluginStatus: t("skills.failed_load_active"),
        sidebarPluginList: [],
      }));
    } finally {
      refreshPluginsInFlight = false;
    }
  }

  async function addPlugin(pluginNameOverride?: string) {
    if (extensionMutationDenied()) return;
    const pluginName = (pluginNameOverride ?? snapshot.pluginInput).trim();
    const isManualInput = pluginNameOverride == null;
    const triggerName = stripPluginVersion(pluginName);

    const isLocalWorkspace = options.workspaceType() === "local";
    const { idexalOsSnapshot, idexalOsClient, idexalOsWorkspaceId, hasIdexalOsTarget } =
      await resolveWorkspaceServerTarget();
    const canUseIdexalOsServer =
      hasIdexalOsTarget &&
      idexalOsSnapshot.idexalOsServerCapabilities?.plugins?.write !== false;

    if (!pluginName) {
      if (isManualInput) setStateField("pluginStatus", t("skills.enter_plugin_name"));
      return;
    }

    if (snapshot.pluginScope !== "project" && !isLocalWorkspace) {
      setStateField("pluginStatus", "Global plugins are only available for local workers.");
      return;
    }

    if (snapshot.pluginScope === "project" && canUseIdexalOsServer && idexalOsClient && idexalOsWorkspaceId) {
      try {
        setStateField("pluginStatus", null);
        await idexalOsClient.addPlugin(idexalOsWorkspaceId, pluginName);
        options.markReloadRequired?.("plugins", { type: "plugin", name: triggerName, action: "added" });
        if (isManualInput) setStateField("pluginInput", "");
        await refreshPlugins("project");
      } catch (error) {
        setStateField("pluginStatus", error instanceof Error ? error.message : "Failed to add plugin.");
      }
      return;
    }

    if (snapshot.pluginScope === "project" && hasIdexalOsTarget) {
      setStateField("pluginStatus", "idexal Os server cannot write plugins for this workspace.");
      return;
    }

    if (!isDesktopRuntime()) {
      setStateField("pluginStatus", t("skills.plugin_management_host_only"));
      return;
    }

    if (!isLocalWorkspace) {
      setStateField("pluginStatus", "idexal Os server unavailable. Connect to manage plugins.");
      return;
    }

    const scope = snapshot.pluginScope;
    const targetDir = options.projectDir().trim();

    if (scope === "project" && !targetDir) {
      setStateField("pluginStatus", t("skills.pick_project_for_plugins"));
      return;
    }

    try {
      setStateField("pluginStatus", null);
      const config = (await readOpencodeConfig(scope, targetDir)) as OpencodeConfigFile;
      const raw = config.content ?? "";

      if (!raw.trim()) {
        const payload = { $schema: "https://opencode.ai/config.json", plugin: [pluginName] };
        await writeOpencodeConfig(scope, targetDir, `${JSON.stringify(payload, null, 2)}\n`);
        options.markReloadRequired?.("plugins", { type: "plugin", name: triggerName, action: "added" });
        if (isManualInput) setStateField("pluginInput", "");
        await refreshPlugins(scope);
        return;
      }

      const plugins = parsePluginListFromContent(raw);
      const desired = stripPluginVersion(pluginName).toLowerCase();
      if (plugins.some((entry) => stripPluginVersion(entry).toLowerCase() === desired)) {
        setStateField("pluginStatus", t("skills.plugin_already_listed"));
        return;
      }

      const next = [...plugins, pluginName];
      const edits = modify(raw, ["plugin"], next, { formattingOptions: { insertSpaces: true, tabSize: 2 } });
      const updated = applyEdits(raw, edits);
      await writeOpencodeConfig(scope, targetDir, updated);
      options.markReloadRequired?.("plugins", { type: "plugin", name: triggerName, action: "added" });
      if (isManualInput) setStateField("pluginInput", "");
      await refreshPlugins(scope);
    } catch (error) {
      setStateField("pluginStatus", error instanceof Error ? error.message : t("skills.failed_update_opencode"));
    }
  }

  async function removePlugin(pluginName: string) {
    if (extensionMutationDenied()) return;
    const name = pluginName.trim();
    if (!name) return;
    const triggerName = stripPluginVersion(name);
    const existingPlugin = snapshot.pluginList.find((entry) => entry.name === name);
    if (existingPlugin && !existingPlugin.removable) {
      setStateField("pluginStatus", "Directory-discovered plugins are read-only.");
      return;
    }

    const isLocalWorkspace = options.workspaceType() === "local";
    const { idexalOsSnapshot, idexalOsClient, idexalOsWorkspaceId, hasIdexalOsTarget } =
      await resolveWorkspaceServerTarget();
    const canUseIdexalOsServer =
      hasIdexalOsTarget &&
      idexalOsSnapshot.idexalOsServerCapabilities?.plugins?.write !== false;

    if (snapshot.pluginScope !== "project" && !isLocalWorkspace) {
      setStateField("pluginStatus", "Global plugins are only available for local workers.");
      return;
    }

    if (snapshot.pluginScope === "project" && canUseIdexalOsServer && idexalOsClient && idexalOsWorkspaceId) {
      try {
        setStateField("pluginStatus", null);
        await idexalOsClient.removePlugin(idexalOsWorkspaceId, name);
        options.markReloadRequired?.("plugins", { type: "plugin", name: triggerName, action: "removed" });
        await refreshPlugins("project");
      } catch (error) {
        setStateField("pluginStatus", error instanceof Error ? error.message : "Failed to remove plugin.");
      }
      return;
    }

    if (snapshot.pluginScope === "project" && hasIdexalOsTarget) {
      setStateField("pluginStatus", "idexal Os server cannot write plugins for this workspace.");
      return;
    }

    if (!isDesktopRuntime()) {
      setStateField("pluginStatus", t("skills.plugin_management_host_only"));
      return;
    }

    if (!isLocalWorkspace) {
      setStateField("pluginStatus", "idexal Os server unavailable. Connect to manage plugins.");
      return;
    }

    const scope = snapshot.pluginScope;
    const targetDir = options.projectDir().trim();
    if (scope === "project" && !targetDir) {
      setStateField("pluginStatus", t("skills.pick_project_for_plugins"));
      return;
    }

    try {
      setStateField("pluginStatus", null);
      const config = (await readOpencodeConfig(scope, targetDir)) as OpencodeConfigFile;
      const raw = config.content ?? "";
      if (!raw.trim()) {
        setStateField("pluginStatus", null);
        return;
      }

      const plugins = parsePluginListFromContent(raw);
      const desired = stripPluginVersion(name).toLowerCase();
      const next = plugins.filter((entry) => stripPluginVersion(entry).toLowerCase() !== desired);
      if (next.length === plugins.length) {
        setStateField("pluginStatus", "Plugin not found.");
        return;
      }

      const edits = modify(raw, ["plugin"], next, { formattingOptions: { insertSpaces: true, tabSize: 2 } });
      const updated = applyEdits(raw, edits);
      await writeOpencodeConfig(scope, targetDir, updated);
      options.markReloadRequired?.("plugins", { type: "plugin", name: triggerName, action: "removed" });
      await refreshPlugins(scope);
    } catch (error) {
      setStateField("pluginStatus", error instanceof Error ? error.message : t("skills.failed_update_opencode"));
    }
  }

  async function importLocalSkill() {
    if (extensionMutationDenied()) return;
    const isLocalWorkspace = options.workspaceType() === "local";
    if (!isDesktopRuntime()) {
      options.setError(t("skills.desktop_required"));
      return;
    }
    if (!isLocalWorkspace) {
      options.setError("Local workers are required to import skills.");
      return;
    }
    const targetDir = options.projectDir().trim();
    if (!targetDir) {
      options.setError(t("skills.pick_project_first"));
      return;
    }

    options.setBusy(true);
    options.setError(null);
    setStateField("skillsStatus", null);
    try {
      const selection = await pickDirectory({ title: t("skills.select_skill_folder") });
      const sourceDir = typeof selection === "string" ? selection : Array.isArray(selection) ? selection[0] : null;
      if (!sourceDir) return;
      const inferredName = sourceDir.split(/[\\/]/).filter(Boolean).pop();
      const result = (await importSkill(targetDir, sourceDir, { overwrite: false })) as { ok: boolean; stderr?: string; stdout?: string; status?: number };
      if (!result.ok) {
        setStateField("skillsStatus", result.stderr || result.stdout || t("skills.import_failed").replace("{status}", String(result.status)));
      } else {
        setStateField("skillsStatus", result.stdout || t("skills.imported"));
        options.markReloadRequired?.("skills", { type: "skill", name: inferredName, action: "added" });
      }
      await refreshSkills({ force: true });
    } catch (error) {
      const message = error instanceof Error ? error.message : t("skills.unknown_error");
      options.setError(addOpencodeCacheHint(message));
    } finally {
      options.setBusy(false);
    }
  }

  async function installSkillCreator(): Promise<{ ok: boolean; message: string }> {
    if (extensionMutationDenied()) return { ok: false, message: desktopRestrictionNotice("allowManageExtensions") };
    const isLocalWorkspace = options.workspaceType() === "local";
    const { idexalOsSnapshot, idexalOsClient, idexalOsWorkspaceId, hasIdexalOsTarget } =
      await resolveWorkspaceServerTarget();
    const canUseIdexalOsServer =
      hasIdexalOsTarget &&
      idexalOsSnapshot.idexalOsServerCapabilities?.skills?.write !== false;

    if (canUseIdexalOsServer && idexalOsClient && idexalOsWorkspaceId) {
      options.setBusy(true);
      options.setError(null);
      setStateField("skillsStatus", t("skills.installing_skill_creator"));
      try {
        await idexalOsClient.upsertSkill(idexalOsWorkspaceId, { name: "skill-creator", content: skillCreatorTemplate });
        const message = t("skills.skill_creator_installed");
        setStateField("skillsStatus", message);
        options.markReloadRequired?.("skills", { type: "skill", name: "skill-creator", action: "added" });
        await refreshSkills({ force: true });
        return { ok: true, message };
      } catch (error) {
        const raw = error instanceof Error ? error.message : t("skills.unknown_error");
        const message = addOpencodeCacheHint(raw);
        setStateField("skillsStatus", message);
        options.setError(message);
        return { ok: false, message };
      } finally {
        options.setBusy(false);
      }
    }

    if (hasIdexalOsTarget) {
      const message = "idexal Os server cannot write skills for this workspace.";
      setStateField("skillsStatus", message);
      return { ok: false, message };
    }

    if (!isDesktopRuntime()) {
      const message = t("skills.desktop_required");
      setStateField("skillsStatus", message);
      return { ok: false, message };
    }
    if (!isLocalWorkspace) {
      const message = "Local workers are required to install skills.";
      options.setError(message);
      setStateField("skillsStatus", message);
      return { ok: false, message };
    }

    const targetDir = options.selectedWorkspaceRoot().trim();
    if (!targetDir) {
      const message = t("skills.pick_workspace_first");
      setStateField("skillsStatus", message);
      return { ok: false, message };
    }

    options.setBusy(true);
    options.setError(null);
    setStateField("skillsStatus", t("skills.installing_skill_creator"));
    try {
      const result = (await installSkillTemplate(targetDir, "skill-creator", skillCreatorTemplate, { overwrite: false })) as { ok: boolean; stderr: string; stdout: string };
      if (!result.ok && /already exists/i.test(result.stderr)) {
        const message = t("skills.skill_creator_already_installed");
        setStateField("skillsStatus", message);
        await refreshSkills({ force: true });
        return { ok: true, message };
      }
      if (!result.ok) {
        const message = result.stderr || result.stdout || t("skills.install_failed");
        setStateField("skillsStatus", message);
        await refreshSkills({ force: true });
        return { ok: false, message };
      }
      const message = result.stdout || t("skills.skill_creator_installed");
      setStateField("skillsStatus", message);
      options.markReloadRequired?.("skills", { type: "skill", name: "skill-creator", action: "added" });
      await refreshSkills({ force: true });
      return { ok: true, message };
    } catch (error) {
      const raw = error instanceof Error ? error.message : t("skills.unknown_error");
      const message = addOpencodeCacheHint(raw);
      setStateField("skillsStatus", message);
      options.setError(message);
      return { ok: false, message };
    } finally {
      options.setBusy(false);
    }
  }

  async function revealSkillsFolder() {
    if (!isDesktopRuntime()) {
      setStateField("skillsStatus", t("skills.desktop_required"));
      return;
    }
    const root = options.selectedWorkspaceRoot().trim();
    if (!root) {
      setStateField("skillsStatus", t("skills.pick_workspace_first"));
      return;
    }

    try {
      const [opencodeSkills, claudeSkills, legacySkills] = await Promise.all([
        joinDesktopPath(root, ".opencode", "skills"),
        joinDesktopPath(root, ".claude", "skills"),
        joinDesktopPath(root, ".opencode", "skill"),
      ]);
      const tryOpen = async (target: string) => {
        try {
          await openDesktopPath(target);
          return true;
        } catch {
          return false;
        }
      };
      if (await tryOpen(opencodeSkills)) return;
      if (await tryOpen(claudeSkills)) return;
      if (await tryOpen(legacySkills)) return;
      await revealDesktopItemInDir(opencodeSkills);
    } catch (error) {
      setStateField("skillsStatus", error instanceof Error ? error.message : t("skills.reveal_failed"));
    }
  }

  async function installSuggestedSkill(name: string, content: string): Promise<{ ok: boolean; message: string }> {
    if (extensionMutationDenied()) return { ok: false, message: desktopRestrictionNotice("allowManageExtensions") };
    const skillName = name.trim();
    if (!skillName || !content.trim()) {
      const message = t("skills.catalog_invalid");
      setStateField("skillsStatus", message);
      return { ok: false, message };
    }
    if (snapshot.skills.some((skill) => skill.name === skillName)) {
      const message = t("skills.catalog_installed");
      setStateField("skillsStatus", message);
      return { ok: true, message };
    }

    const isLocalWorkspace = options.workspaceType() === "local";
    const { idexalOsSnapshot, idexalOsClient, idexalOsWorkspaceId, hasIdexalOsTarget } =
      await resolveWorkspaceServerTarget();
    const canUseIdexalOsServer =
      hasIdexalOsTarget &&
      idexalOsSnapshot.idexalOsServerCapabilities?.skills?.write !== false;

    if (canUseIdexalOsServer && idexalOsClient && idexalOsWorkspaceId) {
      options.setBusy(true);
      options.setError(null);
      setStateField("skillsStatus", t("skills.catalog_installing"));
      try {
        await idexalOsClient.upsertSkill(idexalOsWorkspaceId, { name: skillName, content });
        const message = t("skills.catalog_installed");
        setStateField("skillsStatus", message);
        options.markReloadRequired?.("skills", { type: "skill", name: skillName, action: "added" });
        await refreshSkills({ force: true });
        return { ok: true, message };
      } catch (error) {
        const raw = error instanceof Error ? error.message : t("skills.unknown_error");
        const message = addOpencodeCacheHint(raw);
        setStateField("skillsStatus", message);
        options.setError(message);
        return { ok: false, message };
      } finally {
        options.setBusy(false);
      }
    }

    if (hasIdexalOsTarget) {
      const message = "idexal Os server cannot write skills for this workspace.";
      setStateField("skillsStatus", message);
      return { ok: false, message };
    }

    if (!isDesktopRuntime()) {
      const message = t("skills.desktop_required");
      setStateField("skillsStatus", message);
      return { ok: false, message };
    }
    if (!isLocalWorkspace) {
      const message = "Local workers are required to install skills.";
      setStateField("skillsStatus", message);
      options.setError(message);
      return { ok: false, message };
    }

    const targetDir = options.selectedWorkspaceRoot().trim();
    if (!targetDir) {
      const message = t("skills.pick_workspace_first");
      setStateField("skillsStatus", message);
      return { ok: false, message };
    }

    options.setBusy(true);
    options.setError(null);
    setStateField("skillsStatus", t("skills.catalog_installing"));
    try {
      const result = (await installSkillTemplate(targetDir, skillName, content, { overwrite: false })) as { ok: boolean; stderr: string; stdout: string };
      if (!result.ok && /already exists/i.test(result.stderr)) {
        const message = t("skills.catalog_installed");
        setStateField("skillsStatus", message);
        await refreshSkills({ force: true });
        return { ok: true, message };
      }
      if (!result.ok) {
        const message = result.stderr || result.stdout || t("skills.catalog_failed");
        setStateField("skillsStatus", message);
        await refreshSkills({ force: true });
        return { ok: false, message };
      }
      const message = result.stdout || t("skills.catalog_installed");
      setStateField("skillsStatus", message);
      options.markReloadRequired?.("skills", { type: "skill", name: skillName, action: "added" });
      await refreshSkills({ force: true });
      return { ok: true, message };
    } catch (error) {
      const raw = error instanceof Error ? error.message : t("skills.unknown_error");
      const message = addOpencodeCacheHint(raw);
      setStateField("skillsStatus", message);
      options.setError(message);
      return { ok: false, message };
    } finally {
      options.setBusy(false);
    }
  }

  async function uninstallSkill(name: string) {
    if (extensionMutationDenied()) return;
    const trimmed = name.trim();
    if (!trimmed) return;

    options.setBusy(true);
    options.setError(null);
    setStateField("skillsStatus", null);
    try {
      await deleteWorkspaceSkill(trimmed);
      setStateField("skillsStatus", t("skills.uninstalled"));
      options.markReloadRequired?.("skills", { type: "skill", name: trimmed, action: "removed" });
      await refreshSkills({ force: true });
    } catch (error) {
      const message = error instanceof Error ? error.message : t("skills.unknown_error");
      setStateField("skillsStatus", message);
      options.setError(addOpencodeCacheHint(message));
    } finally {
      options.setBusy(false);
    }
  }

  async function readSkill(name: string): Promise<{ name: string; path: string; content: string } | null> {
    const trimmed = name.trim();
    if (!trimmed) return null;
    const root = options.selectedWorkspaceRoot().trim();
    const isLocalWorkspace = options.workspaceType() === "local";
    const { idexalOsSnapshot, idexalOsClient, idexalOsWorkspaceId, hasIdexalOsTarget } =
      await resolveWorkspaceServerTarget();
    const canUseIdexalOsServer =
      hasIdexalOsTarget &&
      idexalOsSnapshot.idexalOsServerCapabilities?.skills?.read !== false;

    if (canUseIdexalOsServer && idexalOsClient && idexalOsWorkspaceId) {
      try {
        setStateField("skillsStatus", null);
        const result = await idexalOsClient.getSkill(idexalOsWorkspaceId, trimmed, { includeGlobal: isLocalWorkspace });
        return { name: result.item.name, path: result.item.path, content: result.content };
      } catch (error) {
        setStateField("skillsStatus", error instanceof Error ? error.message : t("skills.failed_to_load"));
        return null;
      }
    }

    if (hasIdexalOsTarget) {
      setStateField("skillsStatus", "idexal Os server cannot read skills for this workspace.");
      return null;
    }

    if (!root) {
      setStateField("skillsStatus", t("skills.pick_workspace_first"));
      return null;
    }

    if (!isDesktopRuntime()) {
      setStateField("skillsStatus", t("skills.desktop_required"));
      return null;
    }
    if (!isLocalWorkspace) {
      setStateField("skillsStatus", "Local workers are required to view skills.");
      return null;
    }

    try {
      setStateField("skillsStatus", null);
      const result = (await readLocalSkill(root, trimmed)) as { path: string; content: string };
      return { name: trimmed, path: result.path, content: result.content };
    } catch (error) {
      setStateField("skillsStatus", error instanceof Error ? error.message : t("skills.failed_to_load"));
      return null;
    }
  }

  async function saveSkill(input: { name: string; content: string; description?: string }) {
    if (extensionMutationDenied()) return;
    const trimmed = input.name.trim();
    if (!trimmed) return;
    const root = options.selectedWorkspaceRoot().trim();
    const isLocalWorkspace = options.workspaceType() === "local";
    const { idexalOsSnapshot, idexalOsClient, idexalOsWorkspaceId, hasIdexalOsTarget } =
      await resolveWorkspaceServerTarget();
    const canUseIdexalOsServer =
      hasIdexalOsTarget &&
      idexalOsSnapshot.idexalOsServerCapabilities?.skills?.write !== false;

    if (canUseIdexalOsServer && idexalOsClient && idexalOsWorkspaceId) {
      options.setBusy(true);
      options.setError(null);
      setStateField("skillsStatus", null);
      try {
        await idexalOsClient.upsertSkill(idexalOsWorkspaceId, {
          name: trimmed,
          content: input.content,
          description: input.description,
        });
        options.markReloadRequired?.("skills", { type: "skill", name: trimmed, action: "updated" });
        await refreshSkills({ force: true });
        setStateField("skillsStatus", "Saved.");
      } catch (error) {
        const message = error instanceof Error ? error.message : t("skills.unknown_error");
        options.setError(addOpencodeCacheHint(message));
      } finally {
        options.setBusy(false);
      }
      return;
    }

    if (hasIdexalOsTarget) {
      setStateField("skillsStatus", "idexal Os server cannot write skills for this workspace.");
      return;
    }

    if (!root) {
      setStateField("skillsStatus", t("skills.pick_workspace_first"));
      return;
    }

    if (!isDesktopRuntime()) {
      setStateField("skillsStatus", t("skills.desktop_required"));
      return;
    }
    if (!isLocalWorkspace) {
      setStateField("skillsStatus", "Local workers are required to edit skills.");
      return;
    }

    options.setBusy(true);
    options.setError(null);
    setStateField("skillsStatus", null);
    try {
      const result = (await writeLocalSkill(root, trimmed, input.content)) as { ok: boolean; stderr?: string; stdout?: string };
      if (!result.ok) {
        setStateField("skillsStatus", result.stderr || result.stdout || t("skills.unknown_error"));
      } else {
        setStateField("skillsStatus", result.stdout || "Saved.");
        options.markReloadRequired?.("skills", { type: "skill", name: trimmed, action: "updated" });
      }
      await refreshSkills({ force: true });
    } catch (error) {
      const message = error instanceof Error ? error.message : t("skills.unknown_error");
      options.setError(addOpencodeCacheHint(message));
    } finally {
      options.setBusy(false);
    }
  }

  async function createLibraryItem(
    kind: LibraryAuthorableKind,
    input: CreateLibraryItemInput,
  ): Promise<string> {
    const description = input.description.trim();
    const instructions = input.instructions.trim();
    const drafts = input.components?.filter((component) => component.name.trim() && component.content.trim()) ?? [];
    if (!input.name.trim()) {
      throw new Error(t("extensions.add_name_required"));
    }
    if (kind === "mcp") {
      if (!instructions) {
        throw new Error(t("extensions.add_mcp_url_required"));
      }
    } else if (kind !== "plugin") {
      if (!description) {
        throw new Error(t("extensions.add_description_required"));
      }
      if (!instructions) {
        throw new Error(t("extensions.add_instructions_required"));
      }
    }
    if (kind === "plugin" && drafts.length === 0) {
      throw new Error(t("extensions.add_plugin_component_required"));
    }

    const settings = readDenSettings();
    const token = settings.authToken?.trim() ?? "";
    const orgId = settings.activeOrgId?.trim() ?? "";
    if (!token || !orgId) {
      throw new Error(t("extensions.add_sign_in_required"));
    }
    const client = createDenClient({
      baseUrl: settings.baseUrl,
      token,
    });
    const body = denLibraryPluginCreateRequest(kind, {
      ...input,
      components: kind === "plugin" ? drafts : input.components,
    });
    try {
      await client.setActiveOrganization({ organizationId: orgId });
      const pluginId = await client.createOrgPlugin(orgId, body);
      await waitForListedLibraryPlugin(
        () => client.listMeLibraryPlugins(orgId),
        pluginId,
      );
      clearCloudInventoryCache();
      return pluginId;
    } catch (error) {
      if (error instanceof DenApiError && error.status === 401) {
        throw new Error(t("extensions.add_unauthorized"));
      }
      throw error;
    }
  }

  function abortRefreshes() {
    refreshSkillsAborted = true;
    refreshPluginsAborted = true;
    refreshCloudOrgMarketplacesAborted = true;
  }

  function ensureSkillsFresh() {
    if (!snapshot.skillsStale) return;
    void refreshSkills({ force: true });
  }

  function ensurePluginsFresh(scopeOverride?: PluginScope) {
    if (!snapshot.pluginsStale) return;
    void refreshPlugins(scopeOverride);
  }

  const start = () => {
    if (started) return;
    // StrictMode double-mount re-arms after dispose.
    disposed = false;
    started = true;

    if (typeof window !== "undefined") {
      const onDenSessionUpdated = () => {
        cloudOrgMarketplacesLoaded = false;
        touch();
      };
      window.addEventListener("idexal-os-den-session-updated", onDenSessionUpdated);
      stopDenSessionListener = () => window.removeEventListener("idexal-os-den-session-updated", onDenSessionUpdated);
    }

    stopIdexalOsSubscription = options.idexalOsServer.subscribe(() => {
      syncFromOptions();
    });

    syncFromOptions();
  };

  const dispose = () => {
    if (disposed) return;
    disposed = true;
    started = false;
    abortRefreshes();
    stopIdexalOsSubscription?.();
    stopIdexalOsSubscription = null;
    stopDenSessionListener?.();
    stopDenSessionListener = null;
    listeners.clear();
  };

  const syncFromOptions = () => {
    if (disposed) return;
    const key = getWorkspaceContextKey();
    if (key === lastWorkspaceContextKey) return;
    lastWorkspaceContextKey = key;
    invalidateWorkspaceCaches();
    touch();
    if (!key || key === "::::") return;
    void refreshSkills({ force: true });
    void refreshPlugins();
    void refreshImportedCloudPlugins();
  };

  refreshSnapshot();

  const subscribe = (listener: () => void) => {
    listeners.add(listener);
    return () => {
      listeners.delete(listener);
    };
  };

  const getSnapshot = () => snapshot;

  return {
    subscribe,
    getSnapshot,
    start,
    dispose,
    syncFromOptions,
    skills: () => snapshot.skills,
    skillsStatus: () => snapshot.skillsStatus,
    cloudOrgMarketplaces: () => snapshot.cloudOrgMarketplaces,
    cloudOrgMarketplacesStatus: () => snapshot.cloudOrgMarketplacesStatus,
    importedCloudMarketplaces: () => snapshot.importedCloudMarketplaces,
    importedCloudPlugins: () => snapshot.importedCloudPlugins,
    pendingCloudPluginChanges: () => snapshot.pendingCloudPluginChanges,
    get pluginScope() {
      return snapshot.pluginScope;
    },
    setPluginScope(value: SetStateAction<PluginScope>) {
      const resolved = applyStateAction(state.pluginScope, value);
      setStateField("pluginScope", resolved);
    },
    pluginConfig: () => snapshot.pluginConfig,
    pluginConfigPath: () => snapshot.pluginConfigPath,
    pluginList: () => snapshot.pluginList,
    pluginInput: () => snapshot.pluginInput,
    setPluginInput(value: SetStateAction<string>) {
      const resolved = applyStateAction(state.pluginInput, value);
      setStateField("pluginInput", resolved);
    },
    pluginStatus: () => snapshot.pluginStatus,
    activePluginGuide: () => snapshot.activePluginGuide,
    setActivePluginGuide(value: SetStateAction<string | null>) {
      const resolved = applyStateAction(state.activePluginGuide, value);
      setStateField("activePluginGuide", resolved);
    },
    sidebarPluginList: () => snapshot.sidebarPluginList,
    sidebarPluginStatus: () => snapshot.sidebarPluginStatus,
    workspaceContextKey: () => snapshot.workspaceContextKey,
    skillsStale: () => snapshot.skillsStale,
    pluginsStale: () => snapshot.pluginsStale,
    isPluginInstalledByName,
    refreshSkills,
    refreshCloudOrgMarketplaces,
    refreshPlugins,
    addPlugin,
    removePlugin,
    importLocalSkill,
    installSkillCreator,
    installSuggestedSkill,
    removeCloudOrgPlugin,
    revealSkillsFolder,
    uninstallSkill,
    readSkill,
    saveSkill,
    createLibraryItem,
    abortRefreshes,
    ensureSkillsFresh,
    ensurePluginsFresh,
  };
}

export function useExtensionsStoreSnapshot(store: ExtensionsStore) {
  return React.useSyncExternalStore(store.subscribe, store.getSnapshot, store.getSnapshot);
}
