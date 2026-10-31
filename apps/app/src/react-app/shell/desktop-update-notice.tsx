/** @jsxImportSource react */
import { useState } from "react";
import { Download, RotateCw, X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { t } from "../../i18n";
import { isDesktopRuntime } from "../../app/utils";
import { useBrandAppName } from "../domains/cloud/brand-theme";
import { useDesktopUpdater } from "../domains/settings/state/desktop-updater-provider";

/**
 * A single, quiet upgrade notice for the running desktop app. The updater
 * already checks the platform feed in the background; this makes the result
 * visible without requiring a trip to Settings.
 */
export function DesktopUpdateNotice() {
  const updater = useDesktopUpdater();
  const appName = useBrandAppName();
  const [dismissed, setDismissed] = useState(false);
  const status = updater.updateStatus;
  const state = status?.state;

  if (!isDesktopRuntime() || dismissed || updater.updateEnv?.supported === false) return null;
  if (state !== "available" && state !== "downloading" && state !== "ready") return null;

  const version = status?.version ?? status?.candidate?.version ?? "";
  if (!version) return null;

  const downloading = state === "downloading";
  const ready = state === "ready";
  const downloaded = status?.downloadedBytes ?? 0;
  const total = status?.totalBytes ?? 0;
  const percent = total > 0 ? Math.min(100, Math.round((downloaded / total) * 100)) : 0;

  return (
    <div
      className="fixed bottom-4 end-4 z-[900] w-[min(360px,calc(100vw-2rem))] rounded-2xl border border-dls-border bg-dls-surface p-4 shadow-2xl"
      data-testid="desktop-update-notice"
      role="status"
    >
      <div className="flex items-start gap-3">
        <span className="mt-0.5 grid size-8 shrink-0 place-items-center rounded-xl bg-dls-accent/15 text-dls-accent">
          {ready ? <RotateCw className="size-4" /> : <Download className="size-4" />}
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium text-dls-text">
            {ready
              ? t("updates.notice_ready", { version })
              : downloading
                ? t("updates.notice_downloading", { version })
                : t("updates.notice_available", { version, appName })}
          </p>
          <p className="mt-1 text-xs leading-5 text-dls-secondary">
            {ready
              ? t("updates.notice_ready_body", { appName })
              : downloading
                ? t("updates.notice_downloading_body")
                : t("updates.notice_available_body")}
          </p>
          {downloading ? (
            <div className="mt-3 h-1 overflow-hidden rounded-full bg-dls-hover">
              <div className="h-full rounded-full bg-dls-accent transition-[width]" style={{ width: `${percent}%` }} />
            </div>
          ) : null}
          <div className="mt-3 flex items-center gap-2">
            <Button
              size="sm"
              onClick={() => {
                if (ready) void updater.installUpdateAndRestart();
                else if (!downloading) void updater.downloadUpdate();
              }}
              disabled={downloading}
            >
              {ready ? t("updates.notice_restart") : downloading ? t("updates.notice_downloading_action", { percent }) : t("updates.notice_update_now")}
            </Button>
            <Button size="sm" variant="ghost" onClick={() => setDismissed(true)} disabled={downloading}>
              {t("updates.notice_later")}
            </Button>
          </div>
        </div>
        <button
          type="button"
          className="rounded-md p-1 text-dls-secondary transition-colors hover:bg-dls-hover hover:text-dls-text"
          aria-label={t("updates.notice_later")}
          onClick={() => setDismissed(true)}
          disabled={downloading}
        >
          <X className="size-4" />
        </button>
      </div>
    </div>
  );
}
