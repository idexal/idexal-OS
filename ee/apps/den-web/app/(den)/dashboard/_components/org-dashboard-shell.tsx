"use client";

import { desktopPolicyScreenRemoved } from "../_lib/desktop-policy-screen-rollout";

import { Popover } from "@base-ui/react/popover";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  ChevronDown,
  ChevronRight,
  FileText,
  LogOut,
  Menu,
  MessageSquare,
  X,
} from "lucide-react";
import { useDenFlow } from "../../_providers/den-flow-provider";
import { DEFAULT_AUTH_NAME } from "../../_lib/den-flow";
import {
  getAiGatewayRoute,
  getAnalyticsRoute,
  getAutomationsRoute,
  getApiKeysRoute,
  getAuditLogsRoute,
  getBrandAppearanceRoute,
  getBillingRoute,
  getCustomLlmProvidersRoute,
  getDiagnosticsRoute,
  getDeploymentsRoute,
  getDesktopPoliciesRoute,
  getWorkbotSettingsRoute,
  getOrgAccessFlags,
  orgFeatureEnabled,
  getIntegrationsRoute,
  getLibraryRoute,
  getMcpConnectionsRoute,
  getManagedBrandIconUrl,
  getMembersRoute,
  getToolTesterRoute,
  getYourConnectionsRoute,
  getOrgDashboardRoute,
  getOrgSettingsRoute,
  getMarketplacesRoute,
  getMarketplaceOnboardingRoute,
  getPluginsRoute,
  getSsoRoute,
  getScimRoute,
  getWorkflowRunsRoute,
  getWebRoute,
  getPermissionsRoute,
} from "../../_lib/den-org";
import { useOrgListWindow } from "../../_lib/use-org-list-window";
import { useOrgDashboard } from "../_providers/org-dashboard-provider";
import { buildDenFeedbackUrl } from "../../_lib/feedback";
import { OrgSelectionScreen } from "../_features/org-selection/org-selection-screen";
import {
  buildDashboardNavSections,
  type DashboardNavChild,
} from "../_lib/dashboard-navigation";
import { DenCommandPalette } from "./command-palette/den-command-palette";
import {
  DenSearchBar,
  type DenSearchBarHandle,
} from "./command-palette/den-search-bar";
import { useDashboardPrefetch } from "./use-dashboard-prefetch";
import { useLibraryNeedsSignInCount } from "./library-data";
import { useLibraryModels } from "./library-models-data";
import { UserProfileDialog } from "./user-profile-dialog";
import { DownloadIdexalOsButton } from "./download-idexal-os-button";

const IDEXAL_DOCS_URL = "https://os.idexal.com/docs";

/** The sidebar only tells people whether they can manage the organization. */
function sidebarRoleLabel(role: string): string {
  return getOrgAccessFlags(role, false).isAdmin ? "Admin" : "Member";
}

