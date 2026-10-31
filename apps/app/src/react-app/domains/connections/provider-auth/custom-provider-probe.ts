import { isDesktopRuntime } from "@/app/lib/runtime-env";
import { desktopFetchViaMain } from "@/app/lib/desktop";
import {
  parseProviderModelList,
  providerErrorMessage,
  type CustomProviderFamily,
} from "./custom-provider-config";

/**
 * Live checks for person-added custom providers: list the models an endpoint
 * serves (which also validates the key) and send one minimal completion to
 * prove a model answers. Desktop routes the request through the Electron main
 * process, so provider CORS policies never block the check.
 */

export type CustomProviderProbeInput = {
  family: CustomProviderFamily;
  baseUrl: string;
  apiKey?: string;
};

export type CustomProviderProbeFailure = {
  ok: false;
  /** HTTP status when the endpoint answered, `null` for network/timeout errors. */
  status: number | null;
  message: string;
};

export type CustomProviderModelsResult =
  | { ok: true; models: string[] }
  | CustomProviderProbeFailure;

export type CustomProviderModelTestResult = { ok: true } | CustomProviderProbeFailure;

const MODELS_TIMEOUT_MS = 15_000;
const COMPLETION_TIMEOUT_MS = 30_000;

function trimmedBaseUrl(baseUrl: string): string {
  return baseUrl.trim().replace(/\/+$/, "");
}

function probeHeaders(family: CustomProviderFamily, apiKey?: string): Record<string, string> {
  const headers: Record<string, string> = { accept: "application/json" };
  const key = apiKey?.trim();
  if (family === "anthropic") {
    headers["anthropic-version"] = "2023-06-01";
    if (key) headers["x-api-key"] = key;
  } else if (key) {
    headers.authorization = `Bearer ${key}`;
  }
  return headers;
}

async function probeFetch(url: string, init: RequestInit, timeoutMs: number): Promise<Response> {
  if (isDesktopRuntime()) return desktopFetchViaMain(url, init, timeoutMs);
  // Web builds have no main process; a provider that refuses browser origins
  // reports a network error, which the caller surfaces as the probe failure.
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    return await globalThis.fetch(url, { ...init, signal: controller.signal });
  } finally {
    clearTimeout(timer);
  }
}

function parseJson(text: string): unknown {
  try {
    return JSON.parse(text);
  } catch {
    return null;
  }
}

async function failureFromResponse(response: Response): Promise<CustomProviderProbeFailure> {
  const text = await response.text().catch(() => "");
  const fallback = response.statusText?.trim() || `HTTP ${response.status}`;
  return {
    ok: false,
    status: response.status,
    message: providerErrorMessage(parseJson(text), fallback),
  };
}

function failureFromError(error: unknown): CustomProviderProbeFailure {
  const message = error instanceof Error
    ? (error.name === "AbortError" ? "The endpoint did not answer in time." : error.message)
    : "Could not reach the endpoint.";
  return { ok: false, status: null, message };
}

/** `GET {baseUrl}/models` with the provider's key; the list is the key check. */
export async function probeCustomProviderModels(
  input: CustomProviderProbeInput,
): Promise<CustomProviderModelsResult> {
  const url = `${trimmedBaseUrl(input.baseUrl)}/models`;
  let response: Response;
  try {
    response = await probeFetch(url, {
      method: "GET",
      headers: probeHeaders(input.family, input.apiKey),
    }, MODELS_TIMEOUT_MS);
  } catch (error) {
    return failureFromError(error);
  }
  if (!response.ok) return failureFromResponse(response);
  const payload = parseJson(await response.text().catch(() => ""));
  return { ok: true, models: parseProviderModelList(payload) };
}

/** One minimal completion on `model`, for "does this model actually answer". */
export async function testCustomProviderModel(
  input: CustomProviderProbeInput & { model: string },
): Promise<CustomProviderModelTestResult> {
  const base = trimmedBaseUrl(input.baseUrl);
  const anthropic = input.family === "anthropic";
  const url = anthropic ? `${base}/messages` : `${base}/chat/completions`;
  const body = anthropic
    ? { model: input.model, max_tokens: 8, messages: [{ role: "user", content: "ping" }] }
    : { model: input.model, max_tokens: 8, stream: false, messages: [{ role: "user", content: "ping" }] };
  let response: Response;
  try {
    response = await probeFetch(url, {
      method: "POST",
      headers: { ...probeHeaders(input.family, input.apiKey), "content-type": "application/json" },
      body: JSON.stringify(body),
    }, COMPLETION_TIMEOUT_MS);
  } catch (error) {
    return failureFromError(error);
  }
  if (!response.ok) return failureFromResponse(response);
  return { ok: true };
}
