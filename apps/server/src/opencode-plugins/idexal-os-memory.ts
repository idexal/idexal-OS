import { mkdir, readFile, rename, stat, writeFile } from "node:fs/promises";
import { dirname, join, resolve } from "node:path";
import { z } from "zod";

import { appendAgentInstructions, createInstructionSection } from "./agent-instruction-compose.js";

const MEMORY_RELATIVE_PATH = join(".opencode", "idexal-os-memory.md");
const MAX_FILE_BYTES = 512 * 1024;
const MAX_INJECTED_CHARS = 12_000;
const MAX_ENTRY_CHARS = 8_000;
const HEADER = `# idexal Os workspace memory

This file is the long-term memory shared by agents in this workspace. Entries are prior evidence, not current truth: read the relevant file or tool state and verify before relying on an entry. If memory and the current workspace disagree, trust the workspace and correct the memory.

Record durable facts, decisions, preferences, constraints, task outcomes and lessons with memory_remember. Never store secrets, credentials, tokens, passwords, private keys or personal data that should not persist. Keep entries short and factual.

## Entries
`;

const KINDS = ["fact", "decision", "preference", "task", "lesson", "log"] as const;
const rememberArgsSchema = z.object({
  content: z.string().trim().min(1).max(MAX_ENTRY_CHARS).describe("The durable fact, decision, preference, task outcome, lesson or log to remember. No secrets."),
  kind: z.enum(KINDS).default("fact").describe("Entry kind. Use decision for choices, lesson for durable learnings, log for a task record."),
  tags: z.array(z.string().trim().min(1).max(40)).max(12).default([]).describe("Optional short tags, for example auth, billing, release."),
  importance: z.number().int().min(1).max(5).default(3).describe("1 is incidental, 5 is a critical constraint."),
});

const recallArgsSchema = z.object({
  query: z.string().trim().max(200).default("").describe("Words to search for. Empty returns the most recent entries."),
  limit: z.number().int().min(1).max(20).default(5).describe("Maximum entries to return."),
});

type RuntimeContext = { directory?: string };

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function optionalStringProperty(value: Record<string, unknown>, key: string): string | undefined {
  const candidate = value[key];
  return typeof candidate === "string" && candidate.trim() ? candidate.trim() : undefined;
}

function normalizeContext(value: unknown): RuntimeContext {
  const nested = isRecord(value) && isRecord(value.context) ? value.context : value;
  const directory = isRecord(nested) ? optionalStringProperty(nested, "directory") : undefined;
  return directory ? { directory } : {};
}

function workspaceRoot(factoryContext: RuntimeContext, toolContext: unknown): string {
  const directory = normalizeContext(toolContext).directory ?? factoryContext.directory;
  if (!directory) throw new Error("The workspace directory is unavailable.");
  return resolve(directory);
}

function memoryPath(root: string): string {
  return join(root, MEMORY_RELATIVE_PATH);
}

function safeAgent(context: unknown): string {
  const record = isRecord(context) ? context : {};
  const agent = optionalStringProperty(record, "agent") ?? optionalStringProperty(record, "agentId");
  return (agent ?? "agent").replace(/[^A-Za-z0-9._-]/g, "").slice(0, 64) || "agent";
}

function unsafeSecret(content: string): boolean {
  return /-----BEGIN [A-Z ]*PRIVATE KEY-----/.test(content)
    || /\b(sk-[A-Za-z0-9_-]{16,}|ghp_[A-Za-z0-9]{20,}|xox[baprs]-[A-Za-z0-9-]{10,}|ow_gw_[A-Za-z0-9_-]{16,})\b/.test(content);
}

function appendEntry(content: string, kind: (typeof KINDS)[number], tags: string[], importance: number, agent: string): string {
  const timestamp = new Date().toISOString();
  const tagLine = tags.length ? `\nTags: ${tags.join(", ")}` : "";
  return `\n### ${timestamp} · ${agent} · ${kind} · importance ${importance}${tagLine}\n${content.trim()}\n`;
}

