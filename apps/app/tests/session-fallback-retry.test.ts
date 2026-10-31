import { describe, expect, test } from "bun:test";

import {
  clearFallbackRetryAttempts,
  retrySessionAfterProviderError,
} from "../src/react-app/domains/session/sync/session-fallback-retry";

const primary = { providerID: "custom-broken", modelID: "deepseek" };
const backup = { providerID: "custom-hub", modelID: "deepseek" };
const second = { providerID: "custom-dahl", modelID: "minimax" };
const providerAuth = { name: "ProviderAuthError", status: 401, type: "provider.auth" };
const unrelated = new Error("The prompt could not be prepared.");

function harness(sessionId: string, overrides: Record<string, unknown> = {}) {
  const calls: string[] = [];
  const sent: Array<{ providerID: string; text: string }> = [];
  const finalErrors: unknown[] = [];
  return {
    calls,
    sent,
    finalErrors,
    input: {
      sessionId,
      error: providerAuth,
      currentModel: primary,
      fallbacks: [backup, second],
      transcriptUser: { id: "msg_transcript", text: "Reply with exactly: OK" },
      loadEngineUser: async () => {
        calls.push("load");
        return { id: "msg_engine", text: "Reply with exactly: OK" };
      },
      revert: async (messageID: string) => {
        calls.push(`revert:${messageID}`);
      },
      send: async (model: { providerID: string; modelID: string }, text: string) => {
        calls.push(`send:${model.providerID}`);
        sent.push({ providerID: model.providerID, text });
      },
      onFinalError: (error: unknown) => {
        finalErrors.push(error);
      },
      ...overrides,
    },
  };
}

describe("post-admission provider fallback", () => {
  test("reverts the failed turn and resends it on the first backup", async () => {
    const sessionId = "ses_retry_first";
    clearFallbackRetryAttempts(sessionId);
    const h = harness(sessionId);

    const started = await retrySessionAfterProviderError(h.input);

    expect(started).toBe(true);
    expect(h.calls).toEqual(["load", "revert:msg_engine", "send:custom-hub"]);
    expect(h.sent[0]).toEqual({ providerID: "custom-hub", text: "Reply with exactly: OK" });
    expect(h.finalErrors).toHaveLength(0);
  });

  test("does not retry an unrelated error", async () => {
    const sessionId = "ses_retry_unrelated";
    clearFallbackRetryAttempts(sessionId);
    const h = harness(sessionId, { error: unrelated });

    expect(await retrySessionAfterProviderError(h.input)).toBe(false);
    expect(h.calls).toEqual([]);
    expect(h.finalErrors).toEqual([unrelated]);
  });

  test("falls back to the transcript user when the engine read fails", async () => {
    const sessionId = "ses_retry_transcript";
    clearFallbackRetryAttempts(sessionId);
    const h = harness(sessionId, {
      loadEngineUser: async () => {
        throw new Error("offline");
      },
    });

    expect(await retrySessionAfterProviderError(h.input)).toBe(true);
    expect(h.calls).toContain("revert:msg_transcript");
  });

  test("walks the next backup when admission on the first one fails", async () => {
    const sessionId = "ses_retry_admission";
    clearFallbackRetryAttempts(sessionId);
    const attempts: string[] = [];
    const h = harness(sessionId, {
      send: async (model: { providerID: string }) => {
        attempts.push(model.providerID);
        if (model.providerID === "custom-hub") throw providerAuth;
      },
    });

    expect(await retrySessionAfterProviderError(h.input)).toBe(true);
    expect(attempts).toEqual(["custom-hub", "custom-dahl"]);
  });

  test("stops at the first non-failover admission error", async () => {
    const sessionId = "ses_retry_hard_fail";
    clearFallbackRetryAttempts(sessionId);
    const h = harness(sessionId, {
      send: async () => {
        throw unrelated;
      },
    });

    expect(await retrySessionAfterProviderError(h.input)).toBe(false);
    expect(h.finalErrors).toEqual([unrelated]);
    expect(h.sent).toHaveLength(0);
  });

  test("a revert failure preserves the original error", async () => {
    const sessionId = "ses_retry_revert_fail";
    clearFallbackRetryAttempts(sessionId);
    const h = harness(sessionId, {
      revert: async () => {
        throw new Error("revert unavailable");
      },
    });

    expect(await retrySessionAfterProviderError(h.input)).toBe(false);
    expect(h.sent).toHaveLength(0);
    expect(h.finalErrors).toHaveLength(1);
  });

  test("never reuses a model in the same chain", async () => {
    const sessionId = "ses_retry_chain";
    clearFallbackRetryAttempts(sessionId);

    const first = harness(sessionId);
    expect(await retrySessionAfterProviderError(first.input)).toBe(true);
    expect(first.sent.map((entry) => entry.providerID)).toEqual(["custom-hub"]);

    const secondAttempt = harness(sessionId, { currentModel: backup });
    expect(await retrySessionAfterProviderError(secondAttempt.input)).toBe(true);
    expect(secondAttempt.sent.map((entry) => entry.providerID)).toEqual(["custom-dahl"]);

    const exhausted = harness(sessionId, { currentModel: second });
    expect(await retrySessionAfterProviderError(exhausted.input)).toBe(false);
    expect(exhausted.sent).toHaveLength(0);

    clearFallbackRetryAttempts(sessionId);
    const reset = harness(sessionId);
    expect(await retrySessionAfterProviderError(reset.input)).toBe(true);
  });

  test("cancellation does not resend", async () => {
    const sessionId = "ses_retry_cancel";
    clearFallbackRetryAttempts(sessionId);
    const h = harness(sessionId, { isCancelled: () => true });

    expect(await retrySessionAfterProviderError(h.input)).toBe(false);
    expect(h.sent).toHaveLength(0);
    expect(h.finalErrors).toEqual([providerAuth]);
  });
});
