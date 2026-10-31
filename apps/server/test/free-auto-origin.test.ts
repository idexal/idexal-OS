import { expect, test } from "bun:test";

import { resolveAnonymousInferenceOrigin } from "../src/free-auto/settings.ts";

test("Auto defaults to the idexal Os platform origin", () => {
  expect(resolveAnonymousInferenceOrigin({})).toBe("https://os.idexal.com");
});

test("an explicit Auto origin still wins", () => {
  expect(resolveAnonymousInferenceOrigin({ IDEXAL_FREE_INFERENCE_ORIGIN: "https://inference.example.com" }))
    .toBe("https://inference.example.com");
});
