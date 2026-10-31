import { describe, expect, test } from "bun:test";

import { IdexalOsServerError } from "../src/app/lib/idexal-os-server";
import {
  canRestartDesktopForReloadError,
  reloadEngineWithDesktopFallback,
} from "../src/react-app/shell/engine-reload-escalation";

function unreachable(code = "opencode_unreachable") {
  return new IdexalOsServerError(502, code, "OpenCode engine is unavailable");
}

function client(failures: unknown[]) {
  let calls = 0;
  return {
    calls: () => calls,
    reloadEngine: async () => {
      calls += 1;
      const failure = failures.shift();
      if (failure) throw failure;
    },
  };
}

describe("engine reload escalation", () => {
  test("treats a proxied engine 502 as restartable", () => {
    expect(canRestartDesktopForReloadError(unreachable())).toBe(true);
    expect(canRestartDesktopForReloadError(unreachable("opencode_engine_unreachable"))).toBe(true);
    expect(canRestartDesktopForReloadError(new IdexalOsServerError(400, "invalid_request", "bad"))).toBe(false);
  });

  test("restarts the desktop engine when both reload attempts stay unreachable", async () => {
    const fake = client([unreachable(), unreachable()]);
    let restarts = 0;

    const result = await reloadEngineWithDesktopFallback(fake, "ws_test", {
      isDesktop: () => true,
      retryDelayMs: 0,
      restartEngine: async () => {
        restarts += 1;
      },
    });

    expect(result.restartedEngine).toBe(true);
    expect(fake.calls()).toBe(2);
    expect(restarts).toBe(1);
  });

  test("a recovered second attempt does not kill live sessions", async () => {
    const fake = client([unreachable()]);
    let restarts = 0;

    const result = await reloadEngineWithDesktopFallback(fake, "ws_test", {
      isDesktop: () => true,
      retryDelayMs: 0,
      restartEngine: async () => {
        restarts += 1;
      },
    });

    expect(result.restartedEngine).toBe(false);
    expect(fake.calls()).toBe(2);
    expect(restarts).toBe(0);
  });

  test("a client transport abort never restarts the engine", async () => {
    const aborted = new DOMException("The operation was aborted.", "AbortError");
    const fake = client([aborted]);
    let restarts = 0;

    await expect(reloadEngineWithDesktopFallback(fake, "ws_test", {
      isDesktop: () => true,
      restartEngine: async () => {
        restarts += 1;
      },
    })).rejects.toBe(aborted);

    expect(fake.calls()).toBe(1);
    expect(restarts).toBe(0);
  });
});
