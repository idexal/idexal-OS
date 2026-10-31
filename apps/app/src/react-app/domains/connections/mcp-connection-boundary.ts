import { getMcpServerName, type McpDirectoryInfo } from "../../../app/constants";
import { CLOUD_MCP_SERVER_NAME } from "./cloud-mcp-user-state";

export function conflictsWithIdexalOsConnect(
  entry: Pick<McpDirectoryInfo, "id" | "name" | "serverName" | "managedBy">,
): boolean {
  const serverName = entry.id ?? getMcpServerName({
    ...entry,
    description: "",
    oauth: false,
  });
  return entry.managedBy !== "idexal-os-connect" && serverName === CLOUD_MCP_SERVER_NAME;
}
