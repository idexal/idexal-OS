export type SchemaFormPrimitive = string | number | boolean;
export type SchemaFormFieldType = "string" | "number" | "integer" | "boolean";

export type SchemaFormField = {
  name: string;
  required: boolean;
  type: SchemaFormFieldType;
  description: string | null;
  enumValues: SchemaFormPrimitive[] | null;
};

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function isPrimitive(value: unknown): value is SchemaFormPrimitive {
  return typeof value === "string" || typeof value === "number" || typeof value === "boolean";
}

function isFieldType(value: unknown): value is SchemaFormFieldType {
  return value === "string" || value === "number" || value === "integer" || value === "boolean";
}

function inferredFieldType(value: SchemaFormPrimitive): SchemaFormFieldType {
  if (typeof value === "number") return "number";
  if (typeof value === "boolean") return "boolean";
  return "string";
}

export function getSchemaFormFields(inputSchema: Record<string, unknown>): SchemaFormField[] | null {
  if (
    inputSchema.type !== "object"
    || inputSchema.oneOf !== undefined
    || inputSchema.anyOf !== undefined
    || inputSchema.allOf !== undefined
    || !isRecord(inputSchema.properties)
  ) return null;
  const entries = Object.entries(inputSchema.properties);
  if (entries.length === 0) return null;
  const required = new Set(
    Array.isArray(inputSchema.required)
      ? inputSchema.required.filter((value): value is string => typeof value === "string")
      : [],
  );
  const fields: SchemaFormField[] = [];
  for (const [name, value] of entries) {
    if (!isRecord(value)) return null;
    if (
      value.oneOf !== undefined
      || value.anyOf !== undefined
      || value.allOf !== undefined
      || (value.type !== undefined && !isFieldType(value.type))
    ) return null;
    const enumValues = Array.isArray(value.enum)
      && value.enum.length > 0
      && value.enum.every(isPrimitive)
      ? value.enum
      : null;
    const type = isFieldType(value.type)
      ? value.type
      : enumValues
        ? inferredFieldType(enumValues[0])
        : null;
    if (!type) return null;
    fields.push({
      name,
      required: required.has(name),
      type,
      description: typeof value.description === "string" ? value.description : null,
      enumValues,
    });
  }
  return fields;
}

export function schemaSupportsForm(inputSchema: Record<string, unknown>): boolean {
  return getSchemaFormFields(inputSchema) !== null;
}

export function serializeFormValues(
  inputSchema: Record<string, unknown>,
  values: Record<string, string>,
): Record<string, unknown> {
  const fields = getSchemaFormFields(inputSchema) ?? [];
  const result: Record<string, unknown> = {};
  for (const field of fields) {
    const value = values[field.name] ?? "";
    if (!field.required && value === "") continue;
    const enumValue = field.enumValues?.find((candidate) => String(candidate) === value);
    if (enumValue !== undefined) {
      result[field.name] = enumValue;
    } else if (field.type === "number" || field.type === "integer") {
      result[field.name] = Number(value);
    } else if (field.type === "boolean") {
      result[field.name] = value === "true";
    } else {
      result[field.name] = value;
    }
  }
  return result;
}

export function formValuesFromArguments(
  inputSchema: Record<string, unknown>,
  argumentsValue: Record<string, unknown>,
): Record<string, string> {
  const fields = getSchemaFormFields(inputSchema) ?? [];
  return Object.fromEntries(fields.map((field) => {
    const value = argumentsValue[field.name];
    return [field.name, value === undefined || value === null ? "" : String(value)];
  }));
}
