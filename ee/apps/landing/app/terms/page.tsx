import { LegalPage } from "../../components/legal-page";
import { withSocialMetadata } from "../../lib/seo";

export const metadata = withSocialMetadata({
  title: "idexal Os — Terms of Use",
  description: "Terms of use for Idexal, doing business as idexal Os.",
  alternates: {
    canonical: "/terms"
  }
});

export default function TermsPage() {
  return <LegalPage file="terms/terms-of-use.md" />;
}
