import { legacyExecutionPermissions } from "./managed-policy-rules.js";
import { DESKTOP_POLICY_ENFORCEMENT_ENABLED, desktopCapabilityConfig } from "@idexal/types/den/desktop-policies-runtime";
import { materializeLegacyFastProviders } from "@idexal/types/cloud-model-fast";
import { isManagedPolicyPlugin } from "./managed-policy-plugin.js";
/**
 * Runtime OpenCode configuration injected via a server-managed config file
 * passed to the engine as OPENCODE_CONFIG.
 *
 * This is the single source of truth for the idexalos agent definition,
 * plugins, and any other config that should be injected at runtime rather
 * than written to the user's own config files. Both cli.ts and embedded.ts
 * use this.
 *
 * The engine re-reads the OPENCODE_CONFIG file from disk on every instance
 * rebuild (e.g. /instance/dispose), so the file is synchronized on every
 * runtime-DB write — unlike the previous OPENCODE_CONFIG_CONTENT env var,
 * which was frozen at spawn and reverted MCP state on each dispose.
 */
import { mkdir, readFile, rename, writeFile } from "node:fs/promises";
import { join } from "node:path";
import { randomUUID } from "node:crypto";
import {
  idexalOsExtensionsPreviewPluginPath,
  idexalOsCapabilitiesKnowledgePluginPath,
  idexalOsAnthropicAdaptiveThinkingPluginPath,
  idexalOsAnthropicToolSchemaPluginPath,
  idexalOsTitleRecoveryPluginPath,
  idexalOsGatewayQuotaPluginPath,
  idexalOsOfficeAttachmentsPluginPath,
  idexalOsSpreadsheetsPluginPath,
  idexalOsChromeDevtoolsPluginPath,
  idexalOsPdfAttachmentsPluginPath,
  idexalOsMemoryPluginPath,
} from "./idexal-os-extensions-plugin-path.js";
import type { ServerConfig } from "./types.js";
import { runtimeStorageDir } from "./runtime-db.js";
import {
  onRuntimeOpencodeConfigWrite,
  isEngineGlobalRuntimeConfigId,
  readGlobalRuntimeOpencodeConfig,
  runtimeDisabledProviderList,
  runtimeMcpMap,
  runtimeProviderMap,
  runtimePluginList,
  type RuntimeOpencodeConfig,
} from "./runtime-opencode-config-store.js";
import { CONNECT_MCP_SERVER_NAME_PREFIX } from "./connect-mcp-server-catalog.js";
import { IDEXAL_AGENT_PROMPT } from "./idexal-os-agent-prompt.js";

export async function buildIdexalOsRuntimeConfigObject(
  config?: ServerConfig,
): Promise<Record<string, unknown>> {
  // Workspace-independent by design: the injected engine config file is
  // rendered from the ENGINE_GLOBAL runtime row plus static built-ins only,
  // so workspace activation rewrites identical bytes and never varies the
  // engine-pool fingerprint. Per-workspace MCPs reach the engine through the
  // dynamic push path instead.
  const runtimeConfig = config ? await readGlobalRuntimeOpencodeConfig(config) : {};
  return buildIdexalOsRuntimeConfigObjectFromSnapshot(runtimeConfig);
}

