import { useEffect, useMemo, useState, type ReactNode } from "react";
import { ChevronDown, ChevronUp, Lock, Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { ModelOption } from "@/app/types";
import type { DesktopFreePreferences } from "@/app/lib/idexal-os-server";
import {
  modelRefKey,
  readModelFallbacks,
  writeModelFallbacks,
  type FallbackModelRef,
} from "@/react-app/domains/session/sync/model-fallback";
import { t } from "@/i18n";
import { gatewayConnectProviderKey, type GatewayConnectProvider, isCloudManagedProviderKey } from "../../connections/provider-auth/cloud-provider-config";
import { isKeylessZenProvider } from "../../connections/provider-auth/provider-policy";
import type { ProviderLoadState } from "../../connections/provider-auth/store";
import { AUTO_PROVIDER_ID, autoProviderSubtitle } from "@/react-app/domains/models/model-catalog";
import { useShellConfig } from "../../../shell/shell-config";
import { SettingsNotice } from "../settings-section";
import { LayoutStack } from "../settings-layout";
import { SettingsPanel, SettingsPanelDescription, SettingsPanelHeading, SettingsPanelTitle } from "../shell/panel";
import { getSettingsTabLabel } from "../shell/settings-page";
import { ProviderList, ProviderMeta, ProviderRow, ProviderStatus, ProviderTile } from "./provider-rows";

export type ConnectedProvider = {
  id: string;
  name: string;
  source?: "env" | "api" | "config" | "custom";
};

export type AiSettingsViewProps = {
  busy: boolean;
  providerAuthBusy: boolean;
  providerStatusLabel: string;
  providerStatusStyle: string;
  providerSummary: string;
  providerLoadState: ProviderLoadState;
  onRetryProviders: () => void | Promise<void>;
  connectedProviders: ConnectedProvider[];
  disconnectingProviderId: string | null;
  providerConnectError: string | null;
  providerDisconnectStatus: string | null;
  providerDisconnectError: string | null;
  onOpenProviderAuth: () => void | Promise<void>;
  onDisconnectProvider: (providerId: string) => void | Promise<void>;
  canDisconnectProvider: (provider: ConnectedProvider) => boolean;
  /** Providers hidden by Disconnect (disabled_providers); each can be enabled again. */
  disabledProviders?: { id: string; name: string }[];
  enablingProviderId?: string | null;
  onEnableProvider?: (providerId: string) => void | Promise<void>;
  /** The Gateway reports free Auto switched off: no idexal Os Models row for it. */
  autoSwitchedOff?: boolean;
  canAddProviders: boolean;
  signedIn?: boolean;
  organizationName?: string;
  cloudProviderIds?: Set<string>;
  gatewayProviderIds?: ReadonlySet<string>;
  gatewayConnectProviders?: GatewayConnectProvider[];
  connectingGatewayProviderId?: string | null;
  onConnectGatewayProvider?: (provider: GatewayConnectProvider) => void | Promise<void>;
  onCancelGatewayConnect?: () => void;
  showIdexalOsModelsConnect?: boolean;
  showIdexalOsModelsSyncing?: boolean;
  onDismissIdexalOsModels?: () => void | Promise<void>;
  cloudProvidersView?: ReactNode;
  autoPreferences?: DesktopFreePreferences | null;
  autoBusy?: boolean;
  autoError?: string | null;
  onSetAutoEnabled?: (enabled: boolean) => void | Promise<void>;
  organizationProviderIds?: ReadonlySet<string>;
  /** Providers the active plan covers, as delivered by the platform. */
  planProviders?: ReadonlyArray<{ slug: string; name: string; driver?: string }>;
  /** True when the platform told us the plan's provider list is authoritative. */
  planEnabled?: boolean;
  planName?: string;
  /** False when the operator turned members' own provider keys off. */
  byokAllowed?: boolean;
  onOpenDen?: () => void;
  fallbackModelOptions?: readonly ModelOption[];
};

function deviceSourceLabel(source: ConnectedProvider["source"]) {
  if (source === "api") return "API key";
  if (source === "env") return "Environment credential";
  return "Configured on this device";
}

export function GatewayConnectRow({ provider, busy, onConnect, onCancel }: {
  provider: GatewayConnectProvider;
  busy: boolean;
  onConnect?: (provider: GatewayConnectProvider) => void | Promise<void>;
  onCancel?: () => void;
}) {
  return (
    <ProviderRow
      scope="organization"
      tile={<ProviderTile providerId={provider.providerId} name={provider.name} />}
      name={provider.name}
      status={<ProviderStatus tone="attention">Needs your sign-in</ProviderStatus>}
      meta={<ProviderMeta id={provider.cloudProviderId} parts={["idexal Os Gateway", "Each member signs in"]} />}
      actions={<>
        <Button disabled={busy || !onConnect} onClick={() => void onConnect?.(provider)}>{busy ? "Waiting for sign-in…" : `Sign in to ${provider.name}`}</Button>
        {busy && onCancel ? <Button variant="ghost" onClick={onCancel}>Stop waiting</Button> : null}
      </>}
    />
  );
}

export function aiProvidersDescription(input: { signedIn: boolean; organizationName?: string; offerCloudSignIn?: boolean }) {
  const base = "Models your agents can use in this workspace. Keys you add here stay on this device.";
  if (input.signedIn) {
    return `${base} Providers from ${input.organizationName || "your organization"} are included automatically and run through the idexal Os Gateway.`;
  }
  // A build without Cloud sign-in on offer has no sign-in action to point at.
  return input.offerCloudSignIn
    ? `${base} Sign in to idexal Os to also get the providers your organization already pays for.`
    : base;
}

export function AiSettingsView(props: AiSettingsViewProps) {
  const { config: shellConfig } = useShellConfig();
  const ready = props.providerLoadState.status === "ready";
  const loading = props.providerLoadState.status === "loading" || props.providerLoadState.status === "idle";
  const error = props.providerLoadState.error;
  const signedIn = props.signedIn ?? Boolean(props.organizationName);
  const managed = (provider: ConnectedProvider) => provider.id !== AUTO_PROVIDER_ID && (isCloudManagedProviderKey(provider.id) || props.cloudProviderIds?.has(provider.id) || props.gatewayProviderIds?.has(provider.id));
  // Keyless built-in Zen is offered under "Available to add" in Connect a provider, not listed as connected.
  const local = props.connectedProviders.filter((provider) => !managed(provider) && provider.id !== AUTO_PROVIDER_ID && !isKeylessZenProvider(provider));
  const organization = props.connectedProviders.filter(managed);
  const disabled = props.onEnableProvider ? props.disabledProviders ?? [] : [];
  const showAuto = !props.autoSwitchedOff && Boolean(props.autoPreferences || props.connectedProviders.some((provider) => provider.id === AUTO_PROVIDER_ID));
  const autoOff = props.autoPreferences?.enabled === false;
  const autoAvailable = props.autoPreferences?.available ?? ready;
  const locked = !props.canAddProviders;
  const deviceCount = local.length + Number(showAuto);
  const rowsBusy = props.busy || props.providerAuthBusy || props.disconnectingProviderId !== null;

  return <LayoutStack>
    <SettingsPanel>
      <SettingsPanelHeading className="max-w-xl">
        <SettingsPanelTitle>{getSettingsTabLabel("ai")}</SettingsPanelTitle>
        <SettingsPanelDescription>{aiProvidersDescription({ signedIn, organizationName: props.organizationName, offerCloudSignIn: shellConfig.cloudSignin })}</SettingsPanelDescription>
      </SettingsPanelHeading>
      <Button className="shrink-0 self-start" disabled={locked || props.busy || props.providerAuthBusy || !ready} onClick={() => void props.onOpenProviderAuth()}>
        {locked ? <Lock className="size-4" /> : <Plus className="size-4" />}Connect a provider
      </Button>
    </SettingsPanel>

    {locked ? <p className="-mt-3 text-xs text-muted-foreground">Provider connections are managed by your organization administrator.</p> : null}
    {error ? <SettingsNotice tone="error" className="flex flex-wrap items-center justify-between gap-3"><div role="alert"><p>{error}</p>{props.connectedProviders.length ? <p>{t("settings.providers_not_refreshed")}</p> : null}</div><Button variant="outline" disabled={props.busy || loading} aria-busy={loading} onClick={() => void props.onRetryProviders()}>{t("settings.providers_retry")}</Button></SettingsNotice> : null}

    <section className="flex flex-col gap-3" aria-labelledby="ai-providers-device">
      <div className="space-y-1">
        <div className="flex items-baseline justify-between gap-3">
          <h2 id="ai-providers-device" className="text-base font-medium text-dls-text">On this device</h2>
          {ready ? <span className="text-xs text-muted-foreground">{deviceCount === 1 ? "1 provider" : `${deviceCount} providers`}</span> : null}
        </div>
        <p className="text-sm text-muted-foreground">{signedIn ? "Signed in or pasted by you. Keys never leave this device and only you can disconnect them." : "Works without an account. Keys you paste never leave this device and only you can disconnect them."}</p>
      </div>

      {loading && !props.connectedProviders.length ? <div role="status" aria-label={t("settings.loading_providers")} className="grid gap-2">{[0, 1].map((key) => <div key={key} className="h-16 animate-pulse rounded-2xl bg-muted" />)}</div> : null}

      {showAuto || local.length || disabled.length ? <ProviderList>
        {showAuto ? <ProviderRow
          testId="settings-auto-provider"
          scope="device"
          tile={<ProviderTile providerId="idexalos" />}
          name="idexal Os Models"
          status={autoOff ? <ProviderStatus tone="neutral">Turned off</ProviderStatus>
            : props.autoError ? <ProviderStatus tone="error">Could not verify</ProviderStatus>
            : autoAvailable ? <ProviderStatus tone="ready">Ready to use</ProviderStatus>
            : <ProviderStatus tone="neutral">Unavailable</ProviderStatus>}
          meta={<ProviderMeta id="idexalos" parts={[autoProviderSubtitle()]} />}
          actions={props.onSetAutoEnabled ? <Button variant="ghost" disabled={props.autoBusy || (autoOff && !props.autoPreferences?.canEnable)} onClick={() => void props.onSetAutoEnabled?.(autoOff)}>{autoOff ? "Turn on" : "Turn off"}</Button> : null}
        /> : null}
        {local.map((provider) => <ProviderRow
          key={provider.id}
          scope="device"
          tile={<ProviderTile providerId={provider.id} name={provider.name} />}
          name={provider.name}
          status={<ProviderStatus tone="ready">Ready to use</ProviderStatus>}
          meta={<ProviderMeta id={provider.id} parts={[deviceSourceLabel(provider.source), props.organizationProviderIds?.has(provider.id) ? `also available from ${props.organizationName || "your organization"}` : null]} />}
          actions={<Button variant="ghost" disabled={rowsBusy || !ready || !props.canDisconnectProvider(provider)} onClick={() => void props.onDisconnectProvider(provider.id)}>{props.disconnectingProviderId === provider.id ? t("settings.disconnecting") : props.canDisconnectProvider(provider) ? t("settings.disconnect") : t("settings.managed_by_env")}</Button>}
        />)}
        {disabled.map((provider) => <ProviderRow
          key={`disabled:${provider.id}`}
          testId="disabled-provider"
          scope="device"
          tile={<ProviderTile providerId={provider.id} name={provider.name} />}
          name={provider.name}
          status={<ProviderStatus tone="neutral">{t("settings.provider_disabled_badge")}</ProviderStatus>}
          meta={<ProviderMeta id={provider.id} parts={[t("settings.provider_disabled_hint")]} />}
          actions={<Button variant="ghost" disabled={rowsBusy || (props.enablingProviderId ?? null) !== null} onClick={() => void props.onEnableProvider?.(provider.id)}>{props.enablingProviderId === provider.id ? t("settings.enabling_provider") : t("settings.enable_provider")}</Button>}
        />)}
      </ProviderList> : ready ? <p className="text-sm text-muted-foreground">{t("settings.no_providers_connected")}</p> : null}

      {props.autoError ? <SettingsNotice tone="error">{props.autoError}</SettingsNotice> : null}
      {props.autoPreferences && !props.autoPreferences.canEnable ? <p className="text-xs text-muted-foreground">Auto is unavailable on this device or blocked by your organization administrator.</p> : null}

      {props.planEnabled || props.planProviders?.length ? <SettingsPanel>
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="space-y-1">
            <h2 className="text-base font-medium text-dls-text">Included in your plan</h2>
            <p className="text-sm text-muted-foreground">
              {props.planName ? `Your ${props.planName} plan reaches these providers through the idexal Os Gateway.` : "Your plan reaches these providers through the idexal Os Gateway."}
            </p>
          </div>
          {props.onOpenDen ? <Button variant="outline" onClick={() => props.onOpenDen?.()}>Manage in idexal Os</Button> : null}
        </div>
        {props.planProviders?.length ? <div className="flex flex-wrap gap-2">
          {props.planProviders.map((provider) => (
            <span key={provider.slug} className="rounded-full border border-border bg-muted/40 px-3 py-1 text-xs text-foreground">{provider.name}</span>
          ))}
        </div> : <p className="text-sm text-muted-foreground">No providers are assigned to this plan yet. An administrator can assign them in idexal Os.</p>}
        {props.byokAllowed === false ? <p className="text-xs text-muted-foreground">Adding your own provider keys is turned off for this workspace, so models come from the plan above.</p> : null}
      </SettingsPanel> : null}
      {props.providerConnectError ? <SettingsNotice tone="error">{props.providerConnectError}</SettingsNotice> : null}
      {props.providerDisconnectStatus ? <SettingsNotice>{props.providerDisconnectStatus}</SettingsNotice> : null}
      {props.providerDisconnectError ? <SettingsNotice tone="error">{props.providerDisconnectError}</SettingsNotice> : null}
    </section>

    {props.cloudProvidersView ?? (organization.length || props.gatewayConnectProviders?.length ? <section className="flex flex-col gap-3">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-base font-medium text-dls-text">From {props.organizationName || "your organization"}</h2>
        {props.onOpenDen ? <Button variant="ghost" onClick={props.onOpenDen}>Open in Den</Button> : null}
      </div>
      <ProviderList>
        {organization.map((provider) => <ProviderRow key={provider.id} scope="organization" tile={<ProviderTile providerId={provider.id} name={provider.name} />} name={provider.name} status={<ProviderStatus tone="ready">Ready to use</ProviderStatus>} meta={<ProviderMeta id={provider.id} parts={["Managed in Den"]} />} />)}
        {props.gatewayConnectProviders?.map((provider) => <GatewayConnectRow key={gatewayConnectProviderKey(provider)} provider={provider} busy={props.connectingGatewayProviderId === gatewayConnectProviderKey(provider)} onConnect={props.onConnectGatewayProvider} onCancel={props.onCancelGatewayConnect} />)}
      </ProviderList>
    </section> : null)}
    <ModelFailoverSection options={props.fallbackModelOptions ?? []} />
  </LayoutStack>;
}

function ModelFailoverSection({ options }: { options: readonly ModelOption[] }) {
  const [chain, setChain] = useState<FallbackModelRef[]>(() => readModelFallbacks());
  const [selected, setSelected] = useState("");

  const available = useMemo(
    () => options.filter((option) => !option.disabled && !chain.some((entry) => modelRefKey(entry) === modelRefKey(option))),
    [chain, options],
  );

  useEffect(() => {
    if (selected && available.some((option) => modelRefKey(option) === selected)) return;
    setSelected(available[0] ? modelRefKey(available[0]) : "");
  }, [available, selected]);

  const labelFor = (model: FallbackModelRef): string => {
    const option = options.find((entry) => modelRefKey(entry) === modelRefKey(model));
    return option ? `${option.title} · ${option.providerID}` : modelRefKey(model);
  };

  const commit = (next: FallbackModelRef[]) => {
    setChain(writeModelFallbacks(next));
  };

  return <SettingsPanel>
    <SettingsPanelHeading className="max-w-xl">
      <SettingsPanelTitle>{t("providers.failover_title")}</SettingsPanelTitle>
      <SettingsPanelDescription>{t("providers.failover_description")}</SettingsPanelDescription>
    </SettingsPanelHeading>

    <div className="flex flex-col gap-2 sm:flex-row">
      <select
        aria-label={t("providers.failover_select")}
        className="input h-10 min-w-0 flex-1"
        value={selected}
        onChange={(event) => setSelected(event.target.value)}
        disabled={available.length === 0}
      >
        {available.length === 0
          ? <option value="">{t("providers.failover_no_models")}</option>
          : available.map((option) => <option key={modelRefKey(option)} value={modelRefKey(option)}>{option.title} · {option.providerID}</option>)}
      </select>
      <Button
        disabled={!selected}
        onClick={() => {
          const option = available.find((entry) => modelRefKey(entry) === selected);
          if (!option) return;
          commit([...chain, { providerID: option.providerID, modelID: option.modelID }]);
        }}
      >
        <Plus className="size-4" />{t("providers.failover_add")}
      </Button>
    </div>

    {chain.length === 0
      ? <p className="text-sm text-muted-foreground">{t("providers.failover_empty")}</p>
      : <ol className="flex flex-col gap-2">
          {chain.map((model, index) => <li key={modelRefKey(model)} className="flex items-center gap-3 rounded-2xl border border-border/70 px-3 py-2">
            <span className="w-5 shrink-0 text-center font-mono text-xs text-muted-foreground">{index + 1}</span>
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm">{labelFor(model)}</p>
              <p className="truncate font-mono text-[10px] text-muted-foreground">{modelRefKey(model)}</p>
            </div>
            <div className="flex shrink-0 items-center gap-1">
              <Button variant="ghost" size="icon-sm" aria-label={t("providers.failover_move_up")} disabled={index === 0} onClick={() => {
                const next = [...chain];
                [next[index - 1], next[index]] = [next[index], next[index - 1]];
                commit(next);
              }}><ChevronUp className="size-4" /></Button>
              <Button variant="ghost" size="icon-sm" aria-label={t("providers.failover_move_down")} disabled={index === chain.length - 1} onClick={() => {
                const next = [...chain];
                [next[index + 1], next[index]] = [next[index], next[index + 1]];
                commit(next);
              }}><ChevronDown className="size-4" /></Button>
              <Button variant="ghost" size="icon-sm" aria-label={t("providers.failover_remove", { model: labelFor(model) })} onClick={() => commit(chain.filter((entry) => modelRefKey(entry) !== modelRefKey(model)))}>
                <Trash2 className="size-4" />
              </Button>
            </div>
          </li>)}
        </ol>}
  </SettingsPanel>;
}
