import { spawn } from "node:child_process";
import { randomBytes } from "node:crypto";
import { readFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { setTimeout as delay } from "node:timers/promises";
import { allocateFreePort } from "@idexal/cdp";
import { checkedExec, defaultDaytonaExec } from "@idexal/hosts";
import type { ChildProcess } from "node:child_process";
import type { DenRef } from "@idexal/behaviors";
import type { DaytonaExec } from "@idexal/hosts";

const DAYTONA_LINK_SCRIPT = "/tmp/idexal-os-den-link-server.mjs";
const DAYTONA_LINK_PID = "/tmp/idexal-os-den-link-server.pid";
const DAYTONA_LINK_BASE64 = `${DAYTONA_LINK_SCRIPT}.b64`;
const MAX_LINK_SCRIPT_BYTES = 512 * 1024;
const DAYTONA_LINK_BASE64_CHUNK_LENGTH = 8 * 1024;
/** Conservative ceiling for each complete Daytona argv command string. */
export const MAX_LINK_DAYTONA_COMMAND_LENGTH = 12 * 1024;

export type LinkRule = { pathPrefix?: string; times?: number; everyNth?: number } & (
  | { kind: "latency"; delayMs: number; jitterMs?: number }
  | { kind: "status"; statusCode: number; body?: unknown }
  | { kind: "reset" }
  | { kind: "stall" }
);

export type LinkProfile = "baseline" | "vpn-flaky-emulated";
export type DenLinkClient = "public-preview" | "sandbox-loopback";

export interface LinkRequestEntry {
  method: string;
  path: string;
  status: number;
  faulted: boolean;
  fault?: string;
  phase: string;
  profile: LinkProfile;
  at: number;
}

export interface LinkLog {
  requests: LinkRequestEntry[];
  refusedConnections: Record<string, number>;
  phase: string;
  profile: LinkProfile;
}

export interface LinkStats {
  requests: number;
  faults: number;
  refusedConnections: number;
  phase: string;
  profile: LinkProfile;
}

export interface DenLink extends AsyncDisposable {
  /** Den ref as seen by the app: webUrl is the shaped data URL. */
  ref: DenRef;
  admin: {
    phase(name: string, profile?: LinkProfile): Promise<void>;
    rules(rules: LinkRule[]): Promise<void>;
    bandwidth(bytesPerSec: number | null): Promise<void>;
    offline(durationMs: number): Promise<void>;
    clear(): Promise<void>;
    requests(): Promise<LinkLog>;
    stats(): Promise<LinkStats>;
    health(): Promise<{ ok: boolean; phase: string; offline: boolean }>;
  };
}

export interface DenLinkOptions {
  /** Run in this known Daytona sandbox, normally `den.placement.sandboxId`. Omit for a local child process. */
  sandboxId?: string;
  /** URL exposed to the app for Daytona placement. Defaults to the public data preview. */
  client?: DenLinkClient;
  /** Fixed in-sandbox ports; defaults 3985 (data) / 3986 (admin). Local placement allocates free ports and ignores these. */
  port?: number;
  adminPort?: number;
  /** Override Daytona command execution, primarily for deterministic tests. */
  daytonaExec?: DaytonaExec;
}

interface LinkPlacement {
  dataUrl: string;
  adminUrl: string;
  dispose(): Promise<void>;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function messageText(error: unknown): string {
  return error instanceof Error ? error.message : String(error);
}

function isProfile(value: unknown): value is LinkProfile {
  return value === "baseline" || value === "vpn-flaky-emulated";
}

function parseHealth(value: unknown): { ok: boolean; phase: string; offline: boolean } {
  if (isRecord(value) && typeof value.ok === "boolean" && typeof value.phase === "string" && typeof value.offline === "boolean") {
    return { ok: value.ok, phase: value.phase, offline: value.offline };
  }
  throw new Error("Link health response has an invalid shape.");
}

function parseRequestEntry(value: unknown): LinkRequestEntry {
  if (!isRecord(value)
    || typeof value.method !== "string"
    || typeof value.path !== "string"
    || typeof value.status !== "number"
    || typeof value.faulted !== "boolean"
    || (value.fault !== undefined && typeof value.fault !== "string")
    || typeof value.phase !== "string"
    || !isProfile(value.profile)
    || typeof value.at !== "number") {
    throw new Error("Link request entry has an invalid shape.");
  }
  return {
    method: value.method,
    path: value.path,
    status: value.status,
    faulted: value.faulted,
    ...(value.fault === undefined ? {} : { fault: value.fault }),
    phase: value.phase,
    profile: value.profile,
    at: value.at,
  };
}

function parseLog(value: unknown): LinkLog {
  if (!isRecord(value) || !Array.isArray(value.requests) || !isRecord(value.refusedConnections) || typeof value.phase !== "string" || !isProfile(value.profile)) {
    throw new Error("Link requests response has an invalid shape.");
  }
  const refusedConnections: Record<string, number> = {};
  for (const [name, count] of Object.entries(value.refusedConnections)) {
    if (typeof count !== "number") throw new Error("Link refusal count has an invalid shape.");
    refusedConnections[name] = count;
  }
  return { requests: value.requests.map(parseRequestEntry), refusedConnections, phase: value.phase, profile: value.profile };
}

function parseStats(value: unknown): LinkStats {
  if (!isRecord(value)
    || typeof value.requests !== "number"
    || typeof value.faults !== "number"
    || typeof value.refusedConnections !== "number"
    || typeof value.phase !== "string"
    || !isProfile(value.profile)) {
    throw new Error("Link stats response has an invalid shape.");
  }
  return {
    requests: value.requests,
    faults: value.faults,
    refusedConnections: value.refusedConnections,
    phase: value.phase,
    profile: value.profile,
  };
}

async function adminJson(baseUrl: string, path: string, token: string, body?: object): Promise<unknown> {
  const response = await fetch(`${baseUrl}${path}`, {
    method: body === undefined ? "GET" : "POST",
    headers: {
      authorization: `Bearer ${token}`,
      ...(body === undefined ? {} : { "content-type": "application/json" }),
    },
    body: body === undefined ? undefined : JSON.stringify(body),
    signal: AbortSignal.timeout(10_000),
  });
  const text = await response.text();
  if (!response.ok) throw new Error(`Den link admin ${path} failed with HTTP ${response.status}: ${text.slice(0, 1_000)}`);
  try {
    return JSON.parse(text);
  } catch {
    throw new Error(`Den link admin ${path} returned invalid JSON: ${text.slice(0, 1_000)}`);
  }
}

async function distinctLocalPorts(): Promise<[number, number]> {
  const port = await allocateFreePort();
  let adminPort = await allocateFreePort();
  while (adminPort === port) adminPort = await allocateFreePort();
  return [port, adminPort];
}

function waitForExit(child: ChildProcess): Promise<void> {
  if (child.exitCode !== null || child.signalCode !== null) return Promise.resolve();
  return new Promise((resolve) => child.once("exit", () => resolve()));
}

async function localPlacement(upstream: DenRef, token: string): Promise<LinkPlacement> {
  const [port, adminPort] = await distinctLocalPorts();
  const script = fileURLToPath(new URL("./link-server.mjs", import.meta.url));
  const child = spawn(process.execPath, [script, "--upstream", upstream.webUrl, "--port", String(port), "--admin-port", String(adminPort)], {
    env: { ...process.env, LINK_ADMIN_TOKEN: token },
    stdio: ["ignore", "pipe", "pipe"],
  });
  if (!child.stdout || !child.stderr) throw new Error("Den link child process did not expose output streams.");
  let output = "";
  child.stdout.setEncoding("utf8");
  child.stderr.setEncoding("utf8");
  child.stdout.on("data", (chunk) => { output += String(chunk); });
  child.stderr.on("data", (chunk) => { output += String(chunk); });
  const listening = `link-server listening data=${port} admin=${adminPort}`;
  try {
    const deadline = Date.now() + 15_000;
    while (!output.includes(listening) && Date.now() < deadline) {
      if (child.exitCode !== null) throw new Error(`Den link exited during startup (${child.exitCode}): ${output.slice(-2_000)}`);
      await delay(50);
    }
    if (!output.includes(listening)) throw new Error(`Timed out waiting for Den link startup: ${output.slice(-2_000)}`);
    await adminJson(`http://127.0.0.1:${adminPort}`, "/health", token);
  } catch (error) {
    child.kill("SIGKILL");
    throw error;
  }
  let disposed = false;
  return {
    dataUrl: `http://127.0.0.1:${port}`,
    adminUrl: `http://127.0.0.1:${adminPort}`,
    async dispose(): Promise<void> {
      if (disposed) return;
      disposed = true;
      child.kill("SIGTERM");
      const exited = waitForExit(child);
      await Promise.race([exited, delay(5_000)]);
      if (child.exitCode === null && child.signalCode === null) {
        child.kill("SIGKILL");
        await waitForExit(child);
      }
    },
  };
}

function safeRemoteUrl(value: string): string {
  const parsed = new URL(value);
  if ((parsed.protocol !== "http:" && parsed.protocol !== "https:") || !/^[A-Za-z0-9:/?&=._~%+\-]+$/.test(value)) {
    throw new Error(`Den link upstream URL is not safe for Daytona execution: ${value}`);
  }
  return value;
}

function validPort(value: number, name: string): number {
  if (!Number.isInteger(value) || value < 1 || value > 65_535) throw new Error(`${name} must be a valid port.`);
  return value;
}

async function remoteExec(exec: DaytonaExec, sandbox: string, script: string, context: string, timeoutMs = 30_000): Promise<string> {
  if (script.includes("'")) throw new Error(`Remote script for ${context} must not contain single quotes.`);
  const args = ["exec", sandbox, "--", `bash -lc '${script}'`];
  const commandLength = args.join(" ").length;
  if (commandLength > MAX_LINK_DAYTONA_COMMAND_LENGTH) {
    throw new Error(`Den link Daytona command is ${commandLength} characters; maximum is ${MAX_LINK_DAYTONA_COMMAND_LENGTH}.`);
  }
  const result = await checkedExec(exec, args, context, { timeoutMs });
  return `${result.stdout}${result.stderr}`;
}

function firstHttpsUrl(text: string): string | null {
  const match = /https:\/\/[^\s"'<>)]+/.exec(text);
  return match ? match[0].replace(/[.,;:]+$/, "") : null;
}

export function daytonaLinkCommands(
  source: Uint8Array,
  upstream: string,
  port: number,
  adminPort: number,
  token: string,
): { cleanup: string; upload: string[]; detach: string } {
  if (source.byteLength === 0 || source.byteLength > MAX_LINK_SCRIPT_BYTES) {
    throw new Error(`Den link runner must be between 1 and ${MAX_LINK_SCRIPT_BYTES} bytes.`);
  }
  const upstreamUrl = safeRemoteUrl(upstream);
  const dataPort = validPort(port, "port");
  const controlPort = validPort(adminPort, "adminPort");
  if (!/^[a-f0-9]{32,}$/.test(token)) throw new Error("Den link admin token must be at least 32 lowercase hex characters.");
  const encoded = Buffer.from(source).toString("base64");
  const upload = [`: > ${DAYTONA_LINK_BASE64}`];
  for (let offset = 0; offset < encoded.length; offset += DAYTONA_LINK_BASE64_CHUNK_LENGTH) {
    const chunk = encoded.slice(offset, offset + DAYTONA_LINK_BASE64_CHUNK_LENGTH);
    upload.push(`printf %s ${chunk} >> ${DAYTONA_LINK_BASE64}`);
  }
  upload.push([
    "decode_status=0",
    `base64 -d ${DAYTONA_LINK_BASE64} > ${DAYTONA_LINK_SCRIPT} || decode_status=$?`,
    `actual_bytes=$(wc -c < ${DAYTONA_LINK_SCRIPT})`,
    `rm -f ${DAYTONA_LINK_BASE64}`,
    'test "$decode_status" -eq 0',
    `test "$actual_bytes" -eq ${source.byteLength}`,
  ].join("; "));
  return {
    cleanup: `if test -f ${DAYTONA_LINK_PID}; then pid=$(<${DAYTONA_LINK_PID}); case "$pid" in ""|*[!0-9]*) ;; *) if test "$pid" -gt 0 2>/dev/null; then kill "$pid" 2>/dev/null || true; attempts=0; while kill -0 "$pid" 2>/dev/null && test "$attempts" -lt 50; do sleep 0.1; attempts=$((attempts + 1)); done; fi ;; esac; fi; rm -f ${DAYTONA_LINK_PID} ${DAYTONA_LINK_SCRIPT} ${DAYTONA_LINK_BASE64}`,
    upload,
    detach: `python3 - <<PYEOF
import os
import subprocess
log = open("/tmp/link-server.log", "ab", buffering=0)
process = subprocess.Popen(["node", "${DAYTONA_LINK_SCRIPT}", "--upstream", "${upstreamUrl}", "--port", "${dataPort}", "--admin-port", "${controlPort}"], stdin=subprocess.DEVNULL, stdout=log, stderr=subprocess.STDOUT, start_new_session=True, close_fds=True, env={**os.environ, "LINK_ADMIN_TOKEN": "${token}"})
temporary_pid = "${DAYTONA_LINK_PID}." + str(os.getpid())
try:
    with open(temporary_pid, "w", encoding="ascii") as pid_file:
        pid_file.write(str(process.pid) + "\\n")
        pid_file.flush()
        os.fsync(pid_file.fileno())
    os.replace(temporary_pid, "${DAYTONA_LINK_PID}")
except Exception:
    try:
        os.unlink(temporary_pid)
    except FileNotFoundError:
        pass
    process.terminate()
    raise
PYEOF`,
  };
}

async function daytonaPlacement(upstream: DenRef, sandbox: string, options: DenLinkOptions, token: string): Promise<LinkPlacement> {
  const exec = options.daytonaExec ?? defaultDaytonaExec;
  const port = validPort(options.port ?? 3985, "port");
  const adminPort = validPort(options.adminPort ?? 3986, "adminPort");
  const client = options.client ?? "public-preview";
  if (port === adminPort) throw new Error("Den link data and admin ports must differ.");
  // Pin the original public Den URL before creating the shaper preview URLs;
  // running inside that Den sandbox therefore cannot proxy back into itself.
  const upstreamUrl = safeRemoteUrl(upstream.webUrl);
  const source = await readFile(fileURLToPath(new URL("./link-server.mjs", import.meta.url)));
  const commands = daytonaLinkCommands(source, upstreamUrl, port, adminPort, token);
  const cleanup = (): Promise<string> => remoteExec(exec, sandbox, commands.cleanup, `Den link cleanup for ${sandbox}`);
  await cleanup();
  try {
    for (const [index, upload] of commands.upload.entries()) {
      await remoteExec(exec, sandbox, upload, `Den link upload ${index + 1}/${commands.upload.length} for ${sandbox}`, 30_000);
    }
    await remoteExec(exec, sandbox, commands.detach, `Den link detach for ${sandbox}`);
    const deadline = Date.now() + 60_000;
    let last = "not attempted";
    while (Date.now() < deadline) {
      try {
        await remoteExec(exec, sandbox, `curl -sf -H "Authorization: Bearer ${token}" http://127.0.0.1:${adminPort}/health`, `Den link health for ${sandbox}`, 10_000);
        last = "ok";
        break;
      } catch (error) {
        last = messageText(error);
        await delay(1_000);
      }
    }
    if (last !== "ok") {
      const log = await remoteExec(exec, sandbox, "tail -80 /tmp/link-server.log 2>&1 || true", `Den link log for ${sandbox}`).catch((error: unknown) => messageText(error));
      throw new Error(`Den link health gate failed in ${sandbox}. Last: ${last}. Log tail:\n${log.slice(-4_000)}`);
    }
    const dataPreview = client === "public-preview"
      ? await checkedExec(exec, ["preview-url", sandbox, "-p", String(port)], `daytona preview-url ${sandbox} -p ${port}`, { timeoutMs: 30_000 })
      : null;
    const adminPreview = await checkedExec(exec, ["preview-url", sandbox, "-p", String(adminPort)], `daytona preview-url ${sandbox} -p ${adminPort}`, { timeoutMs: 30_000 });
    const dataUrl = dataPreview ? firstHttpsUrl(dataPreview.stdout) : `http://127.0.0.1:${port}`;
    const adminUrl = firstHttpsUrl(adminPreview.stdout);
    if (!dataUrl) throw new Error(`daytona preview-url ${sandbox} -p ${port} did not print an https URL: ${dataPreview?.stdout.trim()}`);
    if (!adminUrl) throw new Error(`daytona preview-url ${sandbox} -p ${adminPort} did not print an https URL: ${adminPreview.stdout.trim()}`);
    await adminJson(adminUrl, "/health", token);
    let disposed = false;
    return {
      dataUrl,
      adminUrl,
      async dispose(): Promise<void> {
        if (disposed) return;
        disposed = true;
        await cleanup().catch((error: unknown) => console.error(`Den link cleanup failed for ${sandbox}: ${messageText(error)}`));
      },
    };
  } catch (error) {
    await cleanup().catch(() => undefined);
    throw error;
  }
}

export async function denLink(upstream: DenRef, options: DenLinkOptions = {}): Promise<DenLink> {
  const token = randomBytes(32).toString("hex");
  const placement = options.sandboxId
    ? await daytonaPlacement(upstream, options.sandboxId, options, token)
    : await localPlacement(upstream, token);
  const post = async (path: string, body: object): Promise<void> => {
    await adminJson(placement.adminUrl, path, token, body);
  };
  return {
    ref: { apiUrl: `${placement.dataUrl}/api/den`, webUrl: placement.dataUrl },
    admin: {
      phase: (name, profile) => post("/phase", profile === undefined ? { name } : { name, profile }),
      rules: (newRules) => post("/rules", { rules: newRules }),
      bandwidth: (rate) => post("/bandwidth", { bytesPerSec: rate }),
      offline: (durationMs) => post("/offline", { durationMs }),
      clear: () => post("/clear", {}),
      async requests(): Promise<LinkLog> {
        return parseLog(await adminJson(placement.adminUrl, "/requests", token));
      },
      async stats(): Promise<LinkStats> {
        return parseStats(await adminJson(placement.adminUrl, "/stats", token));
      },
      async health(): Promise<{ ok: boolean; phase: string; offline: boolean }> {
        return parseHealth(await adminJson(placement.adminUrl, "/health", token));
      },
    },
    [Symbol.asyncDispose]: () => placement.dispose(),
  };
}
