
import {
  buildDenAuthUrl,
  getDenInferenceUrl,
  isSelfHostedControlPlane,
  HOSTED_DEFAULT_DEN_BASE_URL,
  readDenBootstrapConfig,
  readDenSettings,
} from "../../../app/lib/den";
import { isDefaultControlPlaneUrl } from "../settings/cloud/control-plane-url";
import { denSettingsChangedEvent } from "../../../app/lib/den-session-events";
import { useSyncExternalStore } from "react";

export const IDEXAL_MODELS_PROVIDER_ID = "idexalos";
export const IDEXAL_MODELS_PROMO_HIDDEN_KEY = "idexal-os.idexalOsModelsPromo.hidden";
export const IDEXAL_MODELS_PROMO_LAST_SHOWN_KEY = "idexal-os.idexalOsModelsPromo.lastShownAt";
export const IDEXAL_MODELS_STARTUP_PROMO_SHOWN_KEY = "idexal-os.idexalOsModelsPromo.startupShown";
export const idexalOsModelsPromoChangedEvent = "idexal-os-idexal-os-models-promo-changed";
export const IDEXAL_MODELS_PROMO_REPEAT_MS = 6 * 60 * 60 * 1000;

export function areIdexalOsModelsPromosDisabled() {
  if (/^(1|true|yes|on)$/i.test(String(import.meta.env.VITE_DISABLE_IDEXAL_MODELS ?? "").trim())) {
    return true;
  }
  // idexal Os Models are a hosted idexal Os Cloud offering; self-hosted
  // deployments should never see the upsell surfaces.
  return isSelfHostedControlPlane();
}

export function isIdexalOsModelsPromoEligibleForDenBaseUrl(baseUrl: string) {
  return !areIdexalOsModelsPromosDisabled() && isDefaultControlPlaneUrl(baseUrl, HOSTED_DEFAULT_DEN_BASE_URL);
}

export function isIdexalOsModelsPromoEligible() {
  return isIdexalOsModelsPromoEligibleForDenBaseUrl(readDenSettings().baseUrl);
}

export function useIdexalOsModelsPromoEligibility() {
  return useSyncExternalStore(
    (notify) => {
      if (typeof window === "undefined") return () => undefined;
      window.addEventListener(denSettingsChangedEvent, notify);
      return () => window.removeEventListener(denSettingsChangedEvent, notify);
    },
    isIdexalOsModelsPromoEligible,
    isIdexalOsModelsPromoEligible,
  );
}

export type IdexalOsModelPreview = {
  id: string;
  title: string;
  subtitle: string;
};

export function hasIdexalOsModelsProvider(providerIds: readonly string[]) {
  return providerIds.some((id) => id.trim().toLowerCase() === IDEXAL_MODELS_PROVIDER_ID);
}

/** Local engine has idexal Os Models connected with at least one selectable model. */
export function hasIdexalOsModelsAvailable(input: {
  providerConnectedIds: readonly string[];
  providers: ReadonlyArray<{ id: string; models?: Record<string, unknown> | null }>;
}) {
  if (!hasIdexalOsModelsProvider(input.providerConnectedIds)) return false;
  const idexalos = input.providers.find(
    (provider) => provider.id.trim().toLowerCase() === IDEXAL_MODELS_PROVIDER_ID,
  );
  return Object.keys(idexalos?.models ?? {}).length > 0;
}

export function shouldShowIdexalOsModelsSyncing(input: {
  entitled: boolean;
  available: boolean;
  workspaceReady: boolean;
  reloadPending: boolean;
}) {
  return input.entitled && !input.available && input.workspaceReady && input.reloadPending;
}

export function getIdexalOsModelsActionUrl(
  isSignedIn: boolean,
  authMode: "sign-in" | "sign-up" = "sign-in",
) {
  const settings = readDenSettings();
  const baseUrl = settings.baseUrl || readDenBootstrapConfig().baseUrl;
  // Signed-in users go straight to the idexal Os Models page — the value-prop
  // + subscribe surface — never to a bare auth or billing page.
  return isSignedIn ? getDenInferenceUrl(baseUrl) : buildDenAuthUrl(baseUrl, authMode);
}

export function isIdexalOsModelsPromoHidden() {
  if (areIdexalOsModelsPromosDisabled()) return true;
  if (typeof window === "undefined") return false;
  try {
    return window.localStorage.getItem(IDEXAL_MODELS_PROMO_HIDDEN_KEY) === "1";
  } catch {
    return false;
  }
}

export function hideIdexalOsModelsPromo() {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.setItem(IDEXAL_MODELS_PROMO_HIDDEN_KEY, "1");
    window.dispatchEvent(new Event(idexalOsModelsPromoChangedEvent));
  } catch {}
}

export function wasIdexalOsModelsStartupPromoShown() {
  if (!isIdexalOsModelsPromoEligible()) return true;
  if (typeof window === "undefined") return true;
  try {
    return window.localStorage.getItem(IDEXAL_MODELS_STARTUP_PROMO_SHOWN_KEY) === "1";
  } catch {
    return true;
  }
}

export function markIdexalOsModelsStartupPromoShown() {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.setItem(IDEXAL_MODELS_STARTUP_PROMO_SHOWN_KEY, "1");
  } catch {}
}

export function shouldShowIdexalOsModelsPromo(now = Date.now()) {
  if (!isIdexalOsModelsPromoEligible() || typeof window === "undefined" || isIdexalOsModelsPromoHidden()) return false;
  try {
    const lastShown = Number(window.localStorage.getItem(IDEXAL_MODELS_PROMO_LAST_SHOWN_KEY) ?? "0");
    return !Number.isFinite(lastShown) || now - lastShown >= IDEXAL_MODELS_PROMO_REPEAT_MS;
  } catch {
    return true;
  }
}
