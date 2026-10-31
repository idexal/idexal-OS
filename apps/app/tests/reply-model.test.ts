import { expect, test } from "bun:test"
import type { UIMessage } from "ai"
import { replyModelLabel, replyModelProvider } from "../src/react-app/domains/session/sync/reply-model"
import { AUTO_MODEL_ID } from "../src/react-app/domains/models/model-catalog"

function reply(model: object): UIMessage {
  return { id: "reply", role: "assistant", parts: [], metadata: { opencode: { replyModel: model } } }
}

test("Auto keeps the idexal Os mark and reports the routed name once", () => {
  const message = reply({ requestedProviderID: "idexalos", requestedModelID: AUTO_MODEL_ID,
    providerID: "openai", modelID: "gpt-6", name: "GPT-6", resolved: true })
  expect(replyModelLabel(message)).toBe("GPT-6")
  expect(replyModelProvider(message)).toBe("idexalos")
})

test("direct provider replies use their provider mark", () => {
  const message = reply({ providerID: "anthropic", modelID: "claude-sonnet", name: "Claude Sonnet", resolved: true })
  expect(replyModelLabel(message)).toBe("Claude Sonnet")
  expect(replyModelProvider(message)).toBe("anthropic")
})

test("opaque gateway ids are neither labels nor provider monograms", () => {
  const message = reply({ providerID: "ipr_fixture", modelID: "gwm_fixture", resolved: true })
  expect(replyModelLabel(message)).toBeNull()
  expect(replyModelProvider(message)).toBeUndefined()
})

test("gateway requested identities fall back only to a catalog title", () => {
  const message = reply({ providerID: "ipr_fixture", modelID: "gwm_fixture" })
  const resolve = (model: { providerID: string; modelID: string }) =>
    model.providerID === "ipr_fixture" && model.modelID === "gwm_fixture" ? "GPT-6 Luna" : undefined
  expect(replyModelLabel(message, resolve)).toBe("GPT-6 Luna")
  expect(replyModelLabel(reply({ providerID: "ipr_fixture", modelID: "gwm_fixture", resolved: true }), resolve)).toBe("GPT-6 Luna")
  expect(replyModelLabel(message, () => "gwm_fixture")).toBeNull()
  expect(replyModelLabel(message, () => "ipr_fixture")).toBeNull()
  expect(replyModelProvider(message)).toBeUndefined()
})

test("resolved identity wins over the requested catalog title", () => {
  expect(replyModelLabel(reply({ providerID: "openai", modelID: "gpt-6", name: "GPT-6", resolved: true }), () => "Other model")).toBe("GPT-6")
  expect(replyModelLabel(reply({ providerID: "idexalos", modelID: AUTO_MODEL_ID, resolved: true }))).toBe("GPT-6 Luna")
  expect(replyModelLabel(reply({ providerID: "idexalos", modelID: AUTO_MODEL_ID }), () => "Auto")).toBeNull()
})

test("an opaque resolved name never leaks even without a gateway provider", () => {
  expect(replyModelLabel(reply({ modelID: "gwm_fixture", name: "ipr_fixture", resolved: true }))).toBeNull()
})

test("unresolved and missing metadata stay absent without a catalog match", () => {
  expect(replyModelLabel(reply({ providerID: "openai", modelID: "gpt-6" }))).toBeNull()
  expect(replyModelLabel(reply({}))).toBeNull()
})
