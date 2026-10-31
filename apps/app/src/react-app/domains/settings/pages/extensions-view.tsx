/** @jsxImportSource react */
import { type ReactNode } from "react";
import { Cpu } from "lucide-react";

import type { ExtensionInventoryFilter, ExtensionInventoryState } from "../extension-taxonomy";
import { PluginsView, type PluginsExtensionsStore } from "./plugins-view";

export type ExtensionsSection =
  | "all"
  | "apps"
  | "connections"
  | "mcps"
  | "skills"
  | "commands"
  | "agents"
  | "plugins"
  | "needs-sign-in"
  | "needs-admin-setup"
  | "ready";

/** Sections are the URL spelling of the inventory filters. */
export function filterForSection(section: ExtensionsSection | undefined): ExtensionInventoryFilter {
  switch (section) {
    case "apps":
      return "app";
    case "connections":
    case "mcps":
      return "connection";
    case "skills":
      return "skill";
    case "commands":
      return "command";
    case "agents":
      return "agent";
    case "plugins":
      return "plugin";
    default:
      return "all";
  }
}

function sectionForFilter(filter: ExtensionInventoryFilter): ExtensionsSection {
  switch (filter) {
    case "app":
      return "apps";
    case "connection":
      return "connections";
    case "mcp":
      return "mcps";
    case "skill":
      return "skills";
    case "command":
      return "commands";
    case "agent":
      return "agents";
    case "plugin":
      return "plugins";
    case "all":
      return "all";
  }
}

export function stateForSection(section: ExtensionsSection | undefined): ExtensionInventoryState {
  if (section === "needs-sign-in") return "needs_signin";
  if (section === "needs-admin-setup") return "needs_admin_setup";
  if (section === "ready") return "ready";
  return "ready";
}

type SuggestedPlugin = {
  name: string;
  packageName: string;
  description: string;
  tags: string[];
  aliases?: string[];
  installMode?: "simple" | "guided";
  steps?: Array<{
    title: string;
    description: string;
    command?: string;
    url?: string;
    path?: string;
    note?: string;
  }>;
};

export type ExtensionsViewProps = {
  busy: boolean;
  /** Hide the view's own description line (the settings shell already shows the tab description in-pane). */
  hideDescription?: boolean;
  selectedWorkspaceRoot: string;
  canEditPlugins: boolean;
  canUseGlobalScope: boolean;
  accessHint?: string | null;
  suggestedPlugins: SuggestedPlugin[];
  /** Curated skill catalog shown under the Skills category. */
  skillsContent?: ReactNode;
  extensions: PluginsExtensionsStore;
  /** The MCP view (quick-connect grid + configured servers). Skills are injected into it. */
  mcpView: (routing: {
    initialFilter: ExtensionInventoryFilter;
    onFilterChange: (filter: ExtensionInventoryFilter) => void;
    initialState: ExtensionInventoryState;
    pluginsContent: ReactNode;
    skillsContent: ReactNode;
    detailId: string | null;
    onDetailIdChange?: (id: string | null) => void;
    onRefresh: () => void;
  }) => ReactNode;
  onRefresh: () => void;
  initialSection?: ExtensionsSection;
  setSectionRoute?: (tab: ExtensionsSection) => void;
  showHeader?: boolean;
  detailId?: string | null;
  onDetailIdChange?: (id: string | null) => void;
};

export function ExtensionsView(props: ExtensionsViewProps) {
  const initialFilter = filterForSection(props.initialSection);
  const initialState = stateForSection(props.initialSection);
  const setFilterRoute = (filter: ExtensionInventoryFilter) => {
    props.setSectionRoute?.(sectionForFilter(filter));
  };
  const detailId = props.detailId ?? null;
  const mcpRouting = {
    initialFilter,
    onFilterChange: setFilterRoute,
    initialState,
    // OpenCode plugins keep their own disclosure under the Plugins category.
    // The store stays visible even with nothing installed so the curated
    // catalog is discoverable.
    pluginsContent: (
      <details className="group" open>
        <summary className="flex cursor-pointer items-center gap-2 rounded-lg px-1 py-2 text-sm font-medium text-dls-secondary transition-colors hover:text-dls-text">
          <Cpu size={14} />
          <span>idexal Os Plugins</span>
          {props.extensions.pluginList().length > 0 ? (
            <span className="text-[11px] text-dls-secondary">({props.extensions.pluginList().length})</span>
          ) : null}
        </summary>
        <div className="mt-3">
          <PluginsView
            extensions={props.extensions}
            busy={props.busy}
            selectedWorkspaceRoot={props.selectedWorkspaceRoot}
            canEditPlugins={props.canEditPlugins}
            canUseGlobalScope={props.canUseGlobalScope}
            accessHint={props.accessHint}
            suggestedPlugins={props.suggestedPlugins}
          />
        </div>
      </details>
    ),
    skillsContent: props.skillsContent ?? null,
    detailId,
    onDetailIdChange: props.onDetailIdChange,
    onRefresh: props.onRefresh,
  };

  return (
    <section className="w-full animate-in fade-in duration-300">
      {props.mcpView(mcpRouting)}
    </section>
  );
}
