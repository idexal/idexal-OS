import { isIdexalOsGatewayRuntime } from "./gateway-runtime";

export function canCreateWorkspaces() {
  return !isIdexalOsGatewayRuntime();
}
