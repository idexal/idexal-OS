import type { CreateThreadFieldOnThreadInput, CreateThreadFieldSchemaInput } from "@team-plain/graphql";

export type FeedbackContext = {
  source?: string;
  entrypoint?: string;
  deployment?: string;
  appVersion?: string;
  idexalOsServerVersion?: string;
  opencodeVersion?: string;
  osName?: string;
  osVersion?: string;
  platform?: string;
};

type FeedbackMetadata = FeedbackContext & {
  name: string;
  email: string;
  mode: "feedback" | "contact";
  submittedAt: string;
};

const fieldDefinitions = [
  { key: "idexal_os_os_name", label: "idexal Os OS" },
  { key: "idexal_os_app_version", label: "idexal Os app version" },
  { key: "idexal_os_deployment", label: "idexal Os deployment" },
  { key: "idexal_os_metadata", label: "idexal Os metadata" },
];

// Shared by the form and the one-time setup script so schema keys/types stay in sync.
export const plainFeedbackFieldSchemas: CreateThreadFieldSchemaInput[] = fieldDefinitions.map((field, order) => ({
  key: field.key,
  label: field.label,
  description: `${field.label} recorded when a contact or feedback form was submitted.`,
  type: "STRING",
  enumValues: [],
  order,
  isRequired: false,
  isClientReadonly: true,
  isAiAutoFillEnabled: false,
  isAvailableToAgents: false,
}));

function availableValue(value: string | undefined) {
  const trimmed = value?.trim();
  return trimmed && trimmed.toLowerCase() !== "unknown" ? trimmed : undefined;
}

export function buildFeedbackThreadFields(input: FeedbackMetadata): CreateThreadFieldOnThreadInput[] {
  const { osName, appVersion, deployment, ...context } = input;
  const metadata = Object.fromEntries(Object.entries(context)
    .map(([key, value]) => [key, availableValue(value)])
    .filter(([, value]) => value !== undefined));
  const values = [osName, appVersion, deployment, JSON.stringify(metadata, null, 2)];
  return fieldDefinitions.flatMap((field, index) => {
    const value = availableValue(values[index]);
    return value ? [{ key: field.key, type: "STRING", stringValue: value }] : [];
  });
}
