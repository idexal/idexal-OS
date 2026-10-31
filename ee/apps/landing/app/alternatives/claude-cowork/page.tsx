import { ClaudeCoworkAlternativePage } from "../../../components/claude-cowork-alternative-page";
import { StructuredData } from "../../../components/structured-data";
import {
  CLAUDE_COWORK_ALTERNATIVE_URL,
  claudeCoworkAlternativeFaq
} from "../../../lib/claude-cowork-alternative";
import { getGithubData } from "../../../lib/github";
import { baseOpenGraph, withSocialMetadata } from "../../../lib/seo";

export const metadata = withSocialMetadata({
  title: "Free, open-source Claude Cowork alternative — idexal Os",
  description:
    "idexal Os is the free, open-source Claude Cowork alternative for macOS, Windows, and Linux. Any model, local models, your own API keys.",
  alternates: {
    canonical: "/alternatives/claude-cowork"
  },
  openGraph: {
    ...baseOpenGraph,
    url: "https://os.idexal.com/alternatives/claude-cowork"
  }
});

const softwareApplicationSchema = {
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  name: "idexal Os",
  description:
    "Free, open-source alternative to Claude Cowork. Desktop app for macOS, Windows, and Linux that works with 50+ model providers, local models, and your own API keys.",
  url: CLAUDE_COWORK_ALTERNATIVE_URL,
  applicationCategory: "BusinessApplication",
  operatingSystem: "macOS, Windows, Linux",
  isAccessibleForFree: true,
  license: "https://github.com/idexal/idexalOs/blob/dev/LICENSE",
  downloadUrl: "https://os.idexal.com/download",
  offers: {
    "@type": "Offer",
    price: "0",
    priceCurrency: "USD",
    url: "https://os.idexal.com/download"
  },
  publisher: {
    "@type": "Organization",
    name: "idexal Os",
    url: "https://os.idexal.com"
  }
};

const faqSchema = {
  "@context": "https://schema.org",
  "@type": "FAQPage",
  mainEntity: claudeCoworkAlternativeFaq.map((entry) => ({
    "@type": "Question",
    name: entry.question,
    acceptedAnswer: {
      "@type": "Answer",
      text: entry.answer
    }
  }))
};

const breadcrumbSchema = {
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  itemListElement: [
    { "@type": "ListItem", position: 1, name: "idexal Os", item: "https://os.idexal.com" },
    {
      "@type": "ListItem",
      position: 2,
      name: "Claude Cowork alternative",
      item: CLAUDE_COWORK_ALTERNATIVE_URL
    }
  ]
};

export default async function ClaudeCoworkAlternative() {
  const github = await getGithubData();

  return (
    <>
      <StructuredData data={softwareApplicationSchema} />
      <StructuredData data={faqSchema} />
      <StructuredData data={breadcrumbSchema} />
      <ClaudeCoworkAlternativePage stars={github.stars} />
    </>
  );
}
