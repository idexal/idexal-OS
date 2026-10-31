import { isPromptAdmissionUnknown } from "../../../../app/lib/opencode";
import { fallbackCandidates, modelRefKey, type FallbackModelRef } from "./model-fallback";

const modelUnavailableNames = new Set([
  "ModelUnavailable",
  "ModelUnavailableError",
  "ProviderModelNotFound",
  "ProviderModelNotFoundError",
]);

const modelFailoverNames = new Set([
  ...modelUnavailableNames,
  "ModelDeprecated",
  "ModelDeprecatedError",
  "ProviderAuthError",
  "ProviderError",
  "ProviderUnavailableError",
  "ProviderRateLimitError",
  "RateLimitError",
  "OverloadedError",
]);

const failoverMessagePattern =
  /model[^.]{0,40}(deprecated|not found|unavailable|unsupported|retired|disabled|overloaded|rate.?limit|quota)|no provider|provider[^.]{0,40}(unavailable|failed|error|rate.?limit|overload)|insufficient[ _](quota|balance|credit|funds)|balance[^.]{0,30}(\$0|zero)|too many requests|unauthorized|authentication (required|failed)|invalid[ _-]?api[ _-]?key|api[ _-]?key[^.]{0,40}(required|invalid|missing|rejected|expired)|access denied|status(?:Code)?["'=:\s]+(401|402|403|404|408|409|410|429|5\d\d)\b/i;

// Nested messages on generic engine errors are weak evidence: only auth,
// quota, rate-limit, and provider-outage wording may trigger failover there.
const nestedFailoverMessagePattern =
  /insufficient[ _](quota|balance|credit|funds)|balance[^.]{0,30}(\$0|zero)|too many requests|rate.?limit|unauthorized|authentication (required|failed)|invalid[ _-]?api[ _-]?key|api[ _-]?key[^.]{0,40}(required|invalid|missing|rejected|expired)|access denied|status(?:Code)?["'=:\s]+(401|402|403|408|409|410|429|5\d\d)/i;

/** Only explicit engine rejections are safe to replay, never transport failures. */
export function isModelUnavailableRejection(error: unknown): boolean {
  if (isPromptAdmissionUnknown(error)) return false;
  if (typeof error === "string") {
    try {
      return isModelUnavailableRejection(JSON.parse(error));
    } catch {
      return false;
    }
  }
  if (typeof error !== "object" || error === null) return false;
  const name = Reflect.get(error, "name") ?? Reflect.get(error, "_tag");
  if (typeof name === "string" && modelUnavailableNames.has(name)) return true;
  // Some SDK paths throw Error(JSON.stringify(engineError)). Do not search
  // arbitrary messages/causes: an admission-unknown failure may mention a model.
  return error instanceof Error && isModelUnavailableRejection(error.message);
}

/**
 * A failure that another provider/model can plausibly survive: deprecated or
 * missing model, provider outage, auth/quota/rate limit. Transport-level
 * failures stay out because the prompt may already have been admitted.
 */
export function isModelFailoverRejection(error: unknown): boolean {
  if (isModelUnavailableRejection(error)) return true;
  if (isPromptAdmissionUnknown(error)) return false;
  if (typeof error === "string") {
    try {
      return isModelFailoverRejection(JSON.parse(error));
    } catch {
      return /model.*(deprecated|not found|unavailable)|too many requests|quota|status\s*(401|402|403|404|408|409|410|429|5\d\d)/i.test(error);
    }
  }
  if (typeof error !== "object" || error === null) return false;

  const name = Reflect.get(error, "name") ?? Reflect.get(error, "_tag");
  if (typeof name === "string" && modelFailoverNames.has(name)) return true;
  const status = Number(Reflect.get(error, "status") ?? Reflect.get(error, "statusCode") ?? 0);
  if ([401, 402, 403, 404, 408, 409, 410, 429].includes(status) || status >= 500) return true;

  const type = Reflect.get(error, "type");
  if (typeof type === "string" && /model|provider/i.test(type) && /deprecat|unavailable|not.?found|error|auth/i.test(type)) {
    return true;
  }

  // Engine SSE errors arrive as { name: "UnknownError", data: { message } }
  // with the provider's HTTP status stripped, so the message is the only
  // evidence of an auth/quota/provider failure.
  const nestedMessage = typeof Reflect.get(error, "message") === "string"
    ? Reflect.get(error, "message")
    : (() => {
        const data = Reflect.get(error, "data");
        return data && typeof data === "object" && typeof Reflect.get(data, "message") === "string"
          ? Reflect.get(data, "message")
          : "";
      })();
  if (typeof nestedMessage === "string" && nestedMessage.trim() && nestedFailoverMessagePattern.test(nestedMessage)) {
    return true;
  }

  if (error instanceof Error) {
    // Nested JSON engine payloads are common; recurse so a ModelDeprecated body
    // is recognized without ever scanning unrelated cause chains.
    try {
      const parsed: unknown = JSON.parse(error.message);
      if (parsed !== error && isModelFailoverRejection(parsed)) return true;
    } catch {
      // Not JSON.
    }
    return failoverMessagePattern.test(error.message);
  }
  return false;
}

/**
 * Retry one rejected admission after refreshing the engine's model catalog,
 * then walk the user's ordered fallback chain. The exact request (especially
 * messageID) is preserved and the caller's existing send claim is reused, so
 * recovery cannot produce a second user turn.
 */
export async function sendWithModelUnavailableRetry<
  Request extends { model?: FallbackModelRef | null },
  Result extends { error?: unknown },
>(input: {
  request: Request;
  send: (request: Request) => Promise<Result>;
  refreshModels: () => Promise<unknown>;
  withModel?: (request: Request, model: FallbackModelRef) => Request;
  fallbacks?: readonly FallbackModelRef[];
  assertCurrent?: () => void;
}): Promise<Result> {
  input.assertCurrent?.();
  let rejection: unknown;
  let rejectedResult: Result | undefined;
  try {
    const result = await input.send(input.request);
    if (!isModelFailoverRejection(result.error)) return result;
    rejection = result.error;
    rejectedResult = result;
  } catch (error) {
    if (!isModelFailoverRejection(error)) throw error;
    rejection = error;
  }

  input.assertCurrent?.();
  try {
    await input.refreshModels();
  } catch {
    // Preserve the actionable engine rejection instead of replacing it with a
    // catalog/network failure. Without a refresh, do not replay the request.
    input.assertCurrent?.();
    if (rejectedResult) return rejectedResult;
    throw rejection;
  }
  input.assertCurrent?.();

  // The catalog refresh often revives a model that was reported missing.
  let current = await input.send(input.request);
  if (!isModelFailoverRejection(current.error)) return current;
  rejection = current.error;

  const fallbacks = input.fallbacks ?? fallbackCandidates(input.request.model);
  for (const fallback of fallbacks) {
    if (!input.withModel) break;
    input.assertCurrent?.();
    const nextRequest = input.withModel(input.request, fallback);
    try {
      current = await input.send(nextRequest);
    } catch (error) {
      if (!isModelFailoverRejection(error)) throw error;
      rejection = error;
      continue;
    }
    if (!isModelFailoverRejection(current.error)) return current;
    rejection = current.error;
  }

  // Every candidate failed. Return the last engine rejection instead of the
  // first, so the UI names the provider that was actually tried last.
  if (current) return current;
  throw rejection;
}

export function fallbackLabel(model: FallbackModelRef): string {
  return modelRefKey(model);
}