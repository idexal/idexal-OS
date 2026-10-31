import type { RemoteSessionThreadState } from "@idexal/types/automations"

import { formatRelativeTime } from "@/app/utils"
import { t } from "@/i18n"
import type { RemoteThreadStart } from "@/lib/remote-session-thread"
import { formatElapsedSeconds } from "@/lib/tool-call-duration"
import type { RemoteThreadEntry } from "./remote-thread-store"

/** One reading of a task for its row and its panel, so both always say the same thing. */
export type RemoteThreadView = {
  title: string
  /** Where it runs: "Cloud computer", the computer's name, or "your computer". */
  computer: string
  state: RemoteSessionThreadState
  waitingFor: "permission" | "question" | null
  stopped: boolean
  /** The state line, e.g. "Working 1m 20s" or "Completed · The notes cover…". */
  status: string
  /** Set when Den couldn't confirm the state lately; the row shows the last known one. */
  lastChecked: string | null
  active: boolean
  /** A session exists to read, follow up on, or stop. */
  started: boolean
}

const STOPPED = /abort|interrupt|cancel/i

export function remoteThreadView(start: RemoteThreadStart, entry: RemoteThreadEntry | null, now: number, stoppedHere = false): RemoteThreadView {
  const status = entry?.status ?? null
  // Before the first answer: Cloud tasks are already running, others wait for their computer.
  const state: RemoteSessionThreadState = status?.state ?? (start.target === "cloud" ? "running" : "starting")
  const computer = start.target === "cloud"
    ? t("session.remote_task_cloud")
    : status?.computerLabel ?? t("session.remote_task_your_computer")
  const error = status?.error ?? null
  const stopped = state === "error" && (stoppedHere || STOPPED.test(`${error?.code ?? ""} ${error?.message ?? ""}`))
  const waitingFor = state === "waiting" ? status?.waitingFor ?? "question" : null
  const since = entry?.activeSince ?? null
  const elapsed = since === null ? "" : formatElapsedSeconds(Math.max(0, Math.floor((now - since) / 1000)))
  const line = state === "starting" ? t("session.remote_task_starting", { computer })
    : waitingFor === "permission" ? t("session.remote_task_permission", { computer })
    : waitingFor === "question" ? t("session.remote_task_question", { computer })
    : state === "idle" ? [t("session.remote_task_completed"), status?.preview].filter(Boolean).join(" · ")
    : state === "error" ? stopped ? t("session.remote_task_stopped") : error?.message ?? t("session.remote_task_stopped")
    : t("session.remote_task_working", { elapsed }).trim()
  return {
    title: status?.title && status.title !== "Remote session" ? status.title : start.title,
    computer,
    state,
    waitingFor,
    stopped,
    status: line,
    lastChecked: entry?.stale && entry.checkedAt ? t("session.remote_task_last_checked", { time: formatRelativeTime(entry.checkedAt) }) : null,
    active: state !== "idle" && state !== "error",
    started: Boolean(status?.sessionId ?? start.sessionId),
  }
}
