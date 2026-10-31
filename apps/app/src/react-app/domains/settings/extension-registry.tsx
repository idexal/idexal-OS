/** @jsxImportSource react */
import type { ReactNode } from "react";
import type { McpDirectoryInfo } from "../../../app/constants";
import { extensionContribution } from "../../../app/extensions";
import type { IdexalOsServerClient } from "../../../app/lib/idexal-os-server";
import type { LocalProviderInstallInput, LocalProviderSyncInput } from "./openai-image-extension";

/**
 * Context bag that the settings route passes to extension config factories.
 * Each extension picks what it needs; unused fields are ignored.
 */
export type ExtensionConfigContext = {
  idexalOsServerClient?: IdexalOsServerClient | null;
  hostIdexalOsServerClient?: IdexalOsServerClient | null;
  restartLocalServer?: () => Promise<boolean>;
  localProvider: {
    busy: boolean;
    status: string | null;
    error: string | null;
    onInstall: (input: LocalProviderInstallInput) => void | Promise<void>;
    onSync: (input: LocalProviderSyncInput) => void | Promise<void>;
  };
};

export type ExtensionConfigFactory = (ctx: ExtensionConfigContext) => ReactNode;

const registry = new Map<string, ExtensionConfigFactory>();

export function registerExtensionConfig(id: string, factory: ExtensionConfigFactory) {
  registry.set(id, factory);
}

function configRegistryId(entry: McpDirectoryInfo) {
  return extensionContribution(entry.extensionManifest, "settings-panel")?.ref ?? entry.serverName ?? entry.name;
}

export function getExtensionConfigSlot(
  entry: McpDirectoryInfo,
  ctx: ExtensionConfigContext,
): ReactNode | null {
  const id = configRegistryId(entry);
  const factory = registry.get(id);
  return factory ? factory(ctx) : null;
}
