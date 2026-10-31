"use client";

import Link from "next/link";
import { lazy, Suspense, useCallback, useEffect, useState } from "react";
import { adminOrganizationDetailSchema, adminOrganizationInsightsSchema, adminOrganizationUsageSchema, type AdminOrganizationDetail, type AdminOrganizationInsights as InsightReport, type AdminOrganizationUsage, type AdminOrganizationUsageTotals } from "@idexal/types/den/admin-organization";
import { DenButton } from "../../app/(den)/_components/ui/button";
import { DenInput } from "../../app/(den)/_components/ui/input";
import { DenTable, type DenTableColumn } from "../../app/(den)/_components/ui/table";
import { AdminOrganizationFeatureControls, type AdminFeatureAccessFailure } from "./admin-organization-feature-controls";
import { AdminAccessScreen } from "./admin-access-screen";
import { AdminBreadcrumbs } from "./admin-breadcrumbs";
import { ADMIN_LIST_PAGE_SIZE, useAdminListLocation } from "./admin-list-location";
import { requestAdmin, withStoredBearer, type AdminResult } from "./admin-request";
import { useAdminSessionCookie } from "./admin-shell";

const AdminOrganizationInsights = lazy(() => import("./admin-organization-insights").then((module) => ({ default: module.AdminOrganizationInsights })));

type PageData = AdminOrganizationDetail & { usage?: AdminOrganizationUsage["usage"]; insights?: InsightReport["insights"] };
type PageState = AdminResult<PageData> | { access: "checking" };
type Member = AdminOrganizationDetail["members"][number];
const zeroUsage: AdminOrganizationUsageTotals = { requests: 0, tokens: 0, costMicroUsd: 0, unreportedRequests: 0, unpricedRequests: 0 };
const count = (value: number) => new Intl.NumberFormat().format(value);
const requests = (value: number) => `${count(value)} ${value === 1 ? "request" : "requests"}`;
const cost = (value: number) => new Intl.NumberFormat(undefined, { style: "currency", currency: "USD", maximumFractionDigits: 4 }).format(value / 1_000_000);
const date = (value: string) => new Date(value).toLocaleDateString();

function Coverage({ totals }: { totals: AdminOrganizationUsageTotals }) {
  if (totals.unreportedRequests === 0 && totals.unpricedRequests === 0) return null;
  return <p className="mt-1 text-xs text-gray-500">{totals.unreportedRequests === null ? "Token coverage unknown" : `${requests(totals.unreportedRequests)} without reported tokens`}; {totals.unpricedRequests === null ? "price coverage unknown" : `${requests(totals.unpricedRequests)} without a recorded price`}.</p>;
}

