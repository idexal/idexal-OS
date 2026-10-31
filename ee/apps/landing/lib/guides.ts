import type { CompareCard } from "./compare";
import type { FaqEntry } from "./faq";

/**
 * Answer-first guide pages for search engines and AI answer engines (SEO / GEO / AEO).
 *
 * Each guide renders as HTML at /guides/<slug>, as Markdown for agents that send
 * `Accept: text/markdown` (see middleware.ts), and as FAQPage + Article JSON-LD.
 * Keep the short answer under ~45 words: it is the passage answer engines quote.
 */

export const SITE_URL = "https://os.idexal.com";
export const GUIDES_PATH = "/guides";

const MCP_URL = "https://api.idexal.com/mcp/agent";

export type GuideStep = {
  title: string;
  body: string;
  code?: string;
};

export type GuideTable = {
  caption: string;
  columns: string[];
  rows: string[][];
};

export type GuideSection = {
  id: string;
  heading: string;
  /** Short status label shown next to the heading, e.g. "In preview". */
  badge?: string;
  paragraphs?: string[];
  bullets?: string[];
  steps?: GuideStep[];
  table?: GuideTable;
};

export type Guide = {
  slug: string;
  /** Short label for the guides index and footer. */
  label: string;
  /** <title>; keep under 60 characters. */
  title: string;
  /** Meta description; keep under 160 characters. */
  description: string;
  /** The question the page answers, phrased the way people ask it. */
  heading: string;
  /** Short, quotable answer shown under the heading. */
  answer: string;
  /** ISO date of the last factual review. Shown on the page and in Article JSON-LD. */
  updated: string;
  /** Concrete, verifiable facts (numbers, names, limits). Answer engines cite specifics. */
  keyFacts: string[];
  sections: GuideSection[];
  cards: CompareCard[];
  faq: FaqEntry[];
  cta: {
    heading: string;
    sub: string;
    primary: { label: string; href: string };
    secondary: { label: string; href: string };
  };
};

