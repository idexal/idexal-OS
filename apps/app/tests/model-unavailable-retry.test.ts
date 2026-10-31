import { describe, expect, test } from "bun:test";

import { PromptAdmissionUnknownError } from "../src/app/lib/opencode";
import { isModelFailoverRejection, sendWithModelUnavailableRetry } from "../src/react-app/domains/session/sync/model-unavailable-retry";

const names = ["ModelUnavailable", "ModelUnavailableError", "ProviderModelNotFound", "ProviderModelNotFoundError"];
const request = { sessionID: "ses_retry", messageID: "msg_same_admission", parts: [{ type: "text", text: "hello" }] };

describe("send-time model unavailable recovery", () => {
  for (const name of names) {
    for (const shape of ["thrown", "sdk", "serialized"] ) {
      test(`${name} (${shape}) refreshes and retries the exact request once`, async () => {
        const calls: string[] = [];
        const requests: unknown[] = [];
        const rejection = { name, data: { providerID: "provider", modelID: "model" } };
        const accepted = { data: {}, error: undefined };
        const result = await sendWithModelUnavailableRetry({
          request,
          send: async (value) => {
            requests.push(value);
            calls.push("send");
            if (requests.length > 1) return accepted;
            if (shape === "sdk") return { error: rejection };
            if (shape === "serialized") throw new Error(JSON.stringify(rejection));
            throw Object.assign(new Error("Unavailable"), { name });
          },
          refreshModels: async () => { calls.push("refresh"); },
        });
        expect(result).toBe(accepted);
        expect(calls).toEqual(["send", "refresh", "send"]);
        expect(requests).toHaveLength(2);
        expect(requests[0]).toBe(request);
        expect(requests[1]).toBe(request);
      });
    }
  }

  test.each(["thrown", "sdk"])("a second rejection is final (%s)", async (shape) => {
    let sends = 0;
    let refreshes = 0;
    const error = { name: "ModelUnavailable" };
    const result = { error };
    const operation = sendWithModelUnavailableRetry({
      request,
      send: async () => {
        sends++;
        if (shape === "thrown") throw error;
        return result;
      },
      refreshModels: async () => { refreshes++; },
    });
    if (shape === "thrown") await expect(operation).rejects.toBe(error);
    else expect(await operation).toBe(result);
    expect(sends).toBe(2);
    expect(refreshes).toBe(1);
  });

  test.each(["thrown", "sdk"])("refresh failure preserves the original rejection without replay (%s)", async (shape) => {
    const error = { name: "ProviderModelNotFoundError" };
    const result = { error };
    let sends = 0;
    const operation = sendWithModelUnavailableRetry({
      request,
      send: async () => {
        sends++;
        if (shape === "thrown") throw error;
        return result;
      },
      refreshModels: async () => { throw new Error("Catalog offline"); },
    });
    if (shape === "thrown") await expect(operation).rejects.toBe(error);
    else expect(await operation).toBe(result);
    expect(sends).toBe(1);
  });

  for (const error of [
    { name: "APIError", message: "ProviderModelNotFoundError" },
    new TypeError("Failed to fetch"),
    new PromptAdmissionUnknownError({ messageID: request.messageID, cause: { name: "ModelUnavailable" } }),
  ]) {
    test.each(["thrown", "sdk"])(`does not replay unrelated/ambiguous ${error.name} (%s)`, async (shape) => {
      let sends = 0;
      let refreshes = 0;
      const result = { error };
      const operation = sendWithModelUnavailableRetry({
        request,
        send: async () => {
          sends++;
          if (shape === "thrown") throw error;
          return result;
        },
        refreshModels: async () => { refreshes++; },
      });
      if (shape === "thrown") await expect(operation).rejects.toBe(error);
      else expect(await operation).toBe(result);
      expect(sends).toBe(1);
      expect(refreshes).toBe(0);
    });
  }

  test("successful admission does not refresh or replay", async () => {
    let sends = 0;
    let refreshes = 0;
    const result = { data: {}, error: undefined };
    expect(await sendWithModelUnavailableRetry({
      request,
      send: async () => { sends++; return result; },
      refreshModels: async () => { refreshes++; },
    })).toBe(result);
    expect(sends).toBe(1);
    expect(refreshes).toBe(0);
  });

  test("Stop during catalog refresh cancels retry", async () => {
    let current = true;
    let sends = 0;
    await expect(sendWithModelUnavailableRetry({
      request,
      send: async () => { sends++; return { error: { name: "ModelUnavailable" } }; },
      refreshModels: async () => { current = false; },
      assertCurrent: () => { if (!current) throw new Error("Send cancelled by Stop."); },
    })).rejects.toThrow("Send cancelled by Stop.");
    expect(sends).toBe(1);
  });

  test("ambiguous retry admission is surfaced intact, never sent a third time", async () => {
    let sends = 0;
    const ambiguous = new PromptAdmissionUnknownError({ messageID: request.messageID });
    await expect(sendWithModelUnavailableRetry({
      request,
      send: async () => {
        sends++;
        if (sends === 1) throw { name: "ModelUnavailable" };
        throw ambiguous;
      },
      refreshModels: async () => {},
    })).rejects.toBe(ambiguous);
    expect(sends).toBe(2);
  });

  test("a deprecated model walks the ordered fallback chain", async () => {
    const models: string[] = [];
    const accepted = { data: {}, error: undefined };
    const result = await sendWithModelUnavailableRetry({
      request: { ...request, model: { providerID: "primary", modelID: "old" } },
      send: async (value) => {
        models.push(`${value.model?.providerID}/${value.model?.modelID}`);
        if (value.model?.providerID === "primary") {
          return { error: { name: "APIError", status: 410, message: "ModelDeprecated" } };
        }
        return accepted;
      },
      refreshModels: async () => {},
      withModel: (value, model) => ({ ...value, model }),
      fallbacks: [
        { providerID: "backup-1", modelID: "one" },
        { providerID: "backup-2", modelID: "two" },
      ],
    });

    expect(result).toBe(accepted);
    expect(models).toEqual(["primary/old", "primary/old", "backup-1/one"]);
  });

  test("the fallback chain has no fixed length", async () => {
    const models: string[] = [];
    const accepted = { data: {}, error: undefined };
    const fallbacks = Array.from({ length: 8 }, (_, index) => ({
      providerID: `backup-${index}`,
      modelID: "model",
    }));
    const result = await sendWithModelUnavailableRetry({
      request: { ...request, model: { providerID: "primary", modelID: "old" } },
      send: async (value) => {
        const key = `${value.model?.providerID}/${value.model?.modelID}`;
        models.push(key);
        return key === "backup-7/model"
          ? accepted
          : { error: { name: "ProviderUnavailableError" } };
      },
      refreshModels: async () => {},
      withModel: (value, model) => ({ ...value, model }),
      fallbacks,
    });

    expect(result).toBe(accepted);
    expect(models).toHaveLength(10);
    expect(models.at(-1)).toBe("backup-7/model");
  });

  test("a transport failure is never replayed on a backup model", async () => {
    let sends = 0;
    await expect(sendWithModelUnavailableRetry({
      request: { ...request, model: { providerID: "primary", modelID: "old" } },
      send: async () => {
        sends++;
        throw new TypeError("fetch failed");
      },
      refreshModels: async () => {},
      withModel: (value, model) => ({ ...value, model }),
      fallbacks: [{ providerID: "backup", modelID: "one" }],
    })).rejects.toThrow("fetch failed");
    expect(sends).toBe(1);
  });

  test("the last engine rejection is returned after every backup fails", async () => {
    const result = await sendWithModelUnavailableRetry({
      request: { ...request, model: { providerID: "primary", modelID: "old" } },
      send: async (value) => ({
        error: { name: "ModelDeprecated", provider: value.model?.providerID },
      }),
      refreshModels: async () => {},
      withModel: (value, model) => ({ ...value, model }),
      fallbacks: [{ providerID: "backup", modelID: "one" }],
    });

    expect(result.error).toMatchObject({ name: "ModelDeprecated", provider: "backup" });
  });
});
describe("provider error classification", () => {
  test("classifies an engine SSE auth error with no status", () => {
    expect(isModelFailoverRejection({
      name: "UnknownError",
      data: { message: "API key required for remote API access" },
    })).toBe(true);
  });

  test("classifies a zero balance as retryable on a backup", () => {
    expect(isModelFailoverRejection({
      name: "UnknownError",
      data: { message: "Your Token Harbor balance is at $0. Top up to keep using paid models." },
    })).toBe(true);
  });

  test("does not classify an ordinary prompt failure", () => {
    expect(isModelFailoverRejection(new Error("The prompt could not be prepared."))).toBe(false);
    expect(isModelFailoverRejection({ name: "UnknownError", data: { message: "Invalid request: messages are required" } })).toBe(false);
  });

  test("keeps ambiguous admissions out", () => {
    expect(isModelFailoverRejection(new PromptAdmissionUnknownError({ messageID: "msg_1" }))).toBe(false);
  });
});
