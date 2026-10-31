import assert from "node:assert/strict";
import { execFile } from "node:child_process";
import { createServer, type Server } from "node:http";
import { mkdtemp, writeFile, readFile, mkdir, rm } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { promisify } from "node:util";
import { test } from "node:test";
import { localEvidenceReady, parseGuestHealth } from "./health.ts";

const exec = promisify(execFile);
async function listen(server: Server) {
  await new Promise<void>((resolve) => server.listen(0, "127.0.0.1", resolve));
  const address = server.address();
  assert.ok(address && typeof address !== "string");
  return address.port;
}
async function close(server: Server) { await new Promise<void>((resolve) => server.close(() => resolve())); }

test("real guest script probes CDP with private cookie and emits only bounded allowlisted facts", async () => {
  const dir = await mkdtemp(join(tmpdir(), "daytona-guest-health-"));
  const secret = "fixture-private-cookie";
  let cookie = "";
  let chromeAlive = true;
  let oversized = false;
  const chrome = createServer((_req, res) => { res.writeHead(chromeAlive ? 200 : 503, { "content-type": "application/json" }); res.end(JSON.stringify(chromeAlive ? { Browser: "Chrome/123", webSocketDebuggerUrl: "ws://127.0.0.1/private-browser", ...(oversized ? { padding: "x".repeat(100_000) } : {}) } : { private: secret })); });
  const gateway = createServer((req, res) => { cookie = req.headers.cookie ?? ""; res.setHeader("content-type", "application/json"); res.end(JSON.stringify({ Browser: "Chrome/123", webSocketDebuggerUrl: "ws://127.0.0.1/private-browser" })); });
  try {
    const chromePort = await listen(chrome);
    const gatewayPort = await listen(gateway);
    await writeFile(join(dir, "services.json"), JSON.stringify({ cdp: `http://127.0.0.1:${chromePort}`, private: secret }));
    await writeFile(join(dir, "access.json"), JSON.stringify({ token: secret, expiresAt: new Date(Date.now() + 60_000).toISOString() }));
    await mkdir(join(dir, "bin"));
    await writeFile(join(dir, "bin", "systemctl"), `#!/bin/sh\ncase "$*" in\n*Environment*) printf '%s\\n' 'Environment=IDEXAL_PREVIEW_UPSTREAM_PORT=${chromePort} PRIVATE=${secret}';;\n*) printf '%s\\n' 'ActiveState=active' 'SubState=running' 'Result=success' 'ExecMainStatus=0';;\nesac\n`, { mode: 0o700 });
    await writeFile(join(dir, "bin", "journalctl"), `#!/bin/sh\nprintf '%s\\n' 'private=${secret} https://signed.example/?token=${secret}' 'ECONNREFUSED' 'segfault'\n`, { mode: 0o700 });
    await writeFile(join(dir, "bin", "ss"), `#!/bin/sh\nprintf '%s\\n' 'LISTEN 0 511 127.0.0.1:${chromePort} 0.0.0.0:*'\n`, { mode: 0o700 });
    await mkdir(join(dir, "surfaces", "123", "evidence-web-123"), { recursive: true });
    await writeFile(join(dir, "surfaces", "123", "evidence-web-123", "chrome.log"), `${secret}\n${"x".repeat(20_000)}\nchrome crash\n`);
    const source = (await readFile(new URL("./guest-health.mjs", import.meta.url), "utf8"))
      .replace('const root = "/opt/idexal-os-preview";', `const root = ${JSON.stringify(dir)};`)
      .replace('const surfaces = "/workspace/evals/results/.surfaces";', `const surfaces = ${JSON.stringify(join(dir, "surfaces"))};`)
      .replace('http://127.0.0.1:8081/json/version', `http://127.0.0.1:${gatewayPort}/json/version`);
    const run = async () => {
      const { stdout } = await exec(process.execPath, ["--input-type=module", "-e", source], { env: { ...process.env, PATH: `${join(dir, "bin")}:${process.env.PATH}` }, timeout: 10_000 });
      assert.ok(!stdout.includes(secret)); assert.ok(!stdout.includes("signed.example")); assert.ok(!stdout.includes("private-browser"));
      return parseGuestHealth(stdout);
    };
    const healthy = await run();
    assert.equal(cookie, `__Host-idexal-os-preview=${secret}`);
    assert.equal(localEvidenceReady(healthy), true);
    assert.deepEqual(healthy.journals[0]?.events, ["refused", "crash"]);
    assert.deepEqual(healthy.browserEvents, ["crash"]);
    assert.deepEqual(healthy.listeners, [chromePort]);
    oversized = true;
    const largeBody = await run();
    assert.equal(largeBody.chrome.status, 200); assert.equal(largeBody.chrome.cdp, false);
    assert.equal(localEvidenceReady(largeBody), false);
    oversized = false;
    chromeAlive = false;
    const deadChrome = await run();
    assert.equal(deadChrome.chrome.status, 503); assert.equal(localEvidenceReady(deadChrome), false);
    await writeFile(join(dir, "services.json"), "null");
    const missingServices = await run();
    assert.equal(missingServices.chromePort, 0); assert.equal(missingServices.units[0]?.active, "active");
    await close(gateway);
    const deadGateway = await run();
    assert.equal(deadGateway.gateway.status, 0); assert.equal(localEvidenceReady(deadGateway), false);
  } finally {
    await close(chrome); await close(gateway); await rm(dir, { recursive: true, force: true });
  }
});
