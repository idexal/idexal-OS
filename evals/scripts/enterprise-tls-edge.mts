import { execFile } from "node:child_process";
import { createHash, timingSafeEqual } from "node:crypto";
import { readFile, rm, writeFile } from "node:fs/promises";
import http from "node:http";
import { setTimeout } from "node:timers/promises";

import {
  linuxTrustStorePlan,
  readTrustedEnterpriseTlsManifest,
  stageTrustedEnterpriseTlsRoot,
  startEnterpriseTlsReverseEdge,
  type EnterpriseTlsEdgeManifest,
  type LinuxTrustCommand,
} from "../packages/labs/src/egress.ts";

type EdgeManifest = EnterpriseTlsEdgeManifest;

function option(name: string, fallback?: string): string {
  const index = process.argv.indexOf(name);
  const value = index >= 0 ? process.argv[index + 1] : fallback;
  if (!value) throw new Error(`Missing ${name}.`);
  return value;
}

function portOption(name: string, fallback: number): number {
  const value = Number.parseInt(option(name, String(fallback)), 10);
  if (!Number.isInteger(value) || value < 1 || value > 65_535) throw new Error(`Invalid ${name}.`);
  return value;
}

async function readManifest(manifestPath: string): Promise<EdgeManifest> {
  return (await readTrustedEnterpriseTlsManifest(manifestPath)).manifest;
}

function run(command: LinuxTrustCommand): Promise<void> {
  return new Promise((resolve, reject) => {
    execFile(command.file, command.args, { encoding: "utf8" }, (error, stdout, stderr) => {
      if (!error) return resolve();
      reject(new Error(`${command.file} failed: ${String(stderr || stdout).trim() || error.message}`));
    });
  });
}

async function changeTrust(action: "install" | "remove", manifestPath: string): Promise<void> {
  const staged = action === "install" ? await stageTrustedEnterpriseTlsRoot(manifestPath) : null;
  try {
    const manifest = staged?.manifest ?? await readManifest(manifestPath);
    const plan = linuxTrustStorePlan(staged?.stagedRootPemPath ?? manifest.rootPemPath);
    const prerequisite = await plan.checkPrerequisites();
    if (!prerequisite.ok) throw new Error(prerequisite.failure);
    const commands = action === "install"
      ? plan.install(prerequisite.updateCaCertificatesPath)
      : plan.remove(prerequisite.updateCaCertificatesPath);
    for (const command of commands) await run(command);
  } finally {
    await staged?.cleanup().catch(() => undefined);
  }
}

async function stop(manifestPath: string): Promise<void> {
  const manifest = await readManifest(manifestPath);
  process.kill(manifest.pid, "SIGTERM");
  for (let attempt = 0; attempt < 100; attempt += 1) {
    try {
      await readFile(manifestPath);
    } catch {
      return;
    }
    await setTimeout(100);
  }
  throw new Error(`Enterprise TLS edge did not stop cleanly: ${manifest.pid}`);
}

function listen(server: http.Server, port: number): Promise<void> {
  return new Promise((resolve, reject) => {
    server.once("error", reject);
    server.listen(port, "127.0.0.1", () => resolve());
  });
}

function close(server: http.Server): Promise<void> {
  return new Promise((resolve, reject) => server.close((error) => error ? reject(error) : resolve()));
}

async function serve(manifestPath: string): Promise<void> {
  const adminToken = process.env.ENTERPRISE_TLS_ADMIN_TOKEN;
  if (typeof adminToken !== "string" || !/^[a-f0-9]{32,}$/.test(adminToken)) {
    throw new Error("ENTERPRISE_TLS_ADMIN_TOKEN must be at least 32 hex characters.");
  }
  const expectedAuthorizationHash = createHash("sha256").update(`Bearer ${adminToken}`).digest();
  const candidatePort = portOption("--candidate-port", 8443);
  const negativePort = portOption("--negative-port", 9443);
  const adminPort = portOption("--admin-port", 8445);
  if (new Set([candidatePort, negativePort, adminPort]).size !== 3) throw new Error("Candidate, negative, and admin ports must be distinct.");
  const edge = await startEnterpriseTlsReverseEdge({
    upstream: option("--upstream"),
    candidatePort,
    negativePort,
  });
  const adminUrl = `http://127.0.0.1:${adminPort}`;
  const manifest: EdgeManifest = {
    pid: process.pid,
    candidateUrl: edge.candidateUrl,
    negativeUrl: edge.negativeUrl,
    adminUrl,
    rootPemPath: edge.rootPemPath,
  };
  const admin = http.createServer((request, response) => {
    response.setHeader("content-type", "application/json; charset=utf-8");
    const receivedAuthorizationHash = createHash("sha256").update(request.headers.authorization ?? "").digest();
    if (!timingSafeEqual(receivedAuthorizationHash, expectedAuthorizationHash)) {
      response.writeHead(401).end(`${JSON.stringify({ error: "Unauthorized" })}\n`);
      return;
    }
    if (request.url === "/health" || request.url === "/manifest") response.end(`${JSON.stringify({ ok: true, ...manifest })}\n`);
    else if (request.url === "/requests") response.end(`${JSON.stringify(edge.requests)}\n`);
    else response.writeHead(404).end(`${JSON.stringify({ error: "not_found" })}\n`);
  });
  try {
    await listen(admin, adminPort);
    await writeFile(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`, { encoding: "utf8", mode: 0o600 });
    await waitForSignal();
  } finally {
    await close(admin).catch(() => undefined);
    await edge.stop();
    await rm(manifestPath, { force: true });
  }
}

function waitForSignal(): Promise<void> {
  return new Promise((resolve) => {
    process.once("SIGTERM", resolve);
    process.once("SIGINT", resolve);
  });
}

const action = process.argv[2];
const manifestPath = option("--manifest", "/tmp/idexal-os-enterprise-tls-edge.json");

if (action === "serve") await serve(manifestPath);
else if (action === "install" || action === "remove") await changeTrust(action, manifestPath);
else if (action === "stop") await stop(manifestPath);
else throw new Error("Usage: enterprise-tls-edge.mts <serve|install|remove|stop> [options]");
