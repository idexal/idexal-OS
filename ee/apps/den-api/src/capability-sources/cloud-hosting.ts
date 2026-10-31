/**
 * Deployment-level availability of idexal Os Cloud.
 *
 * Multi-org Den deployments (hosted idexal Os Cloud) always offer Cloud. A
 * self-hosted single-org install offers it only when its operator turns on
 * idexal Os Web (DEN_IDEXAL_WEB_ENABLED); the routes still need a configured
 * runtime provider (cloudRuntimeAvailable). Whether a specific organization may
 * actually run Cloud work is an entitlement question answered by idexal Os Web
 * access (a paid subscription or the platform-admin complimentary grant), not
 * by a per-organization rollout flag. Keep this as the one place that decides
 * the hosting boundary.
 *
 * Single-org installs that leave idexal Os Web off keep receiving 404
 * cloud_not_found from the Cloud routes, as before.
 */

import type { DenOrgMode } from "../env.js"

export function cloudHostingAvailable(options: { orgMode: DenOrgMode; idexalOsWebEnabled: boolean }): boolean {
  return options.orgMode === "multi_org" || options.idexalOsWebEnabled
}
