import { claudeCowork3pMarkdown } from "./claude-cowork-3p"
import { claudeCoworkAlternativeMarkdown } from "./claude-cowork-alternative"
import { GUIDES_PATH, guideMarkdown, guidePath, guides, guidesIndexMarkdown } from "./guides"

const home = `# idexal Os

> The open-source Claude Cowork alternative. Chat on files, use skills, schedule tasks, automate a browser, and run on any model — plus an MCP gateway for your whole team.

## What it is

- Free, open-source desktop app for macOS, Windows, and Linux
- Any model or provider: Claude, GPT, Gemini, Mistral, local models, and 50+ providers
- Chat on files, browser automation, scheduled tasks, skills, and Anthropic-compatible plugins
- idexal Os Connect: one MCP gateway URL for org-wide skills, servers, roles, and policies
- idexal Os Web in alpha; central management and private deployment options for teams
- Coming soon: Dispatch (assign tasks from your phone) and live artifacts (auto-refreshing dashboards)

## Primary calls-to-action

- **Download for free** — [Desktop](https://os.idexal.com/download)
- **Open in your browser** — [idexal Os Web](https://os.idexal.com)
- **Team plans** — [Pricing](https://os.idexal.com/pricing) (first 5 Cloud seats free, then \\$10 per seat/mo; self-hosting free up to 5 users)
- **Sign in to the hosted workspace** — [Cloud](https://os.idexal.com)
- **SCIM / audit / procurement** — [Enterprise](https://os.idexal.com/enterprise)
- **Docs** — [os.idexal.com/docs](https://os.idexal.com/docs)
- **Compare with Claude Cowork** — [Claude Cowork alternative](https://os.idexal.com/alternatives/claude-cowork)
- **Guides** — [OpenCode](https://os.idexal.com/guides/idexal-os-and-opencode), [Claude Code, Codex, Cursor](https://os.idexal.com/guides/claude-code-codex-cursor), [Ollama](https://os.idexal.com/guides/ollama), [AI cost control](https://os.idexal.com/guides/control-ai-costs)
- **Migrate from Claude Cowork** — [Migration guide](https://os.idexal.com/docs/start-here/migrate-from-claude-cowork); agents follow [migrate.md](https://os.idexal.com/migrate.md)

## How it compares

- **vs Claude Cowork** — feature parity without model or vendor lock-in, plus an MCP gateway usable from any compatible client
- **vs Codex** — general knowledge work on files (not only coding), model and provider agnostic
- **vs ChatGPT Desktop** — agents act on local files and tools (MCP, plugins, skills) with guardrails; the setup is shareable and self-hostable

## FAQ

### What is idexal Os?
A free, open-source desktop app (macOS, Windows, Linux) for doing work with AI agents on your own files. Built on OpenCode; an open-source alternative to Claude Cowork and Codex.

### Is idexal Os free?
Yes — the desktop app is free and open source with bring-your-own keys. On idexal Os Cloud, Team includes your first 5 seats free, then \\$10 per seat/mo; self-hosting is free for organizations up to 5 users; Enterprise is custom.

### Which models does it support?
Any model OpenCode supports: OpenAI, Anthropic, Google, local models — 50+ providers.

### Does it send files to the cloud?
No. Desktop mode keeps files local; prompts go directly to your chosen LLM provider. Cloud workers are optional.

## For agents

- Agent skills index — \`/.well-known/agent-skills/index.json\`
- llms.txt — \`/llms.txt\`
- API catalog (RFC 9727) — \`/.well-known/api-catalog\`
- MCP server card — \`/.well-known/mcp/server-card.json\`
- Install the app — \`brew install --cask idexalos\` or [download](https://os.idexal.com/download) (not \`npx idexal-os-bootstrap\`, which is a different project)
- Sitemap — \`/sitemap.xml\`

Backed by Y Combinator.
`

