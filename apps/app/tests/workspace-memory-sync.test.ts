import { describe, expect, mock, test } from "bun:test";

import { reconcileWorkspaceMemory } from "../src/react-app/domains/settings/state/workspace-memory-sync";

function snapshot(content: string, updatedAt: string | null) {
  return { content, updatedAt };
}

describe("reconcileWorkspaceMemory", () => {
  test("pulls a strictly newer cloud copy into the workspace", async () => {
    const pullLocal = mock(async () => undefined);
    const pushRemote = mock(async () => undefined);

    const outcome = await reconcileWorkspaceMemory({
      local: snapshot("old", "2026-10-22T10:00:00Z"),
      remote: snapshot("new", "2026-10-22T11:00:00Z"),
      pullLocal,
      pushRemote,
    });

    expect(outcome).toBe("pulled");
    expect(pullLocal).toHaveBeenCalledTimes(1);
    expect(pullLocal.mock.calls[0]).toEqual(["new"]);
    expect(pushRemote).not.toHaveBeenCalled();
  });

  test("pushes a strictly newer workspace copy to the cloud", async () => {
    const pullLocal = mock(async () => undefined);
    const pushRemote = mock(async () => undefined);

    const outcome = await reconcileWorkspaceMemory({
      local: snapshot("new", "2026-10-22T11:00:00Z"),
      remote: snapshot("old", "2026-10-22T10:00:00Z"),
      pullLocal,
      pushRemote,
    });

    expect(outcome).toBe("pushed");
    expect(pushRemote).toHaveBeenCalledTimes(1);
    expect(pushRemote.mock.calls[0]).toEqual(["new"]);
    expect(pullLocal).not.toHaveBeenCalled();
  });

  test("does nothing when both copies already match", async () => {
    const pullLocal = mock(async () => undefined);
    const pushRemote = mock(async () => undefined);

    const outcome = await reconcileWorkspaceMemory({
      local: snapshot("same", "2026-10-22T10:00:00Z"),
      remote: snapshot("same", "2026-10-22T12:00:00Z"),
      pullLocal,
      pushRemote,
    });

    expect(outcome).toBe("in_sync");
    expect(pullLocal).not.toHaveBeenCalled();
    expect(pushRemote).not.toHaveBeenCalled();
  });

  test("pulls the cloud copy when the workspace file is empty", async () => {
    const pullLocal = mock(async () => undefined);
    const pushRemote = mock(async () => undefined);

    const outcome = await reconcileWorkspaceMemory({
      local: snapshot("", null),
      remote: snapshot("cloud memory", "2026-10-22T10:00:00Z"),
      pullLocal,
      pushRemote,
    });

    expect(outcome).toBe("pulled");
    expect(pullLocal.mock.calls[0]).toEqual(["cloud memory"]);
  });

  test("seeds the cloud copy when it has no memory yet", async () => {
    const pullLocal = mock(async () => undefined);
    const pushRemote = mock(async () => undefined);

    const outcome = await reconcileWorkspaceMemory({
      local: snapshot("local memory", null),
      remote: snapshot("", null),
      pullLocal,
      pushRemote,
    });

    expect(outcome).toBe("pushed");
    expect(pushRemote.mock.calls[0]).toEqual(["local memory"]);
  });

  test("keeps local agents' latest write when both timestamps tie", async () => {
    const pullLocal = mock(async () => undefined);
    const pushRemote = mock(async () => undefined);

    const outcome = await reconcileWorkspaceMemory({
      local: snapshot("agent write", "2026-10-22T10:00:00Z"),
      remote: snapshot("mirror", "2026-10-22T10:00:00Z"),
      pullLocal,
      pushRemote,
    });

    expect(outcome).toBe("pushed");
    expect(pushRemote.mock.calls[0]).toEqual(["agent write"]);
  });

  test("skips empty memory on both sides", async () => {
    const pullLocal = mock(async () => undefined);
    const pushRemote = mock(async () => undefined);

    const outcome = await reconcileWorkspaceMemory({
      local: snapshot("   ", null),
      remote: snapshot("", null),
      pullLocal,
      pushRemote,
    });

    expect(outcome).toBe("skipped");
    expect(pullLocal).not.toHaveBeenCalled();
    expect(pushRemote).not.toHaveBeenCalled();
  });
});
