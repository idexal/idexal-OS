import { env } from "./env.js"
import { hasIdexalOsWebComplimentaryAccess } from "./idexal-os-web-access.js"

export function idexalOsWebDeploymentAvailable(enabled: boolean) {
  return enabled === true
}

export function isIdexalOsWebAvailable() {
  return idexalOsWebDeploymentAvailable(env.idexalOsWebEnabled)
}

export function idexalOsWebAvailableForOrganization(
  enabled: boolean,
  metadata: Record<string, unknown> | string | null | undefined,
) {
  return idexalOsWebDeploymentAvailable(enabled) || hasIdexalOsWebComplimentaryAccess(metadata)
}

export function isIdexalOsWebAvailableForOrganization(
  metadata: Record<string, unknown> | string | null | undefined,
) {
  return idexalOsWebAvailableForOrganization(env.idexalOsWebEnabled, metadata)
}
