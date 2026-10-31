// Owned here: reload vocabulary is part of the extension manifest contract.
// types.ts re-exports it for the rest of the app.
export type ReloadReason = "plugins" | "skills" | "mcp" | "config" | "agents" | "commands";

export type IdexalOsExtensionSourceFormat =
  | "agent-plugin"
  | "idexal-os-builtin"
  | "idexal-os-extension-manifest"
  | "claude-plugin"
  | "opencode-plugin"
  | "mcp-directory"
  | "manual";

export type IdexalOsExtensionSource = {
  format: IdexalOsExtensionSourceFormat;
  trusted: boolean;
  origin?: "builtin" | "den" | "workspace" | "local";
  reference?: string;
};

export type IdexalOsExtensionResourceType =
  | "skill"
  | "agent"
  | "command"
  | "tool"
  | "mcp"
  | "opencode-plugin"
  | "provider"
  | "hook"
  | "context"
  | "secret"
  | "file"
  | "local-service"
  | "native-binary";

export type IdexalOsExtensionResource = {
  type: IdexalOsExtensionResourceType;
  id: string;
  label?: string;
  description?: string;
  path?: string;
  command?: string[];
  envKey?: string;
  packageName?: string;
  providerId?: string;
  mcpServerName?: string;
  localCommandRef?: "idexal-os.uiMcp";
  required?: boolean;
};

export type IdexalOsExtensionContributionType =
  | "settings-panel"
  | "setup-instructions"
  | "composer-prompt"
  | "session-side-panel"
  | "session-rail-item"
  | "control-actions"
  | "server-route"
  | "native-capability"
  | "test-action";

export type IdexalOsExtensionContribution = {
  type: IdexalOsExtensionContributionType;
  ref?: string;
  label?: string;
  description?: string;
  prompt?: string;
  location?: "settings-detail" | "composer" | "session-right-pane" | "session-rail" | "server" | "native";
};

export type IdexalOsExtensionSetup = {
  instructions?: string;
  primaryCta?: string;
  secondaryCta?: string;
  requiredEnv?: string[];
  testActionRef?: string;
};

export type IdexalOsExtensionLifecycle = {
  reload?: ReloadReason[];
  detection?: string[];
};

// ---------------------------------------------------------------------------
// Enablement — declarative conditions for extension "active" state
// ---------------------------------------------------------------------------

export type EnablementConditionType =
  | "mcp-connected"
  | "provider-connected"
  | "env-set"
  | "toggle-enabled";

export type EnablementCondition = {
  type: EnablementConditionType;
  /** What to check — MCP server name, plugin id, env key, etc. */
  ref: string;
  /** Human-readable label shown in the UI. */
  label: string;
};

/** Result of evaluating a single enablement condition at runtime. */
export type EnablementResult = {
  condition: EnablementCondition;
  met: boolean;
};

export type IdexalOsExtensionManifest = {
  schemaVersion: 1;
  id: string;
  name: string;
  description: string;
  preview?: boolean;
  source: IdexalOsExtensionSource;
  icon?: {
    src?: string;
    simpleIconSlug?: string;
  };
  composer?: {
    prompt: string;
  };
  setup?: IdexalOsExtensionSetup;
  resources: IdexalOsExtensionResource[];
  contributions?: IdexalOsExtensionContribution[];
  lifecycle?: IdexalOsExtensionLifecycle;
  /** Declarative conditions that must ALL be true for the extension to be "active". */
  enablement?: EnablementCondition[];
  defaultEnabled?: boolean;
  defaultHidden?: boolean;
  platform?: Array<"darwin" | "linux" | "windows" | "web">;
};

export type IdexalOsExtensionPlatform = NonNullable<IdexalOsExtensionManifest["platform"]>[number];

export function extensionContribution(
  manifest: IdexalOsExtensionManifest | undefined,
  type: IdexalOsExtensionContributionType,
): IdexalOsExtensionContribution | undefined {
  return manifest?.contributions?.find((contribution) => contribution.type === type);
}

export function extensionResource(
  manifest: IdexalOsExtensionManifest | undefined,
  type: IdexalOsExtensionResourceType,
): IdexalOsExtensionResource | undefined {
  return manifest?.resources.find((resource) => resource.type === type);
}

export function isTrustedBuiltInExtension(manifest: IdexalOsExtensionManifest | undefined): boolean {
  return manifest?.source.origin === "builtin" && manifest.source.trusted;
}

