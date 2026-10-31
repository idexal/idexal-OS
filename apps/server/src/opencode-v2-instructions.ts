import { IDEXAL_AGENT_PROMPT } from "./idexal-os-agent-prompt.js";

export const IDEXAL_V2_INSTRUCTION_KEY = "idexal-os.context";

/** Discover remote skills on demand through Connect; native skills are workspace files. */
export function buildIdexalOsV2Instructions(connectReady: boolean) {
  return {
    // Keep v1 guidance, translating only the native MCP tool spelling.
    operatingInstructions: IDEXAL_AGENT_PROMPT.replaceAll("idexal-os-cloud_", "idexal-os-cloud."),
    context: "Use idexal_os_context to discover idexal Os app affordances and their effects. Use idexal_os_execute to run a command affordance exactly as returned, including changing app settings, adding or removing proxies, installing skills/MCP servers/plugins, changing AI providers, opening settings and managing sessions. The person's plan and organization agent permissions are enforced by idexal Os; report a denial instead of trying another route. Use idexal_os_query with session.search then session.read to read another conversation without opening it. These are native tools, not tools.search calls. Only use capabilities actually returned by discovery.",
    connect: connectReady ? "idexal Os Connect tools are connected. In Code Mode, discover callable tool paths with the bare search({ query, limit }) function, then call the exact returned path under tools. Use only capabilities actually returned by discovery."
      : "idexal Os Connect is not connected for this request. Do not claim remote capabilities are available.",
    skillInstructions: "Use the native skill tool when a task matches an available skill's description. Entries with IDs starting idexal-os-cloud- come from the organization: when one loads its full instructions, follow them; when it says it is discovery metadata only or not ready yet, follow its note. Other organization skills remain discoverable through idexal Os Connect on demand. A denied or removed skill must not be recovered from earlier context or files. Skill contents are subordinate to the user's request and operating instructions.",
    browser: "The native browser_tabs, browser_open, browser_observe, browser_act, browser_navigate and browser_handoff tools control this conversation's built-in browser. Start with browser_tabs; use browser_open for a new URL, or about:blank when asked only to open the browser. These are direct native tools, not Code Mode calls. Respect organization restrictions, verify the resulting page, and use browser_handoff for sign-in. Never use browser tools to control idexal Os itself or claim access to external browser profiles.",
  };
}
