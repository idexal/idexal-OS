import { basename, dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

declare global {
  namespace NodeJS {
    interface Process {
      resourcesPath?: string;
    }
  }
}

function resourcesPathFromAppAsarPath(path: string): string | null {
  const match = /[\\/]app\.asar(?:[\\/]|$)/.exec(path);
  return match ? path.slice(0, match.index) : null;
}

export function idexalOsPluginPath(name: string, here?: string): string {
  const pluginDir = process.env.IDEXAL_EXTENSIONS_PLUGIN_DIR;
  if (pluginDir) {
    return join(pluginDir, `${name}.js`);
  }

  here = here ?? dirname(fileURLToPath(import.meta.url));
  const resourcesPath = resourcesPathFromAppAsarPath(here);
  if (resourcesPath) {
    const electronResourcesPath = process.resourcesPath?.includes("app.asar") ? resourcesPath : process.resourcesPath?.trim();
    return join(electronResourcesPath || resourcesPath, "opencode-plugins", `${name}.js`);
  }

  const extension = basename(here) === "dist" ? "js" : "ts";
  return join(here, "opencode-plugins", `${name}.${extension}`);
}

export const idexalOsExtensionsPreviewPluginPath = () => idexalOsPluginPath("idexal-os-extensions-preview");
export const idexalOsChromeDevtoolsPluginPath = () => idexalOsPluginPath("idexal-os-chrome-devtools");
export const idexalOsCapabilitiesKnowledgePluginPath = () => idexalOsPluginPath("idexal-os-capabilities-knowledge");
export const idexalOsAnthropicAdaptiveThinkingPluginPath = () => idexalOsPluginPath("idexal-os-anthropic-adaptive-thinking");
export const idexalOsAnthropicToolSchemaPluginPath = () => idexalOsPluginPath("idexal-os-anthropic-tool-schema");
export const idexalOsOfficeAttachmentsPluginPath = () => idexalOsPluginPath("idexal-os-office-attachments");
export const idexalOsSpreadsheetsPluginPath = () => idexalOsPluginPath("idexal-os-spreadsheets");
export const idexalOsPdfAttachmentsPluginPath = () => idexalOsPluginPath("idexal-os-pdf-attachments");
export const idexalOsMemoryPluginPath = () => idexalOsPluginPath("idexal-os-memory");
export const idexalOsTitleRecoveryPluginPath = () => idexalOsPluginPath("idexal-os-title-recovery");
export const idexalOsGatewayQuotaPluginPath = () => idexalOsPluginPath("idexal-os-gateway-quota");
export const idexalOsGatewayQuotaV2PluginPath = () => idexalOsPluginPath("idexal-os-gateway-quota-v2");
export const idexalOsProviderSyncV2PluginPath = () => {
  const path = idexalOsPluginPath("idexal-os-provider-sync-v2");
  // The provider-only entry belongs to the shared plugin package. Bundle it
  // alongside server plugins for packaged apps; load its source in Bun dev.
  return path.endsWith(".ts")
    ? join(dirname(fileURLToPath(import.meta.url)), "../../../packages/opencode-plugin/server.ts")
    : path;
};
export const idexalOsContextV2PluginPath = () => idexalOsPluginPath("idexal-os-context-v2");
export const idexalOsProviderFiltersV2PluginPath = () => idexalOsPluginPath("idexal-os-provider-filters-v2");
export const idexalOsMcpResultsV2PluginPath = () => idexalOsPluginPath("idexal-os-mcp-results-v2");
export const idexalOsMcpReadinessV2PluginPath = () => idexalOsPluginPath("idexal-os-mcp-readiness-v2");
export const idexalOsAgentPermissionsV2PluginPath = () => idexalOsPluginPath("idexal-os-agent-permissions-v2");