function OrgMark({ name }: { name: string }) {
  const initials = useMemo(() => {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    return (parts[0]?.slice(0, 1) ?? "O") + (parts[1]?.slice(0, 1) ?? "");
  }, [name]);

  return (
    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[#011627] text-xs font-semibold uppercase tracking-[0.08em] text-white">
      {initials}
    </div>
  );
}

function IdexalOsMark({ className = "h-9 w-auto" }: { className?: string }) {
  return (
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 377 380"
      fill="none"
      className={className}
      aria-label="idexal Os"
    >
      <defs>
        <linearGradient id="idexal-os-sliver" gradientUnits="userSpaceOnUse" x1="-34.478" y1="332.95" x2="-5.221" y2="50.423">
          <stop offset="0" stopColor="rgb(0,253,4)" />
          <stop offset="1" stopColor="rgb(61,253,4)" />
        </linearGradient>
        <linearGradient id="idexal-os-square" gradientUnits="userSpaceOnUse" x1="4.275" y1="-18.383" x2="17.544" y2="-75.436">
          <stop offset="0" stopColor="rgb(92,253,3)" />
          <stop offset="1" stopColor="rgb(121,253,3)" />
        </linearGradient>
        <linearGradient id="idexal-os-ne" gradientUnits="userSpaceOnUse" x1="-1.437" y1="1.712" x2="109.633" y2="-130.659">
          <stop offset="0" stopColor="rgb(9,253,3)" />
          <stop offset="1" stopColor="rgb(105,253,3)" />
        </linearGradient>
        <linearGradient id="idexal-os-se" gradientUnits="userSpaceOnUse" x1="-36.947" y1="39.2" x2="26.369" y2="-27.976">
          <stop offset="0" stopColor="rgb(0,252,1)" />
          <stop offset="1" stopColor="rgb(13,252,1)" />
        </linearGradient>
      </defs>
      <path fill="url(#idexal-os-sliver)" d="M 5.8939 337.1301 L 6.0367 51.5886 L 85.1534 146.8953 L 85.1246 243.1621 L 5.8939 337.1301 Z" />
      <path fill="url(#idexal-os-square)" d="M 304.39 2.64 L 352.625 2.64 A 5.45 5.45 0 0 1 358.075 8.09 L 358.075 45.82 A 5.45 5.45 0 0 1 352.625 51.27 L 304.39 51.27 A 5.45 5.45 0 0 1 298.94 45.82 L 298.94 8.09 A 5.45 5.45 0 0 1 304.39 2.64 Z" />
      <path fill="url(#idexal-os-ne)" d="M 354.0644 74.4388 L 267.5786 74.4647 A 3.9 3.9 0 0 0 263.6819 75.9995 L 214.8545 138.19 A 4.15 4.15 0 0 0 214.95 141.9381 L 245.6181 181.3625 A 22.3 22.3 0 0 1 247.4543 210.4422 L 354.0644 74.4388 Z" />
      <path fill="url(#idexal-os-se)" d="M 371.7243 336.362 L 265.2408 197.1722 A 59.8 59.8 0 0 1 258.7081 214.782 L 227.4575 265.1954 A 2 2 0 0 0 226.6662 267.178 L 278.8901 336.3564 A 2 2 0 0 0 278.1802 336.2213 L 353.1295 337.3427 A 118.5 118.5 0 0 1 371.7243 336.362 Z" />
      <path fill="#0B0B0B" d="M 94.5628 2.714 L 6.4295 2.6171 L 158.4632 188.8062 A 10.9 10.9 0 0 1 158.3804 202.1671 L 14.5998 376.1206 L 110.5818 376.0918 A 5.775 5.775 0 0 0 115.7849 374.2534 L 246.8612 209.9904 A 22.65 22.65 0 0 0 246.8982 180.6461 L 103.6775 6.8675 A 11 11 0 0 0 94.5628 2.714 Z" />
    </svg>
  );
}

export function SidebarBrandMark({
  metadata,
  organizationName,
}: {
  metadata: string | null | undefined;
  organizationName: string;
}) {
  const [loadedUrl, setLoadedUrl] = useState<string | null>(null);
  const [failedUrl, setFailedUrl] = useState<string | null>(null);

  if (metadata === undefined) {
    return (
      <div
        className="h-10 w-10 rounded-xl"
        aria-label="Loading organization icon"
        data-sidebar-brand-icon="loading"
      />
    );
  }

  const iconUrl = getManagedBrandIconUrl(metadata);
  if (!iconUrl || failedUrl === iconUrl) {
    return (
      <div data-sidebar-brand-icon="fallback">
        <IdexalOsMark />
      </div>
    );
  }

  return (
    <div
      className="h-10 w-10 overflow-hidden rounded-xl"
      data-sidebar-brand-icon={loadedUrl === iconUrl ? "ready" : "loading"}
    >
      <img
        src={iconUrl}
        alt={`${organizationName} icon`}
        className={`h-full w-full object-contain transition-opacity ${loadedUrl === iconUrl ? "opacity-100" : "opacity-0"}`}
        onLoad={() => setLoadedUrl(iconUrl)}
        onError={() => setFailedUrl(iconUrl)}
      />
    </div>
  );
}

const DEFAULT_WORKSPACE_FAVICON_HREF = "/idexal-os-mark.svg";

export function WorkspaceFavicon({
  metadata,
}: {
  metadata: string | null | undefined;
}) {
  const orgContextLoading = metadata === undefined;
  const iconUrl = getManagedBrandIconUrl(metadata ?? null);

  useEffect(() => {
    if (orgContextLoading) {
      // Keep the server-rendered favicon until the org context is known.
      return;
    }

    let favicon = document.head.querySelector<HTMLLinkElement>('link[rel="icon"]');
    if (!favicon) {
      favicon = document.createElement("link");
      favicon.rel = "icon";
      document.head.appendChild(favicon);
    }

    if (!iconUrl) {
      favicon.href = DEFAULT_WORKSPACE_FAVICON_HREF;
      favicon.removeAttribute("type");
      return;
    }

    const previousHref = favicon.getAttribute("href");
    const previousType = favicon.getAttribute("type");
    favicon.href = iconUrl;
    favicon.type = iconUrl.toLowerCase().endsWith(".jpg")
      || iconUrl.toLowerCase().endsWith(".jpeg")
      ? "image/jpeg"
      : "image/png";

    return () => {
      if (previousHref === null) {
        favicon.setAttribute("href", DEFAULT_WORKSPACE_FAVICON_HREF);
      } else {
        favicon.setAttribute("href", previousHref);
      }
      if (previousType === null) {
        favicon.removeAttribute("type");
      } else {
        favicon.setAttribute("type", previousType);
      }
    };
  }, [orgContextLoading, iconUrl]);

  return null;
}

function getDashboardPageTitle(pathname: string, orgSlug: string | null) {
  if (!orgSlug) {
    return "Home";
  }

  const dashboardRoot = getOrgDashboardRoute(orgSlug);

  if (pathname === dashboardRoot) {
    return "Home";
  }
  if (pathname.startsWith(getAuditLogsRoute(orgSlug))) {
    return "Audit logs";
  }
  if (pathname.startsWith(getAnalyticsRoute(orgSlug))) {
    return "Analytics";
  }
  if (pathname.startsWith(getMembersRoute(orgSlug))) {
    return "Members";
  }
  if (pathname.startsWith(getPermissionsRoute(orgSlug))) {
    return "Permissions";
  }
  if (pathname.startsWith(getApiKeysRoute(orgSlug))) {
    return "API Keys";
  }
  if (pathname.startsWith(getScimRoute(orgSlug))) {
    return "SCIM";
  }
  if (pathname.startsWith(getSsoRoute(orgSlug))) {
    return "SSO";
  }
  if (pathname.startsWith(getAutomationsRoute(orgSlug))) {
    return "My Automations";
  }
  if (pathname.startsWith(getCustomLlmProvidersRoute(orgSlug))) {
    return "Bring your Own Keys";
  }
  if (pathname.startsWith(getAiGatewayRoute(orgSlug))) {
    return "AI Gateway";
  }
  if (pathname.startsWith(getDesktopPoliciesRoute(orgSlug))) {
    return "Desktop policies";
  }
  if (pathname.startsWith(getWorkbotSettingsRoute(orgSlug))) {
    return "Workbot";
  }
  if (
    pathname.startsWith(getMarketplacesRoute(orgSlug))
    || pathname.startsWith(getBrandAppearanceRoute(orgSlug))
  ) {
    return "Advanced";
  }
  if (pathname.startsWith(getDeploymentsRoute(orgSlug))) {
    return "Deployments";
  }
  if (pathname.startsWith(getDiagnosticsRoute(orgSlug))) {
    return "Diagnostics";
  }
  if (pathname.startsWith(getWebRoute(orgSlug))) {
    return "idexal Os Web";
  }
  if (pathname.startsWith(getLibraryRoute(orgSlug))) {
    return "My Library";
  }
  if (pathname.startsWith(getPluginsRoute(orgSlug))) {
    return "Plugins";
  }
  if (pathname.startsWith(getIntegrationsRoute(orgSlug))) {
    return "Plugins";
  }
  if (pathname.startsWith(getMcpConnectionsRoute(orgSlug))) {
    return "Connectors";
  }
  if (pathname.startsWith(getYourConnectionsRoute(orgSlug))) {
    return "Your Connections";
  }
  if (pathname.startsWith(getWorkflowRunsRoute(orgSlug))) {
    return "Workflow Runs";
  }
  if (pathname.startsWith(getToolTesterRoute(orgSlug))) {
    return "Tool Tester";
  }
  if (pathname.startsWith(getBillingRoute(orgSlug))) {
    return "Billing";
  }
  if (pathname.startsWith(getOrgSettingsRoute(orgSlug))) {
    return "Org Settings";
  }

  return "Home";
}

// Desktop and mobile mount their own copy of the sidebar. Keep open state in
// each mounted switcher so opening one never portals the hidden copy as well.
function WorkspaceSwitcher({ children }: { children: (close: () => void) => React.ReactNode }) {
  const [open, setOpen] = useState(false);
  return (
    <Popover.Root open={open} onOpenChange={setOpen}>
      {children(() => setOpen(false))}
    </Popover.Root>
  );
}

export function OrgDashboardShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const onboardingRoute = getMarketplaceOnboardingRoute();
  const isOnboarding = pathname === onboardingRoute || pathname.startsWith(`${onboardingRoute}/`);
  const { user, signOut, updateUserProfile, runtimeConfig, runtimeConfigLoaded, setupPending, setupOrganizationId } = useDenFlow();
  const {
    activeOrg,
    orgDirectory,
    orgContext,
    orgSelectionOpen,
    orgBusy,
    orgError,
    mutationBusy,
    switchOrganization,
  } = useOrgDashboard();
  const prefetch = useDashboardPrefetch();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [commandPaletteOpen, setCommandPaletteOpen] = useState(false);
  const [profilePromptDismissed, setProfilePromptDismissed] = useState(false);
  const searchBarRef = useRef<DenSearchBarHandle>(null);
  const commandPaletteWasOpenRef = useRef(false);
  const isSingleOrgMode = runtimeConfigLoaded && runtimeConfig.orgMode === "single_org";
  const {
    query: switcherQuery,
    setQuery: setSwitcherQuery,
    visible: visibleOrgDirectory,
    filteredCount: switcherFilteredCount,
    hiddenCount: switcherHiddenCount,
    hasMore: switcherHasMore,
    showMore: showMoreOrgDirectory,
    showSearch: showSwitcherSearch,
  } = useOrgListWindow(orgDirectory, 20);

  const handleCommandPaletteOpenChange = useCallback((open: boolean) => {
    setCommandPaletteOpen(open);
  }, []);

  useEffect(() => {
    function handleKeyDown(event: KeyboardEvent) {
      if (isOnboarding) return;
      if (
        event.key.toLowerCase() !== "k"
        || (!event.metaKey && !event.ctrlKey)
        || event.shiftKey
        || event.altKey
      ) {
        return;
      }
      event.preventDefault();
      setCommandPaletteOpen((current) => !current);
    }

    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [isOnboarding]);

  useEffect(() => {
    if (commandPaletteWasOpenRef.current && !commandPaletteOpen) {
      searchBarRef.current?.focus();
    }
    commandPaletteWasOpenRef.current = commandPaletteOpen;
  }, [commandPaletteOpen]);

  // Keep sidebar hooks unconditional across picker and onboarding transitions,
  // but don't fetch sidebar data while the full-page shell is showing.
  const sidebarQueriesEnabled = !orgSelectionOpen && !isOnboarding && Boolean(activeOrg);
  const libraryModels = useLibraryModels(sidebarQueriesEnabled);
  const libraryNeedsSignIn = useLibraryNeedsSignInCount(sidebarQueriesEnabled)
    + (libraryModels.data ?? []).filter((provider) => provider.state === "needs_signin").length;

  // The picker replaces the whole shell until a workspace is chosen.
  if (orgSelectionOpen) {
    return (
      <OrgSelectionScreen
        orgs={orgDirectory}
        pending={mutationBusy === "switch-organization"}
        errorMessage={orgError}
        onPick={switchOrganization}
        onSignOut={() => void signOut()}
      />
    );
  }

  // Setup owns the full page until the user leaves for their dashboard.
  if (isOnboarding) {
    return (
      <>
        <WorkspaceFavicon metadata={orgContext?.organization.metadata} />
        <main data-testid="den-onboarding-shell">{children}</main>
      </>
    );
  }

  const access = getOrgAccessFlags(
    orgContext?.currentMember.role ?? "member",
    orgContext?.currentMember.isOwner ?? false,
    orgContext?.currentMember.permissions,
  );

  const basePageTitle = getDashboardPageTitle(pathname, activeOrg?.slug ?? null);
  // teamRoles: the permission pages are role pages.
  const pageTitle = basePageTitle === "Permissions" && orgFeatureEnabled(orgContext, "teamRoles") ? "Roles" : basePageTitle;
  const shouldShowProfilePrompt = Boolean(
    user &&
      !profilePromptDismissed &&
      user.name?.trim() === DEFAULT_AUTH_NAME,
  );
  const showFeedbackLink = runtimeConfigLoaded && runtimeConfig.orgMode === "multi_org";
  const feedbackHref = buildDenFeedbackUrl({
    pathname,
    orgSlug: activeOrg?.slug,
  });
  const navSections = buildDashboardNavSections({
    libraryNeedsSignIn,
    orgSlug: activeOrg?.slug ?? null,
    access,
    capabilities: orgContext?.capabilities ?? {
      auditLogs: false,
      cloud: false,
      installLinks: false,
      mcpConnections: false,
      idexalOsWeb: false,
      workflows: false,
    },
    orgMode: runtimeConfig.orgMode,
    runtimeConfigLoaded,
    permissionsEnabled: orgFeatureEnabled(orgContext, "permissions"),
    teamRoles: orgFeatureEnabled(orgContext, "teamRoles"),
    managedDeployments: orgFeatureEnabled(orgContext, "managedDeployments"),
    workbotSettings: orgContext?.capabilities.workbot === true,
    desktopPolicyScreenRemoved: desktopPolicyScreenRemoved(orgContext),
  });

  const orgSwitcher = isSingleOrgMode ? (
    <div className="flex w-full items-center justify-between gap-3 rounded-xl px-2 py-2">
      <div className="flex min-w-0 items-center gap-3">
        <OrgMark name={activeOrg?.name ?? runtimeConfig.singleOrgName} />
        <div className="min-w-0">
          <p className="truncate text-[14px] font-medium text-gray-900">
            {activeOrg?.name ?? runtimeConfig.singleOrgName}
          </p>
          <p className="truncate text-[12px] text-gray-500">
            {activeOrg ? sidebarRoleLabel(activeOrg.role) : "Preparing workspace"}
          </p>
        </div>
      </div>
      <button
        type="button"
        onClick={() => void signOut()}
        className="shrink-0 rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-900"
        aria-label="Sign out"
      >
        <LogOut className="h-4 w-4" />
      </button>
    </div>
  ) : (
    <WorkspaceSwitcher>
      {(close) => (
        <div data-workspace-switcher-root="" className="relative">
          <Popover.Trigger
            type="button"
            data-testid="workspace-switcher-trigger"
            className="flex w-full items-center justify-between gap-3 rounded-xl px-2 py-2 text-left transition-colors hover:bg-gray-100"
          >
            <div className="flex min-w-0 items-center gap-3">
              <OrgMark name={activeOrg?.name ?? "idexal Os"} />
              <div className="min-w-0">
                <p className="truncate text-[14px] font-medium text-gray-900">
                  {activeOrg?.name ?? "Loading..."}
                </p>
                <p className="truncate text-[12px] text-gray-500">
                  {activeOrg ? sidebarRoleLabel(activeOrg.role) : "Preparing workspace"}
                </p>
              </div>
            </div>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-gray-400 shrink-0">
              <path d="M17 3l4 4-4 4"/>
              <path d="M3 7h18"/>
              <path d="M7 21l-4-4 4-4"/>
              <path d="M21 17H3"/>
            </svg>
          </Popover.Trigger>

          <Popover.Portal>
            <Popover.Positioner
              positionMethod="fixed"
              side="top"
              align="start"
              sideOffset={8}
              collisionBoundary={[]}
              collisionPadding={8}
              collisionAvoidance={{ side: "shift", align: "shift", fallbackAxisSide: "none" }}
              className="z-[60]"
            >
              <Popover.Popup
                data-testid="workspace-switcher-menu"
                aria-label="Workspace switcher"
                className="flex max-h-[var(--available-height)] w-[240px] max-w-[calc(100vw-1.5rem)] flex-col gap-1 overflow-hidden rounded-2xl border border-gray-200 bg-white py-2 shadow-[0_12px_24px_-12px_rgba(0,0,0,0.15)]"
              >
                <div className="min-w-0 shrink-0 px-3 py-1.5">
                  <p className="truncate text-[13px] font-medium text-gray-900">
                    {user?.email ?? "idexal Os user"}
                  </p>
                </div>

                <div className="mx-2 my-1 h-px shrink-0 bg-gray-100" />

                <div className="shrink-0 px-3 pb-1 pt-1">
                  <p className="text-[11px] font-medium text-gray-500">
                    Switch workspace
                  </p>
                </div>

                {showSwitcherSearch ? (
                  <div className="shrink-0 px-2 pb-1">
                    <input
                      type="search"
                      value={switcherQuery}
                      onChange={(event) => setSwitcherQuery(event.target.value)}
                      placeholder="Search workspaces"
                      className="w-full rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-[12px] text-gray-900 outline-hidden transition focus:border-gray-400"
                    />
                  </div>
                ) : null}

                <div className="grid min-h-0 max-h-64 grid-cols-1 gap-0.5 overflow-y-auto px-1.5">
                  {visibleOrgDirectory.map((org) => (
                    <button
                      key={org.id}
                      type="button"
                      onClick={() => {
                        close();
                        switchOrganization(org.slug);
                      }}
                      className={`flex items-center justify-between gap-3 rounded-lg px-2 py-1.5 text-left transition-colors ${
                        org.isActive
                          ? "bg-gray-50 text-gray-900"
                          : "text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                      }`}
                    >
                      <div className="flex min-w-0 items-center gap-2.5">
                        <div className="min-w-0">
                          <span className="block truncate text-[13px] font-medium tracking-[-0.1px]">{org.name}</span>
                          <span className="block truncate text-[12px] text-gray-500">
                            {org.role === "owner" ? "Creator plan" : "Free plan"} • {org.memberCount} {org.memberCount === 1 ? "member" : "members"}
                          </span>
                        </div>
                      </div>
                      {org.isActive ? (
                        <svg className="h-4 w-4 shrink-0 text-gray-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                          <polyline points="20 6 9 17 4 12" />
                        </svg>
                      ) : null}
                    </button>
                  ))}
                </div>

                {switcherFilteredCount === 0 && switcherQuery ? (
                  <p className="shrink-0 px-3 py-1 text-[12px] text-gray-500">No organizations match your search.</p>
                ) : null}

                {switcherHasMore ? (
                  <div className="shrink-0 px-1.5">
                    <button
                      type="button"
                      onClick={showMoreOrgDirectory}
                      className="flex w-full items-center justify-center rounded-lg px-2 py-1.5 text-[12px] font-medium text-gray-600 transition-colors hover:bg-gray-50 hover:text-gray-900"
                    >
                      Show more ({switcherHiddenCount})
                    </button>
                  </div>
                ) : null}

                <div className="mt-0.5 shrink-0 px-1.5">
                  <Link
                    href="/organization"
                    className="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-[13px] font-medium text-gray-600 transition-colors hover:bg-gray-50 hover:text-gray-900"
                    onClick={close}
                  >
                    <span className="text-gray-400 text-[16px] leading-none">+</span> Create or join workspace
                  </Link>
                </div>

                <div className="mx-2 my-1 h-px shrink-0 bg-gray-100" />

                <div className="shrink-0 px-1.5">
                  <button
                    type="button"
                    onClick={() => void signOut()}
                    className="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-[13px] font-medium text-gray-600 transition-colors hover:bg-gray-50 hover:text-gray-900"
                  >
                    <LogOut className="h-4 w-4 text-gray-400" />
                    Sign out
                  </button>
                </div>
              </Popover.Popup>
            </Popover.Positioner>
          </Popover.Portal>
        </div>
      )}
    </WorkspaceSwitcher>
  );

  const sidebarContent = (
    <div className="flex h-full min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
      <div className="shrink-0 border-b border-gray-100 px-4 pb-4 pt-5">
        <div className="flex items-center justify-between gap-3">
          <SidebarBrandMark
            metadata={orgContext?.organization.metadata}
            organizationName={activeOrg?.name ?? runtimeConfig.singleOrgName}
          />
          <button
            type="button"
            className="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700 md:hidden"
            onClick={() => setSidebarOpen(false)}
            aria-label="Close menu"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
      </div>

      <nav className="min-h-0 min-w-0 flex-1 overflow-x-hidden overflow-y-auto px-3 py-5" data-testid="den-org-sidebar">
        <div className="space-y-5">
          {navSections.map((section) => (
            <div key={section.label} data-sidebar-section={section.label.toLowerCase()}>
              <p className="px-3 pb-2 text-[11px] font-medium text-gray-500">
                {section.label}
              </p>
              <div className="space-y-1">
                {section.items.map((item) => {
                  const isDashboardRoot =
                    activeOrg && item.href === getOrgDashboardRoute(activeOrg.slug);
                  const isLibraryItem =
                    Boolean(activeOrg && item.href === getLibraryRoute(activeOrg.slug));
                  const childActive = (child: DashboardNavChild) =>
                    pathname === child.href || pathname.startsWith(`${child.href}/`);
                  const groupActive = (item.href !== "#" && (
                    pathname === item.href || (!isDashboardRoot && pathname.startsWith(`${item.href}/`))
                  )) || (item.children ?? []).some(childActive);
                  const selected =
                    item.href !== "#" &&
                    (item.children
                      ? groupActive
                      : isDashboardRoot
                        ? pathname === item.href
                        : isLibraryItem
                          ? pathname === item.href
                            || pathname.startsWith(`${item.href}/`)
                            || (activeOrg != null && (
                              pathname === getYourConnectionsRoute(activeOrg.slug)
                              || pathname.startsWith(`${getYourConnectionsRoute(activeOrg.slug)}/`)
                            ))
                          : pathname === item.href
                            || pathname.startsWith(`${item.href}/`)
                            || (item.matchHrefs ?? []).some(
                              (href) => pathname === href || pathname.startsWith(`${href}/`),
                            ));

                  return (
                    <div key={item.label}>
                      <Link
                        href={item.href}
                        data-testid={item.testId}
                        onClick={() => setSidebarOpen(false)}
                        onPointerEnter={() => prefetch(item.href)}
                        onFocus={() => prefetch(item.href)}
                        className={`flex min-w-0 items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-[13px] tracking-[-0.1px] transition-colors ${
                          selected
                            ? "bg-gray-100 text-gray-900"
                            : "text-gray-500 hover:bg-gray-50 hover:text-gray-700"
                        }`}
                      >
                        <span className="flex min-w-0 items-center gap-3">
                          <item.icon className="h-4 w-4 shrink-0" strokeWidth={1.8} />
                          <span className="min-w-0 truncate">{item.label}</span>
                        </span>
                        <span className="flex shrink-0 items-center gap-1.5">
                          {item.attention ? (
                            <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" role="img" aria-label={item.attention} title={item.attention} data-testid="nav-attention" />
                          ) : null}
                          {item.badge ? (
                            <span className="shrink-0 rounded-full border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium leading-3 text-gray-600" data-testid="nav-badge">
                              {item.badge}
                            </span>
                          ) : null}
                          {item.children ? (
                            groupActive
                              ? <ChevronDown className="h-3.5 w-3.5 text-gray-400" strokeWidth={2} />
                              : <ChevronRight className="h-3.5 w-3.5 text-gray-300" strokeWidth={2} />
                          ) : null}
                        </span>
                      </Link>
                      {item.children && groupActive ? (
                        <div className="ml-[22px] mt-1 min-w-0 space-y-0.5 border-l border-gray-100 pl-3">
                          {item.children.map((child) => (
                            <Link
                              key={child.label}
                              href={child.href}
                              onClick={() => setSidebarOpen(false)}
                              className={`flex min-w-0 items-center justify-between gap-3 rounded-lg px-2.5 py-1.5 text-[13px] tracking-[-0.1px] transition-colors ${
                                childActive(child)
                                  ? "font-medium text-gray-900"
                                  : "text-gray-500 hover:text-gray-700"
                              }`}
                            >
                              <span className="min-w-0 truncate">{child.label}</span>
                              {child.badge ? (
                                <span className="shrink-0 rounded-full border border-gray-200 bg-white px-1.5 py-0.5 text-[11px] font-medium leading-3 text-gray-600" data-testid="nav-badge">
                                  {child.badge}
                                </span>
                              ) : null}
                            </Link>
                          ))}
                        </div>
                      ) : null}
                    </div>
                  );
                })}
              </div>
            </div>
          ))}
        </div>
      </nav>

      <div className="mt-auto shrink-0 p-3" data-testid="den-org-sidebar-footer">
        {orgSwitcher}

        {orgBusy ? (
          <p className="mt-3 px-2 text-[11px] text-gray-400">Refreshing workspace…</p>
        ) : null}
        {orgError ? (
          <p className="mt-3 px-2 text-[11px] font-medium text-rose-600">{orgError}</p>
        ) : null}
      </div>
    </div>
  );

  return (
    <div className="flex min-h-screen flex-col bg-[#fafafa] md:h-screen md:flex-row">
      <WorkspaceFavicon metadata={orgContext?.organization.metadata} />
      {/* Desktop sidebar — always visible at md+ */}
      <aside className="hidden min-h-0 shrink-0 overflow-hidden border-r border-gray-100 bg-white md:flex md:h-screen md:w-[260px] md:flex-col">
        {sidebarContent}
      </aside>

      {/* Mobile sidebar — off-canvas drawer */}
      {sidebarOpen ? (
        <div className="fixed inset-0 z-50 md:hidden">
          <div className="absolute inset-0 bg-black/30" onClick={() => setSidebarOpen(false)} aria-hidden />
          <aside className="relative z-10 flex h-full min-h-0 w-[280px] max-w-[85vw] flex-col overflow-hidden bg-white shadow-xl">
            {sidebarContent}
          </aside>
        </div>
      ) : null}

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-[52px] shrink-0 items-center justify-between border-b border-gray-100 bg-white px-4 md:px-6">
          <div className="flex min-w-0 items-center gap-3">
            <button
              type="button"
              className="rounded-lg p-1.5 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 md:hidden"
              onClick={() => setSidebarOpen(true)}
              aria-label="Open menu"
            >
              <Menu className="h-5 w-5" />
            </button>
            <span className="truncate text-[14px] tracking-[-0.1px] text-gray-900">{pageTitle}</span>
          </div>

          <>
              <div className="flex shrink-0 justify-center px-2 lg:min-w-0 lg:flex-1 lg:px-4">
                <DenSearchBar
                  ref={searchBarRef}
                  onOpen={() => handleCommandPaletteOpenChange(true)}
                />
              </div>

              <div className="flex shrink-0 items-center gap-1">
                {showFeedbackLink ? (
                  <a
                    href={feedbackHref}
                    target="_blank"
                    rel="noreferrer"
                    className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-700"
                  >
                    <MessageSquare className="h-4 w-4" />
                    <span className="hidden sm:inline">Feedback</span>
                  </a>
                ) : null}
                <a
                  href={IDEXAL_DOCS_URL}
                  target="_blank"
                  rel="noreferrer"
                  className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-700"
                >
                  <FileText className="h-4 w-4" />
                  <span className="hidden sm:inline">Docs</span>
                </a>
                <DownloadIdexalOsButton
                  key={`${activeOrg?.id}:${pathname}`}
                  organization={access.isAdmin && orgContext?.capabilities.installLinks && activeOrg ? activeOrg : undefined}
                />
              </div>
          </>
        </header>

        <main className="flex-1 overflow-y-auto bg-[#fafafa] scroll-pb-28">
          {setupPending && setupOrganizationId === activeOrg?.id ? (
            <div className="border-b border-gray-100 px-4 py-3 text-sm md:px-6">
              <Link href={onboardingRoute} className="font-medium text-gray-900 underline underline-offset-4">Back to setup</Link>
            </div>
          ) : null}
          {children}
        </main>
      </div>

      <DenCommandPalette
        open={commandPaletteOpen}
        onOpenChange={handleCommandPaletteOpenChange}
      />

      {shouldShowProfilePrompt && user ? (
        <UserProfileDialog
          key={user.id}
          user={user}
          descriptor="Change how your name appears in the organization"
          onCancel={() => setProfilePromptDismissed(true)}
          onSave={async (input) => {
            await updateUserProfile(input);
            setProfilePromptDismissed(true);
          }}
        />
      ) : null}
    </div>
  );
}
