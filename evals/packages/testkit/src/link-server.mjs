import { createServer, request as httpRequest } from "node:http";
import { request as httpsRequest } from "node:https";
import { createHash, timingSafeEqual } from "node:crypto";
import { clearTimeout, setTimeout } from "node:timers";
import { URL } from "node:url";

const HOP_BY_HOP_HEADERS = new Set([
  "connection",
  "keep-alive",
  "proxy-authenticate",
  "proxy-authorization",
  "proxy-connection",
  "te",
  "trailer",
  "transfer-encoding",
  "upgrade",
]);

function cliValue(name, fallback) {
  const index = process.argv.indexOf(`--${name}`);
  return index >= 0 ? process.argv[index + 1] : fallback;
}

function requiredPort(value, name) {
  const port = Number(value);
  if (!Number.isInteger(port) || port < 1 || port > 65_535) throw new Error(`${name} must be a valid port.`);
  return port;
}

const upstream = new URL(cliValue("upstream", process.env.LINK_UPSTREAM));
const dataPort = requiredPort(cliValue("port", process.env.LINK_PORT), "port");
const adminPort = requiredPort(cliValue("admin-port", process.env.LINK_ADMIN_PORT), "admin-port");
const adminToken = process.env.LINK_ADMIN_TOKEN ?? cliValue("admin-token");
if (upstream.protocol !== "http:" && upstream.protocol !== "https:") throw new Error("upstream must use http or https.");
if (typeof adminToken !== "string" || !/^[a-f0-9]{32,}$/.test(adminToken)) throw new Error("admin token must be at least 32 hex characters.");
const expectedAuthorizationHash = createHash("sha256").update(`Bearer ${adminToken}`).digest();

let phase = "default";
let profile = "baseline";
let rules = [];
let bytesPerSec = null;
let offline = false;
let offlineUntil = null;
let offlineTimer = null;
let shuttingDown = false;
const requests = [];
const refusedConnections = Object.create(null);
const dataSockets = new Set();
const adminSockets = new Set();
const stalledSockets = new Set();

function sleep(milliseconds) {
  return new Promise((resolve) => setTimeout(resolve, milliseconds));
}

