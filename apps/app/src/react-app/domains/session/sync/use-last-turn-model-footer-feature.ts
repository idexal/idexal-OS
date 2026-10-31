import { useEffect, useMemo, useSyncExternalStore } from "react"
import { useQuery } from "@tanstack/react-query"

import { createDenClient, readDenSettings } from "@/app/lib/den"
import { useDenAuth } from "@/react-app/domains/cloud/den-auth-provider"
import { useAutomationsDenContext } from "@/react-app/domains/automations/use-automations"
import { useOrgFeature } from "@/react-app/domains/cloud/use-org-feature"

// The shell resolves the rollout once; transcripts also render without Den providers.
let enabled = false
const listeners = new Set<() => void>()
function subscribe(listener: () => void) {
  listeners.add(listener)
  return () => { listeners.delete(listener) }
}

export function useLastTurnModelFooterEnabled(): boolean {
  return useSyncExternalStore(subscribe, () => enabled, () => false)
}

/** Missing, unreachable or signed-in without an organization means off. */
export function useLastTurnModelFooterFeature(): void {
  const denAuth = useDenAuth()
  const context = useAutomationsDenContext()
  const baseUrl = readDenSettings().baseUrl
  const client = useMemo(() => createDenClient({ baseUrl }), [baseUrl])
  const organization = useOrgFeature("lastTurnModelFooter")
  const deployment = useQuery({
    queryKey: ["den", "deployment-features", baseUrl],
    queryFn: () => client.getDeploymentFeatures(),
    enabled: !context.ready && denAuth.status === "signed_out",
    staleTime: 30_000,
    refetchInterval: 60_000,
    refetchIntervalInBackground: true,
    retry: false,
    select: (features) => features.lastTurnModelFooter === true,
  })
  const on = context.ready ? organization : denAuth.status === "signed_out" && deployment.data === true
  useEffect(() => {
    if (enabled === on) return
    enabled = on
    for (const listener of listeners) listener()
  }, [on])
}