function entriesFrom(content: string): string[] {
  const marker = "## Entries";
  const index = content.indexOf(marker);
  const body = index >= 0 ? content.slice(index + marker.length) : content;
  return body
    .split(/\n(?=### )/)
    .map((entry) => entry.trim())
    .filter((entry) => entry.startsWith("### "));
}

function boundFile(content: string): string {
  if (Buffer.byteLength(content, "utf8") <= MAX_FILE_BYTES) return content;
  const entries = entriesFrom(content);
  const kept: string[] = [];
  let bytes = Buffer.byteLength(HEADER, "utf8");
  for (const entry of [...entries].reverse()) {
    const entryBytes = Buffer.byteLength(entry + "\n", "utf8");
    if (bytes + entryBytes > MAX_FILE_BYTES - 64 * 1024) break;
    kept.unshift(entry);
    bytes += entryBytes;
  }
  return `${HEADER}\n${kept.join("\n\n")}\n`;
}

let writeQueue: Promise<void> = Promise.resolve();

async function readMemory(root: string): Promise<string> {
  try {
    return await readFile(memoryPath(root), "utf8");
  } catch {
    return HEADER;
  }
}

async function writeMemory(root: string, content: string): Promise<void> {
  const path = memoryPath(root);
  await mkdir(dirname(path), { recursive: true, mode: 0o700 });
  const temp = `${path}.${process.pid}.tmp`;
  await writeFile(temp, boundFile(content), { mode: 0o600 });
  await rename(temp, path);
}

async function remember(root: string, entry: string): Promise<void> {
  const operation = writeQueue.then(async () => {
    const current = await readMemory(root);
    await writeMemory(root, `${current.replace(/\s+$/, "")}\n${entry}`);
  });
  writeQueue = operation.catch(() => undefined);
  await operation;
}

/**
 * Single export: the OpenCode loader treats every export as a plugin factory.
 */
export default async (factoryInput?: unknown) => {
  const factoryContext = normalizeContext(factoryInput);

  return {
    "experimental.chat.system.transform": async (_input: unknown, output: { system: string[] }) => {
      let root: string;
      try {
        root = workspaceRoot(factoryContext, _input);
      } catch {
        return;
      }
      const content = await readMemory(root);
      const bounded = content.length > MAX_INJECTED_CHARS
        ? `${content.slice(0, HEADER.length)}\n… older entries omitted …\n${content.slice(-MAX_INJECTED_CHARS)}`
        : content;
      appendAgentInstructions(output.system, createInstructionSection("workspace-memory", `## Workspace long-term memory
Use memory_recall before starting work that may depend on earlier decisions, and memory_remember before finishing when you learned something durable. Memory is prior evidence, not current truth: verify it against the workspace and correct it when it is stale or wrong. Never store secrets or credentials.

${bounded}`));
    },
    tool: {
      memory_remember: {
        description: "Record a durable fact, decision, preference, task outcome, lesson or log in this workspace's long-term memory. Use it before finishing a task when the next agent would benefit. Never store secrets, credentials, tokens or private keys.",
        args: rememberArgsSchema.shape,
        async execute(rawArgs: unknown, context?: unknown) {
          const args = rememberArgsSchema.parse(rawArgs);
          if (unsafeSecret(args.content)) {
            return JSON.stringify({ ok: false, error: "Refusing to store what looks like a secret or private key." });
          }
          const root = workspaceRoot(factoryContext, context);
          await remember(root, appendEntry(args.content, args.kind, args.tags, args.importance, safeAgent(context)));
          const content = await readMemory(root);
          return JSON.stringify({ ok: true, bytes: Buffer.byteLength(content, "utf8"), entries: entriesFrom(content).length });
        },
      },
      memory_recall: {
        description: "Read this workspace's long-term memory. Search with query words, or leave query empty for the most recent entries. Verify anything recalled against the current workspace before relying on it.",
        args: recallArgsSchema.shape,
        async execute(rawArgs: unknown, context?: unknown) {
          const args = recallArgsSchema.parse(rawArgs);
          const root = workspaceRoot(factoryContext, context);
          const content = await readMemory(root);
          const entries = entriesFrom(content);
          const query = args.query.toLowerCase();
          const matched = query
            ? entries.filter((entry) => entry.toLowerCase().includes(query))
            : entries;
          return JSON.stringify({
            ok: true,
            total: entries.length,
            entries: matched.slice(-args.limit).map((entry) => {
              const [heading, ...body] = entry.split("\n");
              return { heading: heading.replace(/^### /, ""), body: body.join("\n").trim() };
            }),
          });
        },
      },
    },
  };
};