export const BUILT_IN_IDEXAL_EXTENSION_MANIFESTS: IdexalOsExtensionManifest[] = [
  {
    schemaVersion: 1,
    id: "idexal-os-browser",
    name: "idexal Os Browser",
    description: "Automate the built-in browser panel that stays visible inside idexal Os.",
    source: { format: "idexal-os-builtin", origin: "builtin", trusted: true },
    icon: { src: "/idexal-os-mark.svg" },
    composer: { prompt: "Use the idexal Os Browser extension to " },
    setup: {
      instructions: "idexal Os Browser is ready by default in desktop workspaces.",
    },
    resources: [
      {
        type: "opencode-plugin",
        id: "opencode-chrome-devtools",
        packageName: "opencode-chrome-devtools",
        required: true,
      },
    ],
    contributions: [
      { type: "settings-panel", ref: "idexal-os.browser.settings", location: "settings-detail" },
      { type: "session-side-panel", ref: "idexal-os.browser.panel", location: "session-right-pane" },
      { type: "composer-prompt", prompt: "Use the idexal Os Browser extension to ", location: "composer" },
    ],
    enablement: [
      { type: "toggle-enabled", ref: "idexal-os-browser", label: "Enabled" },
    ],
    lifecycle: { reload: ["plugins", "agents"] },
    defaultEnabled: true,
    platform: ["darwin", "linux", "windows"],
  },
  {
    schemaVersion: 1,
    id: "ollama",
    name: "Ollama",
    description: "Local model provider at http://localhost:11434.",
    source: { format: "idexal-os-builtin", origin: "builtin", trusted: true },
    icon: { src: "/ext-ollama.svg" },
    composer: { prompt: "Use the Ollama extension to " },
    setup: {
      instructions: "Run Ollama locally, choose or pull a model, then add it as an idexal Os provider.",
      primaryCta: "Add Ollama model",
      secondaryCta: "Pull model",
    },
    resources: [
      { type: "local-service", id: "ollama-api", label: "Ollama API", description: "http://localhost:11434", required: true },
      { type: "provider", id: "ollama", providerId: "ollama", packageName: "@ai-sdk/openai-compatible", required: true },
    ],
    contributions: [
      { type: "settings-panel", ref: "idexal-os.ollama.settings", location: "settings-detail" },
      { type: "test-action", ref: "idexal-os.ollama.listModels", label: "Check local models" },
      { type: "composer-prompt", prompt: "Use the Ollama extension to ", location: "composer" },
    ],
    enablement: [
      { type: "provider-connected", ref: "ollama", label: "Ollama provider" },
    ],
    lifecycle: { reload: ["config"], detection: ["provider:ollama"] },
  },
  {
    schemaVersion: 1,
    id: "rea",
    name: "REA — Reverse Engineer Anything",
    description: "Inspect binaries, Electron and .NET apps, websites and saved captures locally, with evidence, so an agent can explain a feature and rebuild it.",
    preview: true,
    source: { format: "idexal-os-builtin", origin: "builtin", trusted: true },
    icon: { src: "/ext-rea.svg" },
    composer: {
      prompt: "Use REA to inspect how this works, cite the evidence, and then ",
    },
    setup: {
      instructions: "REA runs locally through npx and needs Node.js 22.19+ (24.11+ or 26+ also work). Deep native analysis uses an existing Ghidra, Hopper or IDA installation; idexal Os never installs an analysis engine silently and never uploads the target. Enable the MCP server, restart the workspace, then ask the agent to inspect the target and show the evidence.",
      primaryCta: "Enable REA",
    },
    resources: [
      {
        type: "mcp",
        id: "rea-mcp",
        label: "REA MCP server",
        description: "Local MCP server for evidence-backed reverse engineering.",
        mcpServerName: "rea",
        command: ["npx", "-y", "rea-agents@6.7.0", "mcp"],
        required: true,
      },
    ],
    contributions: [
      { type: "composer-prompt", prompt: "Use REA to inspect how this works, cite the evidence, and then ", location: "composer" },
      { type: "setup-instructions", location: "settings-detail" },
    ],
    enablement: [
      { type: "mcp-connected", ref: "rea", label: "REA MCP server" },
    ],
    lifecycle: { reload: ["mcp", "agents"] },
    platform: ["darwin", "linux", "windows"],
  },
];
