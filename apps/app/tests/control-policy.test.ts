import { describe, expect, test } from "bun:test";

import type { AgentPermissionRule } from "@idexal/types/den/agent-permissions-runtime";
import type { DesktopAgentControl } from "@idexal/types/den/desktop-policies";

import {
  controlPolicyFailure,
  type IdexalOsAgentControlPolicy,
  type IdexalOsControlAction,
  type IdexalOsControlPermission,
} from "../src/react-app/shell/control/control-provider";

function action(permission?: IdexalOsControlPermission): IdexalOsControlAction {
  return {
    id: "test.action",
    label: "Test action",
    kind: "query",
    effects: { data: "read", ui: "none", external: false },
    sideEffect: "none",
    ...(permission ? { permission } : {}),
    execute: () => null,
  };
}

function policy(
  agentControl?: DesktopAgentControl,
  rules?: AgentPermissionRule[],
): IdexalOsAgentControlPolicy {
  return {
    config: agentControl ? { agentControl } : {},
    ...(rules ? { rules } : {}),
  };
}

describe("controlPolicyFailure", () => {
  test("leaves actions without permission metadata ungated", () => {
    expect(controlPolicyFailure(action(), policy({ enabled: false }))).toBeNull();
  });

  test("keeps legacy control planes working when agentControl is absent", () => {
    expect(controlPolicyFailure(action({ action: "proxy", plan: "proxies" }), undefined)).toBeNull();
    expect(controlPolicyFailure(action({ action: "proxy", plan: "proxies" }), { config: {} })).toBeNull();
  });

  test("denies every family when the plan disables agent control", () => {
    const failure = controlPolicyFailure(action({ action: "settings", plan: "settings" }), policy({ enabled: false }));
    expect(failure?.error).toContain("does not include agent desktop control");
  });

  test("denies a family the plan did not include", () => {
    const failure = controlPolicyFailure(
      action({ action: "proxy", plan: "proxies" }),
      policy({ enabled: true, proxies: false }),
    );
    expect(failure?.error).toContain("network proxy");
  });

  test("allows a family the plan included", () => {
    expect(controlPolicyFailure(
      action({ action: "proxy", plan: "proxies" }),
      policy({ enabled: true, proxies: true }),
    )).toBeNull();
  });

  test("keeps turning a proxy off available when the plan revokes proxies", () => {
    expect(controlPolicyFailure(
      action({ action: "proxy" }),
      policy({ enabled: true, proxies: false }),
    )).toBeNull();
  });

  test("applies the organization's agent permission rules", () => {
    const rules: AgentPermissionRule[] = [
      { action: "extensions", resource: "*", effect: "deny", source: "Everyone" },
    ];
    const failure = controlPolicyFailure(
      action({ action: "extensions", plan: "extensions" }),
      policy({ enabled: true, extensions: true }, rules),
    );
    expect(failure?.error).toContain("installing and managing extensions");
  });

  test("treats Ask first as blocked for agent control", () => {
    const rules: AgentPermissionRule[] = [
      { action: "browser", resource: "*", effect: "ask", source: "Everyone" },
    ];
    const failure = controlPolicyFailure(
      action({ action: "browser", plan: "browser" }),
      policy({ enabled: true, browser: true }, rules),
    );
    expect(failure?.error).toContain("Ask first");
  });

  test("allows when the plan and permission rule both allow", () => {
    const rules: AgentPermissionRule[] = [
      { action: "browser", resource: "*", effect: "allow", source: "Everyone" },
    ];
    expect(controlPolicyFailure(
      action({ action: "browser", plan: "browser" }),
      policy({ enabled: true, browser: true }, rules),
    )).toBeNull();
  });
});
