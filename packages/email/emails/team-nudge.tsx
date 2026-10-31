import { TeamNudgeEmail, type TeamNudgeEmailProps } from "../src/templates/lifecycle-reminders"

export default function TeamNudgePreview(props: TeamNudgeEmailProps) {
  return <TeamNudgeEmail {...props} />
}

TeamNudgePreview.PreviewProps = {
  organizationName: "idexal Os Preview",
  membersLink: "https://os.idexal.com/dashboard/members",
  mcpUrl: "https://api.idexal.com/mcp/agent",
  mcpDocsLink: "https://os.idexal.com/docs/start-here/connect-idexal-os-mcp",
  unsubscribeLink: "https://api.idexal.com/v1/email/unsubscribe?email=ada%40example.com&token=preview",
} satisfies TeamNudgeEmailProps
