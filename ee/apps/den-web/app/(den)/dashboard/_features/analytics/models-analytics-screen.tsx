"use client";

import { useQuery } from "@tanstack/react-query";
import { DenButton } from "../../../_components/ui/button";
import { DenNotice } from "../../../_components/ui/notice";
import { getErrorMessage, requestJson } from "../../../_lib/den-flow";
import { redirect } from "next/navigation";
import { getAnalyticsRoute, getInferenceRoute, orgFeatureEnabled } from "../../../_lib/den-org";
import { parseInferencePayload } from "../../../_lib/inference-status";
import { useDenFlow } from "../../../_providers/den-flow-provider";
import { ModelsAnalyticsPanel } from "../../_components/models-analytics-panel";
import { useOrgDashboard } from "../../_providers/org-dashboard-provider";
import { AnalyticsEmptyState, AnalyticsErrorState, AnalyticsLoading, AnalyticsPageHeader, analyticsPageClass, analyticsSurfaceClass } from "./analytics-layout";
import { UsageLimitsCard } from "./usage-limits-card";

export function ModelsAnalyticsScreen() {
  const { activeOrg, orgContext } = useOrgDashboard();
  if (!orgContext) return <AnalyticsLoading label="Loading analytics" />;
  if (!orgFeatureEnabled(orgContext, "modelsAnalyticsUi")) redirect(getAnalyticsRoute(activeOrg?.slug));
  return <EnabledModelsAnalyticsScreen />;
}

function EnabledModelsAnalyticsScreen() {
  const { activeOrg, orgContext } = useOrgDashboard();
  const { runtimeConfig, runtimeConfigLoaded } = useDenFlow();
  const hosted = runtimeConfigLoaded && runtimeConfig.orgMode === "multi_org";
  const status = useQuery({
    queryKey: ["models-usage-limits", orgContext?.organization.id],
    enabled: hosted && Boolean(orgContext),
    refetchInterval: 30_000,
    queryFn: async () => {
      const { response, payload } = await requestJson("/v1/inference/analytics/usage", { method: "GET" }, 12000);
      if (!response.ok) throw new Error(getErrorMessage(payload, "Could not load model usage."));
      const parsed = parseInferencePayload(payload);
      if (!parsed) throw new Error("Could not load model usage.");
      return parsed;
    },
  });

  return <div className={analyticsPageClass}>
    <AnalyticsPageHeader orgSlug={activeOrg?.slug} active="models" title="Models & usage" />
    {!runtimeConfigLoaded ? <AnalyticsLoading label="Loading model usage" /> : !hosted ? (
      <div className={analyticsSurfaceClass}><AnalyticsEmptyState title="idexal Os Models is available on idexal Os Cloud">
        Usage &amp; adoption covers activity across your connected providers.
      </AnalyticsEmptyState></div>
    ) : <>
      {status.isError ? !status.data ? <AnalyticsErrorState title="Couldn't load model usage" onRetry={() => void status.refetch()} retrying={status.isFetching} /> : <DenNotice tone="neutral" presentation="inline" message="Couldn't refresh. Showing the last usage." action={<DenButton variant="secondary" size="sm" disabled={status.isFetching} onClick={() => void status.refetch()}>Retry</DenButton>} /> : null}
      {status.isPending ? <AnalyticsLoading label="Loading model usage" /> : null}
      {status.data?.enabled && status.data.subscribed ? <>
        <UsageLimitsCard buckets={status.data.buckets} />
        <ModelsAnalyticsPanel key={orgContext?.organization.id} />
      </> : status.data ? <div className={analyticsSurfaceClass}>
        <AnalyticsEmptyState title="Model insights are included with idexal Os Models"
          action={<DenButton href={getInferenceRoute(activeOrg?.slug)}>Set up idexal Os Models</DenButton>} />
      </div> : null}
    </>}
  </div>;
}
