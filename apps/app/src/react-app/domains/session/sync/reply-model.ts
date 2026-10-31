import type { UIMessage } from "ai";
import { AUTO_MODEL_ID } from "@/react-app/domains/models/model-catalog";

function field(value: unknown, key: string): unknown {
  return value && typeof value === "object" ? Reflect.get(value, key) : undefined;
}
function text(value: unknown) { return typeof value === "string" && value.trim() ? value.trim() : undefined; }

export function replyModelFromInfo(info: unknown) {
  if (field(info, "role") !== "assistant") return undefined;
  const resolved = field(info, "resolvedModel");
  const model = field(info, "model");
  const resolvedModelID = text(field(resolved, "modelID")) ?? text(field(resolved, "id")) ?? text(field(info, "resolvedModelID"));
  const requestedModelID = text(field(info, "modelID")) ?? text(field(model, "id"));
  const requestedProviderID = text(field(info, "providerID")) ?? text(field(model, "providerID"));
  const modelID = resolvedModelID ?? requestedModelID;
  const providerID = text(field(resolved, "providerID")) ?? text(field(info, "resolvedProviderID")) ?? requestedProviderID;
  const name = text(field(resolved, "name"));
  if (!modelID) return undefined;
  return { modelID, ...(providerID ? { providerID } : {}),
    ...(requestedModelID ? { requestedModelID } : {}),
    ...(requestedProviderID ? { requestedProviderID } : {}),
    ...(resolvedModelID ? { resolved: true, ...(name ? { name } : {}) } : {}),
  };
}

export function mergeReplyMetadata(previous: unknown, next: unknown) {
  const record = (value: unknown): object => value && typeof value === "object" && !Array.isArray(value) ? value : {};
  const previousModel = field(field(previous, "opencode"), "replyModel");
  const nextModel = field(field(next, "opencode"), "replyModel");
  return { ...record(previous), ...record(next), opencode: {
    ...record(field(previous, "opencode")), ...record(field(next, "opencode")),
    ...(previousModel || nextModel ? { replyModel: field(previousModel, "resolved") === true && field(nextModel, "resolved") !== true
      ? { ...record(nextModel), ...record(previousModel) }
      : { ...record(previousModel), ...record(nextModel) } } : {}),
  } };
}

/** The requested Auto route keeps idexal Os's mark even after routing upstream. */
export function replyModelProvider(message: UIMessage) {
  const model = field(field(message.metadata, "opencode"), "replyModel");
  const requestedProviderID = text(field(model, "requestedProviderID"));
  const requestedModelID = text(field(model, "requestedModelID"));
  const providerID = text(field(model, "providerID"));
  if (requestedProviderID === "idexalos" || providerID === "idexalos"
    || requestedModelID === AUTO_MODEL_ID) return "idexalos";
  // Opaque gateway ids must not become a monogram or a remote logo request.
  return providerID?.startsWith("ipr_") ? undefined : providerID;
}

export type ReplyModelTitleResolver = (model: { providerID: string; modelID: string }) => string | undefined;

function publicLabel(value: unknown) {
  const label = text(value);
  return label && !/^(ipr|gwm)_/.test(label) ? label : undefined;
}

/** Prefer verified routing metadata; older engines only report the requested model. */
export function replyModelLabel(message: UIMessage, resolveTitle?: ReplyModelTitleResolver) {
  const model = field(field(message.metadata, "opencode"), "replyModel");
  const modelID = text(field(model, "modelID"));
  if (!modelID) return null;
  if (field(model, "resolved") === true) {
    if (modelID === AUTO_MODEL_ID) return "GPT-6 Luna";
    const resolvedLabel = publicLabel(field(model, "name")) ?? publicLabel(modelID);
    if (resolvedLabel) return resolvedLabel;
  }
  const requestedModelID = text(field(model, "requestedModelID")) ?? modelID;
  const providerID = text(field(model, "requestedProviderID")) ?? text(field(model, "providerID"));
  // Auto is a route, not the identity of the model that answered.
  if (!providerID || requestedModelID === AUTO_MODEL_ID) return null;
  return publicLabel(resolveTitle?.({ providerID, modelID: requestedModelID })) ?? null;
}
