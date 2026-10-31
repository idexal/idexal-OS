import { ClaimReminderEmail, type ClaimReminderEmailProps } from "../src/templates/lifecycle-reminders"

export default function ClaimReminderPreview(props: ClaimReminderEmailProps) {
  return <ClaimReminderEmail {...props} />
}

ClaimReminderPreview.PreviewProps = {
  organizationName: "idexal Os Preview",
  claimLink: "https://os.idexal.com/workspace-claim?token=claim_preview&email=ada%40example.com",
  expiresAtLabel: "Tuesday, October 6 at 3:00 PM UTC",
} satisfies ClaimReminderEmailProps