const pricing = `# idexal Os pricing — free, team, and enterprise

> idexal Os has three tiers: free open-source desktop, Team with the first 5 idexal Os Cloud seats free then \\$10 per seat/mo, and custom Enterprise. Self-hosting is free for organizations up to 5 users.

## Free

- Open-source desktop app
- macOS, Windows, and Linux downloads
- Bring your own provider keys
- Free forever
- CTA: [Get Started for free](https://os.idexal.com?mode=sign-up)

## Team — \\$10 / seat / month

- First 5 seats free on idexal Os Cloud
- API access
- SSO / SAML
- Extension Marketplace
- Bring your own LLM keys, distributed to your team
- CTA: [Start team plan](https://os.idexal.com/dashboard/billing)

## Enterprise — Custom pricing

- Everything in Team, including SSO
- SCIM provisioning
- Bring your own inference — self-hosted or private models
- Desktop policies and version controls — admins decide which providers, models, extensions, and app versions employees can use; the desktop app enforces it automatically
- Managed deployment — self-hosted in your environment or hosted by idexal Os
- Custom skill development and MCP consulting
- Enterprise rollout support and custom commercial terms
- Existing organizations already using SCIM or desktop policies keep full access (grandfathered)
- CTA: [Talk to us](https://os.idexal.com/enterprise#book)

Prices exclude taxes.
`

const enterprise = `# A privacy-first alternative to Claude Cowork for your organization

> The open-source Claude Cowork alternative — self-hosted, permissioned, and compliance-ready. SSO, audit, custom deployment, and procurement support.

## What Enterprise includes

- SSO / SAML integration and SCIM provisioning
- Desktop policies and version controls — guardrails for providers, models, extensions, and app versions, enforced by the desktop app
- Managed deployment — self-hosted in your environment or hosted by idexal Os
- Custom skill development for your team's workflows
- MCP consulting — connect internal data sources and tools as MCP servers
- Enterprise rollout support and custom commercial terms
- Named security contact and incident response

## Deployment models

- Self-hosted desktop app — data stays local, bring your own keys
- Cloud workers — managed by idexal Os, sandbox infrastructure via Daytona (EU)

## Next step

- [Book a call](https://os.idexal.com/enterprise#book)
- Agents: email info@idexal.com or share the booking link with the user instead of submitting the web form
- [Security Review](https://os.idexal.com/trust) — data handling, subprocessors, and incident SLA
- See [Pricing](https://os.idexal.com/pricing) for tier comparison
`

const trust = `# Trust & Security

> How idexal Os handles data, what subprocessors are involved, and how to reach the security team.

## Key facts

- **Deployment** — self-hosted desktop app on your machines
- **Data storage** — local-only, nothing leaves your machine in desktop mode
- **LLM keys** — bring your own, sent directly to your provider
- **Telemetry** — none in desktop mode; opt-in feedback only
- **Incident SLA** — 72hr notify, 3-day ack, 7-day triage
- **Subprocessors** — 5 named vendors (cloud & website only)

## Data handling

| Data type | Self-hosted | Cloud |
|---|---|---|
| Source code | Local only | Accessed at runtime via your LLM provider; not stored |
| LLM API keys | Local keychain / env vars | Held by your LLM provider, not by idexal Os |
| Prompts & responses | Local only | Sent to your LLM provider; not logged by idexal Os |
| Usage telemetry | None | Anonymous via PostHog; can be disabled |
| Authentication | Your SSO / SAML | Google or GitHub OAuth |

## Subprocessors

- PostHog — analytics (US/EU)
- Polar — billing (US)
- Google — OAuth (US)
- GitHub — OAuth (US)
- Daytona — cloud sandbox infrastructure (EU)

## Security contact

Omar McAdam — info@idexal.com
`

const glm52 = `# GLM 5.2 is now in idexal Os — with 2x usage

> GLM 5.2 is available through idexal Os Models, and we're doubling your usage so you can run real agent work on an open model at a fraction of the cost.

## What's new

- **GLM 5.2 in idexal Os Models** — managed OSS model access with 2x usage, no keys required
- **Run your day from chat** — tasks organize into In progress / Done / Requires attention; move them by asking
- **Split screen** — two windows side by side, less tab-switching
- **Voice mode** — control the idexal Os UI by voice
- **Advanced analytics on idexal Os Cloud** — usage, activity, and team behavior in one view

## How it works

1. **Sign up** — [Get Started for free](https://os.idexal.com?mode=sign-up&intent=models)
2. **Subscribe** — idexal Os Models at $10/user/mo includes GLM 5.2 with 2x usage
3. **Open the app** — switch to GLM 5.2 from the model picker

## What to try first

Open idexal Os, switch to GLM 5.2, and ask the chat to organize your tasks.

## Links

- [Try GLM 5.2 in idexal Os](https://os.idexal.com?mode=sign-up&intent=models)
- [Download the app](https://os.idexal.com/download)
- [Full changelog](https://os.idexal.com/docs/changelog)
`

