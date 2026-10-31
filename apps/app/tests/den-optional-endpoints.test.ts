import { describe, expect, test } from "bun:test";

import { parseDenMcpDiscovery } from "../src/app/lib/den-mcp-discovery";

/**
 * A smaller self-hosted control plane does not implement every optional
 * endpoint the hosted surface has. The desktop treats 404/405/501 from those
 * endpoints as "this feature is not available here" and substitutes a shape
 * the screen can explain, so it never reads as a crash. This asserts that the
 * substitute is a payload the real parser accepts.
 */
describe("optional control-plane endpoints", () => {
  test("the substitute for a missing discovery endpoint parses", () => {
    const discovery = parseDenMcpDiscovery({
      status: "unsupported",
      server: { url: "https://example.test/mcp", initialize: "failed" },
      authentication: {
        kind: "unknown",
        availableRegistrationMethods: [],
        recommendedRegistrationMethod: "pre_registered",
      },
      tools: { visibility: "unavailable" },
      manualRequirements: [],
    });

    expect(discovery).not.toBeNull();
    expect(discovery?.status).toBe("unsupported");
  });

  test("a discovery from a full control plane still parses", () => {
    const discovery = parseDenMcpDiscovery({
      status: "ready",
      server: { url: "https://example.test/mcp", protocolVersion: "2025-06-18", initialize: "succeeded" },
      authentication: {
        kind: "oauth",
        availableRegistrationMethods: ["dynamic"],
        recommendedRegistrationMethod: "dynamic",
      },
      tools: { visibility: "available_without_auth", count: 3 },
      manualRequirements: [],
    });

    expect(discovery?.status).toBe("ready");
    expect(discovery?.tools.count).toBe(3);
  });

  test("a malformed discovery is rejected rather than half-read", () => {
    expect(parseDenMcpDiscovery({ requirements: [] })).toBeNull();
  });
});