export const guides: Guide[] = [
  {
    slug: "idexal-os-and-opencode",
    label: "idexal Os and OpenCode",
    title: "How idexal Os works with OpenCode",
    description:
      "idexal Os is built on OpenCode. Use the idexal Os desktop app, or add the opencode-idexal-os plugin to OpenCode to get your organization's models and MCP connections.",
    heading: "How does idexal Os work with OpenCode?",
    answer:
      "idexal Os runs on OpenCode, the open-source coding agent. The desktop app gives anyone a point-and-click OpenCode, and the opencode-idexal-os plugin brings your organization's models, skills, and connections into OpenCode in a terminal.",
    updated: "2026-10-11",
    keyFacts: [
      "Engine: OpenCode, on macOS, Windows, and Linux",
      "Plugin: opencode-idexal-os, for OpenCode 2",
      "Refreshes models and connections every 5 minutes",
      "License: open source, free desktop app"
    ],
    sections: [
      {
        id: "idexal-os-connect",
        heading: "What is idexal Os Connect?",
        paragraphs: [
          "idexal Os Connect is an AI Gateway, MCP Gateway, and Skills Gateway that lets organizations centrally manage models, tools, reusable skills, and access from one admin panel.",
          "In OpenCode, the opencode-idexal-os plugin brings all three in with one sign-in. You don't need the idexal Os desktop app."
        ],
        bullets: [
          "AI Gateway: approved models, without giving people provider keys. Usage and spend limits in one place.",
          "MCP Gateway: connected tools, such as Slack or Google Workspace, for the people allowed to use them.",
          "Skills Gateway: reusable instructions your team publishes once and every agent can use."
        ]
      },
      {
        id: "built-on-opencode",
        heading: "idexal Os is built on OpenCode",
        paragraphs: [
          "Every idexal Os chat runs on the OpenCode engine. idexal Os adds a desktop app for people who don't use a terminal, plus team features: shared skills, shared connections, models from your company, and admin controls.",
          "Anything OpenCode can do already works in idexal Os, even before it has its own button. Skills, MCP servers, providers, and opencode.json config files work the same way in both."
        ],
        bullets: [
          "Same engine: idexal Os runs OpenCode under the hood on macOS, Windows, and Linux.",
          "Same files: SKILL.md skills, MCP servers, and opencode.json or opencode.jsonc config work in both.",
          "Same models: any of the 50+ providers OpenCode supports, including local models.",
          "Shared access: the plugin exposes only the organization models and connections granted to the signed-in member."
        ]
      },
      {
        id: "plugin",
        heading: "The idexal Os plugin for OpenCode",
        paragraphs: [
          "Engineers who prefer the terminal don't need the desktop app. The opencode-idexal-os plugin connects a normal OpenCode 2 install to your idexal Os organization. You sign in once, and OpenCode loads your organization's setup each time it starts."
        ],
        steps: [
          {
            title: "Install the plugin",
            body: "Add the plugin to OpenCode 2.",
            code: "opencode plugin add opencode-idexal-os"
          },
          {
            title: "Sign in",
            body: "Choose Browser, sign in to idexal Os, and pick your organization. On a machine without a browser, add --method code and confirm the code on any device.",
            code: "opencode auth login idexalos"
          },
          {
            title: "Check what you got",
            body: "Your company's AI Gateway models are listed with ipr_ names. The idexal-os-cloud MCP server gives the agent your organization's skills, plugins, and connections.",
            code: "opencode models | grep ipr_\nopencode mcp list"
          }
        ]
      },
      {
        id: "what-the-plugin-adds",
        heading: "What the plugin adds to OpenCode",
        table: {
          caption: "What the opencode-idexal-os plugin loads into OpenCode",
          columns: ["What", "What it means"],
          rows: [
            ["AI Gateway models", "The models your admin gave you, through your company's gateway. Provider keys stay on the server."],
            ["idexal-os-cloud MCP", "Your organization's skills, plugins, saved workflows, and connections, found with search_capabilities."],
            ["Direct connections", "Tools your admin shared, such as Slack or Google Workspace, listed as idexal-os-direct-<name>."],
            ["Automatic refresh", "New models and connections appear within minutes, with no restart."],
            ["Clean sign-out", "opencode auth logout idexalos removes everything the plugin added."]
          ]
        },
        paragraphs: [
          "Self-hosting idexal Os? Set apiBaseUrl in the plugin options to your own idexal Os API. To load only models or only MCP, set providers or mcp to false."
        ]
      },
      {
        id: "which-one",
        heading: "idexal Os app or OpenCode with the plugin?",
        table: {
          caption: "Choosing between the idexal Os app and OpenCode with the plugin",
          columns: ["", "idexal Os desktop app", "OpenCode + opencode-idexal-os"],
          rows: [
            ["Best for", "Business teams and anyone who prefers a window", "Engineers who live in the terminal"],
            ["Engine", "OpenCode", "OpenCode"],
            ["Company models and connections", "Yes", "Yes"],
            ["Skills shared by your team", "Yes, installed in one click", "Yes, through idexal-os-cloud MCP"],
            ["Browser automation, scheduled tasks, file previews", "Yes", "Use OpenCode's own tools"],
            ["Sign-in", "Joining a team? Sign in", "opencode auth login idexalos"]
          ]
        }
      }
    ],
    cards: [
      { icon: "library", title: "Install the OpenCode plugin", link: { label: "Plugin guide", href: "/docs/model-context-protocol/opencode-plugin" } },
      { icon: "route", title: "Only want MCP in OpenCode?", link: { label: "Connect OpenCode", href: "/docs/model-context-protocol/opencode" } },
      { icon: "users", title: "Share approved models with engineers", link: { label: "AI Gateway", href: "/docs/ai-gateway/overview" } },
      { icon: "monitor", title: "Prefer a desktop app?", link: { label: "Download idexal Os", href: "/download" } }
    ],
    faq: [
      {
        question: "Is idexal Os a fork of OpenCode?",
        answer:
          "No. idexal Os runs the OpenCode engine and adds a desktop app, team sharing, and admin controls. OpenCode features work in idexal Os as they are."
      },
      {
        question: "Do I need the idexal Os app to use the OpenCode plugin?",
        answer:
          "No. The opencode-idexal-os plugin works in plain OpenCode 2. Install it, sign in once, and OpenCode loads your organization's models and MCP connections."
      },
      {
        question: "Which OpenCode version does the plugin need?",
        answer: "OpenCode 2. OpenCode 1 is not supported by the plugin; use the idexal Os MCP URL instead."
      },
      {
        question: "Does the plugin work with self-hosted idexal Os?",
        answer: "Yes. Set apiBaseUrl in the plugin options in ~/.config/opencode/opencode.json to your idexal Os API address."
      },
      {
        question: "Are my existing OpenCode skills and config used by idexal Os?",
        answer:
          "Yes. idexal Os reads the same SKILL.md skills, MCP servers, and opencode.json files, so you can open an existing folder and keep working."
      }
    ],
    cta: {
      heading: "One engine for the whole company.",
      sub: "Engineers keep OpenCode. Everyone else gets the idexal Os app.",
      primary: { label: "Download idexal Os free", href: "/download" },
      secondary: { label: "OpenCode plugin docs", href: "/docs/model-context-protocol/opencode-plugin" }
    }
  },
  {
    slug: "claude-code-codex-cursor",
    label: "Claude Code, Codex, and Cursor",
    title: "Use idexal Os with Claude Code, Codex, and Cursor",
    description:
      "Yes. Add one MCP URL to Claude Code, Codex, Cursor, Claude Desktop, VS Code, or Gemini CLI to get your organization's idexal Os skills and connections.",
    heading: "Can you use idexal Os with Claude Code, Codex, or Cursor?",
    answer:
      "Yes. Add one MCP server URL to Claude Code, Codex, Cursor, Claude Desktop, VS Code, Gemini CLI, or ChatGPT, and the agent gets your organization's idexal Os skills, plugins, and connections, with the same access rules. AI Gateway models for these tools are in progress.",
    updated: "2026-10-10",
    keyFacts: [
      "MCP URL: https://api.idexal.com/mcp/agent",
      "Sign-in: OAuth in the browser, per organization",
      "Setup guides for 10 MCP clients",
      "First 5 idexal Os Cloud seats free"
    ],
    sections: [
      {
        id: "one-url",
        heading: "One MCP URL for every agent",
        paragraphs: [
          `idexal Os Connect is an MCP gateway at ${MCP_URL}. You sign in with your idexal Os account in the browser, pick your organization, and the agent can use what your admin shared with you.`,
          "The agent sees two main tools: search_capabilities finds the right skill, workflow, or connection, and execute_capability runs it. Your team adds a skill or connection once in idexal Os, and it shows up in every agent."
        ]
      },
      {
        id: "setup",
        heading: "Set it up in your agent",
        steps: [
          {
            title: "Claude Code",
            body: "Add the server, then run /mcp in Claude Code and choose idexalos to sign in. Add -s user to use it in every project.",
            code: `claude mcp add --transport http idexalos ${MCP_URL}`
          },
          {
            title: "Codex",
            body: "Add the server, then sign in.",
            code: `codex mcp add idexalos --url ${MCP_URL}\ncodex mcp login idexalos`
          },
          {
            title: "Cursor",
            body: "Add a remote MCP server in Cursor settings and paste the URL, or use the one-click install link in the docs.",
            code: MCP_URL
          },
          {
            title: "Claude Desktop and claude.ai",
            body: "Open Settings, then Connectors, then Add custom connector, and paste the URL.",
            code: MCP_URL
          },
          {
            title: "VS Code",
            body: "Add the server from the command line.",
            code: `code --add-mcp '{"name":"idexalos","type":"http","url":"${MCP_URL}"}'`
          },
          {
            title: "Gemini CLI",
            body: "Add the server, then run /mcp auth idexal-os.",
            code: `gemini mcp add --transport http idexalos ${MCP_URL}`
          }
        ]
      },
      {
        id: "what-you-get",
        heading: "What your agent gets",
        bullets: [
          "Your organization's skills, written once in idexal Os and used from any agent.",
          "Shared connections such as Google Workspace, Microsoft 365, Slack, and other MCP servers, without each person setting up their own keys.",
          "Saved workflows your team built, run by name.",
          "The same access rules as the idexal Os app: you only see what your organization, team, or admin granted you.",
          "Tool settings on every call: if an admin turns a tool off, it's off in every agent."
        ]
      },
      {
        id: "ai-gateway",
        heading: "AI Gateway models in Claude Code, Codex, and Cursor",
        paragraphs: [
          "The idexal Os AI Gateway lets admins add model providers once (OpenAI, Anthropic, Bedrock, Vertex, Azure, or any OpenAI-compatible endpoint), choose who can use them, and set spend limits. Provider keys stay on the server.",
          "OpenCode already gets these models through the opencode-idexal-os plugin. We are building the same integration for Claude Code, Codex, and Cursor, so every agent your engineers use runs on approved models with usage counted per person."
        ]
      }
    ],
    cards: [
      { icon: "route", title: "Connect any MCP client", link: { label: "Setup guide", href: "/docs/start-here/connect-idexal-os-mcp" } },
      { icon: "key", title: "Add models once with the AI Gateway", link: { label: "AI Gateway", href: "/docs/ai-gateway/overview" } },
      { icon: "library", title: "Use OpenCode? Install the plugin", link: { label: "idexal Os and OpenCode", href: "/guides/idexal-os-and-opencode" } },
      { icon: "users", title: "Share connections with your team", link: { label: "idexal Os Connect", href: "/connect" } }
    ],
    faq: [
      {
        question: "Does idexal Os work with Claude Code?",
        answer: `Yes. Run claude mcp add --transport http idexalos ${MCP_URL}, then run /mcp and sign in.`
      },
      {
        question: "Does idexal Os work with Codex?",
        answer: `Yes. Run codex mcp add idexalos --url ${MCP_URL}, then codex mcp login idexal-os.`
      },
      {
        question: "Does idexal Os work with Cursor?",
        answer: "Yes. Add the idexal Os MCP URL as a remote MCP server in Cursor, then sign in with your idexal Os account."
      },
      {
        question: "Do I need an idexal Os account?",
        answer: "Yes, an idexal Os Cloud account. The first 5 seats are free."
      },
      {
        question: "Can I use my company's AI Gateway models in Claude Code, Codex, or Cursor?",
        answer:
          "Not yet. OpenCode gets AI Gateway models today through the opencode-idexal-os plugin. The same integration for Claude Code, Codex, and Cursor is in progress."
      },
      {
        question: "How do I switch organizations?",
        answer: "Sign out of the idexalos MCP server in your agent and sign in again, then pick the other organization."
      }
    ],
    cta: {
      heading: "Your team's skills, in every agent.",
      sub: "Add a skill or connection once. Use it from idexal Os, Claude Code, Codex, Cursor, and more.",
      primary: { label: "Get started free", href: "https://os.idexal.com?mode=sign-up" },
      secondary: { label: "Setup guide", href: "/docs/start-here/connect-idexal-os-mcp" }
    }
  },
  {
    slug: "ollama",
    label: "idexal Os with Ollama",
    title: "How to use idexal Os with Ollama and local models",
    description:
      "idexal Os has a built-in Ollama screen. Pull a model, add it to your workspace, and run AI agents on your files with no API key and nothing leaving your computer.",
    heading: "How does idexal Os work with Ollama?",
    answer:
      "idexal Os has a built-in Ollama screen. Start Ollama, open Settings › Ollama in idexal Os, pick or pull a model, and click Add to workspace. No API key and no account, and your prompts and files stay on your computer.",
    updated: "2026-10-10",
    keyFacts: [
      "Ollama address: http://localhost:11434",
      "LM Studio address: http://localhost:1234/v1",
      "No API key or account needed",
      "$0 idexal Os cost for local models"
    ],
    sections: [
      {
        id: "setup",
        heading: "Set up Ollama in idexal Os",
        steps: [
          {
            title: "Install and start Ollama",
            body: "Download Ollama from ollama.com and start it. idexal Os talks to it at http://localhost:11434."
          },
          {
            title: "Open Settings › Ollama",
            body: "Wait for the status to show Ollama running. If it doesn't, start Ollama and click refresh."
          },
          {
            title: "Pick or pull a model",
            body: "Choose a model you already have, or click Pull a model and type a name from the Ollama library. You can also pull it in a terminal first.",
            code: "ollama pull qwen2.5-coder:7b"
          },
          {
            title: "Add to workspace",
            body: "idexal Os saves it as Ollama (local). Start a chat and pick the model in the model picker."
          }
        ],
        paragraphs: [
          "idexal Os checks each model for image support and thinking levels, and Sync all models matches idexal Os to everything installed in Ollama."
        ]
      },
      {
        id: "other-local",
        heading: "LM Studio, llama.cpp, vLLM, and other local servers",
        paragraphs: [
          "Any local server with an OpenAI-compatible API works. Add it to opencode.jsonc in your workspace folder, then run Reload OpenCode config."
        ],
        steps: [
          {
            title: "Example: LM Studio",
            body: "Replace the model ID with the one your server reports.",
            code: `{
  "provider": {
    "lmstudio": {
      "npm": "@ai-sdk/openai-compatible",
      "name": "LM Studio (local)",
      "options": { "baseURL": "http://localhost:1234/v1" },
      "models": { "qwen2.5-7b-instruct": { "name": "Qwen 2.5 7B Instruct" } }
    }
  }
}`
          }
        ]
      },
      {
        id: "pick-a-model",
        heading: "Pick a model that can use tools",
        paragraphs: [
          "idexal Os is an agent: it reads files, runs tools, and calls MCP servers. Small local models chat well but often struggle with tools. If a task stalls, try a larger model or one built for tool calling, and keep a cloud model connected for long tasks."
        ]
      },
      {
        id: "teams",
        heading: "Local and self-hosted models for a team",
        bullets: [
          "Share one self-hosted model: run Ollama or vLLM behind an endpoint your team can reach, and add it once in idexal Os Cloud as a custom provider. It shows up on everyone's desktop.",
          "Route it through the AI Gateway to choose who can use it and count usage per person.",
          "Want only approved models? Admins can turn on Only models you provide, which stops people adding their own providers, including local ones."
        ]
      }
    ],
    cards: [
      { icon: "cpu", title: "Full local model guide", link: { label: "Use local models", href: "/docs/start-here/connect-your-stack/use-local-models" } },
      { icon: "key", title: "Add a custom LLM or gateway", link: { label: "Custom LLM", href: "/docs/start-here/connect-your-stack/add-a-custom-llm" } },
      { icon: "users", title: "Share a self-hosted model with your team", link: { label: "Custom provider", href: "/docs/cloud/share-with-your-team/custom-llm-provider" } },
      { icon: "monitor", title: "Get the app", link: { label: "Download", href: "/download" } }
    ],
    faq: [
      {
        question: "Is idexal Os free with Ollama?",
        answer: "Yes. idexal Os is free and open source, and a local model adds no idexal Os cost. You only need a computer that can run the model."
      },
      {
        question: "Does anything leave my computer with Ollama?",
        answer: "Model inference goes to Ollama on your computer. Web tools, connectors, updates, and other enabled services can still make network requests; local inference is not a guarantee that the whole app is offline."
      },
      {
        question: "Which Ollama model should I use?",
        answer: "Start with a model built for tool calling, such as qwen2.5-coder. Bigger models handle multi-step tasks better."
      },
      {
        question: "Does idexal Os work offline?",
        answer: "Yes, once the model is downloaded. Chat on your files works without internet; web tools and online connectors don't."
      },
      {
        question: "Why can't I add Ollama on my work computer?",
        answer: "Your organization may allow only the models it provides. Ask your idexal Os admin."
      }
    ],
    cta: {
      heading: "AI agents on your files. Fully local.",
      sub: "Free, open source, and private by default.",
      primary: { label: "Download idexal Os free", href: "/download" },
      secondary: { label: "Local model guide", href: "/docs/start-here/connect-your-stack/use-local-models" }
    }
  },
  {
    slug: "control-ai-costs",
    label: "Keep AI costs under control",
    title: "How to keep AI agent costs under control",
    description:
      "Set daily, weekly, or monthly AI spend limits, see usage by model, team, or person, and choose lower-cost models for routine work.",
    heading: "How do you keep AI agent costs under control?",
    answer:
      "Use organization-managed provider keys, set daily, weekly, or monthly allowances, and review usage by model, team, or person. In idexal Os, use cheaper models for routine tasks and reserve frontier models for work that needs them.",
    updated: "2026-10-10",
    keyFacts: [
      "Spend limits per day, week, or month, in USD",
      "Limits for everyone, a team, or one person",
      "Usage by model, team, or person for the last 31 days",
      "Pause, warn, or approve requests for 25% more"
    ],
    sections: [
      {
        id: "why",
        heading: "Why AI agent costs get out of hand",
        paragraphs: [
          "Agents use far more tokens than chat. One task can read dozens of files, call tools, and retry, and every step is billed. When people pay with personal keys, the company can't see the total until the invoices arrive.",
          "Most of the waste comes from three places: no limits, no visibility, and using a frontier model for questions a small model could answer."
        ]
      },
      {
        id: "limits",
        heading: "Set spend limits",
        paragraphs: [
          "Spend limits apply to priced organization-key providers; they do not cap every external bill or personal provider key. In the AI Gateway, open Limits and choose who a limit applies to: everyone in the organization (including people who join later), a team, or one person. Turn on any mix of a daily, weekly, and monthly amount. Whichever runs out first applies."
        ],
        table: {
          caption: "What happens when someone reaches a spend limit",
          columns: ["Choice", "What happens"],
          rows: [
            ["Pause their models", "Requests stop until the limit resets or an admin gives more."],
            ["Only warn", "Requests keep working and the limit shows as over."],
            ["Let them ask for 25% more", "The person can request more, and admins approve or deny it in Limits."]
          ]
        },
        bullets: [
          "In the desktop app, the chat says which limit was reached and when it resets.",
          "Members see what's left in the account menu and in Settings › Usage."
        ]
      },
      {
        id: "visibility",
        heading: "See who spends what",
        bullets: [
          "The AI Gateway overview shows who spends the most and which models people use.",
          "Usage charts show tokens or cost for the last 31 days, grouped by model, team, or person.",
          "Each person's page shows what they can use, their limit, and their recent spend.",
          "Costs come from token counts and published model prices, including cached input and reasoning tokens."
        ]
      },
      {
        id: "cheaper-models",
        heading: "Use cheaper models where they're good enough",
        bullets: [
          "idexal Os Models gives your team hand-picked open models, such as GLM, Kimi, and DeepSeek, for $10 per user per month, with no API keys to manage.",
          "Local models through Ollama or LM Studio cost nothing per token.",
          "Grant expensive models only to the teams that need them, and turn on Only models you provide so nobody adds a personal key.",
          "Already run LiteLLM? Connect it to idexal Os and keep its budgets."
        ]
      }
    ],
    cards: [
      { icon: "route", title: "Spend limits and model access", link: { label: "AI Gateway", href: "/docs/ai-gateway/overview" } },
      { icon: "library", title: "How token costs are counted", link: { label: "Token costs", href: "/docs/ai-gateway/token-costs" } },
      { icon: "cpu", title: "Run models for free on your computer", link: { label: "idexal Os with Ollama", href: "/guides/ollama" } },
      { icon: "key", title: "How gateway limits work", link: { label: "Request flow", href: "/docs/ai-gateway/how-requests-flow" } }
    ],
    faq: [
      {
        question: "Can I set a monthly AI budget per team?",
        answer: "Yes. In the AI Gateway, add a spend limit for a team with a monthly amount. You can also add daily or weekly amounts; whichever runs out first applies."
      },
      {
        question: "What happens when someone hits their limit?",
        answer: "You choose: pause their models, only warn, or let them ask for 25% more for an admin to approve."
      },
      {
        question: "Are the cost numbers exact?",
        answer: "They're close estimates from token counts and published prices. Your provider's invoice is the final number."
      }
    ],
    cta: {
      heading: "Know what AI costs before the invoice.",
      sub: "Spend allowances and usage reporting for organization-managed providers.",
      primary: { label: "Get started free", href: "https://os.idexal.com?mode=sign-up" },
      secondary: { label: "AI Gateway docs", href: "/docs/ai-gateway/overview" }
    }
  },

];

