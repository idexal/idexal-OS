import assert from "node:assert/strict";
import { test } from "node:test";

import { parseInferenceProviderList } from "../src/cloud-provider-sync.js";

const ORG = "01m5gfbp07d63tb6dex2tbrcxy";

function idWith(suffix: string): string {
  return `gwm_${ORG}_${ORG}_${ORG.slice(0, 25)}${suffix}`;
}

function model(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  const id = String(overrides.id ?? idWith("a"));
  return {
    id,
    name: "GLM-5.3-Flash",
    config: { id, name: "GLM-5.3-Flash" },
    upstreamModelId: "zai-org/GLM-5.3-Flash",
    modelGroupId: `gmg_${ORG}`,
    modelGroupName: "idexal Os",
    credentialSetId: `gcs_${ORG}`,
    credentialSetName: "Provider A",
    ...overrides,
  };
}

function payload(models: unknown[], providerOverrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    inferenceProviders: [
      {
        id: `ipr_${ORG}`,
        providerId: `ipr_${ORG}`,
        name: "idexal Os Gateway",
        source: "idexal_os_gateway",
        updatedAt: null,
        providerConfig: {},
        models,
        pinnedModelIds: [],
        credentialStatus: "ready",
        authUrl: null,
        authorizationRequests: [],
        ...providerOverrides,
      },
    ],
  };
}

test("one unreadable model row no longer discards the whole provider", () => {
  const [provider] = parseInferenceProviderList(payload([
    model({ id: idWith("a") }),
    model({ id: idWith("b"), credentialSetName: undefined }),
    model({ id: idWith("c") }),
  ]));

  assert.equal(provider.id, `ipr_${ORG}`);
  assert.deepEqual(provider.models.map((entry) => entry.id), [idWith("a"), idWith("c")]);
  assert.deepEqual(provider.droppedModels, [{ modelId: idWith("b"), reason: "invalid_gateway_model" }]);
});

test("a structurally broken row is named, and the provider still materializes", () => {
  const [provider] = parseInferenceProviderList(payload([
    model({ id: idWith("a") }),
    { id: idWith("b"), name: "" },
  ]));

  assert.deepEqual(provider.models.map((entry) => entry.id), [idWith("a")]);
  assert.deepEqual(provider.droppedModels, [{ modelId: idWith("b"), reason: "invalid_model_payload" }]);
});

test("pinned ids pointing at dropped models are not left dangling", () => {
  const [provider] = parseInferenceProviderList(payload(
    [model({ id: idWith("a") })],
    { pinnedModelIds: [idWith("a"), idWith("b")] },
  ));

  assert.deepEqual(provider.pinnedModelIds, [idWith("a")]);
});

test("a provider without an id is still rejected outright", () => {
  const broken = payload([model()], { providerId: undefined });

  assert.throws(() => parseInferenceProviderList(broken), /den_inference_provider_list_invalid_response/);
});
