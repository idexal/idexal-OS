/** @jsxImportSource react */
import { applyEdits, modify, parse } from "jsonc-parser";
import { z } from "zod";

import type { IdexalOsServerClient } from "@/app/lib/idexal-os-server";
import {
  desktopBridge,
  listLocalSkills,
  readOpencodeConfig,
  uninstallSkill,
  writeLocalSkill,
  writeOpencodeConfig,
} from "@/app/lib/desktop";
import {
  isDesktopRuntime,
  normalizeDirectoryPath,
} from "@/app/utils";
import { getInitialThemeMode, setThemeMode } from "@/app/theme";
import type { ProviderListItem, ReloadTrigger } from "@/app/types";
import { currentLocale, setLocale, type Language } from "@/i18n";
import {
  parseMcpServersFromContent,
  removeMcpFromConfig,
  validateMcpServerName,
} from "@/app/mcp";
import {
  parsePluginListFromContent,
  stripPluginVersion,
} from "@/app/utils/plugins";
import type { ProviderAuthStore } from "@/react-app/domains/connections/provider-auth/store";
import type { CustomProviderInput } from "@/react-app/domains/connections/provider-auth/custom-provider-config";
import { useDesktopConfig } from "@/react-app/domains/cloud/desktop-config-provider";
import { useLocal } from "@/react-app/kernel/local-provider";
import {
  useControlActions,
  type IdexalOsControlAction,
  type IdexalOsControlPermission,
} from "@/react-app/shell/control/control-provider";
import { useShellConfig } from "@/react-app/shell/shell-config";
import type { ProxyProfileInput } from "@idexal/types/desktop-ipc";
import type { DesktopConfig } from "@idexal/types/den/desktop-policies";
import type { ReloadReason } from "@/app/extensions";

const APP_LANGUAGES = ["en", "ar", "ja", "zh", "vi", "pt-BR", "th", "fr", "ca", "es", "ru"] as const satisfies readonly Language[];

type ProxyEntitlements = {
  enabled: boolean;
  features: {
    proxy: boolean;
    residential: boolean;
    rotation: boolean;
    fingerprint: boolean;
    prewarm: boolean;
  };
  limits: {
    profiles: number | null;
    bandwidthGb: number | null;
  };
};

const LOCAL_PROXY_ENTITLEMENTS: ProxyEntitlements = {
  enabled: true,
  features: { proxy: true, residential: true, rotation: false, fingerprint: false, prewarm: true },
  limits: { profiles: 1, bandwidthGb: 5 },
};

const settingsPermission: IdexalOsControlPermission = { action: "settings", plan: "settings" };
const proxyPermission: IdexalOsControlPermission = { action: "proxy", plan: "proxies" };
const extensionsPermission: IdexalOsControlPermission = { action: "extensions", plan: "extensions" };
const providerPermission: IdexalOsControlPermission = { action: "settings", plan: "providers" };

const proxyKindSchema = z.enum([
  "static_residential",
  "rotating_residential",
  "datacenter",
  "mobile",
  "custom",
]);
const proxyProtocolSchema = z.enum(["http", "https", "socks5"]);

const proxySaveSchema = z.object({
  id: z.string().trim().min(1).optional(),
  name: z.string().trim().min(1),
  kind: proxyKindSchema,
  protocol: proxyProtocolSchema,
  host: z.string().trim().min(1),
  port: z.number().int().min(1).max(65535),
  username: z.string().optional(),
  password: z.string().optional(),
  country: z.string().optional(),
  timezone: z.string().optional(),
  locale: z.string().optional(),
  latitude: z.number().nullable().optional(),
  longitude: z.number().nullable().optional(),
  userAgent: z.string().optional(),
  rotationUrl: z.string().optional(),
  providerId: z.string().optional(),
  providerSlug: z.string().optional(),
  notes: z.string().optional(),
  applyToApp: z.boolean().optional(),
  alignIdentity: z.boolean().optional(),
}).strict();

const shellSettingsSchema = z.object({
  sidebar: z.boolean().optional(),
  statusBar: z.boolean().optional(),
  docsButton: z.boolean().optional(),
  feedbackButton: z.boolean().optional(),
  starterCards: z.boolean().optional(),
  modelPicker: z.boolean().optional(),
  browser: z.boolean().optional(),
  addWorkspace: z.boolean().optional(),
  notifications: z.boolean().optional(),
}).strict();

