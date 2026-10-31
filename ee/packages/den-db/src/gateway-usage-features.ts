import { parseFeatureEnvironment, resolveFeature, type FeatureEnvironment } from "@idexal/features"
import {
  readFeatureRollouts,
  readOrganizationFeatureOverrides,
  type FeatureDatabase,
} from "./organization-features"

let environment: FeatureEnvironment | undefined

/**
 * Whether usage limits per provider (`gatewayProviderUsageLimits`) are on for
 * one organization, read from the feature registry's stored state. The
 * Gateway shares Den's configmap, so DEN_DEPLOYMENT and DEN_FEATURE_* locks
 * resolve the same way they do in den-api.
 */
export async function gatewayProviderUsageLimitsEnabled(
  database: FeatureDatabase,
  organizationId: string,
): Promise<boolean> {
  environment ??= parseFeatureEnvironment(process.env)
  const rollouts = await readFeatureRollouts(database)
  const overrides = await readOrganizationFeatureOverrides(database, organizationId)
  return resolveFeature("gatewayProviderUsageLimits", {
    deployment: environment.deployment,
    locks: environment.locks,
    rollouts,
    overrides,
  }).enabled
}
