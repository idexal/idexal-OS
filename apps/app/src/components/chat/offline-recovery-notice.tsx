import { useSyncExternalStore } from "react";
import { createPortal } from "react-dom";
import { useOfflineRecoveryFeature } from "@/react-app/domains/session/status/use-offline-recovery-feature";
import { TaskRecovery } from "./task-recovery";

function subscribe(listener: () => void) {
  window.addEventListener("online", listener);
  window.addEventListener("offline", listener);
  return () => {
    window.removeEventListener("online", listener);
    window.removeEventListener("offline", listener);
  };
}

const noSubscription = () => () => {};
const isOffline = () => navigator.onLine === false;
const serverSnapshot = () => false;

/** A network hint only. OpenCode v2 owns retries and task recovery. */
export function OfflineRecoveryNotice({ active }: { active: boolean }) {
  const enabled = useOfflineRecoveryFeature(active);
  const offline = useSyncExternalStore(enabled ? subscribe : noSubscription, isOffline, serverSnapshot);
  if (!enabled || !offline) return null;
  return createPortal(
    <div className="pointer-events-none fixed inset-x-4 bottom-24 z-40 flex justify-end">
      <div className="pointer-events-auto max-w-sm rounded-lg bg-popover px-4 py-2 text-popover-foreground shadow-[var(--dls-card-shadow)]">
        <TaskRecovery compact emphasized state="paused" testId="offline-recovery" title="You’re offline" />
      </div>
    </div>,
    document.body,
  );
}
