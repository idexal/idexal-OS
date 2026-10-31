import { applyEdits, modify, parse } from "jsonc-parser";
import type { ProviderConfig } from "@opencode-ai/sdk/v2/client";

/**
 * Pure helpers for person-added custom providers: an OpenAI- or
 * Anthropic-compatible endpoint with a base URL, an optional key, and the
 * model IDs the endpoint serves. Extracted from the provider-auth store so
 * id generation, model parsing, and the `opencode.jsonc` fallback can be
 * unit tested directly.
 */

export type CustomProviderFamily = "openai" | "anthropic";

export type CustomProviderInput = {
  family: CustomProviderFamily;
  name: string;
  baseUrl: string;
  apiKey?: string;
  /** One or more model IDs, separated by newlines or commas. */
  models: string[] | string;
};

export const CUSTOM_PROVIDER_ID_PREFIX = "custom-";

export const CUSTOM_PROVIDER_NPM: Record<CustomProviderFamily, string> = {
  openai: "@ai-sdk/openai-compatible",
  anthropic: "@ai-sdk/anthropic",
};

export const CUSTOM_PROVIDER_FAMILY_LABELS: Record<CustomProviderFamily, string> = {
  openai: "OpenAI-compatible",
  anthropic: "Anthropic-compatible",
};

const slugifyProviderName = (value: string) =>
  value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");

/**
 * A stable, unique provider id for a person-named provider. Taken ids include
 * disabled providers: reusing one would silently inherit `disabled_providers`
 * and hide the new provider from every list.
 */
export function customProviderIdForName(
  name: string,
  takenIds: Iterable<string>,
): string {
  const slug = slugifyProviderName(name) || "provider";
  const taken = new Set(
    [...takenIds].flatMap((id) => {
      const normalized = id.trim().toLowerCase();
      return normalized ? [normalized] : [];
    }),
  );
  const base = `${CUSTOM_PROVIDER_ID_PREFIX}${slug}`;
  let candidate = base;
  for (let index = 2; taken.has(candidate); index += 1) {
    candidate = `${base}-${index}`;
  }
  return candidate;
}

/** Model IDs from free text: newline- or comma-separated, trimmed, de-duped. */
export function parseCustomProviderModelIds(value: string | string[]): string[] {
  const raw = Array.isArray(value) ? value : value.split(/[\n,]+/);
  const seen = new Set<string>();
  const ids: string[] = [];
  for (const entry of raw) {
    const id = entry.trim();
    if (!id || seen.has(id)) continue;
    seen.add(id);
    ids.push(id);
  }
  return ids;
}

/** `""` when the value is an http(s) URL, otherwise the reason it is not. */
export function customProviderBaseUrlError(value: string): "required" | "invalid" | "" {
  const trimmed = value.trim();
  if (!trimmed) return "required";
  let parsed: URL;
  try {
    parsed = new URL(trimmed);
  } catch {
    return "invalid";
  }
  if (parsed.protocol !== "http:" && parsed.protocol !== "https:") return "invalid";
  return "";
}

/**
 * Model IDs from a provider's model-list response (for example
 * `GET {baseUrl}/models`). OpenAI and Anthropic both answer `{ data: [{ id }] }`;
 * some compatible servers answer `{ models: [{ name }] }` or a bare array.
 */
export function parseProviderModelList(payload: unknown): string[] {
  const pick = (entry: unknown): string | null => {
    if (typeof entry === "string") return entry.trim() || null;
    if (isRecord(entry)) {
      for (const field of ["id", "name", "model"]) {
        const value = entry[field];
        if (typeof value === "string" && value.trim()) return value.trim();
      }
    }
    return null;
  };
  const lists: unknown[][] = [];
  if (Array.isArray(payload)) {
    lists.push(payload);
  } else if (isRecord(payload)) {
    if (Array.isArray(payload.data)) lists.push(payload.data);
    if (Array.isArray(payload.models)) lists.push(payload.models);
  }
  const ids: string[] = [];
  const seen = new Set<string>();
  for (const list of lists) {
    for (const entry of list) {
      const id = pick(entry);
      if (id && !seen.has(id)) {
        seen.add(id);
        ids.push(id);
      }
    }
  }
  return ids;
}

/**
 * Human-readable failure text from an endpoint's JSON error body, falling back
 * to the caller's (usually the HTTP status) when the body carries no message.
 */
export function providerErrorMessage(payload: unknown, fallback: string): string {
  if (typeof payload === "string" && payload.trim()) return payload.trim();
  if (isRecord(payload)) {
    if (typeof payload.error === "string" && payload.error.trim()) return payload.error.trim();
    if (isRecord(payload.error) && typeof payload.error.message === "string" && payload.error.message.trim()) {
      return payload.error.message.trim();
    }
    if (typeof payload.message === "string" && payload.message.trim()) return payload.message.trim();
  }
  return fallback;
}

export function buildCustomProviderConfig(input: {
  family: CustomProviderFamily;
  name: string;
  baseUrl: string;
  models: string[];
}): ProviderConfig {
  const models: NonNullable<ProviderConfig["models"]> = {};
  for (const id of input.models) {
    models[id] = { name: id };
  }
  return {
    npm: CUSTOM_PROVIDER_NPM[input.family],
    name: input.name.trim(),
    options: { baseURL: input.baseUrl.trim() },
    models,
  };
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null && !Array.isArray(value);

/**
 * Fallback for workspaces whose runtime config API is unavailable: upsert the
 * provider block into the person's `opencode.jsonc`, preserving their
 * comments, and drop the id from `disabled_providers` so it is visible.
 */
export function formatConfigWithCustomProvider(
  raw: string,
  providerId: string,
  config: ProviderConfig,
): string {
  let updated = raw.trim()
    ? raw
    : '{\n  "$schema": "https://opencode.ai/config.json"\n}\n';
  updated = applyEdits(
    updated,
    modify(updated, ["provider", providerId], config, {
      formattingOptions: { insertSpaces: true, tabSize: 2 },
    }),
  );

  const parsed: unknown = parse(updated);
  const disabledValue = isRecord(parsed) ? parsed.disabled_providers : undefined;
  if (Array.isArray(disabledValue)) {
    const nextDisabled = disabledValue.filter(
      (entry): entry is string => typeof entry === "string" && entry !== providerId,
    );
    if (nextDisabled.length !== disabledValue.length) {
      updated = applyEdits(
        updated,
        modify(updated, ["disabled_providers"], nextDisabled, {
          formattingOptions: { insertSpaces: true, tabSize: 2 },
        }),
      );
    }
  }

  return updated.endsWith("\n") ? updated : `${updated}\n`;
}

/**
 * Disconnect's project-config half: delete the provider block and unhide the id,
 * preserving the person's comments. Removing only the stored key would leave a
 * provider definition behind that every list still shows.
 */
export function formatConfigWithoutCustomProvider(
  raw: string,
  providerId: string,
  disabledProviders: string[] = [],
): string {
  let updated = raw.trim()
    ? raw
    : '{\n  "$schema": "https://opencode.ai/config.json"\n}\n';
  updated = applyEdits(
    updated,
    modify(updated, ["provider", providerId], undefined, {
      formattingOptions: { insertSpaces: true, tabSize: 2 },
    }),
  );

  if (disabledProviders.includes(providerId)) {
    updated = applyEdits(
      updated,
      modify(updated, ["disabled_providers"], disabledProviders.filter((id) => id !== providerId), {
        formattingOptions: { insertSpaces: true, tabSize: 2 },
      }),
    );
  }

  return updated.endsWith("\n") ? updated : `${updated}\n`;
}
