// Type definitions for the desktop bridge.
// The payload shapes and the per-command contract live in
// packages/types/src/desktop-ipc.ts (shared with the Electron main process);
// this module re-exports them as the app-side import path.

import type { WorkspaceWire } from "@idexal/types/workspace";

export type {
  AppBuildInfo,
  BrandIconApplyResult,
  BrandIconState,
  CacheResetResult,
  DesktopBootstrapConfig,
  DesktopDistributionInfo,
  DesktopIntegrationIssue,
  DesktopIntegrationResult,
  DesktopIntegrationStatus,
  DesktopCommandArgs,
  DesktopCommandInvokers,
  DesktopCommandMap,
  DesktopCommandName,
  DesktopCommandResult,
  DesktopBinaryDownloadResult,
  DesktopFetchInit,
  DesktopFetchResult,
  EngineDoctorResult,
  EngineInfo,
  EvalRelaunchResult,
  ExecResult,
  LocalSkillCard,
  LocalSkillContent,
  NativeContextMenuItem,
  NativeContextMenuRequest,
  NukeManifestPreview,
  NukeOptions,
  NukeReceipt,
  NukeReceiptError,
  OpencodeCommandDraft,
  OpencodeConfigFile,
  OpencodeExecutionEnvEntry,
  OpencodeExecutionSnapshot,
  ProxyProfile,
  ProxyProfileInput,
  ProxyProfileKind,
  ProxyProtocol,
  ProxyState,
  ProxyGuardState,
  ProxyTestResult,
  ProxyRotateResult,
  BrowserPrewarmResult,
  IdexalOsDockerCleanupResult,
  IdexalOsServerInfo,
  UpdaterEnvironment,
  WorkspaceCreateInput,
  WorkspaceExportSummary,
  WorkspaceList,
  WorkspaceIdexalOsConfig,
} from "@idexal/types/desktop-ipc";

// Canonical wire shape shared with idexal-os-server and the desktop bridge.
// Single source of truth: packages/types/src/workspace.ts.
export type WorkspaceInfo = WorkspaceWire;

// Browser tab state mirrored across the desktop IPC bridge. The shape is owned
// by @idexal/browser-tabs (shared with the Electron main process); the
// session panel store re-exports it from here.
export type { BrowserPanelTab } from "@idexal/browser-tabs";
