import { expect, test } from "bun:test";

import { isOwnedProvider, ownedProvider } from "../src/free-auto/provider-config.ts";

test("the Auto provider uses the chat-completions adapter and is recognized as ours", () => {
  const token = `owf_local_${"a".repeat(43)}`;
  const provider = ownedProvider(token, 53123);

  expect(provider.npm).toBe("@ai-sdk/openai-compatible");
  expect(isOwnedProvider(provider)).toBe(true);
});
