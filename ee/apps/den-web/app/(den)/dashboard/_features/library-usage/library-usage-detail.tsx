"use client";

import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import type { ReactNode } from "react";
import { DenButton } from "../../../_components/ui/button";
import { DenNotice } from "../../../_components/ui/notice";
import { DenSkeleton } from "../../../_components/ui/skeleton";
import { getMcpConnectionRoute, getPluginRoute, getPluginSkillRoute } from "../../../_lib/den-org";
import { ItemRow, ItemRowsSkeleton, LinkButton } from "../../_components/item-list";
import { LetterTile } from "../../_components/item-logo";
import { useOrgDashboard } from "../../_providers/org-dashboard-provider";
import { AnalyticsEmptyState, AnalyticsErrorState, analyticsSurfaceClass } from "../analytics/analytics-layout";
import { StatCard } from "../analytics/stat-card";
import { TrendChart } from "../analytics/trend-chart";
import {
  dailyLibraryUsage,
  failureLabel,
  lastUsedLabel,
  libraryUsageCopy,
  libraryUsageMember,
  sumLibraryUsageSources,
  type LibraryUsageBreakdownRow,
  type LibraryUsageKind,
  type LibraryUsageReport,
  type LibraryUsageRow,
  type LibraryUsageWindow,
} from "./library-usage-data";
import { useLibraryUsageBreakdown } from "./use-library-usage";

/** Builds a link to another usage view from the current search params. */
export type UsageHref = (change: Record<string, string | null>) => string;

/** Where the item is managed: its Library page, or its Connectors page. */
export function libraryItemHref(kind: LibraryUsageKind, row: Pick<LibraryUsageRow, "id" | "pluginId">, orgSlug: string | null | undefined) {
  if (kind === "skills") return row.pluginId ? getPluginSkillRoute(orgSlug, row.pluginId, row.id) : null;
  if (kind === "plugins") return getPluginRoute(orgSlug, row.id);
  return getMcpConnectionRoute(orgSlug, row.id);
}

function DetailHeading({ backHref, backLabel, title, action, testId }: { backHref: string; backLabel: string; title: string; action?: ReactNode; testId: string }) {
  return <div className="flex flex-wrap items-center justify-between gap-3" data-testid={testId}>
    <div className="flex min-w-0 items-center gap-2">
      <Link href={backHref} aria-label={backLabel} title={backLabel} className="rounded-lg p-2 text-[var(--dls-text-secondary)] outline-none hover:bg-[var(--dls-hover)] hover:text-[var(--dls-text-primary)] focus-visible:ring-2 focus-visible:ring-gray-300">
        <ArrowLeft className="h-4 w-4" aria-hidden />
      </Link>
      <h2 className="truncate text-[16px] font-semibold tracking-[-0.01em] text-[var(--dls-text-primary)]">{title}</h2>
    </div>
    {action}
  </div>;
}

/** The rows of a list that is loading, in the same surface the list will fill. */
function ListLoading({ label }: { label: string }) {
  return <div className={analyticsSurfaceClass}><ItemRowsSkeleton label={label} rows={3} /></div>;
}

/** Stat cards that are loading, in the grid the real cards will fill. */
function StatsLoading({ cards }: { cards: number }) {
  return <div className={`grid gap-3.5 sm:grid-cols-2 ${cards === 4 ? "lg:grid-cols-4" : cards === 3 ? "lg:grid-cols-3" : ""}`} aria-hidden="true">
    {Array.from({ length: cards }, (_, card) => <div key={card} className={`${analyticsSurfaceClass} p-5`}><DenSkeleton className="h-3 w-24" /><DenSkeleton className="mt-4 h-7 w-16" /></div>)}
  </div>;
}

