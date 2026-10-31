"use client";

import { AlertTriangle, Eye, Search, Trash2 } from "lucide-react";
import { DenInput } from "../../../_components/ui/input";
import { DenSwitch } from "../../../_components/ui/switch";
import type { ExternalMcpTool, ExternalMcpToolPolicyView } from "../mcp-connections-data";

function toolTitle(tool: ExternalMcpTool): string {
  return tool.title || tool.annotations?.title || tool.name;
}

export function ToolRail({
  tools,
  selectedToolName,
  policy,
  search,
  policyAttribution,
  policyUpdating,
  onSearchChange,
  onSelect,
  onToggle,
}: {
  tools: ExternalMcpTool[];
  selectedToolName: string;
  policy: ExternalMcpToolPolicyView;
  search: string;
  policyAttribution: string;
  policyUpdating: boolean;
  onSearchChange: (value: string) => void;
  onSelect: (tool: ExternalMcpTool) => void;
  onToggle: (toolName: string, enabled: boolean) => void;
}) {
  const needle = search.trim().toLowerCase();
  const filteredTools = needle
    ? tools.filter((tool) => (
        [tool.name, tool.title, tool.annotations?.title, tool.description]
          .some((value) => value?.toLowerCase().includes(needle))
      ))
    : tools;

  return (
    <aside className="w-full shrink-0 space-y-3 lg:w-[300px]">
      <DenInput
        icon={Search}
        aria-label="Search tools"
        placeholder={`Search ${tools.length} tools`}
        value={search}
        onChange={(event) => onSearchChange(event.target.value)}
      />
      <div className="divide-y divide-gray-100 overflow-hidden rounded-2xl border border-gray-100 bg-white">
        {filteredTools.length === 0 ? (
          <p className="px-4 py-5 text-[12px] text-gray-500">No tools match “{search.trim()}”.</p>
        ) : filteredTools.map((tool) => {
          const disabled = policy.allDisabled || policy.disabledTools.includes(tool.name);
          const selected = tool.name === selectedToolName;
          const Icon = tool.annotations?.readOnlyHint ? Eye : tool.annotations?.destructiveHint ? Trash2 : AlertTriangle;
          const iconClass = tool.annotations?.readOnlyHint ? "text-gray-400" : tool.annotations?.destructiveHint ? "text-red-600" : "text-amber-600";
          return (
            <div key={tool.name} className={`flex items-center ${selected ? "bg-gray-50" : ""} ${policy.allDisabled ? "opacity-60" : ""}`}>
              <button type="button" className="flex min-w-0 flex-1 items-start gap-2.5 px-3 py-3 text-left" onClick={() => onSelect(tool)}>
                <span className="flex w-4 shrink-0 justify-center pt-0.5"><Icon className={`h-3.5 w-3.5 ${iconClass}`} aria-hidden="true" /></span>
                <span className="min-w-0 flex-1">
                  <span className={`block truncate text-[13px] font-medium ${disabled ? "text-gray-400" : "text-gray-900"}`}>{toolTitle(tool)}</span>
                  <span className="mt-0.5 block truncate font-mono text-[10.5px] text-gray-400">{tool.name}</span>
                  {disabled && policy.updatedBy ? <span className="mt-1 block text-[10px] text-gray-400">Disabled{policyAttribution}</span> : null}
                </span>
              </button>
              <span className="shrink-0 px-3">
                <DenSwitch
                  size="sm"
                  checked={!disabled}
                  disabled={policy.allDisabled || policyUpdating}
                  onChange={(enabled) => onToggle(tool.name, enabled)}
                  aria-label={`Toggle ${tool.name} for the organization`}
                  testId={`tool-policy-switch-${tool.name}`}
                />
              </span>
            </div>
          );
        })}
      </div>
    </aside>
  );
}
