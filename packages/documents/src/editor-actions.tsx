import { createPortal } from "react-dom"
import type { ReactNode } from "react"

/** One actions row beside the file tabs; standalone hosts can omit the portal target. */
export function EditorActions({ active, host, children }: { active: boolean; host?: HTMLElement | null; children: ReactNode }) {
  if (!active) return null
  const actions = <div className="ow-editor-toolbar" aria-label="Document actions">{children}</div>
  return host ? createPortal(actions, host) : actions
}