/** The chart's frame with a placeholder where the bars will be, never loading prose. */
function ChartLoading() {
  return <div className={`${analyticsSurfaceClass} p-5`} role="status" aria-label="Loading uses per day" data-testid="library-usage-daily-loading">
    <DenSkeleton className="h-3.5 w-24" /><DenSkeleton className="mt-2 h-3 w-20" />
    <DenSkeleton className="mt-6 h-[168px] w-full" />
    <div className="mt-3 flex justify-between"><DenSkeleton className="h-2.5 w-10" /><DenSkeleton className="h-2.5 w-10" /></div>
  </div>;
}

/** Kept counts with a way to try again when a refresh fails; the last rows stay on screen. */
function RefreshFailed({ onRetry }: { onRetry: () => void }) {
  return <DenNotice tone="neutral" presentation="inline" message="Couldn't refresh. Showing the last counts." action={<DenButton variant="secondary" size="sm" onClick={onRetry}>Retry</DenButton>} />;
}

/** Uses per UTC day, with failures beside them where the kind can fail. */
function DailyUses({ kind, days, scope }: { kind: LibraryUsageKind; days: LibraryUsageWindow; scope: { itemId: string } | { memberId: string } }) {
  const daily = useLibraryUsageBreakdown(kind, days, "day", scope);
  if (daily.isError && !daily.data) return <AnalyticsErrorState title="Couldn't load uses per day" onRetry={() => void daily.refetch()} retrying={daily.isFetching} />;
  if (!daily.data) return <ChartLoading />;
  const points = dailyLibraryUsage(daily.data);
  const failed = kind !== "skills" && points.some((point) => point.failures > 0);
  return <div data-testid="library-usage-daily">
    <TrendChart title="Uses per day" subtitle={`Last ${days} days`} intervalLabel=""
      weeks={points.map((point) => ({ weekStart: point.date }))}
      series={[{ label: "Uses", values: points.map((point) => point.uses) }, ...(failed ? [{ label: "Failed", values: points.map((point) => point.failures) }] : [])]} />
  </div>;
}

/** What started the uses: agents, Workflows, Automations, Apps or live views. */
function StartedBy({ rows }: { rows: readonly LibraryUsageBreakdownRow[] }) {
  const sources = sumLibraryUsageSources(rows);
  if (sources.length === 0) return null;
  return <section className={analyticsSurfaceClass} aria-label="Started by" data-testid="library-usage-sources">
    <h3 className="px-5 pb-2 pt-4 text-xs text-[var(--dls-text-secondary)]">Started by</h3>
    <ul className="divide-y divide-[#e3e7ee] border-t border-[#e3e7ee]">
      {sources.map((entry) => <li key={entry.source} data-source={entry.source} className="flex items-center justify-between gap-4 px-5 py-2.5 text-[13px]">
        <span className="text-[var(--dls-text-primary)]">{entry.label}</span>
        <span className="tabular-nums text-[var(--dls-text-secondary)]">{entry.uses.toLocaleString()}</span>
      </li>)}
    </ul>
  </section>;
}

function UsageColumns({ first, tracksFailures }: { first: string; tracksFailures: boolean }) {
  return <div className={`grid items-center gap-4 px-5 py-2.5 text-xs text-[var(--dls-text-secondary)] ${tracksFailures ? "max-w-[732px] grid-cols-[minmax(0,1fr)_56px_72px_104px]" : "max-w-[644px] grid-cols-[minmax(0,1fr)_56px_104px]"}`}>
    <span>{first}</span><span>Uses</span>{tracksFailures ? <span>Failed</span> : null}<span>Last used</span>
  </div>;
}

