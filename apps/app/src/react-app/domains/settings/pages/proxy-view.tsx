/** @jsxImportSource react */
import { useCallback, useEffect, useMemo, useState } from "react";
import { ExternalLink, Globe2, LockKeyhole, Pencil, Plus, ShieldCheck, Trash2, Zap } from "lucide-react";
import { toast } from "@/components/ui/sonner";

import { Button } from "@/components/ui/button";
import {
  Field,
  FieldDescription,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { desktopBridge } from "@/app/lib/desktop";
import { useDesktopConfig } from "@/react-app/domains/cloud/desktop-config-provider";
import { usePlatform } from "@/react-app/kernel/platform";
import { t } from "@/i18n";
import { cn } from "@/lib/utils";
import {
  LayoutSection,
  LayoutSectionDescription,
  LayoutSectionHeader,
  LayoutSectionTitle,
  LayoutStack,
} from "../settings-layout";

type ProxyKind = "static_residential" | "rotating_residential" | "datacenter" | "mobile" | "custom";
type ProxyProtocol = "http" | "https" | "socks5";

type ProxyEntitlements = {
  enabled: boolean;
  plan?: string | null;
  features: {
    proxy: boolean;
    residential: boolean;
    rotation: boolean;
    fingerprint: boolean;
    prewarm: boolean;
  };
  limits: {
    profiles: number | null;
    bandwidthGb: number | null;
  };
  providers: Array<{
    id: string;
    name: string;
    slug: string;
    protocol?: ProxyProtocol;
    websiteUrl?: string | null;
    docsUrl?: string | null;
    description?: string | null;
    countries?: string[];
  }>;
};

type ProxyProfile = Awaited<ReturnType<typeof desktopBridge.proxyState>>["profiles"][number];
type ProxyManagerState = Awaited<ReturnType<typeof desktopBridge.proxyState>>;

type Draft = {
  id?: string;
  name: string;
  kind: ProxyKind;
  protocol: ProxyProtocol;
  host: string;
  port: string;
  username: string;
  password: string;
  country: string;
  timezone: string;
  locale: string;
  latitude: string;
  longitude: string;
  userAgent: string;
  rotationUrl: string;
  providerId: string;
  providerSlug: string;
  notes: string;
  applyToApp: boolean;
  alignIdentity: boolean;
  hasPassword: boolean;
};

const LOCAL_ENTITLEMENTS: ProxyEntitlements = {
  enabled: true,
  plan: "local",
  features: { proxy: true, residential: true, rotation: false, fingerprint: false, prewarm: true },
  limits: { profiles: 1, bandwidthGb: 5 },
  providers: [],
};

const EMPTY_DRAFT: Draft = {
  name: "",
  kind: "static_residential",
  protocol: "http",
  host: "",
  port: "8080",
  username: "",
  password: "",
  country: "",
  timezone: "",
  locale: "",
  latitude: "",
  longitude: "",
  userAgent: "",
  rotationUrl: "",
  providerId: "",
  providerSlug: "",
  notes: "",
  applyToApp: true,
  alignIdentity: true,
  hasPassword: false,
};

const KIND_LABELS: Record<ProxyKind, string> = {
  static_residential: "Static residential",
  rotating_residential: "Rotating residential",
  datacenter: "Datacenter / proxy server",
  mobile: "Mobile",
  custom: "Custom",
};

function draftFromProfile(profile: ProxyProfile): Draft {
  return {
    id: profile.id,
    name: profile.name,
    kind: profile.kind,
    protocol: profile.protocol,
    host: profile.host,
    port: String(profile.port),
    username: profile.username,
    password: "",
    country: profile.country,
    timezone: profile.timezone,
    locale: profile.locale,
    latitude: profile.latitude === null ? "" : String(profile.latitude),
    longitude: profile.longitude === null ? "" : String(profile.longitude),
    userAgent: profile.userAgent,
    rotationUrl: profile.rotationUrl,
    providerId: profile.providerId,
    providerSlug: profile.providerSlug,
    notes: profile.notes,
    applyToApp: profile.applyToApp,
    alignIdentity: profile.alignIdentity,
    hasPassword: profile.hasPassword,
  };
}

function errorMessage(error: unknown): string {
  return error instanceof Error ? error.message : String(error ?? "Something went wrong.");
}

export function ProxyView() {
  const { config } = useDesktopConfig();
  const platform = usePlatform();
  const entitlements = useMemo<ProxyEntitlements>(() => {
    const configured = config.proxy;
    if (!configured) return LOCAL_ENTITLEMENTS;
    return {
      enabled: configured.enabled,
      plan: configured.plan ?? null,
      features: configured.features,
      limits: configured.limits,
      providers: configured.providers ?? [],
    };
  }, [config.proxy]);

  const [state, setState] = useState<ProxyManagerState | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [testing, setTesting] = useState<Record<string, string>>({});

  const refresh = useCallback(async () => {
    try {
      setState(await desktopBridge.proxyState());
      setLoadError(null);
    } catch (error) {
      setLoadError(errorMessage(error));
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  useEffect(() => {
    void desktopBridge.proxySetLimit(entitlements.limits.bandwidthGb)
      .then(setState)
      .catch(() => undefined);
  }, [entitlements.limits.bandwidthGb]);

  const profileCount = state?.profiles.length ?? 0;
  const atProfileLimit = entitlements.limits.profiles !== null && profileCount >= entitlements.limits.profiles;
  const canManage = entitlements.enabled && entitlements.features.proxy;
  const activeProfile = state?.profiles.find((profile) => profile.id === state.activeId) ?? null;

  const saveDraft = useCallback(async () => {
    if (!draft) return;
    if (draft.kind === "static_residential" && !entitlements.features.residential) {
      toast.error(t("settings.proxy.residential_locked"));
      return;
    }
    if (draft.kind === "rotating_residential" && !entitlements.features.rotation) {
      toast.error(t("settings.proxy.rotation_locked"));
      return;
    }
    setBusy("save");
    try {
      const port = Number(draft.port);
      const latitude = draft.latitude.trim() ? Number(draft.latitude) : null;
      const longitude = draft.longitude.trim() ? Number(draft.longitude) : null;
      const next = await desktopBridge.proxySave({
        ...(draft.id ? { id: draft.id } : {}),
        name: draft.name,
        kind: draft.kind,
        protocol: draft.protocol,
        host: draft.host,
        port,
        username: draft.username,
        ...(draft.password ? { password: draft.password } : {}),
        country: draft.country,
        timezone: draft.timezone,
        locale: draft.locale,
        latitude,
        longitude,
        userAgent: draft.userAgent,
        rotationUrl: draft.rotationUrl,
        providerId: draft.providerId,
        providerSlug: draft.providerSlug,
        notes: draft.notes,
        applyToApp: draft.applyToApp,
        alignIdentity: draft.alignIdentity,
      });
      setState(next);
      setDraft(null);
      toast.success(t("settings.proxy.saved"));
    } catch (error) {
      toast.error(errorMessage(error));
    } finally {
      setBusy(null);
    }
  }, [draft, entitlements.features.residential, entitlements.features.rotation]);

  const activate = useCallback(async (profile: ProxyProfile) => {
    if (!canManage) {
      toast.error(t("settings.proxy.globally_disabled"));
      return;
    }
    if (profile.kind === "rotating_residential" && !entitlements.features.rotation) {
      toast.error(t("settings.proxy.rotation_locked"));
      return;
    }
    if (profile.kind === "static_residential" && !entitlements.features.residential) {
      toast.error(t("settings.proxy.residential_locked"));
      return;
    }
    setBusy(`activate:${profile.id}`);
    try {
      setState(await desktopBridge.proxyActivate(profile.id));
      toast.success(t("settings.proxy.activated"));
    } catch (error) {
      toast.error(errorMessage(error));
      await refresh();
    } finally {
      setBusy(null);
    }
  }, [canManage, entitlements.features.residential, entitlements.features.rotation, refresh]);

  const disable = useCallback(async () => {
    setBusy("disable");
    try {
      setState(await desktopBridge.proxyDisable());
      toast.success(t("settings.proxy.disabled"));
    } catch (error) {
      toast.error(errorMessage(error));
    } finally {
      setBusy(null);
    }
  }, []);

  const test = useCallback(async (profile: ProxyProfile) => {
    setTesting((current) => ({ ...current, [profile.id]: t("settings.proxy.testing") }));
    try {
      const result = await desktopBridge.proxyTest(profile.id);
      setTesting((current) => ({ ...current, [profile.id]: "" }));
      if (result.ok) {
        toast.success(t("settings.proxy.test_ok", {
          ip: result.ip ?? "?",
          country: result.country ?? "?",
          latency: String(result.latencyMs ?? 0),
        }));
      } else {
        toast.error(result.error ?? t("settings.proxy.test_failed"));
      }
    } catch (error) {
      setTesting((current) => ({ ...current, [profile.id]: "" }));
      toast.error(errorMessage(error));
    }
  }, []);

  const rotate = useCallback(async (profile: ProxyProfile) => {
    if (!canManage || !entitlements.features.rotation) {
      toast.error(t("settings.proxy.rotation_locked"));
      return;
    }
    setBusy(`rotate:${profile.id}`);
    try {
      const result = await desktopBridge.proxyRotate(profile.id);
      if (!result.ok) {
        toast.error(result.error ?? t("settings.proxy.rotate_failed"));
        return;
      }
      toast.success(t("settings.proxy.rotate_ok"));
      await test(profile);
    } catch (error) {
      toast.error(errorMessage(error));
    } finally {
      setBusy(null);
    }
  }, [canManage, entitlements.features.rotation, test]);

  const remove = useCallback(async (profile: ProxyProfile) => {
    if (!window.confirm(t("settings.proxy.delete_confirm", { name: profile.name }))) return;
    setBusy(`delete:${profile.id}`);
    try {
      setState(await desktopBridge.proxyDelete(profile.id));
      if (draft?.id === profile.id) setDraft(null);
      toast.success(t("settings.proxy.deleted"));
    } catch (error) {
      toast.error(errorMessage(error));
    } finally {
      setBusy(null);
    }
  }, [draft?.id]);

  const openProvider = useCallback((url: string | null | undefined) => {
    if (url) platform.openLink(url);
  }, [platform]);

  if (loadError) {
    return (
      <LayoutStack>
        <LayoutSection>
          <LayoutSectionHeader>
            <LayoutSectionTitle>{t("settings.proxy.title")}</LayoutSectionTitle>
            <LayoutSectionDescription>{t("settings.proxy.unavailable")}</LayoutSectionDescription>
          </LayoutSectionHeader>
          <p className="text-sm text-destructive">{loadError}</p>
          <Button variant="outline" onClick={() => void refresh()}>{t("settings.proxy.retry")}</Button>
        </LayoutSection>
      </LayoutStack>
    );
  }

  return (
    <LayoutStack>
      <LayoutSection>
        <LayoutSectionHeader>
          <LayoutSectionTitle className="justify-between">
            <span>{t("settings.proxy.title")}</span>
            <span className="rounded-full border border-border px-2.5 py-1 text-[11px] font-medium text-muted-foreground">
              {entitlements.plan ? entitlements.plan.toUpperCase() : t("settings.proxy.plan_local")}
            </span>
          </LayoutSectionTitle>
          <LayoutSectionDescription>{t("settings.proxy.subtitle")}</LayoutSectionDescription>
        </LayoutSectionHeader>

        <div className={cn(
          "rounded-xl border p-4",
          state?.enabled && state.guard
            ? "border-emerald-500/30 bg-emerald-500/5"
            : "border-border bg-muted/30",
        )}>
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div className="flex items-start gap-3">
              <span className={cn(
                "mt-0.5 grid size-9 place-items-center rounded-lg",
                state?.enabled ? "bg-emerald-500/15 text-emerald-600" : "bg-muted text-muted-foreground",
              )}>
                <ShieldCheck className="size-5" />
              </span>
              <div>
                <p className="text-sm font-medium">
                  {state?.enabled ? t("settings.proxy.status_on") : t("settings.proxy.status_off")}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                  {state?.enabled && state.guard
                    ? t("settings.proxy.guard_running", { host: state.guard.host, port: String(state.guard.port) })
                    : t("settings.proxy.guard_stopped")}
                </p>
                {activeProfile ? (
                  <p className="mt-1 text-xs">
                    {activeProfile.name}
                    <span className="text-muted-foreground">
                      {" · "}
                      {activeProfile.protocol.toUpperCase()} {activeProfile.host}:{activeProfile.port}
                    </span>
                  </p>
                ) : null}
                {state?.lastError ? (
                  <p className="mt-2 text-xs text-destructive">{state.lastError}</p>
                ) : null}
                {state?.secretsUnavailable ? (
                  <p className="mt-2 rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                    {t("settings.proxy.secrets_unavailable")}
                  </p>
                ) : null}
              </div>
            </div>
            <div className="flex items-center gap-2">
              {state?.enabled ? (
                <Button variant="outline" size="sm" onClick={() => void disable()} disabled={busy !== null}>
                  {t("settings.proxy.turn_off")}
                </Button>
              ) : null}
              <Button
                size="sm"
                onClick={() => {
                  if (!canManage) {
                    toast.error(t("settings.proxy.globally_disabled"));
                    return;
                  }
                  if (atProfileLimit) {
                    toast.error(t("settings.proxy.limit_reached", {
                      limit: String(entitlements.limits.profiles),
                    }));
                    return;
                  }
                  setDraft({ ...EMPTY_DRAFT });
                }}
                disabled={busy !== null || !canManage}
              >
                <Plus className="size-4" />
                {t("settings.proxy.add")}
              </Button>
            </div>
          </div>

          <div className="mt-4 grid gap-2 text-xs text-muted-foreground sm:grid-cols-2 lg:grid-cols-4">
            <span>{t("settings.proxy.limit_profiles", {
              used: String(profileCount),
              limit: entitlements.limits.profiles === null ? "∞" : String(entitlements.limits.profiles),
            })}</span>
            <span>{t("settings.proxy.limit_bandwidth", {
              limit: entitlements.limits.bandwidthGb === null ? "∞" : String(entitlements.limits.bandwidthGb),
            })}</span>
            <span>{state?.guard
              ? t("settings.proxy.bandwidth_used", {
                  used: (state.guard.usedBytes / 1024 ** 3).toFixed(2),
                  limit: state.guard.limitBytes === null ? "∞" : String(entitlements.limits.bandwidthGb ?? "∞"),
                })
              : t("settings.proxy.bandwidth_idle")}</span>
            <span>{t("settings.proxy.privacy_note")}</span>
          </div>

          {!canManage ? (
            <p className="mt-3 rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
              {t("settings.proxy.globally_disabled")}
            </p>
          ) : null}
        </div>
      </LayoutSection>

      <LayoutSection>
        <LayoutSectionHeader>
          <LayoutSectionTitle>{t("settings.proxy.providers_title")}</LayoutSectionTitle>
          <LayoutSectionDescription>{t("settings.proxy.providers_subtitle")}</LayoutSectionDescription>
        </LayoutSectionHeader>
        <div className="flex flex-wrap gap-2">
          {entitlements.providers.map((provider) => (
            <button
              key={provider.id}
              type="button"
              onClick={() => openProvider(provider.websiteUrl ?? provider.docsUrl ?? null)}
              className="group inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-xs font-medium transition hover:border-foreground/30 hover:bg-muted/50"
              title={provider.description ?? provider.name}
            >
              {provider.name}
              {provider.websiteUrl ? <ExternalLink className="size-3 text-muted-foreground" /> : null}
            </button>
          ))}
          <button
            type="button"
            onClick={() => openProvider("https://www.webshare.io/residential-proxy")}
            className="inline-flex items-center gap-2 rounded-lg border border-dashed border-border px-3 py-2 text-xs font-medium text-muted-foreground transition hover:border-foreground/30 hover:text-foreground"
          >
            <Globe2 className="size-3.5" />
            {t("settings.proxy.buy_anywhere")}
          </button>
        </div>
      </LayoutSection>

      {draft ? (
        <ProxyEditor
          draft={draft}
          onChange={setDraft}
          onCancel={() => setDraft(null)}
          onSave={() => void saveDraft()}
          busy={busy === "save"}
          entitlements={entitlements}
          providers={entitlements.providers}
        />
      ) : null}

      <LayoutSection>
        <LayoutSectionHeader>
          <LayoutSectionTitle>{t("settings.proxy.profiles_title")}</LayoutSectionTitle>
          <LayoutSectionDescription>{t("settings.proxy.profiles_subtitle")}</LayoutSectionDescription>
        </LayoutSectionHeader>

        {!state ? (
          <p className="text-sm text-muted-foreground">{t("settings.proxy.loading")}</p>
        ) : state.profiles.length === 0 ? (
          <div className="rounded-xl border border-dashed border-border p-6 text-center">
            <Globe2 className="mx-auto size-6 text-muted-foreground" />
            <p className="mt-2 text-sm font-medium">{t("settings.proxy.empty")}</p>
            <p className="mt-1 text-xs text-muted-foreground">{t("settings.proxy.empty_hint")}</p>
          </div>
        ) : (
          <div className="space-y-2">
            {state.profiles.map((profile) => {
              const active = state.enabled && state.activeId === profile.id;
              const premiumLocked =
                (profile.kind === "rotating_residential" && !entitlements.features.rotation) ||
                (profile.kind === "static_residential" && !entitlements.features.residential);
              return (
                <div
                  key={profile.id}
                  className={cn(
                    "rounded-xl border p-4 transition",
                    active ? "border-emerald-500/40 bg-emerald-500/5" : "border-border",
                  )}
                >
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-medium">{profile.name}</span>
                        <span className="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                          {KIND_LABELS[profile.kind]}
                        </span>
                        {profile.providerSlug ? (
                          <span className="text-[10px] text-muted-foreground">{profile.providerSlug}</span>
                        ) : null}
                        {active ? (
                          <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-400">
                            <Zap className="size-3" /> {t("settings.proxy.active")}
                          </span>
                        ) : null}
                        {premiumLocked ? (
                          <span className="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-400">
                            <LockKeyhole className="size-3" /> {t("settings.proxy.plan_locked")}
                          </span>
                        ) : null}
                      </div>
                      <p className="mt-1 truncate font-mono text-xs text-muted-foreground" dir="ltr">
                        {profile.protocol}://{profile.host}:{profile.port}
                        {profile.username ? ` · ${profile.username}` : ""}
                      </p>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {[
                          profile.country || null,
                          profile.timezone || null,
                          profile.locale || null,
                          profile.applyToApp ? t("settings.proxy.scope_app") : t("settings.proxy.scope_browser"),
                        ].filter(Boolean).join(" · ")}
                      </p>
                      {testing[profile.id] ? (
                        <p className="mt-1 text-xs text-muted-foreground">{testing[profile.id]}</p>
                      ) : null}
                    </div>
                    <div className="flex flex-wrap items-center gap-1.5">
                      {active ? null : (
                        <Button
                          size="sm"
                          variant={premiumLocked ? "outline" : "default"}
                          disabled={busy !== null || premiumLocked || !canManage}
                          onClick={() => void activate(profile)}
                        >
                          {busy === `activate:${profile.id}` ? t("settings.proxy.activating") : t("settings.proxy.use")}
                        </Button>
                      )}
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={busy !== null}
                        onClick={() => void test(profile)}
                      >
                        {t("settings.proxy.test")}
                      </Button>
                      {profile.kind === "rotating_residential" && profile.rotationUrl ? (
                        <Button
                          size="sm"
                          variant="outline"
                          disabled={busy !== null || !canManage || !entitlements.features.rotation}
                          onClick={() => void rotate(profile)}
                        >
                          {busy === `rotate:${profile.id}` ? t("settings.proxy.rotating") : t("settings.proxy.rotate")}
                        </Button>
                      ) : null}
                      <Button
                        size="sm"
                        variant="ghost"
                        disabled={busy !== null}
                        onClick={() => setDraft(draftFromProfile(profile))}
                        aria-label={t("settings.proxy.edit")}
                      >
                        <Pencil className="size-4" />
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        disabled={busy !== null}
                        onClick={() => void remove(profile)}
                        aria-label={t("settings.proxy.delete")}
                      >
                        <Trash2 className="size-4" />
                      </Button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </LayoutSection>

      <LayoutSection>
        <LayoutSectionHeader>
          <LayoutSectionTitle>{t("settings.proxy.identity_title")}</LayoutSectionTitle>
          <LayoutSectionDescription>{t("settings.proxy.identity_subtitle")}</LayoutSectionDescription>
        </LayoutSectionHeader>
        <div className="rounded-xl border border-border p-4 text-xs leading-5 text-muted-foreground">
          {entitlements.features.fingerprint
            ? t("settings.proxy.identity_enabled")
            : t("settings.proxy.identity_locked")}
        </div>
      </LayoutSection>
    </LayoutStack>
  );
}

function ProxyEditor(props: {
  draft: Draft;
  onChange: (draft: Draft) => void;
  onCancel: () => void;
  onSave: () => void;
  busy: boolean;
  entitlements: ProxyEntitlements;
  providers: ProxyEntitlements["providers"];
}) {
  const { draft, onChange, entitlements } = props;
  const set = <K extends keyof Draft>(key: K, value: Draft[K]) => onChange({ ...draft, [key]: value });
  const identityLocked = !entitlements.features.fingerprint;
  const residentialLocked = !entitlements.features.residential;
  const rotationLocked = !entitlements.features.rotation;

  return (
    <LayoutSection>
      <LayoutSectionHeader>
        <LayoutSectionTitle>{draft.id ? t("settings.proxy.edit_title") : t("settings.proxy.create_title")}</LayoutSectionTitle>
        <LayoutSectionDescription>{t("settings.proxy.form_subtitle")}</LayoutSectionDescription>
      </LayoutSectionHeader>

      <div className="rounded-xl border border-border p-4">
        <FieldGroup>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field>
              <FieldLabel htmlFor="proxy-name">{t("settings.proxy.name")}</FieldLabel>
              <Input
                id="proxy-name"
                value={draft.name}
                maxLength={80}
                placeholder={t("settings.proxy.name_placeholder")}
                onChange={(event) => set("name", event.target.value)}
              />
            </Field>
            <Field>
              <FieldLabel htmlFor="proxy-kind">{t("settings.proxy.kind")}</FieldLabel>
              <select
                id="proxy-kind"
                value={draft.kind}
                onChange={(event) => set("kind", event.target.value as ProxyKind)}
                className="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              >
                <option value="static_residential" disabled={residentialLocked}>{KIND_LABELS.static_residential}</option>
                <option value="rotating_residential" disabled={rotationLocked}>{KIND_LABELS.rotating_residential}</option>
                <option value="datacenter">{KIND_LABELS.datacenter}</option>
                <option value="mobile">{KIND_LABELS.mobile}</option>
                <option value="custom">{KIND_LABELS.custom}</option>
              </select>
              {residentialLocked || rotationLocked ? (
                <FieldDescription>{t("settings.proxy.kind_locked")}</FieldDescription>
              ) : null}
            </Field>
            <Field>
              <FieldLabel htmlFor="proxy-protocol">{t("settings.proxy.protocol")}</FieldLabel>
              <select
                id="proxy-protocol"
                value={draft.protocol}
                onChange={(event) => set("protocol", event.target.value as ProxyProtocol)}
                className="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              >
                <option value="http">HTTP</option>
                <option value="https">HTTPS</option>
                <option value="socks5">SOCKS5</option>
              </select>
            </Field>
            <Field>
              <FieldLabel htmlFor="proxy-provider">{t("settings.proxy.provider")}</FieldLabel>
              <select
                id="proxy-provider"
                value={draft.providerId}
                onChange={(event) => {
                  const provider = props.providers.find((entry) => entry.id === event.target.value);
                  onChange({
                    ...draft,
                    providerId: provider?.id ?? "",
                    providerSlug: provider?.slug ?? "",
                    protocol: provider?.protocol ?? draft.protocol,
                  });
                }}
                className="h-9 w-full rounded-md border border-input bg-transparent px-2.5 text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
              >
                <option value="">{t("settings.proxy.provider_none")}</option>
                {props.providers.map((provider) => (
                  <option key={provider.id} value={provider.id}>{provider.name}</option>
                ))}
              </select>
            </Field>
            <Field>
              <FieldLabel htmlFor="proxy-host">{t("settings.proxy.host")}</FieldLabel>
              <Input
                id="proxy-host"
                value={draft.host}
                dir="ltr"
                className="font-mono"
                placeholder="proxy.example.com"
                onChange={(event) => set("host", event.target.value)}
              />
              <FieldDescription>{t("settings.proxy.host_hint")}</FieldDescription>
            </Field>
            <Field>
              <FieldLabel htmlFor="proxy-port">{t("settings.proxy.port")}</FieldLabel>
              <Input
                id="proxy-port"
                value={draft.port}
                dir="ltr"
                inputMode="numeric"
                className="font-mono"
                placeholder="8080"
                onChange={(event) => set("port", event.target.value)}
              />
            </Field>
            <Field>
              <FieldLabel htmlFor="proxy-username">{t("settings.proxy.username")}</FieldLabel>
              <Input
                id="proxy-username"
                value={draft.username}
                dir="ltr"
                autoComplete="off"
                className="font-mono"
                onChange={(event) => set("username", event.target.value)}
              />
            </Field>
            <Field>
              <FieldLabel htmlFor="proxy-password">{t("settings.proxy.password")}</FieldLabel>
              <Input
                id="proxy-password"
                type="password"
                value={draft.password}
                dir="ltr"
                autoComplete="new-password"
                className="font-mono"
                placeholder={draft.hasPassword ? t("settings.proxy.password_saved") : ""}
                onChange={(event) => set("password", event.target.value)}
              />
              <FieldDescription>{t("settings.proxy.password_hint")}</FieldDescription>
            </Field>
            <Field>
              <FieldLabel htmlFor="proxy-country">{t("settings.proxy.country")}</FieldLabel>
              <Input
                id="proxy-country"
                value={draft.country}
                dir="ltr"
                className="font-mono"
                placeholder="us"
                maxLength={3}
                onChange={(event) => set("country", event.target.value.toLowerCase())}
              />
            </Field>
            <Field>
              <FieldLabel htmlFor="proxy-rotation">{t("settings.proxy.rotation_url")}</FieldLabel>
              <Input
                id="proxy-rotation"
                value={draft.rotationUrl}
                dir="ltr"
                className="font-mono text-xs"
                placeholder="https://"
                disabled={!entitlements.features.rotation}
                onChange={(event) => set("rotationUrl", event.target.value)}
              />
              <FieldDescription>{t("settings.proxy.rotation_hint")}</FieldDescription>
            </Field>
          </div>

          <div className="mt-2 rounded-lg border border-border/70 p-3">
            <p className="text-xs font-medium">{t("settings.proxy.identity_section")}</p>
            <p className="mt-1 text-xs text-muted-foreground">
              {identityLocked ? t("settings.proxy.identity_locked") : t("settings.proxy.identity_hint")}
            </p>
            <div className="mt-3 grid gap-4 sm:grid-cols-2">
              <Field>
                <FieldLabel htmlFor="proxy-timezone">{t("settings.proxy.timezone")}</FieldLabel>
                <Input
                  id="proxy-timezone"
                  value={draft.timezone}
                  dir="ltr"
                  className="font-mono"
                  placeholder="Europe/London"
                  disabled={identityLocked}
                  onChange={(event) => set("timezone", event.target.value)}
                />
              </Field>
              <Field>
                <FieldLabel htmlFor="proxy-locale">{t("settings.proxy.locale")}</FieldLabel>
                <Input
                  id="proxy-locale"
                  value={draft.locale}
                  dir="ltr"
                  className="font-mono"
                  placeholder="en-GB"
                  disabled={identityLocked}
                  onChange={(event) => set("locale", event.target.value)}
                />
              </Field>
              <Field className="sm:col-span-2">
                <FieldLabel htmlFor="proxy-user-agent">{t("settings.proxy.user_agent")}</FieldLabel>
                <Textarea
                  id="proxy-user-agent"
                  value={draft.userAgent}
                  dir="ltr"
                  rows={2}
                  className="font-mono text-xs"
                  placeholder={t("settings.proxy.user_agent_placeholder")}
                  disabled={identityLocked}
                  onChange={(event) => set("userAgent", event.target.value)}
                />
              </Field>
            </div>
          </div>

          <Field>
            <FieldLabel htmlFor="proxy-notes">{t("settings.proxy.notes")}</FieldLabel>
            <Textarea
              id="proxy-notes"
              value={draft.notes}
              rows={2}
              maxLength={2000}
              onChange={(event) => set("notes", event.target.value)}
            />
          </Field>

          <div className="grid gap-2 sm:grid-cols-2">
            <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 text-sm">
              <input
                type="checkbox"
                className="mt-0.5 size-4 accent-primary"
                checked={draft.applyToApp}
                onChange={(event) => set("applyToApp", event.target.checked)}
              />
              <span>
                <span className="block font-medium">{t("settings.proxy.apply_app")}</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">{t("settings.proxy.apply_app_hint")}</span>
              </span>
            </label>
            <label className={cn(
              "flex items-start gap-3 rounded-lg border border-border p-3 text-sm",
              identityLocked ? "cursor-not-allowed opacity-60" : "cursor-pointer",
            )}>
              <input
                type="checkbox"
                className="mt-0.5 size-4 accent-primary"
                checked={draft.alignIdentity && !identityLocked}
                disabled={identityLocked}
                onChange={(event) => set("alignIdentity", event.target.checked)}
              />
              <span>
                <span className="block font-medium">{t("settings.proxy.align_identity")}</span>
                <span className="mt-0.5 block text-xs text-muted-foreground">{t("settings.proxy.align_identity_hint")}</span>
              </span>
            </label>
          </div>

          {props.busy ? <FieldDescription>{t("settings.proxy.saving")}</FieldDescription> : null}
        </FieldGroup>

        <div className="mt-4 flex items-center justify-end gap-2">
          <Button variant="outline" onClick={props.onCancel} disabled={props.busy}>
            {t("settings.proxy.cancel")}
          </Button>
          <Button onClick={props.onSave} disabled={props.busy || !draft.name.trim() || !draft.host.trim()}>
            {props.busy ? t("settings.proxy.saving") : t("settings.proxy.save")}
          </Button>
        </div>
      </div>
    </LayoutSection>
  );
}
