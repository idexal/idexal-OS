import { LandingHome } from "../components/landing-home";
import { getGithubData } from "../lib/github";
import { headers } from "next/headers";
import { StructuredData } from "../components/structured-data";
import { homeFaq } from "../lib/faq";
import { baseOpenGraph, withSocialMetadata } from "../lib/seo";

export const metadata = withSocialMetadata({
  alternates: {
    canonical: "/"
  },
  openGraph: {
    ...baseOpenGraph,
    title: "idexal Os — Free, open-source Claude Cowork alternative",
    description:
      "Free, open-source desktop AI agent app for macOS, Windows, and Linux. Any model, local models, your own keys. Share skills and MCPs with your team.",
    url: "https://os.idexal.com"
  }
});

const softwareApplicationSchema = {
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  name: "idexal Os",
  description:
    "Free, open-source Claude Cowork alternative. Desktop app for macOS, Windows, and Linux that works with 50+ model providers, local models, and your own API keys, with shared skills and MCP servers for teams.",
  url: "https://os.idexal.com",
  applicationCategory: "BusinessApplication",
  operatingSystem: "macOS, Windows, Linux",
  offers: {
    "@type": "Offer",
    price: "0",
    priceCurrency: "USD",
    url: "https://os.idexal.com/pricing"
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
  mainEntity: homeFaq.map((entry) => ({
    "@type": "Question",
    name: entry.question,
    acceptedAnswer: {
      "@type": "Answer",
      text: entry.answer
    }
  }))
};

export default async function Home() {
  const github = await getGithubData();
  const cal = process.env.NEXT_PUBLIC_CAL_URL || "/enterprise#book";
  const userAgent = (await headers()).get("user-agent")?.toLowerCase() || "";
  const isMobileVisitor = /android|iphone|ipad|ipod|mobile/.test(userAgent);

  return (
    <>
      <StructuredData data={softwareApplicationSchema} />
      <StructuredData data={faqSchema} />
      <LandingHome
        stars={github.stars}
        downloadHref={github.downloads.macos}
        windowsDownloadHref={github.downloads.windows}
        linuxDownloadHref={github.downloads.linux}
        callHref={cal}
        isMobileVisitor={isMobileVisitor}
      />
    </>
  );
}
