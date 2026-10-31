import { LegalPage } from "../../../components/legal-page";
import { withSocialMetadata } from "../../../lib/seo";

export const metadata = withSocialMetadata({
  title: "idexal Os — Subscription Terms",
  description:
    "Subscription terms governing production use of idexal Os Enterprise Edition software by Idexal, doing business as idexal Os.",
  alternates: {
    canonical: "/terms/subscription"
  }
});

export default function SubscriptionTermsPage() {
  return <LegalPage file="terms/subscription/subscription-terms.md" />;
}
