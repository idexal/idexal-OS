import type { ServerConfig } from "./types.js";
import { createWorkspaceKvStore, isRecord } from "./workspace-kv-store.js";

function normalizeIdexalOsWorkspaceConfig(value: unknown): Record<string, unknown> {
  return isRecord(value) ? value : {};
}

function parseIdexalOsWorkspaceConfig(configJson: string): Record<string, unknown> {
  try {
    return normalizeIdexalOsWorkspaceConfig(JSON.parse(configJson));
  } catch {
    return {};
  }
}

const idexalOsWorkspaceConfigStore = createWorkspaceKvStore<Record<string, unknown>>({
  tableName: "idexal_os_workspace_configs",
  valueColumn: "config_json",
  parse: parseIdexalOsWorkspaceConfig,
  serialize: (value) => JSON.stringify(value),
});

export async function readIdexalOsWorkspaceConfig(config: ServerConfig, workspaceId: string): Promise<Record<string, unknown>> {
  return await idexalOsWorkspaceConfigStore.get(config, workspaceId) ?? {};
}

export async function writeIdexalOsWorkspaceConfig(
  config: ServerConfig,
  workspaceId: string,
  updater: (current: Record<string, unknown>) => Record<string, unknown>,
): Promise<Record<string, unknown>> {
  const next = normalizeIdexalOsWorkspaceConfig(updater(await readIdexalOsWorkspaceConfig(config, workspaceId)));
  await idexalOsWorkspaceConfigStore.set(config, workspaceId, next);
  return next;
}

export async function hasIdexalOsWorkspaceConfig(
  config: ServerConfig,
  workspaceId: string,
): Promise<boolean> {
  return idexalOsWorkspaceConfigStore.has(config, workspaceId);
}

/**
 * Seed the DB-backed idexalos config for a workspace if no row exists yet.
 * Used at workspace creation and as the migrate-on-read landing spot for
 * legacy `.opencode/idexal-os.json` files. No-op when a row is already present,
 * so it never clobbers live provisioning state.
 */
export async function seedIdexalOsWorkspaceConfigIfEmpty(
  config: ServerConfig,
  workspaceId: string,
  seed: Record<string, unknown>,
): Promise<Record<string, unknown>> {
  if (await hasIdexalOsWorkspaceConfig(config, workspaceId)) {
    return readIdexalOsWorkspaceConfig(config, workspaceId);
  }
  return writeIdexalOsWorkspaceConfig(config, workspaceId, () => seed);
}

export function mergeIdexalOsWorkspaceConfigs(
  legacy: Record<string, unknown>,
  stored: Record<string, unknown>,
): Record<string, unknown> {
  return { ...legacy, ...stored };
}
