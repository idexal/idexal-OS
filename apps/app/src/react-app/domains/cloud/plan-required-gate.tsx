/** @jsxImportSource react */
import { useEffect, useMemo, useState, type ReactNode } from "react";
import { AlertTriangle, ArrowUpRight, KeyRound, LoaderCircle } from "lucide-react";

import {
  clearDenSession,
  readDenSettings,
} from "@/app/lib/den";
import { denSettingsChangedEvent } from "@/app/lib/den-session-events";
import { Button } from "@/components/ui/button";
import { DitherBackdrop } from "@/components/dither-backdrop";
import { PageTitlebarRegion } from "@/components/page";
import { usePlatform } from "@/react-app/kernel/platform";
import { WebStartupScreen } from "@/react-app/shell/workspace-startup-status";
import { t } from "@/i18n";
import { useDenAuth } from "./den-auth-provider";
import { useDesktopAccessGrant, useDesktopConfig } from "./desktop-config-provider";
import { denWebBillingUrl } from "./idexal-os-web-access-gate";
import { readDesktopDistributionInfo } from "@/app/lib/desktop";

/**
 * The "pick a plan" screen the desktop shows a signed-in user whose
 * organization's current plan does not grant desktop access.
 *
 * This is the second half of the two-stage gate:
 *
 * - Sign-in is mandatory on the public installer (handled by the existing
 *   `ForcedSigninPage` / `DenSigninGate` chain, with `requireSignin: true`).
 * - This screen is what the user sees *after* they have signed in but their
 *   plan does not let them open the app. It blocks the shell and offers two
 *   escape hatches: pick a plan on the platform, or sign out and try a
 *   different account.
 *
 * The decision is delivered by the platform under `desktopAccess.granted`
 * in `GET /v1/me/desktop-config`. A missing block (a platform from before
 * this field) is treated as granted, so a deployment that has not enabled
 * the `accountRequiredForDesktopAccess` switch never shows this screen.
 *
 * Re-renders on every `denSettingsChangedEvent` and on the 5-minute
 * desktop-config refresh, so a plan the operator just assigned shows up
 * without a relaunch.
 */
export function PlanRequiredGate(props: { children: ReactNode }) {
  const platform = usePlatform();
  const denAuth = useDenAuth();
  const desktopConfig = useDesktopConfig();
  const { required, granted, planSlug } = useDesktopAccessGrant();
  const [retrying, setRetrying] = useState(false);

  const settings = useMemo(() => readDenSettings(), []);
  const planUrl = useMemo(
    () => denWebBillingUrl(settings.baseUrl || ""),
    [settings.baseUrl],
  );

  // The gate only blocks when the platform explicitly says the user's plan
  // does not grant desktop access. A missing block (platform from before the
  // field) or `granted: true` falls through to the app.
  const blocked = required === true && granted === false;

  useEffect(() => {
    if (!blocked) return;
    const handleSettingsChanged = () => {
      void desktopConfig.refresh();
    };
    window.addEventListener(denSettingsChangedEvent, handleSettingsChanged);
    return () => window.removeEventListener(denSettingsChangedEvent, handleSettingsChanged);
  }, [blocked, desktopConfig]);

  const retry = async () => {
    setRetrying(true);
    try {
      await desktopConfig.refresh();
      await denAuth.refresh();
    } finally {
      setRetrying(false);
    }
  };

  const signOut = () => {
    clearDenSession();
  };

  if (!blocked) return props.children;

  if (retrying) {
    return <WebStartupScreen message={t("den.plan_checking")} />;
  }

  const appName = readDesktopDistributionInfo().appName || "idexal Os";
  const orgName = settings.activeOrgName?.trim() || "your organization";

  return (
    <div
      className="relative min-h-dvh bg-background text-foreground"
      data-testid="plan-required-gate"
      data-plan-slug={planSlug ?? ""}
    >
      <div className="pointer-events-none absolute inset-0 z-0 overflow-hidden opacity-[0.1] dark:invert">
        <DitherBackdrop />
      </div>
      <PageTitlebarRegion />
      <div className="relative z-10 flex min-h-dvh items-center justify-center px-6 py-16">
        <div className="w-full max-w-[640px] rounded-3xl border border-border bg-background px-8 pb-10 pt-10 sm:px-12 sm:pb-14 sm:pt-12">
          <div className="flex items-center gap-2.5">
            <img
              src="/idexal-os-mark.svg"
              alt=""
              width={26}
              height={26}
              className="max-h-[26px] shrink-0 object-contain object-left dark:brightness-0 dark:invert"
              aria-hidden="true"
            />
            <span className="text-[15px] font-semibold tracking-tight text-foreground">
              {appName}
            </span>
          </div>

          <div className="mt-10 flex flex-col gap-2.5 sm:mt-12">
            <h1 className="text-[26px] font-semibold leading-[34px] tracking-[-0.02em] text-foreground sm:text-[32px] sm:leading-[40px]">
              {t("den.plan_required_title")}
            </h1>
            <p className="text-[15px] leading-[23px] text-muted-foreground">
              {t("den.plan_required_body", {
                user: orgName,
                plan: planSlug?.trim()
                  ? t("den.plan_required_plan_named", { plan: planSlug })
                  : t("den.plan_required_plan_none"),
              })}
            </p>
          </div>

          <div className="mt-10 flex flex-col gap-3">
            <Button
              type="button"
              size="lg"
              className="h-12 w-full text-[15px] font-semibold"
              onClick={() => platform.openLink(planUrl)}
            >
              {t("den.plan_required_cta")}
              <ArrowUpRight size={15} />
            </Button>

            <div className="flex flex-wrap items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => void retry()}
              >
                <LoaderCircle size={14} className="animate-spin" aria-hidden="true" />
                {t("den.plan_required_retry")}
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={signOut}
              >
                <KeyRound size={14} aria-hidden="true" />
                {t("den.plan_required_signout")}
              </Button>
            </div>

            <div className="mt-2 flex items-start gap-2 rounded-xl border border-dls-border bg-dls-surface/40 px-3 py-2 text-xs text-dls-secondary">
              <AlertTriangle size={14} className="mt-0.5 shrink-0" aria-hidden="true" />
              <span>{t("den.plan_required_note")}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
