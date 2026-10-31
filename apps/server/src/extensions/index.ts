import { ApiError } from "../errors.js";
import type { EnvService } from "../env-file.js";
import {
  googleWorkspaceCloudRequired,
  type ConnectSnapshot,
} from "../connect-state.js";
import type { ServerConfig } from "../types.js";
import {
  callOpenAiImageGenerationExtensionAction,
  OPENAI_IMAGE_GENERATION_EXTENSION_ACTIONS,
  OPENAI_IMAGE_GENERATION_EXTENSION_ID,
} from "./openai-image-generation.js";
import {
  callIdexalOsCloudUploadAction,
  IDEXAL_CLOUD_UPLOAD_ACTIONS,
  IDEXAL_CLOUD_UPLOADS_EXTENSION_ID,
} from "./cloud-uploads.js";

const IDEXAL_EXPERIMENTAL_EXTENSION_ACTIONS = [
  ...OPENAI_IMAGE_GENERATION_EXTENSION_ACTIONS,
  ...IDEXAL_CLOUD_UPLOAD_ACTIONS,
];

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function readStringField(value: unknown, key: string): string {
  if (!isRecord(value)) return "";
  const field = value[key];
  return typeof field === "string" ? field.trim() : "";
}

export function listExperimentalExtensionActions(extensionId: string) {
  const filter = extensionId.trim();
  return filter
    ? IDEXAL_EXPERIMENTAL_EXTENSION_ACTIONS.filter((action) => action.extensionId === filter)
    : IDEXAL_EXPERIMENTAL_EXTENSION_ACTIONS;
}

export async function callExperimentalExtensionAction(config: ServerConfig, env: EnvService, input: unknown, connectSnapshot?: ConnectSnapshot, signal?: AbortSignal) {
  if (!isRecord(input)) {
    throw new ApiError(400, "invalid_payload", "Expected extension action call payload");
  }
  const extensionId = readStringField(input, "extensionId");
  const action = readStringField(input, "action");
  const args = isRecord(input.args) ? input.args : {};
  const context = isRecord(input.context) ? input.context : {};
  if (!extensionId || !action) {
    throw new ApiError(400, "invalid_payload", "extensionId and action are required");
  }
  if (extensionId === "google-workspace") {
    return googleWorkspaceCloudRequired(connectSnapshot?.cloudHealth ?? null);
  }
  const registered = IDEXAL_EXPERIMENTAL_EXTENSION_ACTIONS.find((item) => item.extensionId === extensionId && item.action === action);
  if (!registered) {
    throw new ApiError(404, "extension_action_not_found", "idexal Os extension action not found");
  }

  if (extensionId === OPENAI_IMAGE_GENERATION_EXTENSION_ID) {
    const result = await callOpenAiImageGenerationExtensionAction(config, env, action, args, context);
    if (result) return result;
  }

  if (extensionId === IDEXAL_CLOUD_UPLOADS_EXTENSION_ID) {
    const result = await callIdexalOsCloudUploadAction(config, action, args, context, { signal });
    if (result) return result;
  }

  throw new ApiError(501, "extension_action_not_implemented", `${registered.title} is registered but not implemented on idexal-os-server yet.`, { extensionId, action, args });
}
