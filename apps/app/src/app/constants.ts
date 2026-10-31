import type { ModelRef, SuggestedPlugin } from "./types";
import { t } from "../i18n";
import { getDenMcpUrl } from "./lib/den";
import { canonicalMcpServerName } from "./mcp";
import {
  BUILT_IN_IDEXAL_EXTENSION_MANIFESTS,
  extensionContribution,
  extensionResource,
  isTrustedBuiltInExtension,
  type IdexalOsExtensionManifest,
  type IdexalOsExtensionPlatform,
} from "./extensions";

export const MODEL_PREF_KEY = "idexal-os.defaultModel";
export const SESSION_MODEL_PREF_KEY = "idexal-os.sessionModels";
export const THINKING_PREF_KEY = "idexal-os.showThinking";
export const VARIANT_PREF_KEY = "idexal-os.modelVariant";
export { LANGUAGE_PREF_KEY } from "../i18n";

export const DEFAULT_MODEL: ModelRef = {
  providerID: "opencode",
  modelID: "big-pickle",
};

/**
 * Curated OpenCode plugins. Each package is published on npm and declares an
 * @opencode-ai/plugin dependency; only add packages verified that way.
 */
export const SUGGESTED_PLUGINS: SuggestedPlugin[] = [
  {
    get name() { return t("plugins.suggested_dcp_name"); },
    packageName: "@tarquinen/opencode-dcp",
    get description() { return t("plugins.suggested_dcp_desc"); },
    tags: ["context", "tokens"],
  },
  {
    get name() { return t("plugins.suggested_mem_name"); },
    packageName: "opencode-mem",
    get description() { return t("plugins.suggested_mem_desc"); },
    tags: ["memory", "local"],
  },
  {
    get name() { return t("plugins.suggested_resume_name"); },
    packageName: "opencode-auto-resume",
    get description() { return t("plugins.suggested_resume_desc"); },
    tags: ["reliability", "streaming"],
  },
  {
    get name() { return t("plugins.suggested_fallback_name"); },
    packageName: "@smart-coders-hq/opencode-model-fallback",
    get description() { return t("plugins.suggested_fallback_desc"); },
    tags: ["models", "reliability"],
  },
  {
    get name() { return t("plugins.suggested_rag_name"); },
    packageName: "opencode-rag-plugin",
    get description() { return t("plugins.suggested_rag_desc"); },
    tags: ["search", "local"],
  },
  {
    get name() { return t("plugins.suggested_pty_name"); },
    packageName: "opencode-pty",
    get description() { return t("plugins.suggested_pty_desc"); },
    tags: ["terminal", "tools"],
  },
];

export type ExtensionKind = "mcp" | "plugin" | "skill" | "ui-control" | "extension";

export type McpDirectoryInfo = {
  id?: string;
  /** Display name shown in the UI. */
  name: string;
  /** Safe server name for opencode.jsonc (alphanumeric, - and _ only). Auto-derived from name if omitted. */
  serverName?: string;
  description: string;
  url?: string;
  type?: "remote" | "local";
  command?: string[];
  oauth: boolean;
  /** Route OAuth through the local idexal Os gateway instead of delegating it to OpenCode. */
  managedOAuth?: boolean;
  /** Identifies MCP entries owned by idexal Os Connect instead of workspace configuration. */
  managedBy?: "idexal-os-connect";
  oauthConfig?: {
    clientId?: string;
    clientSecret?: string;
    scope?: string;
  };
  /** Extension category for UI grouping. Defaults to "mcp". */
  kind?: ExtensionKind;
  /** Simple Icons slug for brand icon (e.g. "notion", "stripe", "figma"). */
  iconSlug?: string;
  /** Direct icon URL (e.g. local SVG). Takes priority over iconSlug. */
  iconSrc?: string;
  /** Prompt inserted from the composer extension picker. */
  composerPrompt?: string;
  /** Whether idexal Os should show this extension as enabled before user setup. */
  defaultEnabled?: boolean;
  /** Whether idexal Os should hide this extension from the default catalog view. */
  defaultHidden?: boolean;
  /** Whether this extension is still in preview. */
  preview?: boolean;
  /** Normalized extension manifest backing this catalog entry. */
  extensionManifest?: IdexalOsExtensionManifest;
};

