import type { CompareCard } from "./compare";
import { capabilityMarkdown } from "./cowork-capabilities";
import type { FaqEntry } from "./faq";

export const CLAUDE_COWORK_3P_PATH = "/alternatives/claude-cowork-3p";
export const CLAUDE_COWORK_3P_URL = `https://os.idexal.com${CLAUDE_COWORK_3P_PATH}`;

export const claudeCowork3pHeading = "idexal Os vs Claude Cowork on 3P";

/** Hero sub-line. Keep it under 20 words. */
export const claudeCowork3pAnswer =
  "Keep Bedrock, Vertex, or Foundry. Add any model, and share skills and MCP servers with every teammate.";

export const threePCards: CompareCard[] = [
  {
    icon: "cloud",
    title: "Keep your cloud account and committed spend",
    link: { label: "Connect Vertex", href: "/docs/ai-gateway/google-agent-platform" }
  },
  {
    icon: "route",
    title: "Send routine work to lower-cost models",
    link: { label: "Add a gateway", href: "/docs/cloud/share-with-your-team/custom-llm-provider" }
  },
  {
    icon: "library",
    title: "Share skills and MCP servers without MDM profiles",
    link: { label: "Publish a skill", href: "/docs/start-here/do-work-with-it/publish-and-copy-a-skill" }
  },
  {
    icon: "migrate",
    title: "Run alongside Claude Desktop while teams switch",
    link: { label: "Migration guide", href: "/docs/start-here/migrate-from-claude-cowork" }
  }
];

export const claudeCowork3pFaq: FaqEntry[] = [
  {
    question: "What is Claude Desktop on 3P?",
    answer:
      "Claude Desktop, including Cowork, sending inference to Bedrock, Vertex, Foundry, or your own gateway. There is no seat fee. It is built for Claude models; others need an Anthropic-compatible gateway you run."
  },
  {
    question: "Can idexal Os use my Bedrock, Vertex, or Foundry account?",
    answer: "Yes, plus any OpenAI-compatible gateway. Your existing cloud commitments keep applying."
  },
  {
    question: "How do we share skills and MCP servers?",
    answer: "Admins publish them once in idexal Os Cloud and assign them to teams. Each person signs in to MCP connections as themselves."
  },
  {
    question: "Can we self-host idexal Os?",
    answer: "Yes, with Helm or Docker Compose. Enterprise costs the same cloud or self-hosted."
  },
  {
    question: "Does idexal Os have a mobile app?",
    answer: "Not yet; it is on the public roadmap. idexal Os runs on macOS, Windows, Linux, and in the browser."
  }
];

export const claudeCowork3pMarkdown = `# ${claudeCowork3pHeading} (Bedrock, Vertex, Foundry)

> ${claudeCowork3pAnswer}

## Claude Enterprise vs Claude Desktop on 3P vs idexal Os

${capabilityMarkdown()}

## Why teams on 3P switch

${threePCards.map((card) => `- ${card.title}: [${card.link.label}](https://os.idexal.com${card.link.href})`).join("\n")}

## FAQ

${claudeCowork3pFaq.map((entry) => `### ${entry.question}\n${entry.answer}`).join("\n\n")}

## Next steps

- [Talk to us about Enterprise](https://os.idexal.com/enterprise#book)
- [Download idexal Os](https://os.idexal.com/download)
- [Claude Cowork alternative overview](https://os.idexal.com/alternatives/claude-cowork)
`;
