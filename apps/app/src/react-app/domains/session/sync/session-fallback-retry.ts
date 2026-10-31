import { modelRefKey, type FallbackModelRef } from "./model-fallback";
import { isModelFailoverRejection } from "./model-unavailable-retry";

export type FallbackRetryUser = {
  id: string;
  text: string;
};

type AttemptState = {
  keys: Set<string>;
  at: number;
};

/**
 * Provider failures happen after admission, so the send-time chain never sees
 * them. Reverting the failed turn before resending keeps one user turn in the
 * transcript and lets the next model answer as if it had been chosen first.
 *
 * Attempts are session-scoped so a chain cannot ping-pong between the same
 * models; the TTL is a backstop in case a completion never arrives.
 */
const ATTEMPT_TTL_MS = 2 * 60_000;
const attempts = new Map<string, AttemptState>();

function state(sessionId: string): AttemptState {
  const now = Date.now();
  const existing = attempts.get(sessionId);
  if (existing && now - existing.at < ATTEMPT_TTL_MS) return existing;
  const next: AttemptState = { keys: new Set<string>(), at: now };
  attempts.set(sessionId, next);
  return next;
}

export function clearFallbackRetryAttempts(sessionId: string): void {
  attempts.delete(sessionId);
}

type RetryInput = {
  sessionId: string;
  error: unknown;
  currentModel: FallbackModelRef | null;
  fallbacks: readonly FallbackModelRef[];
  transcriptUser: FallbackRetryUser | null;
  loadEngineUser: () => Promise<FallbackRetryUser | null>;
  revert: (messageID: string) => Promise<void>;
  send: (model: FallbackModelRef, text: string) => Promise<void>;
  onFinalError: (error: unknown) => void;
  isCancelled?: () => boolean;
};

/**
 * Returns true when a fallback prompt was admitted. Provider failures after
 * that admission arrive as another session error and walk the next candidate.
 */
export async function retrySessionAfterProviderError(input: RetryInput): Promise<boolean> {
  if (!isModelFailoverRejection(input.error) || input.isCancelled?.()) {
    input.onFinalError(input.error);
    return false;
  }

  const attempt = state(input.sessionId);
  const currentKey = modelRefKey(input.currentModel);
  if (currentKey) attempt.keys.add(currentKey);

  const candidates = input.fallbacks.filter((model) => {
    const key = modelRefKey(model);
    return key.length > 0 && key !== currentKey && !attempt.keys.has(key);
  });

  if (candidates.length === 0) {
    input.onFinalError(input.error);
    return false;
  }

  const engineUser = await input.loadEngineUser().catch(() => null);
  const user = engineUser ?? input.transcriptUser;

  if (!user || !user.text.trim()) {
    input.onFinalError(input.error);
    return false;
  }

  try {
    await input.revert(user.id);
  } catch (error) {
    input.onFinalError(error);
    return false;
  }

  let lastError = input.error;

  for (const candidate of candidates) {
    if (input.isCancelled?.()) break;

    attempt.keys.add(modelRefKey(candidate));

    try {
      await input.send(candidate, user.text);
      return true;
    } catch (error) {
      lastError = error;

      if (!isModelFailoverRejection(error)) break;
    }
  }

  input.onFinalError(lastError);
  return false;
}
