import assert from "node:assert/strict";
import { test } from "node:test";

import { parseInferenceProviderList } from "../src/cloud-provider-sync.js";

// The self-hosted platform (Laravel) speaks this exact document at
// GET /v1/inference-providers?scope=usable. The PHP side asserts its own
// regex; this side asserts the *real* platform payload survives the desktop
// parser, because a wire-id drift once made every organization model
// disappear with nothing the user could see.
//
// The payload below is the literal shape CloudProviderController::provider()
// emits, field for field and in order. If the platform ever changes a key or
// the casing of a segment, this fails on the desktop side before a user
// reports an empty catalog.
const ORG = "01m5b6607kf78gmenjaq1ee8h1";
const MODEL_KEYS = [
  "01m5g70vptkf0dcrr5mrxdth88",
  "01m5g6y5jrsjdmcx2d0jptjfev",
  "01m5nnpvzcz146hyym90nf6jqb",
];

const wireId = (modelKey: string) => `gwm_${ORG}_${ORG}_${modelKey}`;

function gatewayModel(modelKey: string, overrides: Record<string, unknown> = {}) {
  const id = wireId(modelKey);
  return {
    id,
    name: "DeepSeek V4 Flash",
    config: { id, name: "DeepSeek V4 Flash", limit: { context: 128000, output: 8192 }, tool_call: true },
    upstreamModelId: "deepseek-v4-flash",
    modelGroupId: `gmg_${ORG}`,
    modelGroupName: "idexal Os",
    credentialSetId: `gcs_${ORG}`,
    credentialSetName: "Upstream",
    ...overrides,
  };
}

function laravelPayload(models: unknown[] = MODEL_KEYS.map((key) => gatewayModel(key))) {
  return {
    inferenceProviders: [
      {
        id: `ipr_${ORG}`,
        providerId: `ipr_${ORG}`,
        name: "idexal Os Gateway",
        source: "idexal_os_gateway",
        updatedAt: "2026-10-24T00:41:56+00:00",
        providerConfig: {
          npm: "@ai-sdk/openai-compatible",
          env: [`IPR_${ORG.toUpperCase()}_API_KEY`],
          api: "https://api.idexal.com/v1",
          options: { baseURL: "https://api.idexal.com/v1" },
        },
        models,
        pinnedModelIds: [],
        credentialStatus: "ready",
        authUrl: null,
        authorizationRequests: [],
      },
    ],
  };
}

test("the platform's real managed-gateway payload materializes every model", () => {
  const [provider] = parseInferenceProviderList(laravelPayload());

  assert.equal(provider.id, `ipr_${ORG}`);
  assert.equal(provider.source, "idexal_os_gateway");
  assert.equal(provider.credentialStatus, "ready");
  assert.deepEqual(provider.droppedModels, []);
  assert.deepEqual(provider.models.map((model) => model.id), MODEL_KEYS.map(wireId));
});

test("an uppercased wire id is dropped, never silently accepted", () => {
  // The regression this guards: a gate that emits the ULID segments in upper
  // case matches no client regex, so the workspace loses every model at once.
  const upper = wireId(MODEL_KEYS[1]).toUpperCase();
  const [provider] = parseInferenceProviderList(laravelPayload([
    gatewayModel(MODEL_KEYS[0]),
    { ...gatewayModel(MODEL_KEYS[1]), id: upper, config: { id: upper, name: "DeepSeek V4 Flash" } },
  ]));

  assert.deepEqual(provider.models.map((model) => model.id), [wireId(MODEL_KEYS[0])]);
  assert.deepEqual(provider.droppedModels, [{ modelId: upper, reason: "invalid_gateway_model" }]);
});

test("a model whose config id disagrees with its own id is dropped", () => {
  const [provider] = parseInferenceProviderList(laravelPayload([
    gatewayModel(MODEL_KEYS[0], { config: { id: wireId(MODEL_KEYS[1]), name: "DeepSeek V4 Flash" } }),
  ]));

  assert.deepEqual(provider.models, []);
  assert.deepEqual(provider.droppedModels, [
    { modelId: wireId(MODEL_KEYS[0]), reason: "invalid_gateway_model" },
  ]);
});

test("a provider with every model dropped still materializes, and names the loss", () => {
  const [provider] = parseInferenceProviderList(laravelPayload([
    gatewayModel(MODEL_KEYS[0], { credentialSetName: undefined }),
  ]));

  assert.equal(provider.id, `ipr_${ORG}`);
  assert.deepEqual(provider.models, []);
  assert.deepEqual(provider.droppedModels, [
    { modelId: wireId(MODEL_KEYS[0]), reason: "invalid_gateway_model" },
  ]);
});
