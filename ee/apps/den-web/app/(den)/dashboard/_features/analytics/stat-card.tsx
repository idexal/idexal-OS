"use client";

import { DenSkeleton } from "../../../_components/ui/skeleton";

export function StatCard({ title, value, sub, attention = false }: {
  title: string; value: string; sub?: string; attention?: boolean;
}) {
  return <div className="rounded-2xl border border-[var(--dls-border)] bg-[var(--dls-surface)] p-5" data-analytics-stat data-attention={attention}>
    <div className="text-[13px] font-medium text-[var(--dls-text-secondary)]">{title}</div>
    <div className={`mt-3 text-3xl font-semibold leading-none tracking-tight tabular-nums ${attention ? "text-[var(--ow-danger)]" : "text-[var(--dls-text-primary)]"}`}>
      {value === "…" ? <DenSkeleton className="h-8 w-16" /> : value}
    </div>
    {sub ? <div className="mt-2 text-xs leading-5 text-[var(--dls-text-secondary)]">{sub}</div> : null}
  </div>;
}