function UsageNumbers({ row, now, tracksFailures }: { row: Pick<LibraryUsageBreakdownRow, "uses" | "failures" | "lastUsedAt">; now: number; tracksFailures: boolean }) {
  const lastUsed = lastUsedLabel(row.lastUsedAt, now);
  return <span className={`grid shrink-0 gap-4 text-[13px] tabular-nums ${tracksFailures ? "grid-cols-[56px_72px_104px]" : "grid-cols-[56px_104px]"}`}>
    <span data-item-uses className="text-[var(--dls-text-primary)]">{row.uses.toLocaleString()}</span>
    {tracksFailures ? <span data-item-failures className={(row.failures ?? 0) > 0 ? "font-medium text-[var(--ow-danger)]" : undefined}>{failureLabel({ failures: row.failures ?? 0, uses: row.uses })}</span> : null}
    {lastUsed && row.lastUsedAt ? <time dateTime={row.lastUsedAt} title={new Date(row.lastUsedAt).toLocaleString()}>{lastUsed}</time> : <span>Not used</span>}
  </span>;
}

/** One skill, plugin or connector: who uses it, how often per day, and what started the uses. */
export function ItemUsageDetail({ kind, days, item, now, usageHref }: {
  kind: LibraryUsageKind; days: LibraryUsageWindow; item: LibraryUsageRow; now: number; usageHref: UsageHref;
}) {
  const { activeOrg, orgContext } = useOrgDashboard();
  const copy = libraryUsageCopy[kind];
  const people = useLibraryUsageBreakdown(kind, days, "member", { itemId: item.id });
  const tracksFailures = item.failures !== null;
  const manageHref = libraryItemHref(kind, item, activeOrg?.slug);
  const members = orgContext?.members ?? [];
  const lastUsed = lastUsedLabel(item.lastUsedAt, now);

  return <section className="grid gap-6" data-testid="library-usage-item" data-item={item.name}>
    <DetailHeading testId="library-usage-item-heading" backHref={usageHref({ item: null, member: null })} backLabel={`All ${copy.plural}`} title={item.name}
      action={manageHref ? <LinkButton href={manageHref} size="sm">{kind === "connectors" ? "Open connector" : "Open in Library"}</LinkButton> : undefined} />

    <div className={`grid gap-3.5 sm:grid-cols-2 ${tracksFailures ? "lg:grid-cols-4" : "lg:grid-cols-3"}`} data-testid="library-usage-item-summary">
      <StatCard title="Uses" value={item.uses.toLocaleString()} />
      <StatCard title="People" value={item.people.toLocaleString()} />
      {tracksFailures ? <StatCard attention={(item.failures ?? 0) > 0} title="Failed" value={(item.failures ?? 0).toLocaleString()} /> : null}
      <StatCard title="Last used" value={lastUsed ?? "Not used"} />
    </div>

    {item.uses === 0 ? (
      <div className={analyticsSurfaceClass}><AnalyticsEmptyState title={`Nobody used this ${copy.noun} in the last ${days} days`} /></div>
    ) : <>
      {people.isError && !people.data ? <AnalyticsErrorState title="Couldn't load who uses it" onRetry={() => void people.refetch()} retrying={people.isFetching} />
        : !people.data ? <><ListLoading label="Loading who uses it" /><ChartLoading /></>
        : <>
          {people.isError ? <RefreshFailed onRetry={() => void people.refetch()} /> : null}
          <div className={`${analyticsSurfaceClass} min-w-0 overflow-x-auto`} data-testid="library-usage-people">
            <div className={tracksFailures ? "min-w-[600px]" : "min-w-[520px]"}>
              <UsageColumns first="Person" tracksFailures={tracksFailures} />
              <ul className="divide-y divide-[#e3e7ee] border-t border-[#e3e7ee]">
                {people.data.rows.map((row) => {
                  const person = libraryUsageMember(members, row.memberId);
                  return <li key={row.memberId ?? "former"} data-testid="library-usage-person" data-person={person.name}>
                    <div className={tracksFailures ? "max-w-[732px]" : "max-w-[644px]"}>
                      <ItemRow logo={<LetterTile name={person.name} />} title={person.name} description={person.email}
                        href={row.memberId ? usageHref({ member: row.memberId, item: null }) : undefined}
                        comparison={<UsageNumbers row={row} now={now} tracksFailures={tracksFailures} />} />
                    </div>
                  </li>;
                })}
              </ul>
            </div>
          </div>
          <DailyUses kind={kind} days={days} scope={{ itemId: item.id }} />
          <StartedBy rows={people.data.rows} />
        </>}
    </>}
  </section>;
}