function isRecord(value) {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function headerText(value) {
  return Array.isArray(value) ? value.join(",") : value ?? "";
}

function forwardedHeaders(source, host) {
  const nominated = new Set(headerText(source.connection).split(",").map((name) => name.trim().toLowerCase()).filter(Boolean));
  const headers = {};
  for (const [name, value] of Object.entries(source)) {
    if (value === undefined || HOP_BY_HOP_HEADERS.has(name) || nominated.has(name)) continue;
    headers[name] = value;
  }
  if (host) headers.host = host;
  return headers;
}

function nonNegativeNumber(value, name) {
  if (typeof value !== "number" || !Number.isFinite(value) || value < 0) throw new Error(`${name} must be a non-negative number.`);
  return value;
}

function positiveInteger(value, name) {
  if (!Number.isInteger(value) || value < 1) throw new Error(`${name} must be a positive integer.`);
  return value;
}

function normalizeRule(value) {
  if (!isRecord(value) || typeof value.kind !== "string") throw new Error("Each rule must be an object with a kind.");
  const pathPrefix = value.pathPrefix === undefined ? "/" : value.pathPrefix;
  if (typeof pathPrefix !== "string") throw new Error("Rule pathPrefix must be a string.");
  const everyNth = value.everyNth === undefined ? null : positiveInteger(value.everyNth, "Rule everyNth");
  let remaining = everyNth === null ? 1 : Infinity;
  if (value.times !== undefined) {
    if (!Number.isInteger(value.times) || value.times < 0) throw new Error("Rule times must be a non-negative integer.");
    remaining = value.times;
  }
  const base = { kind: value.kind, pathPrefix, everyNth, remaining, matches: 0 };
  if (value.kind === "latency") {
    return { ...base, delayMs: nonNegativeNumber(value.delayMs, "Latency delayMs"), jitterMs: nonNegativeNumber(value.jitterMs ?? 0, "Latency jitterMs") };
  }
  if (value.kind === "status") {
    const statusCode = positiveInteger(value.statusCode, "Status statusCode");
    if (statusCode > 999) throw new Error("Status statusCode must be at most 999.");
    return { ...base, statusCode, body: value.body };
  }
  if (value.kind === "reset" || value.kind === "stall") return base;
  throw new Error(`Unsupported rule kind: ${value.kind}`);
}

function takeRule(path) {
  for (const rule of rules) {
    if (rule.remaining <= 0 || !path.startsWith(rule.pathPrefix)) continue;
    rule.matches += 1;
    if (rule.everyNth !== null && rule.matches % rule.everyNth !== 0) continue;
    if (Number.isFinite(rule.remaining)) rule.remaining -= 1;
    return rule;
  }
  return null;
}

function requestEntry(incoming, path, status, fault) {
  requests.push({
    method: incoming.method ?? "GET",
    path,
    status,
    faulted: fault !== null,
    ...(fault === null ? {} : { fault }),
    phase,
    profile,
    at: Date.now(),
  });
}

function writeUpstreamResponse(client, response) {
  const status = response.statusCode ?? 502;
  const headers = forwardedHeaders(response.headers);
  if (response.statusMessage) client.writeHead(status, response.statusMessage, headers);
  else client.writeHead(status, headers);
}

async function pacedPipe(source, destination, rate) {
  const chunkSize = Math.max(1, Math.min(Math.floor(rate / 10), 64 * 1024));
  const intervalMs = 1_000 * chunkSize / rate;
  for await (const value of source) {
    const chunk = Buffer.isBuffer(value) ? value : Buffer.from(value);
    for (let offset = 0; offset < chunk.length; offset += chunkSize) {
      destination.write(chunk.subarray(offset, Math.min(offset + chunkSize, chunk.length)));
      await sleep(intervalMs);
    }
  }
  destination.end();
}

function forward(incoming, client, path, fault) {
  const requested = new URL(path, "http://request-target.invalid");
  const options = {
    protocol: upstream.protocol,
    hostname: upstream.hostname,
    port: upstream.port,
    path: `${requested.pathname}${requested.search}`,
    method: incoming.method ?? "GET",
    headers: forwardedHeaders(incoming.headers, upstream.host),
  };
  const onResponse = (response) => {
    const status = response.statusCode ?? 502;
    requestEntry(incoming, path, status, fault ?? (bytesPerSec === null ? null : "bandwidth"));
    writeUpstreamResponse(client, response);
    const rate = bytesPerSec;
    if (rate === null) {
      response.pipe(client);
      return;
    }
    void pacedPipe(response, client, rate).catch((error) => client.destroy(error));
  };
  const outbound = upstream.protocol === "https:"
    ? httpsRequest(options, onResponse)
    : httpRequest(options, onResponse);
  outbound.on("error", (error) => {
    if (client.destroyed) return;
    if (client.headersSent) {
      client.destroy(error);
      return;
    }
    requestEntry(incoming, path, 502, fault);
    client.writeHead(502, { "content-type": "application/json" });
    client.end(JSON.stringify({ error: error.message }));
  });
  incoming.on("aborted", () => outbound.destroy());
  client.on("close", () => outbound.destroy());
  incoming.pipe(outbound);
}

function destroyStalledSockets() {
  for (const socket of stalledSockets) socket.destroy();
  stalledSockets.clear();
}

function restoreOnline() {
  if (offlineTimer !== null) clearTimeout(offlineTimer);
  offlineTimer = null;
  offline = false;
  offlineUntil = null;
}

function activateOffline(durationMs) {
  restoreOnline();
  offline = true;
  offlineUntil = Date.now() + durationMs;
  for (const socket of dataSockets) socket.destroy();
  stalledSockets.clear();
  offlineTimer = setTimeout(restoreOnline, durationMs);
}

const dataServer = createServer((incoming, response) => {
  void (async () => {
    const path = incoming.url ?? "/";
    const rule = takeRule(path);
    if (rule?.kind === "status") {
      const body = JSON.stringify(rule.body ?? { error: `Injected HTTP ${rule.statusCode}` });
      requestEntry(incoming, path, rule.statusCode, "status");
      response.writeHead(rule.statusCode, {
        "access-control-allow-origin": "*",
        "content-length": Buffer.byteLength(body),
        "content-type": "application/json",
      });
      response.end(body);
      return;
    }
    if (rule?.kind === "reset") {
      requestEntry(incoming, path, 0, "reset");
      response.destroy();
      return;
    }
    if (rule?.kind === "stall") {
      requestEntry(incoming, path, 0, "stall");
      if (incoming.socket) {
        stalledSockets.add(incoming.socket);
        incoming.socket.once("close", () => stalledSockets.delete(incoming.socket));
      }
      incoming.resume();
      return;
    }
    if (rule?.kind === "latency") {
      const span = Math.floor(rule.jitterMs * 2) + 1;
      const jitter = span > 1 ? (rule.matches * 1103515245 + 12345) % span - Math.floor(rule.jitterMs) : 0;
      await sleep(Math.max(0, rule.delayMs + jitter));
    }
    if (response.destroyed) return;
    forward(incoming, response, path, rule === null ? null : "latency");
  })().catch((error) => {
    if (response.destroyed) return;
    if (response.headersSent) {
      response.destroy(error instanceof Error ? error : undefined);
      return;
    }
    response.writeHead(500, { "content-type": "application/json" });
    response.end(JSON.stringify({ error: error instanceof Error ? error.message : String(error) }));
  });
});

dataServer.prependListener("connection", (socket) => {
  if (offline) {
    refusedConnections[phase] = (refusedConnections[phase] ?? 0) + 1;
    socket.destroy();
    return;
  }
  dataSockets.add(socket);
  socket.once("close", () => {
    dataSockets.delete(socket);
    stalledSockets.delete(socket);
  });
});

function readJson(request) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    let length = 0;
    request.on("data", (chunk) => {
      length += chunk.length;
      if (length > 1024 * 1024) {
        reject(new Error("Admin request body is too large."));
        request.destroy();
        return;
      }
      chunks.push(chunk);
    });
    request.on("end", () => {
      try {
        resolve(chunks.length === 0 ? {} : JSON.parse(Buffer.concat(chunks).toString("utf8")));
      } catch (error) {
        reject(error);
      }
    });
    request.on("error", reject);
  });
}

