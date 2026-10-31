export type FallbackModelRef = {
  providerID: string;
  modelID: string;
};

const STORAGE_KEY = "idexal.model-fallbacks.v1";
export const MODEL_FALLBACK_CHANGED_EVENT = "idexal:model-fallbacks-changed";

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function normalizeRef(value: unknown): FallbackModelRef | null {
  if (!isRecord(value)) return null;
  const providerID = typeof value.providerID === "string" ? value.providerID.trim() : "";
  const modelID = typeof value.modelID === "string" ? value.modelID.trim() : "";
  if (!providerID || !modelID) return null;
  return { providerID, modelID };
}

export function modelRefKey(model: FallbackModelRef | null | undefined): string {
  if (!model) return "";
  return `${model.providerID}/${model.modelID}`;
}

/** The saved chain is user-ordered and intentionally has no length cap. */
export function readModelFallbacks(): FallbackModelRef[] {
  if (typeof window === "undefined") return [];
  try {
    const parsed: unknown = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? "[]");
    if (!Array.isArray(parsed)) return [];
    const seen = new Set<string>();
    const result: FallbackModelRef[] = [];
    for (const entry of parsed) {
      const ref = normalizeRef(entry);
      if (!ref) continue;
      const key = modelRefKey(ref);
      if (seen.has(key)) continue;
      seen.add(key);
      result.push(ref);
    }
    return result;
  } catch {
    return [];
  }
}

export function writeModelFallbacks(models: readonly FallbackModelRef[]): FallbackModelRef[] {
  const seen = new Set<string>();
  const normalized: FallbackModelRef[] = [];
  for (const model of models) {
    const ref = normalizeRef(model);
    if (!ref) continue;
    const key = modelRefKey(ref);
    if (seen.has(key)) continue;
    seen.add(key);
    normalized.push(ref);
  }
  if (typeof window !== "undefined") {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(normalized));
      window.dispatchEvent(new CustomEvent(MODEL_FALLBACK_CHANGED_EVENT));
    } catch {
      // Storage can be unavailable in private windows; the in-memory caller
      // still gets the normalized list it just edited.
    }
  }
  return normalized;
}

/**
 * Fallbacks to try after the selected model fails, in order. The selected
 * model is skipped so a stale duplicate can never cause a repeated request.
 */
export function fallbackCandidates(primary: FallbackModelRef | null | undefined): FallbackModelRef[] {
  const primaryKey = modelRefKey(primary);
  return readModelFallbacks().filter((model) => modelRefKey(model) !== primaryKey);
}
