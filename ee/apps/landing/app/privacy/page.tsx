import { LegalPage } from "../../components/legal-page";
import { withSocialMetadata } from "../../lib/seo";

export const metadata = withSocialMetadata({
  title: "idexal Os — Privacy Policy",
  description: "Privacy policy for Idexal, doing business as idexal Os.",
  alternates: {
    canonical: "/privacy"
  }
});

export default function PrivacyPage() {
  return <LegalPage file="privacy/privacy-policy.md" />;
}
