import { z } from "zod";
import { registerCloudSkillDiscovery, type CloudSkillContext } from "./idexal-os-cloud-skills-v2.js";
import { server as browserTools } from "./idexal-os-chrome-devtools.js";

const browserResult = z.object({
  content: z.array(z.union([z.object({ type: z.literal("text"), text: z.string() }),
    z.object({ type: z.literal("file"), uri: z.string(), mime: z.string() })])),
  metadata: z.record(z.string(), z.unknown()).optional(),
});

type Tool = {
  name: string;
  description: string;
  input: Record<string, unknown>;
  options: { codemode: boolean };
  execute(input: unknown, context: { signal: AbortSignal; sessionID: string }): Promise<z.infer<typeof browserResult>>;
};
type Context = CloudSkillContext & {
  options: { url: string; token: string; browser?: { url: string; token: string } };
  tool: { transform(callback: (editor: { add(tool: Tool): void }) => void): Promise<{ dispose(): Promise<void> }> };
};

// Separate credentials authorize app reads and conversation-scoped browser
// operations. Neither grants arbitrary host APIs or idexal Os app commands.
export default {
  id: "idexal-os.context",
  async setup(context: Context) {
    const browser = context.options.browser;
    const browserDefinitions = browser ? await browserTools() : undefined;
    const registration = await context.tool.transform(editor => {
      for (const name of ["idexal_os_context", "idexal_os_query", "idexal_os_execute"]) {
        editor.add({
          name,
          description: name === "idexal_os_context"
            ? "Read idexal Os app context and available affordances, including their effects and executors. Use this to discover read and command ids before calling idexal_os_query or idexal_os_execute."
            : name === "idexal_os_query"
              ? "Read an idexal Os affordance without changing the app or navigating. Use the exact id and arguments from idexal_os_context; session.read includes live background-agent activity."
              : "Run an idexal Os command affordance: change app settings, add/remove/activate proxies, install or remove skills, MCP servers and plugins, change AI providers, or control the app. Use the exact id and arguments from idexal_os_context. The person's plan and organization agent permissions are enforced; report a denial instead of using another route.",
          input: name === "idexal_os_context" ? { type: "object", properties: {}, additionalProperties: false } : {
            type: "object",
            properties: {
              id: { type: "string" },
              args: { type: "object", additionalProperties: true },
              ...(name === "idexal_os_execute" ? { expectedRevision: { type: "number" } } : {}),
            },
            required: ["id"], additionalProperties: false,
          },
          options: { codemode: false },
          async execute(input, call) {
            const response = await fetch(context.options.url, {
              method: "POST", redirect: "error", signal: call.signal,
              headers: { Authorization: `Bearer ${context.options.token}`, "Content-Type": "application/json" },
              body: JSON.stringify({ name, input, ...(name === "idexal_os_execute" ? { sessionId: call.sessionID } : {}) }),
            });
            const text = await response.text();
            if (!response.ok) {
              throw new Error(`idexal Os ${name === "idexal_os_execute" ? "command" : "read"} failed (${response.status}): ${text.slice(0, 500)}`);
            }
            return { content: [{ type: "text", text }] };
          },
        });
      }
      if (browser && browserDefinitions) for (const [name, definition] of Object.entries(browserDefinitions.tool)) {
        editor.add({
          name, description: definition.description,
          input: z.toJSONSchema(z.object(definition.args)),
          options: { codemode: false },
          async execute(input, call) {
            const response = await fetch(browser.url, {
              method: "POST", redirect: "error", signal: call.signal,
              headers: { Authorization: `Bearer ${browser.token}`, "Content-Type": "application/json" },
              body: JSON.stringify({ name, input, sessionId: call.sessionID }),
            });
            if (!response.ok) throw new Error(`Browser request failed (${response.status})`);
            return browserResult.parse(await response.json());
          },
        });
      }
    });
    const stopSkills = await registerCloudSkillDiscovery(context);
    return async () => { await stopSkills(); await registration.dispose(); };
  },
};
