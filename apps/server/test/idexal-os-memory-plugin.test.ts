import assert from "node:assert/strict";
import { mkdtemp, readFile, rm } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { test } from "node:test";

import createMemoryPlugin from "../src/opencode-plugins/idexal-os-memory.js";

test("workspace memory is injected, written and recalled by agents", async () => {
  const root = await mkdtemp(join(tmpdir(), "idexal-memory-"));
  try {
    const plugin = await createMemoryPlugin({ directory: root }) as {
      "experimental.chat.system.transform": (input: unknown, output: { system: string[] }) => Promise<void>;
      tool: Record<string, { execute: (args: unknown, context?: unknown) => Promise<string> }>;
    };

    const system = { system: ["You are an agent."] };
    await plugin["experimental.chat.system.transform"]({}, system);
    assert.match(system.system.join("\n"), /Workspace long-term memory/);
    assert.match(system.system.join("\n"), /prior evidence, not current truth/);

    const remembered = JSON.parse(await plugin.tool.memory_remember.execute({
      content: "The release feed lives at /desktop and must never point at upstream GitHub.",
      kind: "decision",
      tags: ["release", "desktop"],
      importance: 5,
    }, { directory: root, agent: "idexalos" })) as { ok: boolean; entries: number };
    assert.equal(remembered.ok, true);
    assert.equal(remembered.entries, 1);

    const recalled = JSON.parse(await plugin.tool.memory_recall.execute({
      query: "release feed",
      limit: 5,
    }, { directory: root, agent: "another-agent" })) as { ok: boolean; entries: { body: string }[] };
    assert.equal(recalled.ok, true);
    assert.equal(recalled.entries.length, 1);
    assert.match(recalled.entries[0].body, /must never point at upstream GitHub/);

    const stored = await readFile(join(root, ".opencode", "idexal-os-memory.md"), "utf8");
    assert.match(stored, /idexalos · decision/);
    assert.match(stored, /release, desktop/);
  } finally {
    await rm(root, { recursive: true, force: true });
  }
});

test("memory refuses obvious secrets", async () => {
  const root = await mkdtemp(join(tmpdir(), "idexal-memory-"));
  try {
    const plugin = await createMemoryPlugin({ directory: root }) as {
      tool: Record<string, { execute: (args: unknown, context?: unknown) => Promise<string> }>;
    };
    const result = JSON.parse(await plugin.tool.memory_remember.execute({
      content: "-----BEGIN PRIVATE KEY----- abc",
    }, { directory: root })) as { ok: boolean };
    assert.equal(result.ok, false);
  } finally {
    await rm(root, { recursive: true, force: true });
  }
});