function sendJson(response, status, body) {
  const text = JSON.stringify(body);
  response.writeHead(status, { "content-length": Buffer.byteLength(text), "content-type": "application/json" });
  response.end(text);
}

function isAuthorized(request) {
  const receivedHash = createHash("sha256").update(headerText(request.headers.authorization)).digest();
  return timingSafeEqual(receivedHash, expectedAuthorizationHash);
}

const adminServer = createServer((request, response) => {
  void (async () => {
    if (!isAuthorized(request)) {
      sendJson(response, 401, { error: "Unauthorized" });
      return;
    }
    const path = new URL(request.url ?? "/", "http://admin.invalid").pathname;
    if (request.method === "GET" && path === "/health") {
      sendJson(response, 200, { ok: true, upstream: upstream.href, phase, profile, offline, offlineUntil, rules: rules.length });
      return;
    }
    if (request.method === "GET" && path === "/requests") {
      sendJson(response, 200, { requests, refusedConnections, phase, profile });
      return;
    }
    if (request.method === "GET" && path === "/stats") {
      sendJson(response, 200, {
        requests: requests.length,
        faults: requests.filter((entry) => entry.faulted).length,
        refusedConnections: Object.values(refusedConnections).reduce((total, count) => total + count, 0),
        phase,
        profile,
      });
      return;
    }
    if (request.method !== "POST") {
      sendJson(response, 404, { error: "Not found" });
      return;
    }
    const body = await readJson(request);
    if (!isRecord(body)) throw new Error("Admin body must be a JSON object.");
    if (path === "/phase") {
      if (typeof body.name !== "string" || body.name.length === 0) throw new Error("phase name must be a non-empty string.");
      phase = body.name;
      if (body.profile !== undefined) {
        if (body.profile !== "baseline" && body.profile !== "vpn-flaky-emulated") throw new Error("profile must be baseline or vpn-flaky-emulated.");
        profile = body.profile;
        if (profile === "baseline") {
          rules = [];
          bytesPerSec = null;
        } else {
          // Check the periodic rekey-style reset first. Non-reset requests then
          // fall through to the catch-all 300–800ms latency rule.
          rules = [
            normalizeRule({ kind: "reset", everyNth: 3 }),
            normalizeRule({ kind: "latency", delayMs: 550, jitterMs: 250, everyNth: 1 }),
          ];
          bytesPerSec = 256 * 1024;
        }
      }
    } else if (path === "/rules") {
      if (!Array.isArray(body.rules)) throw new Error("rules must be an array.");
      rules = body.rules.map(normalizeRule);
    } else if (path === "/bandwidth") {
      if (body.bytesPerSec === null) bytesPerSec = null;
      else bytesPerSec = positiveInteger(body.bytesPerSec, "bytesPerSec");
    } else if (path === "/offline") {
      activateOffline(nonNegativeNumber(body.durationMs, "durationMs"));
    } else if (path === "/clear") {
      rules = [];
      bytesPerSec = null;
      profile = "baseline";
      restoreOnline();
      destroyStalledSockets();
    } else {
      sendJson(response, 404, { error: "Not found" });
      return;
    }
    sendJson(response, 200, { ok: true });
  })().catch(() => {
    if (!response.headersSent) sendJson(response, 400, { error: "Invalid request" });
    else response.destroy();
  });
});

adminServer.on("connection", (socket) => {
  adminSockets.add(socket);
  socket.once("close", () => adminSockets.delete(socket));
});

function listen(server, port, host) {
  return new Promise((resolve, reject) => {
    server.once("error", reject);
    server.listen(port, host, resolve);
  });
}

function close(server) {
  return new Promise((resolve) => server.close(() => resolve()));
}

async function shutdown() {
  if (shuttingDown) return;
  shuttingDown = true;
  restoreOnline();
  for (const socket of dataSockets) socket.destroy();
  for (const socket of adminSockets) socket.destroy();
  await Promise.all([close(dataServer), close(adminServer)]);
}

process.on("SIGTERM", () => void shutdown().then(() => process.exit(0)));
process.on("SIGINT", () => void shutdown().then(() => process.exit(0)));

await Promise.all([
  listen(dataServer, dataPort, "0.0.0.0"),
  listen(adminServer, adminPort, "0.0.0.0"),
]);
console.log(`link-server listening data=${dataPort} admin=${adminPort} upstream=${upstream.href}`);