export function buildIdexalOsRuntimeConfigObjectFromSnapshot(
  runtimeConfig: RuntimeOpencodeConfig,
): Record<string, unknown> {
  if (!DESKTOP_POLICY_ENFORCEMENT_ENABLED) {
    // Only model access (the AI Gateway's "Who can use models") is enforced: keep allowCustomProviders from the verified
    // policy (cleared on sign-out) and drop execution rules and every other desktop policy.
    const { managedPolicy, ...localConfig } = runtimeConfig;
    runtimeConfig = managedPolicy ? { ...localConfig, managedPolicy: desktopCapabilityConfig(managedPolicy) } : localConfig;
  }
  const disabledProviders = runtimeDisabledProviderList(runtimeConfig);
  const permissions = legacyExecutionPermissions(runtimeConfig.managedPolicy?.execution);
  const { managedPolicy: _managedPolicy, ...engineConfig } = runtimeConfig;
  const provider = materializeLegacyFastProviders(runtimeProviderMap(runtimeConfig));
  return {
    ...engineConfig,
    ...(runtimeConfig.managedPolicy?.allowCustomProviders === false ? { enabled_providers: [
      ...Object.keys(provider).filter((id) => /^(?:lpr_|ipr_|idexalos$)/i.test(id)),
      ...(runtimeConfig.managedPolicy.allowZenModel !== false ? ["opencode"] : []),
    ] } : {}),
    permission: { ...engineConfig.permission, ...permissions },
    default_agent: runtimeConfig.default_agent ?? "idexalos",
    agent: {
      idexalos: {
        description: "idexal Os default agent",
        mode: "primary",
        temperature: 0.2,
        prompt: IDEXAL_AGENT_PROMPT,
        permission: {
          ...permissions,
          skill: {
            // idexal Os supplies its own current skill routing and no longer
            // supports these engine or legacy workspace skills.
            "customize-opencode": "deny",
            "get-started": "deny",
            "command-creator": "deny",
            "agent-creator": "deny",
            "plugin-creator": "deny",
          },
        },
      },
    },
    plugin: [
      idexalOsChromeDevtoolsPluginPath(),
      // Registration order is prompt order: the knowledge plugin appends the
      // operating rules first, then the extensions plugin adds app-control
      // mechanics, live Connect steering, and the remote skill and Automation
      // catalogs, so rules precede state and state precedes data.
      idexalOsCapabilitiesKnowledgePluginPath(),
      idexalOsExtensionsPreviewPluginPath(),
      idexalOsOfficeAttachmentsPluginPath(),
      idexalOsSpreadsheetsPluginPath(),
      idexalOsPdfAttachmentsPluginPath(),
      idexalOsMemoryPluginPath(),
      idexalOsAnthropicAdaptiveThinkingPluginPath(),
      idexalOsAnthropicToolSchemaPluginPath(),
      idexalOsTitleRecoveryPluginPath(),
      idexalOsGatewayQuotaPluginPath(),
      ...runtimePluginList(runtimeConfig).filter((plugin) => !isManagedPolicyPlugin(plugin)),
    ],
    ...(disabledProviders.length ? { disabled_providers: disabledProviders } : {}),
    mcp: Object.fromEntries(Object.entries(runtimeMcpMap(runtimeConfig))
      .filter(([name]) => !name.startsWith(CONNECT_MCP_SERVER_NAME_PREFIX))),
    ...(Object.keys(provider).length ? { provider } : {}),
  };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function stableJsonValue(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(stableJsonValue);
  if (!isRecord(value)) return value;
  return Object.fromEntries(
    Object.keys(value)
      .sort()
      .map((key) => [key, stableJsonValue(value[key])]),
  );
}

function stableStringify(value: unknown): string {
  return JSON.stringify(stableJsonValue(value));
}

export async function buildIdexalOsRuntimeConfig(config?: ServerConfig): Promise<string> {
  return stableStringify(await buildIdexalOsRuntimeConfigObject(config));
}

export function idexalOsRuntimeConfigFilePath(config: ServerConfig): string {
  return join(runtimeStorageDir(config), "runtime-opencode-config.json");
}

// Serialize file writes per path so a slow older write can never land after
// (and clobber) a newer one. Content is built inside the queued job so each
// job reads the latest runtime-DB state.
export interface IdexalOsRuntimeConfigWriteResult {
  path: string;
  changed: boolean;
}

const fileWriteQueue = new Map<string, Promise<IdexalOsRuntimeConfigWriteResult>>();

/**
 * Rebuild the engine-visible runtime config file from the runtime DB.
 * Atomic (temp file + rename) so the engine never reads a partial file
 * mid-dispose.
 */
export async function writeIdexalOsRuntimeConfigFile(
  config: ServerConfig,
): Promise<IdexalOsRuntimeConfigWriteResult> {
  const path = idexalOsRuntimeConfigFilePath(config);
  const job = async () => {
    const content = await buildIdexalOsRuntimeConfig(config);
    const current = await readFile(path, "utf8").catch(() => undefined);
    if (current === content) return { path, changed: false };
    await mkdir(runtimeStorageDir(config), { recursive: true });
    const tmp = `${path}.${randomUUID()}.tmp`;
    await writeFile(tmp, content, "utf8");
    await rename(tmp, path);
    return { path, changed: true };
  };
  const previous = fileWriteQueue.get(path) ?? Promise.resolve();
  const next = previous.then(job, job);
  fileWriteQueue.set(path, next);
  return await next;
}

/**
 * Keep the runtime config file in sync with the runtime DB so every engine
 * instance rebuild reads fresh state instead of a spawn-time snapshot.
 * Returns an unsubscribe function.
 */
export function keepIdexalOsRuntimeConfigFileFresh(config: ServerConfig): () => void {
  return onRuntimeOpencodeConfigWrite((writeConfig, writtenWorkspaceId) => {
    if (!isEngineGlobalRuntimeConfigId(writtenWorkspaceId)) return;
    void writeIdexalOsRuntimeConfigFile(writeConfig).catch(() => undefined);
  });
}
