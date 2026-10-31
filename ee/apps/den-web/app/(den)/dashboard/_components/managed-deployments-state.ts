import type { ManagedDeployment } from "@idexal/types/den/managed-deployments";

export type DeploymentTone = "neutral" | "warning" | "danger";
export type ProviderReadiness = {
  provider: "aws" | "gcp" | "azure";
  available: boolean;
  reason?: "ready" | "not_configured" | "invalid_configuration" | "not_supported" | "feature_disabled";
  missingFields?: string[];
};

export function providerName(provider: ProviderReadiness["provider"]) {
  return provider === "aws" ? "AWS" : provider === "gcp" ? "Google Cloud" : "Azure";
}

/** Configuration values and secrets never belong in readiness copy. */
export function readinessMessage(entry: ProviderReadiness) {
  const name = providerName(entry.provider);
  if (entry.available) return `${name} is ready for customer approval.`;
  switch (entry.reason) {
    case "feature_disabled": return `${name} deployments aren't enabled for this workspace. Ask your idexal Os operator to enable them.`;
    case "invalid_configuration": return `${name} installer configuration is invalid. An idexal Os operator must correct the published release configuration.`;
    case "not_supported": return `${name} deployments aren't supported yet. An idexal Os operator can confirm supported clouds.`;
    default: return `${name} installer isn't configured for this idexal Os environment. An idexal Os operator must publish and configure a verified installer release.`;
  }
}

/** Installation/update failures take precedence over the last known health. */
export function deploymentStatus(deployment: ManagedDeployment): { label: string; tone: DeploymentTone } {
  const run = deployment.run;
  const installed = Boolean(deployment.installedVersion);
  if (run && run.state !== "ready") {
    if (run.expired) return { label: installed ? "Update stopped reporting" : "Installer stopped reporting", tone: "danger" };
    if (run.state === "failed") return { label: installed ? "Update failed" : "Install failed", tone: "danger" };
    if (run.state === "awaiting_approval") return { label: `Awaiting ${deployment.provider === "gcp" ? "Google Cloud" : "AWS"} approval`, tone: "neutral" };
    if (run.state === "provisioning") return { label: installed ? "Updating" : "Installing", tone: "neutral" };
  }
  if (!installed && !run) return { label: "Not launched", tone: "neutral" };
  switch (deployment.health.state) {
    case "operational": return { label: "Operational", tone: "neutral" };
    case "degraded": return { label: "Degraded", tone: "warning" };
    case "down": return { label: "Down", tone: "danger" };
    case "not_reporting": return { label: "Not reporting", tone: "warning" };
    case "awaiting_report": return { label: "Waiting for first report", tone: "neutral" };
  }
}
