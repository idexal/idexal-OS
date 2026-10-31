import { expect, test } from "bun:test";
import { buildModelCatalog, resolveRetainedSelection } from "../src/react-app/domains/models/catalog";
import { AUTO_MODEL_ID, AUTO_PROVIDER_ID, isAutoModel, modelTitle, idexalOsModelsLunaReplacement, shouldSelectInitialAuto, starterModel } from "../src/react-app/domains/models/model-catalog";
import type { ModelOption } from "../src/app/types";

const auto: ModelOption = { providerID: AUTO_PROVIDER_ID, modelID: AUTO_MODEL_ID, title: "Auto", isFree: true };
const idexalOsModel: ModelOption = { providerID: "idexalos", modelID: "anthropic/claude-sonnet", title: "Claude Sonnet" };
const other: ModelOption = { providerID: "anthropic", modelID: "claude-sonnet", title: "Claude Sonnet" };

function catalog(runtime: ModelOption[]) {
  return buildModelCatalog({ runtime, signedIn: true, restrictToCloud: false, checkRestriction: () => false,
    autoStatus: { providerID: AUTO_PROVIDER_ID, modelID: AUTO_MODEL_ID, code: null } });
}

test("free Auto is not listed when the organization has idexal Os Models", () => {
  expect(catalog([auto, other]).options.map((option) => option.providerID)).toContain(AUTO_PROVIDER_ID);
  const covered = catalog([auto, idexalOsModel, other]);
  expect(covered.options.map((option) => option.providerID)).toEqual(["idexalos", "anthropic"]);
  expect(covered.known.map((option) => option.providerID)).toContain(AUTO_PROVIDER_ID);
});

test("a conversation already on free Auto keeps working when it is hidden behind idexal Os Models", () => {
  expect(resolveRetainedSelection({ current: auto, catalog: catalog([auto, idexalOsModel]), signedIn: true,
    restrictToCloud: false, checkRestriction: () => false, catalogState: "ready", sessionScoped: true })).toBeUndefined();
});

test("new members with idexal Os Models do not start on free Auto", () => {
  const input = { current: null, empty: true, explicit: false };
  expect(shouldSelectInitialAuto({ ...input, available: [auto, other] })).toBe(true);
  expect(shouldSelectInitialAuto({ ...input, available: [auto, idexalOsModel] })).toBe(false);
});

const luna: ModelOption = { providerID: "idexalos", modelID: AUTO_MODEL_ID, title: "GPT-6 Luna" };

test("Luna from idexal Os Models is listed under its own name, not as Auto", () => {
  expect(isAutoModel(luna)).toBe(false);
  expect(modelTitle(luna)).toBe("GPT-6 Luna");
  expect(catalog([auto, luna]).options).toEqual([expect.objectContaining({ providerID: "idexalos", title: "GPT-6 Luna" })]);
});

test("members start on Luna from their idexal Os Models once it is listed", () => {
  const input = { current: null, empty: true, explicit: false };
  expect(shouldSelectInitialAuto({ ...input, available: [auto, idexalOsModel, luna] })).toBe(true);
  expect(starterModel([auto, idexalOsModel, luna])).toBe(luna);
  expect(starterModel([auto, other])).toBe(auto);
});

test("a saved free Auto choice moves to Luna from idexal Os Models", () => {
  expect(idexalOsModelsLunaReplacement(auto, [auto, idexalOsModel, luna])).toBe(luna);
  expect(idexalOsModelsLunaReplacement(auto, [auto, idexalOsModel])).toBeUndefined();
  expect(idexalOsModelsLunaReplacement(other, [auto, luna])).toBeUndefined();
});
