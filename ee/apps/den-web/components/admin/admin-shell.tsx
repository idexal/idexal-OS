"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { createContext, useContext, type ReactNode } from "react";
import { ArrowLeft, Building2, ChartNoAxesCombined, Flag, LayoutDashboard, Users } from "lucide-react";
import { AdminBreadcrumbs } from "./admin-breadcrumbs";

const SessionCookieContext = createContext(false);
export const useAdminSessionCookie = () => useContext(SessionCookieContext);

const SECTIONS = [
  { label: "Overview", href: "/admin", icon: LayoutDashboard },
  { label: "Users", href: "/admin/users", icon: Users },
  { label: "Organizations", href: "/admin/organizations", icon: Building2 },
  { label: "Feature rollouts", href: "/admin/features", icon: Flag },
  { label: "Free Auto usage", href: "/admin/free-auto", icon: ChartNoAxesCombined },
];

export function AdminShell({ hasSessionCookie, children }: { hasSessionCookie: boolean; children: ReactNode }) {
  const pathname = usePathname();
  const section = SECTIONS.find(({ href }) => href === "/admin" ? pathname === href : pathname === href || pathname.startsWith(`${href}/`));
  const organizationDetail = pathname.startsWith("/admin/organizations/");
  return (
    <SessionCookieContext.Provider value={hasSessionCookie}>
      <div className="min-h-screen bg-white">
        <a href="#admin-content" className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:p-3">Skip to content</a>
        <aside className="flex flex-col border-b border-slate-200 bg-slate-50/60 md:fixed md:inset-y-0 md:w-52 md:overflow-y-auto md:border-b-0 md:border-r">
          <div className="shrink-0 px-5 py-5">
            <Link href="/admin" prefetch={false} className="text-sm font-semibold text-gray-900">idexal Os admin</Link>
          </div>
          <nav aria-label="Admin navigation" data-testid="admin-navigation" className="grid grid-cols-2 gap-1 px-3 pb-3 sm:grid-cols-3 md:flex md:shrink-0 md:flex-col">
            {SECTIONS.map(({ label, href, icon: Icon }) => {
              const active = href === "/admin" ? pathname === href : pathname === href || pathname.startsWith(`${href}/`);
              return (
                <Link key={href} href={href} prefetch={false} aria-current={active ? pathname === href ? "page" : "location" : undefined}
                  data-testid={`admin-nav-${href === "/admin" ? "overview" : href.split("/").at(-1)}`}
                  className={`flex min-w-0 items-center gap-2 rounded-md px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500 ${active ? "bg-slate-100 text-slate-950" : "text-slate-600 hover:bg-slate-50 hover:text-slate-950"}`}>
                  <Icon size={16} strokeWidth={1.5} aria-hidden="true" className="shrink-0" /><span>{label}</span>
                </Link>
              );
            })}
          </nav>
          <Link href="/" prefetch={false} className="flex shrink-0 items-center gap-2 px-6 py-4 text-sm text-slate-600 hover:text-slate-950 md:mt-auto">
            <ArrowLeft size={16} strokeWidth={1.5} aria-hidden="true" />Back to cloud panel
          </Link>
        </aside>
        <div className="md:pl-52">
          <main id="admin-content" className="mx-auto w-full max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
            {!organizationDetail && section ? <AdminBreadcrumbs items={[{ id: "admin", label: "Admin", href: "/admin" }, { id: "section", label: section.label }]} /> : null}
            {children}
          </main>
        </div>
      </div>
    </SessionCookieContext.Provider>
  );
}
