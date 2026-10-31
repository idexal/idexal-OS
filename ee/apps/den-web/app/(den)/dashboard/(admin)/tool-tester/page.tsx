import { McpConnectionsCapabilityGuard } from "../../_components/mcp-connections-capability-guard";
import { ToolTesterScreen } from "../../_components/tool-tester/tool-tester-screen";

export default function ToolTesterPage() {
  return (
    <McpConnectionsCapabilityGuard>
      <ToolTesterScreen />
    </McpConnectionsCapabilityGuard>
  );
}
