import { z } from "zod"

const id = z.string().min(1).max(512).regex(/^[A-Za-z0-9_-]+$/)
const label = z.string().trim().min(1).max(512)
const instant = z.number().int().min(0).max(8_640_000_000_000_000)
const selection = z.discriminatedUnion("kind", [
  z.object({ kind: z.literal("meeting"), provider: z.enum(["google", "microsoft"]), id: z.string().min(1).max(512).regex(/^[A-Za-z0-9_=.@+/-]+$/), title: label }).strict(),
  z.object({ kind: z.literal("automation"), id, title: label }).strict(),
])

/** A snapshot of visible metadata at Send, never document bytes, a permission, or a live screen feed. */
export const workbotViewContextSchema = z.discriminatedUnion("kind", [
  z.object({ kind: z.literal("documents"), view: z.enum(["recent", "folder"]), folderId: id, folderName: label, query: z.string().max(500) }).strict(),
  z.object({ kind: z.literal("document"), provider: z.literal("google-drive"), fileId: id, title: label, mimeType: z.string().min(1).max(255), unsaved: z.boolean() }).strict(),
  z.object({ kind: z.literal("calendar"), view: z.enum(["day", "week", "month"]), start: instant, end: instant, timeZone: z.string().min(1).max(64).refine(value => { try { new Intl.DateTimeFormat("en", { timeZone: value }); return true } catch { return false } }, "Choose a valid time zone."), selected: selection.optional() }).strict(),
]).refine(value => value.kind !== "calendar" || value.end > value.start && value.end - value.start <= 370 * 86_400_000, "Choose a valid calendar range.")
export type WorkbotViewContext = z.infer<typeof workbotViewContextSchema>

const PREFIX = "[idexal Os visible context v1]\n"
const END = "\n[End idexal Os visible context]\n\n"

/** Only opaque resource identifiers and typed view coordinates reach the model.
 * Display names, search text and provider-supplied MIME labels stay out of the prompt. */
export function workbotContextForModel(value: WorkbotViewContext): WorkbotViewContext {
  const context = workbotViewContextSchema.parse(value)
  if (context.kind === "document") return { kind: context.kind, provider: context.provider, fileId: context.fileId, title: "Current document", mimeType: "application/octet-stream", unsaved: context.unsaved }
  if (context.kind === "documents") return { kind: context.kind, view: context.view, folderId: context.folderId, folderName: "Current folder", query: "" }
  return { kind: context.kind, view: context.view, start: context.start, end: context.end,
    timeZone: new Intl.DateTimeFormat("en", { timeZone: context.timeZone }).resolvedOptions().timeZone,
    ...(context.selected ? { selected: { ...context.selected, title: context.selected.kind === "meeting" ? "Selected meeting" : "Selected automation" } } : {}) }
}

/** The person's command is separate from the fixed-shape, read-only resource reference. */
export function workbotContextPrompt(text: string, context?: WorkbotViewContext) {
  if (!context) return text
  return `${PREFIX}${JSON.stringify(workbotContextForModel(context))}${END}${text}`
}

/** Rehydrate the same snapshot for queued messages, retries and transcript display. */
export function readWorkbotContextPrompt(prompt: string): { text: string; context?: WorkbotViewContext } {
  if (!prompt.startsWith(PREFIX)) return { text: prompt }
  const end = prompt.indexOf(END, PREFIX.length)
  if (end < 0 || end - PREFIX.length > 8192) return { text: prompt }
  try {
    const context = workbotViewContextSchema.safeParse(JSON.parse(prompt.slice(PREFIX.length, end)))
    return context.success ? { text: prompt.slice(end + END.length), context: context.data } : { text: prompt }
  } catch { return { text: prompt } }
}

/** Only canonical Google document URLs become navigation within Workbot. */
export function googleDriveFileId(href: string): string | null {
  try {
    const url = new URL(href)
    if (url.protocol !== "https:" || url.username || url.password || url.port) return null
    const path = url.hostname === "docs.google.com" ? /^\/(?:document|spreadsheets|presentation)\/d\/([A-Za-z0-9_-]{1,512})(?:\/|$)/.exec(url.pathname)
      : url.hostname === "drive.google.com" ? /^\/file\/d\/([A-Za-z0-9_-]{1,512})(?:\/|$)/.exec(url.pathname) : null
    if (path) return path[1] ?? null
    const queryId = url.hostname === "drive.google.com" && url.pathname === "/open" ? url.searchParams.get("id") : null
    return queryId && id.safeParse(queryId).success ? queryId : null
  } catch { return null }
}

export function workbotContextLabel(context: WorkbotViewContext) {
  if (context.kind === "document") return context.title
  if (context.kind === "documents") return context.query ? `Search in ${context.folderName}` : context.folderName
  return context.selected?.title ?? `${context.view[0]?.toUpperCase()}${context.view.slice(1)} calendar`
}