function extensionManifestToDirectoryInfo(manifest: IdexalOsExtensionManifest): McpDirectoryInfo {
  const mcpResource = extensionResource(manifest, "mcp");
  return {
    id: manifest.id,
    name: manifest.name,
    serverName: mcpResource?.mcpServerName ?? manifest.id,
    description: manifest.description,
    type: mcpResource?.command ? "local" : undefined,
    command: mcpResource?.command,
    oauth: false,
    kind: "extension",
    iconSlug: manifest.icon?.simpleIconSlug,
    iconSrc: manifest.icon?.src,
    composerPrompt: extensionContribution(manifest, "composer-prompt")?.prompt ?? manifest.composer?.prompt,
    defaultEnabled: manifest.defaultEnabled,
    defaultHidden: manifest.defaultHidden,
    preview: manifest.preview,
    extensionManifest: manifest,
  };
}

export function isBuiltInIdexalOsExtension(entry: Pick<McpDirectoryInfo, "kind" | "extensionManifest">): boolean {
  return entry.kind === "extension" && isTrustedBuiltInExtension(entry.extensionManifest);
}

/** Derive a safe MCP server name from a display name or explicit serverName. */
export function getMcpServerName(entry: McpDirectoryInfo): string {
  if (entry.serverName) return entry.serverName;
  return canonicalMcpServerName(entry.name);
}