const settingsUpdateSchema = z.object({
  theme: z.enum(["light", "dark", "system"]).optional(),
  language: z.enum(APP_LANGUAGES).optional(),
  showThinking: z.boolean().optional(),
  analyticsEnabled: z.boolean().optional(),
  desktopNotifications: z.enum(["off", "important", "all"]).optional(),
  linkOpenDestination: z.enum(["idexalos", "external"]).optional(),
  askBeforeOpeningLinks: z.boolean().optional(),
  defaultModel: z.object({
    providerID: z.string().trim().min(1),
    modelID: z.string().trim().min(1),
  }).strict().optional(),
  modelVariant: z.string().trim().optional(),
  shell: shellSettingsSchema.optional(),
}).strict().refine((value) => Object.values(value).some((entry) => entry !== undefined), {
  message: "Provide at least one setting to change.",
});

const skillInstallSchema = z.object({
  name: z.string().trim().min(1),
  content: z.string().min(1),
  description: z.string().optional(),
}).strict();

const extensionNameSchema = z.object({
  name: z.string().trim().min(1),
}).strict();

const mcpInstallSchema = z.object({
  name: z.string().trim().min(1),
  config: z.record(z.string(), z.unknown()),
}).strict();

const pluginInstallSchema = z.object({
  spec: z.string().trim().min(1),
}).strict();

const providerKeySchema = z.object({
  providerId: z.string().trim().min(1),
  apiKey: z.string().trim().min(1),
}).strict();

const customProviderSchema = z.object({
  family: z.enum(["openai", "anthropic"]),
  name: z.string().trim().min(1),
  baseUrl: z.string().trim().url(),
  apiKey: z.string().optional(),
  models: z.union([z.string(), z.array(z.string().trim().min(1)).min(1)]),
}).strict();

function parseArgs<T>(schema: z.ZodType<T>, args: unknown): T {
  const parsed = schema.safeParse(args ?? {});
  if (!parsed.success) {
    const detail = parsed.error.issues
      .map((issue) => `${issue.path.join(".") || "args"}: ${issue.message}`)
      .join("; ");
    throw new Error(`Invalid arguments: ${detail}`);
  }
  return parsed.data;
}

function proxyEntitlements(config: DesktopConfig): ProxyEntitlements {
  const configured = config.proxy;
  if (!configured) return LOCAL_PROXY_ENTITLEMENTS;
  return {
    enabled: configured.enabled,
    features: configured.features,
    limits: configured.limits,
  };
}

function requireProxyPlan(entitlements: ProxyEntitlements): void {
  if (!entitlements.enabled || !entitlements.features.proxy) {
    throw new Error("The organization's plan does not include network proxies.");
  }
}

function projectConfigObject(raw: string): Record<string, unknown> {
  if (!raw.trim()) return {};
  const errors: Array<{ error: number; offset: number; length: number }> = [];
  const parsed = parse(raw, errors, { allowTrailingComma: true });
  if (errors.length > 0 || !parsed || typeof parsed !== "object" || Array.isArray(parsed)) {
    throw new Error("The workspace's opencode.json could not be parsed.");
  }
  return parsed as Record<string, unknown>;
}

function requireOk(result: { ok: boolean; stderr?: string; stdout?: string }, fallback: string): void {
  if (result.ok) return;
  throw new Error(result.stderr?.trim() || result.stdout?.trim() || fallback);
}

function formatMcpConfig(config: Record<string, unknown>): Record<string, unknown> {
  const type = config.type;
  if (type !== "remote" && type !== "local") {
    throw new Error("MCP config.type must be remote or local.");
  }
  if (type === "remote" && typeof config.url !== "string") {
    throw new Error("A remote MCP server needs a url.");
  }
  if (type === "local" && typeof config.command !== "string" && !Array.isArray(config.command)) {
    throw new Error("A local MCP server needs a command.");
  }
  return config;
}

