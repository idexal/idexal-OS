import { RoadmapPageShell } from "../../../components/roadmap-page-shell";
import { getGithubData } from "../../../lib/github";
import { baseOpenGraph, withSocialMetadata } from "../../../lib/seo";

export const metadata = withSocialMetadata({
  title: "idexal Os Roadmap",
  description:
    "What idexal Os supports today and what is coming next across desktop, hosted workspaces, external agents, and new surfaces.",
  alternates: {
    canonical: "/roadmap"
  },
  openGraph: {
    ...baseOpenGraph,
    title: "idexal Os Roadmap | A workspace for everyone, on any platform",
    description:
      "What is ready, being built, and coming soon across every idexal Os product.",
    url: "https://os.idexal.com/roadmap"
  }
});

export default async function DocsRoadmapPage() {
  const github = await getGithubData();

  return <RoadmapPageShell stars={github.stars} />;
}