export const MCP_QUICK_CONNECT: McpDirectoryInfo[] = [
  {
    get name() { return t("mcp.quick_connect_notion_title"); },
    serverName: "notion",
    get description() { return t("mcp.quick_connect_notion_desc"); },
    url: "https://mcp.notion.com/mcp",
    type: "remote",
    oauth: true,
    kind: "mcp",
    iconSlug: "notion",
    iconSrc: "/ext-notion.svg",
  },
  {
    get name() { return t("mcp.quick_connect_linear_title"); },
    serverName: "linear",
    get description() { return t("mcp.quick_connect_linear_desc"); },
    url: "https://mcp.linear.app/mcp",
    type: "remote",
    oauth: true,
    kind: "mcp",
    iconSlug: "linear",
    iconSrc: "/ext-linear.svg",
  },
  {
    get name() { return t("mcp.quick_connect_sentry_title"); },
    serverName: "sentry",
    get description() { return t("mcp.quick_connect_sentry_desc"); },
    url: "https://mcp.sentry.dev/mcp",
    type: "remote",
    oauth: true,
    kind: "mcp",
    iconSlug: "sentry",
    iconSrc: "/ext-sentry.svg",
  },
  {
    get name() { return t("mcp.quick_connect_stripe_title"); },
    serverName: "stripe",
    get description() { return t("mcp.quick_connect_stripe_desc"); },
    url: "https://mcp.stripe.com",
    type: "remote",
    oauth: true,
    kind: "mcp",
    iconSlug: "stripe",
    iconSrc: "/ext-stripe.svg",
  },
  {
    get name() { return t("mcp.quick_connect_context7_title"); },
    serverName: "context7",
    get description() { return t("mcp.quick_connect_context7_desc"); },
    url: "https://mcp.context7.com/mcp",
    type: "remote",
    oauth: false,
    kind: "mcp",
    iconSlug: "semanticscholar",
    iconSrc: "/ext-context7.svg",
  },
  {
    get name() { return t("mcp.quick_connect_figma_title"); },
    serverName: "figma",
    get description() { return t("mcp.quick_connect_figma_desc"); },
    url: "https://mcp.figma.com/mcp",
    type: "remote",
    oauth: true,
    kind: "mcp",
    iconSlug: "figma",
  },
  {
    get name() { return t("mcp.quick_connect_slack_title"); },
    serverName: "slack",
    get description() { return t("mcp.quick_connect_slack_desc"); },
    url: "https://mcp.slack.com/mcp",
    type: "remote",
    oauth: true,
    kind: "mcp",
    iconSlug: "slack",
    iconSrc: "/ext-slack.svg",
  },
  {
    get name() { return t("mcp.quick_connect_atlassian_title"); },
    serverName: "atlassian",
    get description() { return t("mcp.quick_connect_atlassian_desc"); },
    url: "https://mcp.atlassian.com/v1/mcp",
    type: "remote",
    oauth: true,
    kind: "mcp",
    iconSlug: "atlassian",
  },
  {
    get name() { return t("mcp.quick_connect_vercel_title"); },
    serverName: "vercel",
    get description() { return t("mcp.quick_connect_vercel_desc"); },
    url: "https://mcp.vercel.com",
    type: "remote",
    oauth: true,
    kind: "mcp",
    iconSlug: "vercel",
  },
  {
    get name() { return t("mcp.quick_connect_supabase_title"); },
    serverName: "supabase",
    get description() { return t("mcp.quick_connect_supabase_desc"); },
    url: "https://mcp.supabase.com/mcp",
    type: "remote",
    oauth: true,
    kind: "mcp",
    iconSlug: "supabase",
  },
  {
    get name() { return t("mcp.quick_connect_neon_title"); },
    serverName: "neon",
    get description() { return t("mcp.quick_connect_neon_desc"); },
    url: "https://mcp.neon.tech/mcp",
    type: "remote",
    oauth: true,
    kind: "mcp",
    iconSlug: "neon",
  },
  {
    get name() { return t("mcp.quick_connect_huggingface_title"); },
    serverName: "huggingface",
    get description() { return t("mcp.quick_connect_huggingface_desc"); },
    url: "https://huggingface.co/mcp",
    type: "remote",
    oauth: true,
    kind: "mcp",
    iconSlug: "huggingface",
  },
  {
    get name() { return t("mcp.quick_connect_deepwiki_title"); },
    serverName: "deepwiki",
    get description() { return t("mcp.quick_connect_deepwiki_desc"); },
    url: "https://mcp.deepwiki.com/mcp",
    type: "remote",
    oauth: false,
    kind: "mcp",
  },
  {
    get name() { return t("mcp.quick_connect_cloudflare_title"); },
    serverName: "cloudflare-docs",
    get description() { return t("mcp.quick_connect_cloudflare_desc"); },
    url: "https://docs.mcp.cloudflare.com/mcp",
    type: "remote",
    oauth: false,
    kind: "mcp",
    iconSlug: "cloudflare",
  },
  {
    get name() { return t("mcp.quick_connect_playwright_title"); },
    serverName: "playwright",
    get description() { return t("mcp.quick_connect_playwright_desc"); },
    command: ["npx", "-y", "@playwright/mcp@latest"],
    type: "local",
    oauth: false,
    kind: "mcp",
    iconSlug: "playwright",
  },
  {
    get name() { return t("mcp.quick_connect_idexal_os_cloud_title"); },
    serverName: "idexal-os-cloud",
    get description() { return t("mcp.quick_connect_idexal_os_cloud_desc"); },
    get url() {
      // The desktop app connects to the minimal, harness-facing surface
      // (/mcp/agent: search_capabilities + execute_capability only), not the
      // full catalog at bare /mcp. getDenMcpUrl heals stale web-app origins;
      // never at the web app's root (see
      // packages/docs/cloud/run-in-the-cloud/cloud-mcp.mdx).
      try {
        return `${getDenMcpUrl()}/agent`;
      } catch {
        return "https://api.os.idexal.com/mcp/agent";
      }
    },
    type: "remote",
    oauth: true,
    managedBy: "idexal-os-connect",
    kind: "mcp",
    iconSrc: "/idexal-os-mark.svg",
    // Auto-managed by the signed-in cloud reconciler (syncCloudControlMcp):
    // configured + enabled while signed in to idexal Os Cloud. Hidden from the
    // default catalog; "Show hidden" reveals it.
    defaultHidden: true,
  },
  {
    get name() { return t("mcp.quick_connect_idexal_os_ui_title"); },
    serverName: "idexal-os-ui",
    get description() { return t("mcp.quick_connect_idexal_os_ui_desc"); },
    type: "local",
    // Dev builds replace this with the local checkout path before writing config.
    command: ["npx", "-y", "idexal-os-ui-mcp"],
    oauth: false,
    kind: "ui-control",
    iconSrc: "/idexal-os-mark.svg",
    // Internal UI-control surface for agents driving the desktop app. Hidden
    // from the default catalog; "Show hidden" reveals it.
    defaultHidden: true,
  },
  ...BUILT_IN_IDEXAL_EXTENSION_MANIFESTS.map(extensionManifestToDirectoryInfo),
];

export const IDEXAL_EXTENSION_CATALOG = MCP_QUICK_CONNECT.filter((entry) => entry.kind === "extension");

export function resolveIdexalOsExtensionCatalogPlatform(
  platform: "web" | "desktop",
  os?: "macos" | "windows" | "linux",
): IdexalOsExtensionPlatform {
  if (platform === "web") return "web";
  if (os === "macos") return "darwin";
  if (os === "windows") return "windows";
  return "linux";
}

export function filterIdexalOsExtensionCatalogForPlatform<TEntry extends Pick<McpDirectoryInfo, "extensionManifest">>(
  entries: TEntry[],
  platform: IdexalOsExtensionPlatform,
): TEntry[] {
  return entries.filter((entry) => {
    const platforms = entry.extensionManifest?.platform;
    return !platforms || platforms.includes(platform);
  });
}