export function guidePath(guide: Guide): string {
  return `${GUIDES_PATH}/${guide.slug}`;
}

export function findGuide(slug: string): Guide | undefined {
  return guides.find((guide) => guide.slug === slug);
}

function absolute(href: string): string {
  return href.startsWith("http") ? href : `${SITE_URL}${href}`;
}

function tableMarkdown(table: GuideTable): string {
  const header = `| ${table.columns.join(" | ")} |`;
  const divider = `| ${table.columns.map(() => "---").join(" | ")} |`;
  const rows = table.rows.map((row) => `| ${row.join(" | ")} |`);
  return [header, divider, ...rows].join("\n");
}

function sectionMarkdown(section: GuideSection): string {
  const parts: string[] = [`## ${section.heading}${section.badge ? ` (${section.badge})` : ""}`];
  for (const paragraph of section.paragraphs ?? []) parts.push(paragraph);
  if (section.steps) {
    parts.push(
      section.steps
        .map((step, index) => {
          const code = step.code ? `\n\n   \`\`\`\n${step.code.replace(/^/gm, "   ")}\n   \`\`\`` : "";
          return `${index + 1}. **${step.title}** — ${step.body}${code}`;
        })
        .join("\n")
    );
  }
  if (section.table) parts.push(tableMarkdown(section.table));
  if (section.bullets) parts.push(section.bullets.map((bullet) => `- ${bullet}`).join("\n"));
  return parts.join("\n\n");
}

export function guideMarkdown(guide: Guide): string {
  return `# ${guide.heading}

> ${guide.answer}

Updated ${guide.updated}

${guide.keyFacts.map((fact) => `- ${fact}`).join("\n")}

${guide.sections.map(sectionMarkdown).join("\n\n")}

## FAQ

${guide.faq.map((entry) => `### ${entry.question}\n${entry.answer}`).join("\n\n")}

## Related

${guide.cards.map((card) => `- ${card.title}: [${card.link.label}](${absolute(card.link.href)})`).join("\n")}
- [${guide.cta.primary.label}](${absolute(guide.cta.primary.href)})
`;
}

export function guidesIndexMarkdown(): string {
  return `# idexal Os guides

> Short answers about idexal Os with OpenCode, MCP clients, and local models, plus spend allowances and usage reporting.

${guides.map((guide) => `- [${guide.heading}](${SITE_URL}${guidePath(guide)}) — ${guide.answer}`).join("\n")}
`;
}
