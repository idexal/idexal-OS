"use client";

import { ChevronDown } from "lucide-react";
import { DenInput } from "../../../_components/ui/input";
import { DenSegmented } from "../../../_components/ui/segmented";
import { DenSelect } from "../../../_components/ui/select";
import { DenTextarea } from "../../../_components/ui/textarea";
import type { ExternalMcpTool } from "../mcp-connections-data";
import { getSchemaFormFields, schemaSupportsForm } from "./schema-form";

export type ToolArgumentsEditorMode = "form" | "json";

export function ToolArgumentsEditor({
  tool,
  mode,
  formValues,
  argumentsText,
  showOptionalFields,
  disabled,
  onModeChange,
  onFormValueChange,
  onArgumentsTextChange,
  onToggleOptionalFields,
}: {
  tool: ExternalMcpTool;
  mode: ToolArgumentsEditorMode;
  formValues: Record<string, string>;
  argumentsText: string;
  showOptionalFields: boolean;
  disabled: boolean;
  onModeChange: (mode: ToolArgumentsEditorMode) => void;
  onFormValueChange: (name: string, value: string) => void;
  onArgumentsTextChange: (value: string) => void;
  onToggleOptionalFields: () => void;
}) {
  const fields = getSchemaFormFields(tool.inputSchema);
  const supportsForm = schemaSupportsForm(tool.inputSchema);
  const optionalFieldCount = fields?.filter((field) => !field.required).length ?? 0;

  return (
    <>
      <div className="flex items-center justify-between gap-3">
        <p className="text-[12px] font-medium text-gray-700">Arguments</p>
        <DenSegmented
          aria-label="Arguments editor mode"
          options={[
            { value: "form", label: "Form", disabled: !supportsForm },
            { value: "json", label: "JSON" },
          ]}
          value={mode}
          onChange={onModeChange}
        />
      </div>

      {mode === "form" && fields ? (
        <div className="space-y-4">
          {fields.filter((field) => field.required || showOptionalFields).map((field) => {
            const description = field.description
              ? `${field.description.slice(0, 40)}${field.description.length > 40 ? "…" : ""}`
              : null;
            const value = formValues[field.name] ?? (field.type === "boolean" && field.required ? "false" : "");
            return (
              <div key={field.name}>
                <div className="mb-1.5 flex items-start justify-between gap-3">
                  <label className="text-[12px] font-medium text-gray-700" htmlFor={`tool-argument-${field.name}`}>
                    {field.name}{field.required ? <span className="text-red-500"> *</span> : null}
                  </label>
                  <span className="text-right font-mono text-[10px] text-gray-400">{field.type}{description ? ` · ${description}` : ""}</span>
                </div>
                {field.enumValues ? (
                  <DenSelect
                    id={`tool-argument-${field.name}`}
                    value={value}
                    onChange={(event) => onFormValueChange(field.name, event.target.value)}
                  >
                    {!field.required ? <option value="">Not set</option> : null}
                    {field.enumValues.map((option) => <option key={`${typeof option}-${String(option)}`} value={String(option)}>{String(option)}</option>)}
                  </DenSelect>
                ) : field.type === "boolean" ? (
                  <DenSelect
                    id={`tool-argument-${field.name}`}
                    value={value}
                    onChange={(event) => onFormValueChange(field.name, event.target.value)}
                  >
                    {!field.required ? <option value="">Not set</option> : null}
                    <option value="true">true</option>
                    <option value="false">false</option>
                  </DenSelect>
                ) : (
                  <DenInput
                    id={`tool-argument-${field.name}`}
                    type={field.type === "number" || field.type === "integer" ? "number" : "text"}
                    value={value}
                    onChange={(event) => onFormValueChange(field.name, event.target.value)}
                  />
                )}
              </div>
            );
          })}
          {optionalFieldCount > 0 ? (
            <button
              type="button"
              className="inline-flex items-center gap-1 text-[11px] font-medium text-gray-500 hover:text-gray-900"
              onClick={onToggleOptionalFields}
            >
              <ChevronDown className={`h-3.5 w-3.5 transition ${showOptionalFields ? "rotate-180" : ""}`} aria-hidden="true" />
              {showOptionalFields ? "Hide" : "Show"} {optionalFieldCount} optional fields
            </button>
          ) : null}
        </div>
      ) : (
        <div>
          <DenTextarea
            className="min-h-56 font-mono text-[12px] leading-5"
            rows={10}
            value={argumentsText}
            onChange={(event) => onArgumentsTextChange(event.target.value)}
            disabled={disabled}
            spellCheck={false}
          />
          {!supportsForm ? <p className="mt-2 text-[11px] text-gray-500">This tool&apos;s schema can&apos;t be shown as a form — edit the JSON directly.</p> : null}
        </div>
      )}
    </>
  );
}
