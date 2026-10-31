import type { SessionCloudMcpMaintenanceState } from "./use-session-mcp-maintenance";
import { t } from "@/i18n";

export type IdexalOsConnectStateStatus = "available" | "missing" | "invalid" | "unreadable";

export type IdexalOsConnectStateSummary = {
  status: "ready" | "not_configured" | "disabled" | "unavailable";
  statusLabel: string;
  tone: "ready" | "neutral" | "error";
  stageLabel: string;
  recommendedAction: string;
};

export function resolveIdexalOsConnectStateSummary(
  status: IdexalOsConnectStateStatus,
  connectEnabled: boolean,
): IdexalOsConnectStateSummary {
  if (status === "missing") {
    return {
      status: "not_configured",
      statusLabel: t("connect.state_not_configured_label"),
      tone: "neutral",
      stageLabel: t("connect.state_not_configured_stage"),
      recommendedAction: t("connect.state_not_configured_action"),
    };
  }
  if (status === "invalid" || status === "unreadable") {
    return {
      status: "unavailable",
      statusLabel: t("connect.state_unavailable_label"),
      tone: "error",
      stageLabel: t("connect.state_unavailable_stage"),
      recommendedAction: t("connect.state_unavailable_action"),
    };
  }
  if (!connectEnabled) {
    return {
      status: "disabled",
      statusLabel: t("connect.state_disabled_label"),
      tone: "neutral",
      stageLabel: t("connect.state_disabled_stage"),
      recommendedAction: t("connect.state_disabled_action"),
    };
  }
  return {
    status: "ready",
    statusLabel: t("connect.state_ready_label"),
    tone: "ready",
    stageLabel: t("connect.state_ready_stage"),
    recommendedAction: t("connect.state_ready_action"),
  };
}

export type IdexalOsConnectStatus = {
  state: "checking" | "ready" | "needs_attention";
  label: "Checking" | "Ready" | "Needs attention";
  description: string;
};

export function idexalOsConnectAttentionTitle(description: string): string {
  return `One possible issue: ${description}`;
}

export function resolveIdexalOsConnectStatus(
  signedIn: boolean,
  maintenance: SessionCloudMcpMaintenanceState | undefined,
): IdexalOsConnectStatus | null {
  if (!signedIn) return null;

  // Den authentication is ready, but maintenance itself is workspace-scoped.
  // When there is no active workspace, report the verified Cloud connection
  // without claiming that connected-service delivery was checked.
  if (!maintenance || maintenance.status === "idle") {
    return {
      state: "ready",
      label: "Ready",
      description: "Signed in to idexal Os Cloud. Connected service tools will be checked when a workspace is active.",
    };
  }

  if (maintenance.status === "ready") {
    return {
      state: "ready",
      label: "Ready",
      description: "Connected service tools are available.",
    };
  }

  if (maintenance.status === "failed") {
    return {
      state: "needs_attention",
      label: "Needs attention",
      description: maintenance.issue?.message
        ?? "idexal Os Connect could not verify connected service tools. Run diagnostics for details.",
    };
  }

  if (maintenance.status === "skipped") {
    // Skipped means there was nothing to maintain for this workspace or the
    // platform disabled Connect. It is not a failed delivery.
    return {
      state: "ready",
      label: "Ready",
      description: "No connected-service maintenance is required for this workspace.",
    };
  }

  return {
    state: "checking",
    label: "Checking",
    description: maintenance.status === "retrying"
      ? `Restoring connected service tools (${maintenance.attempt}/${maintenance.maxAttempts}).`
      : "Checking connected service tools in the background.",
  };
}
