/** Rollout-dependent visibility of Den API catalog operations to agents. Pure, so it is testable without a database. */
export type CatalogAvailabilityContext = {
  generatedArtifactViewsEnabled: boolean
  /** Managed deployment planning, launch preparation and status: both rollouts on. */
  managedDeploymentsMcpEnabled: boolean
  /** Secure keys in chat: agents may add AI Gateway providers and a person adds the key. */
  secureSecretFieldsEnabled?: boolean
}

export function catalogOperationAvailableToCapabilities(
  context: CatalogAvailabilityContext,
  operation: { method: string; path: string },
) {
  if (operation.method.toUpperCase() === "POST" && operation.path === "/v1/inference-providers") {
    return context.secureSecretFieldsEnabled === true
  }
  // Hidden from search, enumeration and execution until the organization has
  // both rollouts; the routes themselves still enforce permissions and features.
  if (operation.path === "/v1/managed-deployments" || operation.path.startsWith("/v1/managed-deployments/")) {
    return context.managedDeploymentsMcpEnabled
  }
  if (context.generatedArtifactViewsEnabled) return true
  return operation.path !== "/v1/workflows/{configObjectId}/views"
    && !operation.path.startsWith("/v1/artifact-views/")
}
