import { fileURLToPath } from "node:url";
import { createServer } from "vite";
import type { Place, Seed } from "@idexal/env";
import { chrome } from "@idexal/hosts";

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

/** Real Workbot client and reusable Documents/Calendar components; only Den/provider responses are synthetic.
 * No live Drive, model, credentials, or Office service is needed for this navigation/context proof. */
export async function workbotWorkspace(_seed: Seed, { place }: { place: Place }) {
  const resources = new AsyncDisposableStack();
  const messages: Record<string, unknown>[] = [];
  const opened: string[] = [];
  let driveDenied = false;
  let documentsOff = false;
  const files = [
    { id: "file_brief", name: "Project brief.txt", mimeType: "text/plain", modifiedTime: "2026-10-12T09:00:00Z", size: "42", webViewLink: "https://drive.google.com/file/d/file_brief/view" },
    { id: "file_budget", name: "Budget notes.txt", mimeType: "text/plain", modifiedTime: "2026-10-11T09:00:00Z", size: "36", webViewLink: "https://drive.google.com/file/d/file_budget/view" },
  ];
  const turns: Record<string, unknown>[] = [];
  try {
    const vite = await createServer({
      configFile: fileURLToPath(new URL("../../ee/apps/workbot/vite.config.ts", import.meta.url)),
      server: { host: "127.0.0.1", port: 0, strictPort: false },
      plugins: [{ name: "workbot-workspace-provider-fixture", configureServer(server) {
        server.middlewares.use(async (req, res, next) => {
          const url = new URL(req.url ?? "/", "http://fixture");
          if (!url.pathname.startsWith("/v1/workbot")) return next();
          const referring = new URL(req.headers.referer ?? "http://fixture");
          const enabled = referring.searchParams.get("assistant") !== "off";
          const documents = !documentsOff && referring.searchParams.get("documents") !== "off";
          const denied = driveDenied || referring.searchParams.get("access") === "denied";
          const json = (body: unknown, status = 200) => { res.statusCode = status; res.setHeader("content-type", "application/json"); res.end(JSON.stringify(body)); };
          if (url.pathname === "/v1/workbot/me") return json({ name: "Alex", email: "alex@example.test", userId: "user_example", organizationId: "org_example", organizationName: "Example", enabled: true, calendar: true, documents, workspaceAssistant: enabled, canSchedule: false, sideChats: referring.searchParams.get("sideChats") === "on", denUrl: "https://workspace.example.test" });
          if (url.pathname === "/v1/workbot") return json({ available: true, name: "Workbot", organizationName: "Example", status: "idle", turns, hasEarlier: false, filesEnabled: false });
          if (url.pathname === "/v1/workbot/chats") return json({ main: null, side: [{ id: "sidechat000000001", title: "Document questions", createdAt: Date.now(), updatedAt: Date.now() }] });
          if (url.pathname === "/v1/workbot/events") { res.statusCode = 200; res.setHeader("content-type", "text/event-stream"); res.end(": fixture\n\n"); return; }
          if (url.pathname === "/v1/workbot/messages" && req.method === "POST") {
            let body = ""; for await (const chunk of req) body += chunk;
            const input: unknown = JSON.parse(body);
            if (!isRecord(input)) return json({ error: "invalid_request" }, 400);
            messages.push(input);
            const now = Date.now();
            turns.push({ ...input, sentAt: now, finishedAt: now, status: "done", attachments: [], outputs: [], parts: [{ kind: "text", text: "I can read the saved version. [Budget notes](https://drive.google.com/file/d/file_budget/view) · [Restricted document](https://docs.google.com/document/d/file_private/edit)" }], modelSteps: 1, error: null });
            return json({ ok: true }, 202);
          }
          if (url.pathname.startsWith("/v1/workbot/documents")) {
            if (!documents) return json({ error: "workbot_documents_not_enabled" }, 403);
            if (denied) return json({ error: "needs_connection" }, 409);
            if (url.pathname.endsWith("/drive-files")) return json({ ok: true, files });
            const match = /\/files\/([^/]+)\/(metadata|editor)$/.exec(url.pathname);
            if (match) {
              const file = files.find(file => file.id === match[1]);
              if (!file) return json({ error: "permission_denied" }, 403);
              if (match[2] === "metadata") { opened.push(file.id); return json({ id: file.id, name: file.name, mimeType: file.mimeType, modifiedTime: file.modifiedTime, size: Number(file.size), openUrl: file.webViewLink }); }
              return json({ kind: "text", fileId: file.id, connectionId: "google-workspace", name: file.name, revision: "v1", canEdit: true, text: file.id === "file_brief" ? "Project overview" : "Budget overview" });
            }
          }
          if (url.pathname.endsWith("/calendar/v1/automations")) return json({ items: [], nextCursor: null });
          if (url.pathname.endsWith("/calendar/v1/automation-runs")) return json({ runs: [], nextCursor: null });
          if (url.pathname.endsWith("/google-workspace/calendar-events")) {
            const from = Date.parse(url.searchParams.get("timeMin") ?? "");
            const date = new Date(from + 2 * 86400000).toISOString().slice(0, 10);
            return json({ ok: true, events: [{ id: "event_review", summary: "Team review", description: "Review the project", location: "", start: `${date}T17:00:00Z`, end: `${date}T18:00:00Z`, status: "confirmed", htmlLink: "https://calendar.google.com/", attendees: [], meetLink: null }] });
          }
          if (url.pathname.includes("microsoft-365/calendar-events")) return json({ error: "needs_connection" }, 409);
          return json({ error: "not_found" }, 404);
        });
      } }],
    });
    resources.defer(() => vite.close());
    await vite.listen();
    const url = vite.resolvedUrls?.local[0];
    if (!url) throw new Error("Workbot fixture did not start");
    const web = resources.use(await chrome({ host: place.host(), name: "workbot-workspace", startUrl: `${url}documents?assistant=off`, headless: true }));
    return { web, url, messages: () => [...messages], opened: () => [...opened], revokeDrive: () => { driveDenied = true; }, restoreDrive: () => { driveDenied = false; }, disableDocuments: () => { documentsOff = true; }, enableDocuments: () => { documentsOff = false; }, async [Symbol.asyncDispose]() { await resources.disposeAsync(); } };
  } catch (error) { await resources.disposeAsync(); throw error; }
}
