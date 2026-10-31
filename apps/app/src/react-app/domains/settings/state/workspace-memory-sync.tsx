/** @jsxImportSource react */

import { useEffect, useRef } from "react";

import { createDenClient, readDenSettings } from "../../../../app/lib/den";
import { denSettingsChangedEvent } from "../../../../app/lib/den-session-events";
import type { IdexalOsServerClient } from "../../../../app/lib/idexal-os-server";

export type WorkspaceMemorySnapshot = {
  content: string;
  updatedAt: string | null;
};

export type WorkspaceMemorySyncOutcome = "pulled" | "pushed" | "in_sync" | "skipped";

function memoryTime(value: string | null | undefined): number {
  const parsed = value ? Date.parse(value) : 0;
  return Number.isFinite(parsed) ? parsed : 0;
}

/**
 * Merge the workspace file with the cloud copy. Strictly newer wins; an empty
 * side always loses. Ties prefer the local copy because agents write it during
 * the session and the cloud copy is a mirror, not the source of truth.
 */
export async function reconcileWorkspaceMemory(options: {
  local: WorkspaceMemorySnapshot;
  remote: WorkspaceMemorySnapshot | null;
  pullLocal: (content: string) => Promise<unknown>;
  pushRemote: (content: string) => Promise<unknown>;
}): Promise<WorkspaceMemorySyncOutcome> {
  const localContent = options.local.content ?? "";
  const remoteContent = options.remote?.content ?? "";
  const hasLocal = localContent.trim().length > 0;
  const hasRemote = remoteContent.trim().length > 0;
  if (!hasLocal && !hasRemote) return "skipped";
  if (hasLocal && localContent === remoteContent) return "in_sync";
  if (hasRemote && !hasLocal) {
    await options.pullLocal(remoteContent);
    return "pulled";
  }
  if (hasLocal && !hasRemote) {
    await options.pushRemote(localContent);
    return "pushed";
  }
  const localTime = memoryTime(options.local.updatedAt);
  const remoteTime = memoryTime(options.remote?.updatedAt);
  if (remoteTime > localTime) {
    await options.pullLocal(remoteContent);
    return "pulled";
  }
  await options.pushRemote(localContent);
  return "pushed";
}

const SYNC_INTERVAL_MS = 45_000;

export function useWorkspaceMemoryAutoSync(
  client: IdexalOsServerClient | null,
  workspaceId: string | null,
): void {
  const busyRef = useRef(false);

  useEffect(() => {
    if (!client || !workspaceId) return;
    if (typeof window === "undefined") return;
    let cancelled = false;
    let timer: number | undefined;

    const syncOnce = async () => {
      if (cancelled || busyRef.current) return;
      const settings = readDenSettings();
      const token = settings.authToken?.trim() ?? "";
      const orgId = settings.activeOrgId?.trim() ?? "";
      if (!token || !orgId) return;
      busyRef.current = true;
      try {
        const cloud = createDenClient({ baseUrl: settings.baseUrl, token });
        const local = await client.getWorkspaceMemory(workspaceId);
        const remote = await cloud
          .getWorkspaceMemory(orgId, workspaceId)
          .then((value) => ({ content: value.content ?? "", updatedAt: value.updatedAt }))
          .catch(() => null);
        await reconcileWorkspaceMemory({
          local: { content: local.content ?? "", updatedAt: local.updatedAt },
          remote,
          pullLocal: (content) => client.putWorkspaceMemory(workspaceId, content),
          pushRemote: (content) => cloud.putWorkspaceMemory(orgId, workspaceId, content),
        });
      } catch {
        // Offline or the local engine is restarting; the next tick retries.
      } finally {
        busyRef.current = false;
      }
    };

    const schedule = () => {
      if (cancelled) return;
      void syncOnce();
    };

    schedule();
    timer = window.setInterval(schedule, SYNC_INTERVAL_MS);
    const onVisibility = () => {
      if (document.visibilityState === "visible") schedule();
    };
    const onFocus = () => schedule();
    const onSettingsChanged = () => schedule();
    document.addEventListener("visibilitychange", onVisibility);
    window.addEventListener("focus", onFocus);
    window.addEventListener(denSettingsChangedEvent, onSettingsChanged);

    return () => {
      cancelled = true;
      if (timer !== undefined) window.clearInterval(timer);
      document.removeEventListener("visibilitychange", onVisibility);
      window.removeEventListener("focus", onFocus);
      window.removeEventListener(denSettingsChangedEvent, onSettingsChanged);
    };
  }, [client, workspaceId]);
}

export function WorkspaceMemoryAutoSync(props: {
  client: IdexalOsServerClient | null;
  workspaceId: string | null;
}) {
  useWorkspaceMemoryAutoSync(props.client, props.workspaceId);
  return null;
}
