/** @jsxImportSource react */
import { useMemo } from "react";
import { useLocation } from "react-router";

import { usePanelTabStore } from "../domains/session/panel/panel-tab-store";
import { useWorkbenchStore } from "../domains/session/chat/workbench-store";
import { useSessionManagementStore } from "../domains/session/sidebar/session-management-store";
import { usePublishIdexalOsContext } from "./control/control-provider";
import { buildIdexalOsContext } from "./idexal-os-context-projector";
import { useUiStateStore } from "./ui-state-store";

export function IdexalOsContextPublisher() {
  const location = useLocation();
  const revision = useWorkbenchStore((state) => state.revision);
  const primary = useWorkbenchStore((state) => state.primary);
  const tabs = useWorkbenchStore((state) => state.tabs);
  const secondary = useWorkbenchStore((state) => state.secondary);
  const focusedPane = useWorkbenchStore((state) => state.focusedPane);
  const sidebarOpen = useUiStateStore((state) => state.sidebarOpen);
  const sidePanelState = useUiStateStore((state) => state.sidePanelState);
  const applicationMenuVisible = useUiStateStore((state) => state.applicationMenuVisible);
  const workspaceRightSidebarExpanded = useUiStateStore((state) => state.workspaceRightSidebarExpanded);
  const panelSessions = usePanelTabStore((state) => state.sessions);
  const pinnedSessionIds = useSessionManagementStore((state) => state.pinnedIds);
  const route = `${location.pathname}${location.search}${location.hash}`;

  const context = useMemo(() => buildIdexalOsContext({
    route,
    revision,
    capturedAt: new Date().toISOString(),
    workbench: {
      revision,
      sideChats: useWorkbenchStore.getState().sideChats,
      primary,
      tabs,
      secondary,
      focusedPane,
    },
    ui: {
      sidebarOpen,
      sidePanelState,
      applicationMenuVisible,
      workspaceRightSidebarExpanded,
    },
    panelSessions,
    pinnedSessionIds,
    availableAffordances: [],
  }), [
    applicationMenuVisible,
    focusedPane,
    panelSessions,
    pinnedSessionIds,
    primary,
    revision,
    route,
    sidebarOpen,
    sidePanelState,
    secondary,
    tabs,
    workspaceRightSidebarExpanded,
  ]);

  usePublishIdexalOsContext(context);
  return null;
}
