/** @jsxImportSource react */
import { useEffect, type ReactNode } from "react";

import { Toaster } from "@/components/ui/sonner";

import { isWebDeployment } from "@/app/lib/idexal-os-deployment";
import { hydrateIdexalOsServerSettingsFromEnv } from "@/app/lib/idexal-os-server";
import { isDesktopRuntime } from "@/app/utils";
import { ConnectLinkProvider } from "@/react-app/domains/cloud/connect-link-provider";
import { DenAuthProvider } from "@/react-app/domains/cloud/den-auth-provider";
import { SecretRevealFeatureProvider } from "@/react-app/domains/cloud/secret-reveal-feature-provider";
import { AutoRejectedTurnRecoveryBridge } from "@/react-app/domains/cloud/auto-access-ui";
import { AutomationRunnerBridge } from "@/react-app/domains/automations/automation-runner-bridge";
import { GlobalQueueDrainerBridge } from "@/react-app/domains/session/sync/global-queue-drainer-bridge";
import { BrandThemeProvider } from "@/react-app/domains/cloud/brand-theme";
import { DesktopConfigProvider } from "@/react-app/domains/cloud/desktop-config-provider";
import { RestrictionNoticeProvider } from "@/react-app/domains/cloud/restriction-notice-provider";
import { LocalProvider } from "@/react-app/kernel/local-provider";
import { ServerProvider } from "@/react-app/kernel/server-provider";
import { ArchitectureMismatchGate } from "./architecture-mismatch-gate";
import { BootStateProvider } from "./boot-state";
import { DesktopRuntimeBoot } from "./desktop-runtime-boot";
import { startDebugLogger, stopDebugLogger } from "./debug-logger";
import { resolveIdexalOsConnection } from "./idexal-os-connection";
import { ReloadCoordinatorProvider } from "./reload-coordinator";
import { LinkOpenDialog } from "./link-open-dialog";

export function resolveDefaultServerUrl(): string {
  if (isDesktopRuntime()) return "http://127.0.0.1:4096";

  const idexalOsUrl =
    typeof import.meta.env?.VITE_IDEXAL_URL === "string"
      ? import.meta.env.VITE_IDEXAL_URL.trim()
      : "";
  if (idexalOsUrl) {
    const baseUrl = idexalOsUrl === "/api/idexalos" && typeof window !== "undefined"
      ? new URL(idexalOsUrl, window.location.origin).href
      : idexalOsUrl;
    return `${baseUrl.replace(/\/+$/, "")}/opencode`;
  }

  if (isWebDeployment() && import.meta.env.PROD && typeof window !== "undefined") {
    return `${window.location.origin}/opencode`;
  }

  const envUrl =
    typeof import.meta.env?.VITE_OPENCODE_URL === "string"
      ? import.meta.env.VITE_OPENCODE_URL.trim()
      : "";
  return envUrl || "http://127.0.0.1:4096";
}

type AppProvidersProps = {
  children: ReactNode;
};

// One provider tree for every activation state. The runtime bridges below
// (DesktopRuntimeBoot, AutomationRunnerBridge,
// GlobalQueueDrainerBridge) each render nothing until enterprise activation
// completes, so AppRoot-level consumers such as DesktopUpdaterProvider always
// find the same contexts and nothing privileged starts before activation.
export function EnterpriseAwareAppProviders({ children }: AppProvidersProps) {
  return (
    <>
      <DesktopRuntimeBoot />
      <ConnectLinkProvider>
        <DesktopConfigProvider>
          <BrandThemeProvider>
            <RestrictionNoticeProvider>
              <LocalProvider>
                <AutomationRunnerBridge />
                <GlobalQueueDrainerBridge />
                <AutoRejectedTurnRecoveryBridge />
                <ReloadCoordinatorProvider>{children}</ReloadCoordinatorProvider>
                <LinkOpenDialog />
                <Toaster />
              </LocalProvider>
            </RestrictionNoticeProvider>
          </BrandThemeProvider>
        </DesktopConfigProvider>
      </ConnectLinkProvider>
    </>
  );
}

export function AppProviders({ children }: AppProvidersProps) {
  hydrateIdexalOsServerSettingsFromEnv();

  useEffect(() => {
    // Start the dev observability forwarder. Reads the current idexal-os-server
    // URL on every flush so reconnects after port changes still work. In prod
    // builds `startDebugLogger` is a no-op.
    startDebugLogger({
      serverUrl: async () => (await resolveIdexalOsConnection()).normalizedBaseUrl,
    });
    return () => {
      stopDebugLogger();
    };
  }, []);

  const defaultUrl = resolveDefaultServerUrl();
  return (
    <BootStateProvider>
      <ServerProvider defaultUrl={defaultUrl}>
        <ArchitectureMismatchGate>
          <DenAuthProvider>
            <SecretRevealFeatureProvider>
              <EnterpriseAwareAppProviders>{children}</EnterpriseAwareAppProviders>
            </SecretRevealFeatureProvider>
          </DenAuthProvider>
        </ArchitectureMismatchGate>
      </ServerProvider>
    </BootStateProvider>
  );
}
