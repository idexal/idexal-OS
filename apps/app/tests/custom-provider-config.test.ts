import { describe, expect, test } from "bun:test";
import { parse as parseJsonc } from "jsonc-parser";

import {
  buildCustomProviderConfig,
  customProviderBaseUrlError,
  customProviderIdForName,
  formatConfigWithCustomProvider,
  formatConfigWithoutCustomProvider,
  parseCustomProviderModelIds,
  parseProviderModelList,
  providerErrorMessage,
} from "../src/react-app/domains/connections/provider-auth/custom-provider-config";

describe("custom provider ids", () => {
  test("prefixes the slug of the display name", () => {
    expect(customProviderIdForName("My Anthropic", [])).toBe("custom-my-anthropic");
    expect(customProviderIdForName("  Acme_LLM  ", [])).toBe("custom-acme-llm");
  });

  test("suffixes when the id is taken, including disabled providers", () => {
    expect(customProviderIdForName("Acme", ["custom-acme"])).toBe("custom-acme-2");
    expect(customProviderIdForName("Acme", ["custom-acme", "custom-acme-2"])).toBe("custom-acme-3");
    expect(customProviderIdForName("Acme", [])).toBe("custom-acme");
  });

  test("falls back to a stable id when the name has no usable characters", () => {
    expect(customProviderIdForName("!!!", [])).toBe("custom-provider");
    expect(customProviderIdForName("", [])).toBe("custom-provider");
  });
});

describe("custom provider model ids", () => {
  test("splits on newlines and commas, trims, and de-dupes", () => {
    expect(parseCustomProviderModelIds("a\nb, c\n\n a ")).toEqual(["a", "b", "c"]);
  });

  test("accepts an array and drops empty entries", () => {
    expect(parseCustomProviderModelIds([" x ", "", "x", "y"])).toEqual(["x", "y"]);
  });

  test("returns nothing for blank input", () => {
    expect(parseCustomProviderModelIds("  , \n ")).toEqual([]);
  });
});

describe("provider model list parsing", () => {
  test("reads OpenAI and Anthropic data entries by id", () => {
    expect(parseProviderModelList({ data: [{ id: "gpt-a" }, { id: "gpt-b" }] })).toEqual(["gpt-a", "gpt-b"]);
    expect(parseProviderModelList({ data: [{ id: "claude-a" }] })).toEqual(["claude-a"]);
  });

  test("reads models arrays that name entries instead of ids", () => {
    expect(parseProviderModelList({ models: [{ name: "llama3" }, { model: "qwen" }] })).toEqual([
      "llama3",
      "qwen",
    ]);
  });

  test("accepts a bare array of strings and trims them", () => {
    expect(parseProviderModelList([" a ", "", "b", "a"])).toEqual(["a", "b"]);
  });

  test("returns nothing for shapes without models", () => {
    expect(parseProviderModelList(null)).toEqual([]);
    expect(parseProviderModelList({ data: "nope" })).toEqual([]);
    expect(parseProviderModelList({ data: [null, 3, {}] })).toEqual([]);
  });
});

describe("provider error messages", () => {
  test("reads the common error shapes", () => {
    expect(providerErrorMessage({ error: { message: "bad key" } }, "fallback")).toBe("bad key");
    expect(providerErrorMessage({ error: "bad key" }, "fallback")).toBe("bad key");
    expect(providerErrorMessage({ message: "bad key" }, "fallback")).toBe("bad key");
  });

  test("falls back for bodies without a message", () => {
    expect(providerErrorMessage(null, "Unauthorized")).toBe("Unauthorized");
    expect(providerErrorMessage({ error: {} }, "HTTP 404")).toBe("HTTP 404");
  });
});

describe("custom provider base url", () => {
  test("accepts http and https urls", () => {
    expect(customProviderBaseUrlError("https://api.example.com/v1")).toBe("");
    expect(customProviderBaseUrlError("http://127.0.0.1:11434/v1")).toBe("");
  });

  test("distinguishes required from invalid", () => {
    expect(customProviderBaseUrlError("   ")).toBe("required");
    expect(customProviderBaseUrlError("api.example.com")).toBe("invalid");
    expect(customProviderBaseUrlError("ftp://api.example.com")).toBe("invalid");
  });
});