/** One person: which skills, plugins or connectors they used, how often per day, and what started the uses. */
export function PersonUsageDetail({ kind, days, memberId, report, now, usageHref }: {
  kind: LibraryUsageKind; days: LibraryUsageWindow; memberId: string; report: LibraryUsageReport; now: number; usageHref: UsageHref;
}) {
  const { orgContext } = useOrgDashboard();
  const copy = libraryUsageCopy[kind];
  const person = libraryUsageMember(orgContext?.members ?? [], memberId);
  const items = useLibraryUsageBreakdown(kind, days, "item", { memberId });
  const tracksFailures = kind !== "skills";
  const names = new Map(report.items.map((entry) => [entry.id, entry]));
  const rows = items.data?.rows ?? [];
  const uses = rows.reduce((sum, row) => sum + row.uses, 0);
  const failures = rows.reduce((sum, row) => sum + (row.failures ?? 0), 0);

  return <section className="grid gap-6" data-testid="library-usage-member" data-person={person.name}>
    <DetailHeading testId="library-usage-member-heading" backHref={usageHref({ member: null, item: null })} backLabel={`All ${copy.plural}`} title={person.name} />

    {items.isError && !items.data ? <AnalyticsErrorState title={`Couldn't load ${person.name}'s usage`} onRetry={() => void items.refetch()} retrying={items.isFetching} />
      : !items.data ? <><StatsLoading cards={tracksFailures ? 3 : 2} /><ListLoading label={`Loading ${person.name}'s usage`} /><ChartLoading /></>
      : <>
        {items.isError ? <RefreshFailed onRetry={() => void items.refetch()} /> : null}
        <div className={`grid gap-3.5 sm:grid-cols-2 ${tracksFailures ? "lg:grid-cols-3" : "lg:grid-cols-2"}`} data-testid="library-usage-member-summary">
          <StatCard title={`${copy.label} used`} value={`${rows.length} of ${report.items.length}`} />
          <StatCard title="Uses" value={uses.toLocaleString()} />
          {tracksFailures ? <StatCard attention={failures > 0} title="Failed" value={failures.toLocaleString()} /> : null}
        </div>
        {rows.length === 0 ? (
          <div className={analyticsSurfaceClass}><AnalyticsEmptyState title={`${person.name} used no ${copy.plural} in the last ${days} days`} /></div>
        ) : <>
          <div className={`${analyticsSurfaceClass} min-w-0 overflow-x-auto`} data-testid="library-usage-member-items">
            <div className={tracksFailures ? "min-w-[600px]" : "min-w-[520px]"}>
              <UsageColumns first={copy.label.replace(/s$/, "")} tracksFailures={tracksFailures} />
              <ul className="divide-y divide-[#e3e7ee] border-t border-[#e3e7ee]">
                {rows.map((row) => {
                  const item = row.itemId ? names.get(row.itemId) : undefined;
                  const name = item?.name ?? `Removed ${copy.noun}`;
                  return <li key={row.itemId ?? "removed"} data-testid="library-usage-member-item" data-item={name}>
                    <div className={tracksFailures ? "max-w-[732px]" : "max-w-[644px]"}>
                      <ItemRow logo={<LetterTile name={name} />} title={name} description={item?.detail ?? undefined}
                        href={item ? usageHref({ item: item.id, member: null }) : undefined}
                        comparison={<UsageNumbers row={row} now={now} tracksFailures={tracksFailures} />} />
                    </div>
                  </li>;
                })}
              </ul>
            </div>
          </div>
          <DailyUses kind={kind} days={days} scope={{ memberId }} />
          <StartedBy rows={rows} />
        </>}
      </>}
  </section>;
}
