import { LandingTrustOverview } from "../../components/landing-trust";
import { getGithubData } from "../../lib/github";
import { baseOpenGraph, withSocialMetadata } from "../../lib/seo";

export const metadata = withSocialMetadata({
  title: "idexal Os — Security & Data Privacy",
  description:
    "How idexal Os handles data, subprocessors, incident response, and compliance for self-hosted enterprise deployments.",
  alternates: {
    canonical: "/trust"
  },
  openGraph: {
    ...baseOpenGraph,
    url: "https://os.idexal.com/trust"
  }
});

export default async function TrustPage() {
  const github = await getGithubData();
  const cal = process.env.NEXT_PUBLIC_CAL_URL ?? "";

  return (
    <LandingTrustOverview
      stars={github.stars}
      calUrl={cal}
    />
  );
}
