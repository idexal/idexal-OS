/** @jsxImportSource react */
import { useState } from "react";

import { idexalOsServerRestart } from "@/app/lib/desktop";
import { bootOverlayCanHide, useBootState, useBootOverlayVisible } from "./boot-state";
import { useCloudWorkspaceStatus } from "./cloud-workspace-overlay";
import { shouldSuppressBootOverlayForGateway } from "./cloud-workspace-status";
import { OwDotTicker } from "./dot-ticker";

/**
 * Quiet, opaque boot overlay. Solid surface fill so nothing bleeds through.
 * A minimal typographic beat plus a small dot ticker. Fades once both the
 * boot hook and the first route load are ready.
 */
export function LoadingOverlay() {
  const visible = useBootOverlayVisible();
  const { phase, routeReady, message, error } = useBootState();
  const cloudWorkspace = useCloudWorkspaceStatus();
  const suppressForGateway = shouldSuppressBootOverlayForGateway({
    gatewayMode: cloudWorkspace.gatewayMode,
    signedIn: cloudWorkspace.visible,
    variant: cloudWorkspace.viewModel.variant,
  });
  const [busyAction, setBusyAction] = useState<"repair" | "update" | null>(null);
  const [actionState, setActionState] = useState<string | null>(null);

  const repairAndRestart = async () => {
    setBusyAction("repair");
    setActionState("Repairing the local runtime and reconnecting…");
    try {
      await idexalOsServerRestart();
      window.location.reload();
    } catch {
      setBusyAction(null);
      setActionState("Repair did not finish. Try Reload, or check the connection and retry.");
    }
  };

  const updateToLatest = async () => {
    const updater = window.__IDEXAL_ELECTRON__?.updater;
    if (!updater?.check) {
      setActionState("Updates are not available in this build.");
      return;
    }
    setBusyAction("update");
    setActionState("Checking for the latest stable version…");
    try {
      const result = await updater.check();
      if (!result?.available) {
        setActionState("You are on the latest stable version.");
        setBusyAction(null);
        return;
      }
      setActionState(`Updating to ${result.latestVersion ?? "the latest version"}…`);
      await updater.download?.();
      await updater.installAndRestart?.();
    } catch {
      setBusyAction(null);
      setActionState("The update could not start. Repair the connection first, then try again.");
    }
  };

  if (!visible || (suppressForGateway && !error)) return null;

  const fading = bootOverlayCanHide(phase, routeReady);

  return (
    <div
      className={`pointer-events-auto fixed inset-0 z-[1000] flex items-center justify-center bg-dls-surface transition-opacity duration-[160ms] ${
        fading ? "opacity-0" : "opacity-100"
      }`}
      aria-live="polite"
      aria-busy={!fading && !error}
      role="status"
    >
      <div className="flex w-full max-w-[320px] flex-col items-center gap-4 px-6 text-center">
        {error ? (
          <div className="flex w-full flex-col gap-3 text-[12px] leading-5">
            <div className="text-base font-medium text-dls-primary">idexal Os needs a quick repair</div>
            <div className="text-dls-secondary">
              Repair the local runtime to continue. The latest stable version stays installed; there is nothing to downgrade.
            </div>
            <button
              type="button"
              disabled={busyAction !== null}
              className="rounded-md bg-dls-accent px-3 py-2 font-medium text-[var(--dls-accent-fg)] disabled:opacity-60"
              onClick={() => void repairAndRestart()}
            >
              {busyAction === "repair" ? "Repairing…" : "Repair and restart"}
            </button>
            <button
              type="button"
              disabled={busyAction !== null}
              className="rounded-md border border-dls-border px-3 py-2 font-medium text-dls-primary disabled:opacity-60"
              onClick={() => void updateToLatest()}
            >
              {busyAction === "update" ? "Checking for updates…" : "Update to the latest version"}
            </button>
            <button
              type="button"
              className="rounded-md border border-dls-border px-3 py-2 font-medium text-dls-secondary"
              onClick={() => window.location.reload()}
            >
              Reload
            </button>
            {actionState ? <div className="text-dls-secondary">{actionState}</div> : null}
            <details className="text-left text-dls-secondary">
              <summary className="cursor-pointer">Technical details</summary>
              <div className="mt-2 break-words" data-testid="boot-error-detail">{error}</div>
            </details>
          </div>
        ) : (
          <>
            <OwDotTicker size="md" />
            <div className="text-[12px] leading-5 text-dls-secondary">
              {message || "Preparing workspace"}
            </div>
            <button
              type="button"
              className="rounded-md border border-dls-border px-3 py-2 text-sm font-medium text-dls-primary"
              onClick={() => window.location.reload()}
            >
              Reload
            </button>
          </>
        )}
      </div>
    </div>
  );
}