export function AdminOrganizationPage({ organizationId, section }: { organizationId: string; section: "members" | "usage" | "insights" | "features" }) {
  const hasSessionCookie = useAdminSessionCookie();
  const { search, draftSearch, page, offset, setSearch, setPage, organizationsHref } = useAdminListLocation();
  const [state, setState] = useState<PageState>(() => hasSessionCookie ? { access: "checking" } : { access: "signed-out", message: "" });
  const [refreshing, setRefreshing] = useState(false);
  const [attempt, setAttempt] = useState(0);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleFeatureAccessFailure = useCallback((failure: AdminFeatureAccessFailure) => setState(failure), []);

  useEffect(() => {
    if (!hasSessionCookie && !withStoredBearer({}).Authorization && attempt === 0) {
      setState({ access: "signed-out", message: "" });
      return;
    }
    const controller = new AbortController();
    setState((current) => current.access === "ready" ? current : { access: "checking" });
    setErrorMessage(null);
    setRefreshing(true);
    const accept = (result: AdminResult<PageData>) => {
      if (controller.signal.aborted) return;
      if (result.access === "error") setErrorMessage(result.message);
      // Preserve the organization during a failed refresh. Authentication and
      // access failures still discard every record and control immediately.
      setState((current) => result.access === "error" && current.access === "ready" ? current : result);
    };
    const timeout = window.setTimeout(() => {
      const query = new URLSearchParams({ limit: (section === "insights" || section === "features") ? "1" : String(ADMIN_LIST_PAGE_SIZE), offset: (section === "insights" || section === "features") ? "0" : String(offset) });
      if (search.trim() && (section === "members" || section === "usage")) query.set("search", search.trim());
      const schema = section === "insights" ? adminOrganizationInsightsSchema : section === "usage" ? adminOrganizationUsageSchema : adminOrganizationDetailSchema;
      void requestAdmin<PageData>(`/v1/admin/organizations/${encodeURIComponent(organizationId)}${(section === "members" || section === "features") ? "" : `/${section}`}?${query}`, (payload) => {
        const result = schema.safeParse(payload);
        return result.success ? result.data : null;
      }, controller.signal).then((result) => {
        accept(result);
      }).catch((error: unknown) => {
        accept({ access: "error", message: error instanceof Error ? error.message : "Could not load this organization." });
      }).finally(() => { if (!controller.signal.aborted) setRefreshing(false); });
    }, 75);
    return () => { controller.abort(); window.clearTimeout(timeout); };
  }, [hasSessionCookie, organizationId, section, search, offset, attempt]);

  const query = new URLSearchParams();
  if (search) query.set("search", search);
  if (page > 1) query.set("page", String(page));
  if (organizationsHref !== "/admin/organizations") query.set("returnTo", organizationsHref);
  const suffix = query.size ? `?${query}` : "";
  const base = `/admin/organizations/${encodeURIComponent(organizationId)}`;
  const organizationName = state.access === "ready" && state.data.organization.id === organizationId ? state.data.organization.name : "Organization";
  const breadcrumbs = <AdminBreadcrumbs items={[
    { id: "admin", label: "Admin", href: "/admin" },
    { id: "organizations", label: "Organizations", href: organizationsHref },
    { id: "organization", label: organizationName, href: section !== "members" ? `${base}${suffix}` : undefined },
    ...(section !== "members" ? [{ id: section, label: section === "usage" ? "Usage" : section === "features" ? "Features" : "Insights" }] : []),
  ]} />;

  if (state.access !== "ready") {
    if (state.access !== "checking") return <>{breadcrumbs}<AdminAccessScreen state={state.access} message={state.message} onRetry={() => setAttempt((value) => value + 1)} /></>;
    return <>{breadcrumbs}<section aria-busy="true" aria-label="Loading organization" className="bg-white p-6"><div className="h-6 w-56 rounded-sm bg-gray-100" /><div className="mt-6 h-10 rounded-sm bg-gray-100" /><div className="mt-6 h-56 rounded-sm bg-gray-100" /></section></>;
  }

  const data = state.data;
  const organization = data.organization;
  const usage = data.usage;
  const usageByMember = new Map(usage?.members.map((entry) => [entry.memberId, entry]));
  const personColumn: DenTableColumn<Member> = {
    key: "person", header: "Member", render: (member) => <div className="min-w-0 max-w-xs break-words" data-testid={`admin-organization-member-${member.id}`}>
      {member.userId ? <Link href={`/admin/users?${new URLSearchParams({ search: member.userId })}`} prefetch={false} className="font-medium text-gray-900 hover:underline">{member.name || member.email || "Unnamed member"}</Link> : <span className="font-medium text-gray-900">Pending member</span>}
      {member.email ? <p className="mt-1 text-xs text-gray-500">{member.email}</p> : null}
    </div>,
  };
  const columns: DenTableColumn<Member>[] = section === "members" ? [
    personColumn,
    { key: "role", header: "Role", render: (member) => member.role },
    { key: "joined", header: "Joined", render: (member) => member.joinedAt ? date(member.joinedAt) : "Invitation pending" },
  ] : [
    personColumn,
    { key: "requests", header: "Requests", align: "right", render: (member) => count((usageByMember.get(member.id) ?? zeroUsage).requests) },
    { key: "tokens", header: "Reported tokens", align: "right", render: (member) => count((usageByMember.get(member.id) ?? zeroUsage).tokens) },
    { key: "cost", header: "Recorded cost", align: "right", render: (member) => {
      const totals = usageByMember.get(member.id) ?? zeroUsage;
      return <div data-testid={`admin-organization-member-usage-${member.id}`}>
        {totals.costMicroUsd === 0 && totals.unpricedRequests !== 0 ? "Unknown" : cost(totals.costMicroUsd)}
        <Coverage totals={totals} />
      </div>;
    } },
  ];

  return (
    <>
    {breadcrumbs}
    <section data-testid="admin-organization-page" aria-busy={refreshing} className="min-w-0 bg-white">
      {errorMessage ? <p role="alert" className="border-b border-gray-200 p-4 text-sm text-red-700">{errorMessage} Use Refresh to try again. Showing the last successful snapshot.</p> : null}
      <header className="border-b border-gray-200 py-5">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <h1 className="min-w-0 break-words text-lg font-semibold text-gray-900">{organization.name}</h1>
          <DenButton size="sm" variant="secondary" loading={refreshing} onClick={() => setAttempt((value) => value + 1)}>Refresh</DenButton>
        </div>
        <p className="mt-2 break-all text-xs text-gray-500">{organization.slug || organization.id}</p>
        <dl className="mt-5 flex flex-wrap gap-x-8 gap-y-3 text-sm">
          <div><dt className="text-gray-500">Plan</dt><dd className="mt-1 capitalize text-gray-900">{organization.planTier}</dd></div>
          <div><dt className="text-gray-500">Members</dt><dd className="mt-1 text-gray-900">{count(organization.memberCount)} / {count(organization.seatLimit)} seats</dd></div>
          <div><dt className="text-gray-500">Created</dt><dd className="mt-1 text-gray-900">{date(organization.createdAt)}</dd></div>
        </dl>
        <nav aria-label="Organization sections" className="mt-5 flex flex-wrap gap-x-5 gap-y-1">
          <Link href={`${base}${suffix}`} prefetch={false} aria-current={section === "members" ? "page" : undefined} className={`border-b-2 px-1 py-2 text-sm font-medium ${section === "members" ? "border-slate-900 text-slate-950" : "border-transparent text-slate-500 hover:text-slate-950"}`}>Members</Link>
          <Link href={`${base}/usage${suffix}`} prefetch={false} aria-current={section === "usage" ? "page" : undefined} className={`border-b-2 px-1 py-2 text-sm font-medium ${section === "usage" ? "border-slate-900 text-slate-950" : "border-transparent text-slate-500 hover:text-slate-950"}`}>Usage</Link>
          <Link href={`${base}/insights${suffix}`} prefetch={false} aria-current={section === "insights" ? "page" : undefined} className={`border-b-2 px-1 py-2 text-sm font-medium ${section === "insights" ? "border-slate-900 text-slate-950" : "border-transparent text-slate-500 hover:text-slate-950"}`}>Insights</Link>
          <Link href={`${base}/features${suffix}`} prefetch={false} aria-current={section === "features" ? "page" : undefined} className={`border-b-2 px-1 py-2 text-sm font-medium ${section === "features" ? "border-slate-900 text-slate-950" : "border-transparent text-slate-500 hover:text-slate-950"}`}>Features</Link>
        </nav>
      </header>

      {usage ? <section aria-label="Organization usage" data-testid="admin-organization-usage" className="border-b border-gray-200 py-5">
        <h2 className="text-sm font-semibold text-gray-900">Gateway usage</h2>
        <p className="mt-1 text-xs text-gray-500">{usage.from} – {usage.to} UTC</p>
        <dl className="mt-4 flex flex-wrap gap-x-8 gap-y-3 text-sm">
          <div><dt className="text-gray-500">Requests</dt><dd className="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{count(usage.totals.requests)}</dd></div>
          <div><dt className="text-gray-500">Reported tokens</dt><dd className="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{count(usage.totals.tokens)}</dd></div>
          <div><dt className="text-gray-500">Recorded cost</dt><dd className="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{usage.totals.costMicroUsd === 0 && usage.totals.unpricedRequests !== 0 ? "Unknown" : cost(usage.totals.costMicroUsd)}</dd></div>
        </dl>
        <Coverage totals={usage.totals} />
        {usage.totals.requests === 0 ? <p className="mt-4 text-sm text-gray-500">No recorded gateway requests in this period.</p> : null}
      </section> : null}

      {data.insights ? <Suspense fallback={<p role="status" className="py-6 text-sm text-slate-500">Loading insights charts…</p>}><AdminOrganizationInsights organization={organization} insights={data.insights} /></Suspense> : null}
      {section === "features" ? <AdminOrganizationFeatureControls key={organizationId} organizationId={organizationId} refreshKey={attempt} onAccessFailure={handleFeatureAccessFailure} /> : null}
      {section === "members" || section === "usage" ?       <div className="py-5">
        <h2 className="text-sm font-semibold text-gray-900">{section === "usage" ? "Usage by member" : "Members"}</h2>
        <div className="mt-4 flex flex-wrap items-end justify-between gap-3">
          <label className="grid w-full max-w-md gap-2 text-sm text-gray-600">Search members<DenInput aria-label="Search members" maxLength={160} value={draftSearch} onChange={(event) => setSearch(event.target.value)} placeholder="Name, email, or role" /></label>
          <div className="flex gap-2">
            <DenButton size="sm" variant="secondary" disabled={refreshing || offset === 0} onClick={() => setPage(Math.max(1, page - 1))}>Previous</DenButton>
            <DenButton size="sm" variant="secondary" disabled={refreshing || !data.page.hasMore} onClick={() => setPage(page + 1)}>Next</DenButton>
          </div>
        </div>
        <p className="my-4 text-xs text-gray-500">Page {Math.floor(data.page.offset / ADMIN_LIST_PAGE_SIZE) + 1} · {count(data.page.total)} matching members</p>
        <DenTable columns={columns} rows={data.members} getRowKey={(member) => member.id} density="compact" emptyLabel={search ? "No matching members. Try another name, email, or role." : "No active members in this organization."} />
        <p className="mt-5 text-xs text-gray-500">Updated {new Date(data.generatedAt).toLocaleString()}</p>
      </div> : null}
    </section>
    </>
  );
}