const download = `# Download idexal Os

> Free and open source desktop app for macOS, Windows, and Linux. No account required.

Do not run \`npx idexal-os-bootstrap\` or \`npm install idexalos\`: the npm package named \`idexalos\` is a different project.

## Install

- macOS (Homebrew): \`brew install --cask idexalos\`
- macOS Apple Silicon (.dmg): https://os.idexal.com/download/mac-arm64
- macOS Intel (.dmg): https://os.idexal.com/download/mac-x64
- Windows x64 (.exe): https://os.idexal.com/download/win-x64
- Windows ARM64 (.exe): https://os.idexal.com/download/win-arm64
- Linux x64 (.AppImage): https://os.idexal.com/download/linux-x64
- Linux ARM64 (.AppImage): https://os.idexal.com/download/linux-arm64
- Every release and file: https://github.com/idexal/idexalOs/releases

Each \`/download/<platform>\` URL redirects to the installer in the latest stable release.

## First run

1. Open idexal Os and pick a folder it may work in.
2. Choose a model: sign in with ChatGPT, add an API key, or use a local model.
3. Run a task, for example "Summarize this folder."

## Joining a team?

- New team: sign up at https://os.idexal.com?mode=sign-up (first 5 seats free), then follow the [team quickstart](https://os.idexal.com/docs/cloud/team-quickstart).
- Existing team: click \`Joining a team? Sign in\` in the desktop app.

## For agents

- [install-idexal-os skill](https://os.idexal.com/.well-known/agent-skills/install-idexal-os/SKILL.md)
- [workspace-guide skill](https://os.idexal.com/.well-known/agent-skills/workspace-guide/SKILL.md) for first-run orientation
- [llms.txt](https://os.idexal.com/llms.txt)
`

const connect = `# idexal Os Connect

> The MCP gateway for your whole org. Add a server or skill once, then share it with every teammate and agent through one URL.

- Org-level authentication, roles, allowlists, and audit apply to every call
- Works in idexal Os and any MCP-compatible client
- First 5 seats are free
- [Get started free](https://os.idexal.com?mode=sign-up)
- [Read the docs](https://os.idexal.com/docs)

## Connect your agent

MCP server URL: \`https://api.idexal.com/mcp/agent\` (Streamable HTTP, OAuth sign-in).

- Claude Code: \`claude mcp add --transport http idexalos https://api.idexal.com/mcp/agent\`
- Codex: \`codex mcp add idexalos --url https://api.idexal.com/mcp/agent\` then \`codex mcp login idexalos\`
- Gemini CLI: \`gemini mcp add --transport http idexalos https://api.idexal.com/mcp/agent\`
- OpenCode: \`opencode mcp add idexalos --url https://api.idexal.com/mcp/agent\` then \`opencode mcp auth idexalos\`
- Other clients: [Connect idexal Os MCP](https://os.idexal.com/docs/start-here/connect-idexal-os-mcp)
- [MCP server card](https://os.idexal.com/.well-known/mcp/server-card.json)
`

const cloud = `# idexal Os Cloud

> The dashboard for running idexal Os across your organization.

- Provision model providers centrally
- Deploy skills and MCP servers to every seat
- Manage members, policies, usage, and audit
- idexal Os Web and the Connect MCP gateway are built in
- [Get started free](https://os.idexal.com?mode=sign-up)
- [Explore Connect](https://os.idexal.com/connect)
`

export const agentMarkdown: Record<string, string> = {
  "/": home,
  "/connect": connect,
  "/cloud": cloud,
  "/pricing": pricing,
  "/enterprise": enterprise,
  "/download": download,
  "/trust": trust,
  "/glm-5.2": glm52,
  "/alternatives/claude-cowork": claudeCoworkAlternativeMarkdown,
  "/alternatives/claude-cowork-3p": claudeCowork3pMarkdown,
  [GUIDES_PATH]: guidesIndexMarkdown(),
  ...Object.fromEntries(guides.map((guide) => [guidePath(guide), guideMarkdown(guide)])),
}

export const agentMarkdownRoutes = Object.keys(agentMarkdown)