describe("buildCustomProviderConfig", () => {
  test("maps the OpenAI family to the compatible package", () => {
    expect(buildCustomProviderConfig({
      family: "openai",
      name: "  Acme  ",
      baseUrl: " https://llm.example.com/v1 ",
      models: ["gpt-5.3-mini", "o5"],
    })).toEqual({
      npm: "@ai-sdk/openai-compatible",
      name: "Acme",
      options: { baseURL: "https://llm.example.com/v1" },
      models: {
        "gpt-5.3-mini": { name: "gpt-5.3-mini" },
        o5: { name: "o5" },
      },
    });
  });

  test("maps the Anthropic family to the anthropic package", () => {
    expect(buildCustomProviderConfig({
      family: "anthropic",
      name: "Claude proxy",
      baseUrl: "https://claude.example.com",
      models: ["claude-sonnet-4-6"],
    }).npm).toBe("@ai-sdk/anthropic");
  });
});

describe("formatConfigWithCustomProvider", () => {
  test("adds the provider block to an empty config", () => {
    const updated = formatConfigWithCustomProvider("", "custom-acme", {
      npm: "@ai-sdk/openai-compatible",
      name: "Acme",
      options: { baseURL: "https://llm.example.com/v1" },
      models: { m: { name: "m" } },
    });
    expect(JSON.parse(updated)).toEqual({
      $schema: "https://opencode.ai/config.json",
      provider: {
        "custom-acme": {
          npm: "@ai-sdk/openai-compatible",
          name: "Acme",
          options: { baseURL: "https://llm.example.com/v1" },
          models: { m: { name: "m" } },
        },
      },
    });
  });

  test("keeps notes and removes the id from disabled_providers", () => {
    const raw = `{
  // keep this note
  "disabled_providers": ["custom-acme", "other"],
  "provider": {}
}
`;
    const updated = formatConfigWithCustomProvider(raw, "custom-acme", {
      npm: "@ai-sdk/openai-compatible",
      name: "Acme",
      options: { baseURL: "https://llm.example.com/v1" },
      models: { m: { name: "m" } },
    });
    expect(updated).toContain("// keep this note");
    expect(updated).toMatch(/"disabled_providers"\s*:\s*\[\s*"other"\s*\]/);
    expect(updated).toContain('"custom-acme"');
    expect(updated).toContain('"baseURL": "https://llm.example.com/v1"');
  });
});

describe("formatConfigWithoutCustomProvider", () => {
  test("deletes the provider block and unhides the id, keeping notes", () => {
    const raw = `{
  // keep this note
  "disabled_providers": ["custom-acme", "other"],
  "provider": {
    "custom-acme": { "npm": "@ai-sdk/openai-compatible" },
    "keep-me": { "npm": "@ai-sdk/anthropic" }
  }
}
`;
    const updated = formatConfigWithoutCustomProvider(raw, "custom-acme", ["custom-acme", "other"]);
    const parsed = parseJsonc(updated) as {
      disabled_providers: string[];
      provider: Record<string, unknown>;
    };
    expect(updated).toContain("// keep this note");
    expect(parsed.provider["custom-acme"]).toBeUndefined();
    expect(parsed.provider["keep-me"]).toBeDefined();
    expect(parsed.disabled_providers).toEqual(["other"]);
  });

  test("leaves an unrelated config as-is", () => {
    const raw = `{
  "provider": { "keep-me": { "npm": "@ai-sdk/anthropic" } }
}
`;
    const updated = formatConfigWithoutCustomProvider(raw, "custom-acme");
    expect(parseJsonc(updated)).toEqual({
      provider: { "keep-me": { npm: "@ai-sdk/anthropic" } },
    });
  });
});