export function AgentDesktopControlActions({
  workspaceRoot,
  workspaceType,
  serverClient,
  serverWorkspaceId,
  providers,
  providerConnectedIds,
  providerAuthStore,
  markReloadRequired,
}: {
  workspaceRoot: string;
  workspaceType: string;
  serverClient: IdexalOsServerClient | null;
  serverWorkspaceId: string | null;
  providers: readonly ProviderListItem[];
  providerConnectedIds: readonly string[];
  providerAuthStore: ProviderAuthStore;
  markReloadRequired: (reason: ReloadReason, trigger?: ReloadTrigger) => void;
}) {
  const local = useLocal();
  const desktopConfig = useDesktopConfig();
  const { config: shellConfig, update: updateShellConfig } = useShellConfig();
  const entitlements = proxyEntitlements(desktopConfig.config);
  const root = normalizeDirectoryPath(workspaceRoot.trim());
  const hasServerWorkspace = Boolean(serverClient && serverWorkspaceId);
  const canManageLocalExtensions = isDesktopRuntime() && Boolean(root);

  const readProjectRaw = async (): Promise<string> => {
    if (!canManageLocalExtensions) {
      throw new Error("This workspace is not available on this computer.");
    }
    const config = await readOpencodeConfig("project", root);
    return config.content ?? "";
  };

  const writeProjectRaw = async (raw: string): Promise<void> => {
    const result = await writeOpencodeConfig("project", root, raw.endsWith("\n") ? raw : `${raw}\n`);
    requireOk(result, "Could not write the workspace's opencode.json.");
  };

  const addLocalMcp = async (name: string, config: Record<string, unknown>): Promise<void> => {
    const raw = await readProjectRaw();
    const next = raw.trim()
      ? applyEdits(raw, modify(raw, ["mcp", name], config, { formattingOptions: { insertSpaces: true, tabSize: 2 } }))
      : `${JSON.stringify({ $schema: "https://opencode.ai/config.json", mcp: { [name]: config } }, null, 2)}\n`;
    await writeProjectRaw(next);
  };

  const addLocalPlugin = async (spec: string): Promise<void> => {
    const raw = await readProjectRaw();
    const existing = parsePluginListFromContent(raw);
    const exists = existing.some((entry) => stripPluginVersion(entry).toLowerCase() === stripPluginVersion(spec).toLowerCase());
    if (exists) throw new Error(`${spec} is already installed in this workspace.`);
    const next = raw.trim()
      ? applyEdits(raw, modify(raw, ["plugin"], [...existing, spec], { formattingOptions: { insertSpaces: true, tabSize: 2 } }))
      : `${JSON.stringify({ $schema: "https://opencode.ai/config.json", plugin: [spec] }, null, 2)}\n`;
    await writeProjectRaw(next);
  };

  const removeLocalPlugin = async (name: string): Promise<void> => {
    const raw = await readProjectRaw();
    const existing = parsePluginListFromContent(raw);
    const wanted = stripPluginVersion(name).toLowerCase();
    const next = existing.filter((entry) => stripPluginVersion(entry).toLowerCase() !== wanted);
    if (next.length === existing.length) throw new Error(`${name} is not installed in this workspace.`);
    await writeProjectRaw(applyEdits(raw, modify(raw, ["plugin"], next, {
      formattingOptions: { insertSpaces: true, tabSize: 2 },
    })));
  };

  const listExtensions = async () => {
    if (hasServerWorkspace && serverClient && serverWorkspaceId) {
      const [skills, mcp, plugins] = await Promise.all([
        serverClient.listSkills(serverWorkspaceId, { includeGlobal: workspaceType === "local" }),
        serverClient.listMcp(serverWorkspaceId),
        serverClient.listPlugins(serverWorkspaceId, { includeGlobal: workspaceType === "local" }),
      ]);
      return {
        skills: skills.items.map((item) => ({ name: item.name, description: item.description, scope: item.scope })),
        mcp: mcp.items.map((item) => ({ name: item.name, config: item.config, source: item.source })),
        plugins: plugins.items.map((item) => ({ spec: item.spec, source: item.source })),
      };
    }
    if (!canManageLocalExtensions) {
      throw new Error("Connect a workspace before managing extensions.");
    }
    const config = await readOpencodeConfig("project", root);
    const raw = config.content ?? "";
    const skills = await listLocalSkills(root);
    return {
      skills: skills.map((item) => ({ name: item.name, description: item.description, path: item.path })),
      mcp: parseMcpServersFromContent(raw),
      plugins: parsePluginListFromContent(raw),
    };
  };

  const installSkill = async (input: z.infer<typeof skillInstallSchema>) => {
    if (hasServerWorkspace && serverClient && serverWorkspaceId) {
      const item = await serverClient.upsertSkill(serverWorkspaceId, input);
      markReloadRequired("skills", { type: "skill", name: input.name, action: "added" });
      return { name: item.name, path: item.path, scope: item.scope };
    }
    if (!canManageLocalExtensions) throw new Error("Connect a workspace before installing skills.");
    const result = await writeLocalSkill(root, input.name, input.content);
    requireOk(result, "Could not install the skill.");
    markReloadRequired("skills", { type: "skill", name: input.name, action: "added" });
    return { name: input.name };
  };

  const removeSkill = async (name: string) => {
    if (hasServerWorkspace && serverClient && serverWorkspaceId) {
      const result = await serverClient.deleteSkill(serverWorkspaceId, name);
      markReloadRequired("skills", { type: "skill", name, action: "removed" });
      return result;
    }
    if (!canManageLocalExtensions) throw new Error("Connect a workspace before removing skills.");
    const result = await uninstallSkill(root, name);
    requireOk(result, "Could not remove the skill.");
    markReloadRequired("skills", { type: "skill", name, action: "removed" });
    return { name };
  };

  const installMcp = async (name: string, config: Record<string, unknown>) => {
    const checked = formatMcpConfig(config);
    if (hasServerWorkspace && serverClient && serverWorkspaceId) {
      const result = await serverClient.addMcp(serverWorkspaceId, { name, config: checked });
      markReloadRequired("mcp", { type: "mcp", name, action: "added" });
      return result.items.find((item) => item.name === name) ?? { name, config: checked };
    }
    if (!canManageLocalExtensions) throw new Error("Connect a workspace before installing MCP servers.");
    await addLocalMcp(name, checked);
    markReloadRequired("mcp", { type: "mcp", name, action: "added" });
    return { name, config: checked };
  };

  const removeMcp = async (name: string) => {
    if (hasServerWorkspace && serverClient && serverWorkspaceId) {
      const result = await serverClient.removeMcp(serverWorkspaceId, name);
      markReloadRequired("mcp", { type: "mcp", name, action: "removed" });
      return result;
    }
    if (!canManageLocalExtensions) throw new Error("Connect a workspace before removing MCP servers.");
    await removeMcpFromConfig(root, name);
    markReloadRequired("mcp", { type: "mcp", name, action: "removed" });
    return { name };
  };

  const installPlugin = async (spec: string) => {
    if (hasServerWorkspace && serverClient && serverWorkspaceId) {
      const result = await serverClient.addPlugin(serverWorkspaceId, spec);
      markReloadRequired("plugins", { type: "plugin", name: stripPluginVersion(spec), action: "added" });
      return result;
    }
    if (!canManageLocalExtensions) throw new Error("Connect a workspace before installing plugins.");
    await addLocalPlugin(spec);
    markReloadRequired("plugins", { type: "plugin", name: stripPluginVersion(spec), action: "added" });
    return { spec };
  };

  const removePlugin = async (name: string) => {
    if (hasServerWorkspace && serverClient && serverWorkspaceId) {
      const result = await serverClient.removePlugin(serverWorkspaceId, name);
      markReloadRequired("plugins", { type: "plugin", name: stripPluginVersion(name), action: "removed" });
      return result;
    }
    if (!canManageLocalExtensions) throw new Error("Connect a workspace before removing plugins.");
    await removeLocalPlugin(name);
    markReloadRequired("plugins", { type: "plugin", name: stripPluginVersion(name), action: "removed" });
    return { name };
  };

  const proxyAction = async <T,>(run: () => Promise<T>): Promise<T> => {
    if (!isDesktopRuntime()) throw new Error("Network proxies are only available in the desktop app.");
    return run();
  };

  const requireFeature = (feature: keyof ProxyEntitlements["features"], message: string): void => {
    if (!entitlements.features[feature]) throw new Error(message);
  };

  const actions: IdexalOsControlAction[] = [
    {
      id: "app.settings.get",
      label: "Read desktop settings",
      description: "Read the app's local appearance, language, notification, link and model preferences, plus the plan's agent control rules.",
      kind: "query",
      effects: { data: "read", ui: "none", external: false },
      sideEffect: "none",
      permission: settingsPermission,
      execute: () => ({
        theme: getInitialThemeMode(),
        language: currentLocale(),
        showThinking: local.prefs.showThinking,
        analyticsEnabled: local.prefs.analyticsEnabled,
        desktopNotifications: local.prefs.desktopNotifications,
        linkOpenDestination: local.prefs.linkOpenDestination,
        askBeforeOpeningLinks: local.prefs.askBeforeOpeningLinks,
        defaultModel: local.prefs.defaultModel,
        modelVariant: local.prefs.modelVariant,
        shell: {
          sidebar: shellConfig.sidebar,
          statusBar: shellConfig.statusBar,
          docsButton: shellConfig.docsButton,
          feedbackButton: shellConfig.feedbackButton,
          starterCards: shellConfig.starterCards,
          modelPicker: shellConfig.modelPicker,
          browser: shellConfig.browser,
          addWorkspace: shellConfig.addWorkspace,
          notifications: shellConfig.notifications,
        },
        agentControl: desktopConfig.config.agentControl ?? null,
      }),
    },
    {
      id: "app.settings.update",
      label: "Change desktop settings",
      description: "Change the app's theme, language, thinking display, analytics, desktop notifications, link handling, default model and visible shell controls.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [
        { name: "theme", type: "string", description: "light, dark or system." },
        { name: "language", type: "string", description: "A supported app language code, such as en or es." },
        { name: "showThinking", type: "boolean", description: "Show the agent's thinking while it works." },
        { name: "analyticsEnabled", type: "boolean", description: "Send anonymous product analytics." },
        { name: "desktopNotifications", type: "string", description: "off, important or all." },
        { name: "linkOpenDestination", type: "string", description: "idexalos or external." },
        { name: "askBeforeOpeningLinks", type: "boolean", description: "Ask before opening links outside the app." },
        { name: "defaultModel", type: "object", description: "A { providerID, modelID } object for new conversations." },
        { name: "modelVariant", type: "string", description: "The preferred model variant, empty for the provider default." },
        { name: "shell", type: "object", description: "Visible shell controls: sidebar, statusBar, docsButton, feedbackButton, starterCards, modelPicker, browser, addWorkspace, notifications." },
      ],
      permission: settingsPermission,
      execute: (args) => {
        const input = parseArgs(settingsUpdateSchema, args);
        if (input.theme) setThemeMode(input.theme);
        if (input.language) setLocale(input.language);
        local.setPrefs((previous) => ({
          ...previous,
          ...(input.showThinking !== undefined ? { showThinking: input.showThinking } : {}),
          ...(input.analyticsEnabled !== undefined ? { analyticsEnabled: input.analyticsEnabled } : {}),
          ...(input.desktopNotifications !== undefined ? { desktopNotifications: input.desktopNotifications } : {}),
          ...(input.linkOpenDestination !== undefined ? { linkOpenDestination: input.linkOpenDestination } : {}),
          ...(input.askBeforeOpeningLinks !== undefined ? { askBeforeOpeningLinks: input.askBeforeOpeningLinks } : {}),
          ...(input.defaultModel !== undefined ? { defaultModel: input.defaultModel } : {}),
          ...(input.modelVariant !== undefined ? { modelVariant: input.modelVariant } : {}),
        }));
        if (input.shell) updateShellConfig(input.shell);
        return { ok: true, changed: Object.keys(input) };
      },
    },
    {
      id: "app.proxy.list",
      label: "List network proxies",
      description: "List the saved proxy profiles, the active profile and the guard's bandwidth use. Credentials are never returned.",
      kind: "query",
      effects: { data: "read", ui: "none", external: false },
      sideEffect: "none",
      permission: proxyPermission,
      disabled: !isDesktopRuntime(),
      execute: async () => {
        requireProxyPlan(entitlements);
        return proxyAction(() => desktopBridge.proxyState());
      },
    },
    {
      id: "app.proxy.save",
      label: "Save a network proxy",
      description: "Add a proxy profile, or update one by id, and optionally apply it to the app. The local guard encrypts its password.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [
        { name: "name", type: "string", required: true, description: "A name for the profile." },
        { name: "kind", type: "string", required: true, description: "static_residential, rotating_residential, datacenter, mobile or custom." },
        { name: "protocol", type: "string", required: true, description: "http, https or socks5." },
        { name: "host", type: "string", required: true, description: "Proxy host." },
        { name: "port", type: "number", required: true, description: "Proxy port." },
        { name: "username", type: "string", description: "Proxy username." },
        { name: "password", type: "string", description: "Proxy password; omit to keep the saved one." },
        { name: "rotationUrl", type: "string", description: "Provider URL that rotates the exit IP." },
        { name: "alignIdentity", type: "boolean", description: "Align browser timezone, locale and user agent with the proxy." },
        { name: "applyToApp", type: "boolean", description: "Apply the profile to the app and built-in browser." },
      ],
      permission: proxyPermission,
      disabled: !isDesktopRuntime(),
      execute: async (args) => {
        requireProxyPlan(entitlements);
        const input = parseArgs(proxySaveSchema, args);
        const current = await proxyAction(() => desktopBridge.proxyState());
        const existing = input.id ? current.profiles.find((profile) => profile.id === input.id) : undefined;
        if (!existing && entitlements.limits.profiles !== null && current.profiles.length >= entitlements.limits.profiles) {
          throw new Error(`This plan allows ${entitlements.limits.profiles} proxy profile${entitlements.limits.profiles === 1 ? "" : "s"}.`);
        }
        if ((input.kind === "static_residential" || input.kind === "mobile") && !entitlements.features.residential) {
          throw new Error("This plan does not include residential proxies.");
        }
        if (input.kind === "rotating_residential" && !entitlements.features.rotation) {
          throw new Error("This plan does not include rotating proxies.");
        }
        const alignIdentity = entitlements.features.fingerprint ? input.alignIdentity : false;
        const rotationUrl = entitlements.features.rotation ? input.rotationUrl : "";
        const profile: ProxyProfileInput = {
          ...(input.id ? { id: input.id } : {}),
          name: input.name,
          kind: input.kind,
          protocol: input.protocol,
          host: input.host,
          port: input.port,
          username: input.username,
          ...(input.password ? { password: input.password } : {}),
          country: input.country,
          timezone: input.timezone,
          locale: input.locale,
          latitude: input.latitude,
          longitude: input.longitude,
          userAgent: input.userAgent,
          rotationUrl,
          providerId: input.providerId,
          providerSlug: input.providerSlug,
          notes: input.notes,
          applyToApp: input.applyToApp,
          alignIdentity,
        };
        return proxyAction(() => desktopBridge.proxySave(profile));
      },
    },
    {
      id: "app.proxy.delete",
      label: "Delete a network proxy",
      description: "Delete a saved proxy profile by id. The active profile is turned off first by the local proxy manager.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [{ name: "id", type: "string", required: true, description: "The proxy profile id." }],
      permission: proxyPermission,
      disabled: !isDesktopRuntime(),
      execute: async (args) => {
        requireProxyPlan(entitlements);
        const input = parseArgs(z.object({ id: z.string().trim().min(1) }).strict(), args);
        return proxyAction(() => desktopBridge.proxyDelete(input.id));
      },
    },
    {
      id: "app.proxy.activate",
      label: "Activate a network proxy",
      description: "Turn on a saved proxy profile and route app traffic through it.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [{ name: "id", type: "string", required: true, description: "The proxy profile id." }],
      permission: proxyPermission,
      disabled: !isDesktopRuntime(),
      execute: async (args) => {
        requireProxyPlan(entitlements);
        const input = parseArgs(z.object({ id: z.string().trim().min(1) }).strict(), args);
        const current = await proxyAction(() => desktopBridge.proxyState());
        const profile = current.profiles.find((entry) => entry.id === input.id);
        if (!profile) throw new Error("That proxy profile does not exist.");
        if ((profile.kind === "static_residential" || profile.kind === "mobile") && !entitlements.features.residential) {
          throw new Error("This plan does not include residential proxies.");
        }
        if (profile.kind === "rotating_residential" && !entitlements.features.rotation) {
          throw new Error("This plan does not include rotating proxies.");
        }
        return proxyAction(() => desktopBridge.proxyActivate(input.id));
      },
    },
    {
      id: "app.proxy.disable",
      label: "Turn off the network proxy",
      description: "Stop routing app traffic through a proxy and restore the system network settings.",
      sideEffect: "mutation",
      // No plan key: turning a proxy off stays available when a plan or the
      // global proxy entitlement is revoked, so an agent can always stop
      // traffic that would otherwise keep leaving through the proxy. The
      // organization's agent permission still gates the action.
      permission: { action: "proxy" },
      disabled: !isDesktopRuntime(),
      execute: async () => proxyAction(() => desktopBridge.proxyDisable()),
    },
    {
      id: "app.proxy.test",
      label: "Test a network proxy",
      description: "Make a request through a saved proxy and return its exit IP, country and latency.",
      sideEffect: "external",
      requiresArgs: true,
      args: [{ name: "id", type: "string", required: true, description: "The proxy profile id." }],
      permission: proxyPermission,
      disabled: !isDesktopRuntime(),
      execute: async (args) => {
        requireProxyPlan(entitlements);
        const input = parseArgs(z.object({ id: z.string().trim().min(1) }).strict(), args);
        return proxyAction(() => desktopBridge.proxyTest(input.id));
      },
    },
    {
      id: "app.proxy.rotate",
      label: "Rotate a proxy exit",
      description: "Ask the proxy provider to rotate the exit IP, then test the profile.",
      sideEffect: "external",
      requiresArgs: true,
      args: [{ name: "id", type: "string", required: true, description: "The rotating proxy profile id." }],
      permission: proxyPermission,
      disabled: !isDesktopRuntime(),
      execute: async (args) => {
        requireProxyPlan(entitlements);
        requireFeature("rotation", "This plan does not include proxy rotation.");
        const input = parseArgs(z.object({ id: z.string().trim().min(1) }).strict(), args);
        return proxyAction(() => desktopBridge.proxyRotate(input.id));
      },
    },
    {
      id: "app.proxy.prewarm",
      label: "Prewarm the browser through a proxy",
      description: "Open the built-in browser through the active proxy before the agent's first reply, within the plan's prewarm allowance.",
      sideEffect: "external",
      args: [{ name: "url", type: "string", description: "Optional URL to open while warming the browser." }],
      permission: proxyPermission,
      disabled: !isDesktopRuntime(),
      execute: async (args) => {
        requireProxyPlan(entitlements);
        requireFeature("prewarm", "This plan does not include browser prewarm.");
        const input = parseArgs(z.object({ url: z.string().url().optional() }).strict(), args);
        return proxyAction(() => desktopBridge.browserPrewarm(input.url ? { url: input.url } : undefined));
      },
    },
    {
      id: "app.proxy.set_limit",
      label: "Set the proxy bandwidth limit",
      description: "Set the monthly proxy bandwidth limit in gigabytes. Values above the plan's limit are rejected.",
      sideEffect: "mutation",
      args: [{ name: "gigabytes", type: "number", description: "Monthly limit in GB, or null for the plan maximum." }],
      permission: proxyPermission,
      disabled: !isDesktopRuntime(),
      execute: async (args) => {
        requireProxyPlan(entitlements);
        const input = parseArgs(z.object({ gigabytes: z.number().min(0).nullable().optional() }).strict(), args);
        const requested = input.gigabytes ?? entitlements.limits.bandwidthGb;
        if (requested !== null && entitlements.limits.bandwidthGb !== null && requested > entitlements.limits.bandwidthGb) {
          throw new Error(`This plan allows ${entitlements.limits.bandwidthGb} GB of proxy bandwidth per month.`);
        }
        return proxyAction(() => desktopBridge.proxySetLimit(requested));
      },
    },
    {
      id: "app.extensions.list",
      label: "List workspace extensions",
      description: "List the skills, MCP servers and plugins installed in the selected workspace.",
      kind: "query",
      effects: { data: "read", ui: "none", external: false },
      sideEffect: "none",
      permission: extensionsPermission,
      execute: () => listExtensions(),
    },
    {
      id: "app.extensions.skill.install",
      label: "Install a workspace skill",
      description: "Install or replace a skill in the selected workspace from its SKILL.md content.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [
        { name: "name", type: "string", required: true, description: "The skill name." },
        { name: "content", type: "string", required: true, description: "The skill's SKILL.md content." },
        { name: "description", type: "string", description: "A short description for listings." },
      ],
      permission: extensionsPermission,
      execute: (args) => installSkill(parseArgs(skillInstallSchema, args)),
    },
    {
      id: "app.extensions.skill.remove",
      label: "Remove a workspace skill",
      description: "Remove a skill from the selected workspace by name.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [{ name: "name", type: "string", required: true, description: "The skill name." }],
      permission: extensionsPermission,
      execute: (args) => removeSkill(parseArgs(extensionNameSchema, args).name),
    },
    {
      id: "app.extensions.mcp.install",
      label: "Install a workspace MCP server",
      description: "Add a local or remote MCP server to the selected workspace. The config uses idexal Os's mcp shape, with type remote and url, or type local and command.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [
        { name: "name", type: "string", required: true, description: "A server name using letters, numbers, dashes or underscores." },
        { name: "config", type: "object", required: true, description: "The idexal Os MCP server config." },
      ],
      permission: extensionsPermission,
      execute: (args) => {
        const input = parseArgs(mcpInstallSchema, args);
        const name = validateMcpServerName(input.name);
        return installMcp(name, input.config);
      },
    },
    {
      id: "app.extensions.mcp.remove",
      label: "Remove a workspace MCP server",
      description: "Remove an MCP server from the selected workspace by name.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [{ name: "name", type: "string", required: true, description: "The MCP server name." }],
      permission: extensionsPermission,
      execute: (args) => removeMcp(validateMcpServerName(parseArgs(extensionNameSchema, args).name)),
    },
    {
      id: "app.extensions.plugin.install",
      label: "Install a workspace plugin",
      description: "Add an idexal Os plugin to the selected workspace by package spec, path or URL.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [{ name: "spec", type: "string", required: true, description: "The plugin package spec or path." }],
      permission: extensionsPermission,
      execute: (args) => installPlugin(parseArgs(pluginInstallSchema, args).spec),
    },
    {
      id: "app.extensions.plugin.remove",
      label: "Remove a workspace plugin",
      description: "Remove a plugin from the selected workspace's plugin list by package spec or name.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [{ name: "name", type: "string", required: true, description: "The plugin package spec or name." }],
      permission: extensionsPermission,
      execute: (args) => removePlugin(parseArgs(extensionNameSchema, args).name),
    },
    {
      id: "app.providers.list",
      label: "List AI providers",
      description: "List the providers the workspace knows, with connected state and model ids. API keys are never returned.",
      kind: "query",
      effects: { data: "read", ui: "none", external: false },
      sideEffect: "none",
      permission: settingsPermission,
      execute: () => providers.map((provider) => ({
        id: provider.id,
        name: provider.name,
        connected: providerConnectedIds.includes(provider.id),
        models: Object.keys(provider.models ?? {}),
      })),
    },
    {
      id: "app.providers.set_key",
      label: "Set an AI provider API key",
      description: "Store an API key for an existing provider and refresh its models. The key is written to the engine's auth storage.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [
        { name: "providerId", type: "string", required: true, description: "The provider id." },
        { name: "apiKey", type: "string", required: true, description: "The provider API key." },
      ],
      permission: providerPermission,
      execute: async (args) => {
        const input = parseArgs(providerKeySchema, args);
        return providerAuthStore.submitProviderApiKey(input.providerId, input.apiKey);
      },
    },
    {
      id: "app.providers.add_custom",
      label: "Add a custom AI provider",
      description: "Add an OpenAI- or Anthropic-compatible provider with its base URL, optional API key and model ids, then connect it.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [
        { name: "family", type: "string", required: true, description: "openai or anthropic." },
        { name: "name", type: "string", required: true, description: "The display name." },
        { name: "baseUrl", type: "string", required: true, description: "The OpenAI- or Anthropic-compatible endpoint." },
        { name: "apiKey", type: "string", description: "The provider API key, when the endpoint needs one." },
        { name: "models", type: "array", required: true, description: "One or more model ids." },
      ],
      permission: providerPermission,
      execute: (args) => {
        const input = parseArgs(customProviderSchema, args);
        const provider: CustomProviderInput = {
          family: input.family,
          name: input.name,
          baseUrl: input.baseUrl,
          ...(input.apiKey ? { apiKey: input.apiKey } : {}),
          models: input.models,
        };
        return providerAuthStore.addCustomProvider(provider);
      },
    },
    {
      id: "app.providers.disconnect",
      label: "Disconnect an AI provider",
      description: "Remove a provider's stored credentials from this workspace. Custom provider definitions are removed with their key.",
      sideEffect: "mutation",
      requiresArgs: true,
      args: [{ name: "providerId", type: "string", required: true, description: "The provider id." }],
      permission: providerPermission,
      execute: (args) => providerAuthStore.disconnectProvider(
        parseArgs(z.object({ providerId: z.string().trim().min(1) }).strict(), args).providerId,
      ),
    },
  ];

  useControlActions(actions);

  return null;
}
