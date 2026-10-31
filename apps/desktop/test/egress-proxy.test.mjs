import assert from "node:assert/strict";
import { createServer, request as requireRequest } from "node:http";
import { connect as netConnect, createServer as createNetServer } from "node:net";
import { test } from "node:test";

import { rotateProxyProfile, startGuardProxy } from "../electron/egress-proxy.mjs";

function listen(server) {
  return new Promise((resolve, reject) => {
    server.once("error", reject);
    server.listen(0, "127.0.0.1", () => {
      server.removeListener("error", reject);
      const address = server.address();
      if (!address || typeof address === "string") {
        reject(new Error("Missing listener address"));
        return;
      }
      resolve(address.port);
    });
  });
}

function close(server) {
  return new Promise((resolve) => server.close(() => resolve(undefined)));
}

function requestThroughGuard(guard, pathName, headers = {}) {
  return new Promise((resolve, reject) => {
    const request = requireRequest({
      host: guard.host,
      port: guard.port,
      method: "GET",
      path: pathName,
      headers,
    }, (response) => {
      let body = "";
      response.setEncoding("utf8");
      response.on("data", (chunk) => { body += chunk; });
      response.on("end", () => resolve({ status: response.statusCode, body }));
    });
    request.once("error", reject);
    request.end();
  });
}

function connectThroughGuard(guard, authority, headers = {}) {
  return new Promise((resolve, reject) => {
    const request = requireRequest({
      host: guard.host,
      port: guard.port,
      method: "CONNECT",
      path: authority,
      headers,
    });
    request.once("connect", (response, socket, head) => {
      let body = "";
      socket.setEncoding("utf8");
      socket.on("data", (chunk) => { body += chunk; });
      socket.once("error", reject);
      if (head?.length) body += head.toString("utf8");
      socket.write(`GET /tunnel HTTP/1.1\r\nHost: ${authority}\r\nConnection: close\r\n\r\n`);
      socket.on("end", () => resolve({ status: response.statusCode, body }));
    });
    request.once("error", reject);
    request.end();
  });
}

function basic(username, password) {
  return `Basic ${Buffer.from(`${username}:${password}`, "utf8").toString("base64")}`;
}

/** A minimal SOCKS5 proxy so the guard's upstream handshake is exercised. */
function socks5Proxy({ username = "", password = "", earlyResponse = "" } = {}) {
  const server = createNetServer();
  server.on("connection", (socket) => {
    let buffer = Buffer.alloc(0);
    let stage = "greeting";
    const onData = (chunk) => {
      buffer = Buffer.concat([buffer, chunk]);
      if (stage === "greeting") {
        if (buffer.length < 2 || buffer.length < 2 + buffer[1]) return;
        const methodCount = buffer[1];
        buffer = buffer.subarray(2 + methodCount);
        socket.write(Buffer.from(username ? [0x05, 0x02] : [0x05, 0x00]));
        stage = username ? "auth" : "request";
      }
      if (stage === "auth") {
        if (buffer.length < 3) return;
        const userLength = buffer[1];
        if (buffer.length < 3 + userLength) return;
        const passwordLength = buffer[2 + userLength];
        if (buffer.length < 3 + userLength + passwordLength) return;
        const user = buffer.subarray(2, 2 + userLength).toString("utf8");
        const pass = buffer.subarray(3 + userLength, 3 + userLength + passwordLength).toString("utf8");
        buffer = buffer.subarray(3 + userLength + passwordLength);
        if (user !== username || pass !== password) {
          socket.end(Buffer.from([0x01, 0x01]));
          return;
        }
        socket.write(Buffer.from([0x01, 0x00]));
        stage = "request";
      }
      if (stage === "request") {
        if (buffer.length < 5) return;
        const atyp = buffer[3];
        const addressLength = atyp === 0x01 ? 4 : atyp === 0x04 ? 16 : atyp === 0x03 ? 1 + buffer[4] : -1;
        if (addressLength < 0) {
          socket.destroy();
          return;
        }
        const total = 4 + addressLength + 2;
        if (buffer.length < total) return;
        const host = atyp === 0x01
          ? [...buffer.subarray(4, 8)].join(".")
          : atyp === 0x04
            ? "::1"
            : buffer.subarray(5, 5 + buffer[4]).toString("utf8");
        const port = buffer.readUInt16BE(total - 2);
        buffer = buffer.subarray(total);
        socket.off("data", onData);
        const reply = Buffer.from([0x05, 0x00, 0x00, 0x01, 0, 0, 0, 0, 0, 0]);
        if (earlyResponse) {
          // Send a complete response in the same packet as the SOCKS reply,
          // proving the guard preserves bytes that share that packet.
          socket.write(Buffer.concat([reply, Buffer.from(earlyResponse, "utf8")]));
          socket.pause();
          setTimeout(() => socket.destroy(), 25);
          return;
        }
        // Park the client until the target connects so early request bytes are
        // not discarded between the handshake and the tunnel.
        socket.pause();
        const upstream = netConnect(port, host, () => {
          socket.write(reply);
          if (buffer.length) upstream.write(buffer);
          socket.pipe(upstream);
          upstream.pipe(socket);
        });
        upstream.once("error", () => socket.destroy());
        socket.once("error", () => upstream.destroy());
      }
    };
    socket.on("data", onData);
  });
  return server;
}

async function fixture({ upstreamAuth = true } = {}) {
  const target = createServer((request, response) => {
    response.writeHead(200, { "Content-Type": "text/plain" });
    response.end(`target:${request.url}`);
  });
  const targetPort = await listen(target);

  const upstream = createServer((_request, response) => {
    response.writeHead(405, { "Content-Type": "text/plain" });
    response.end("CONNECT only");
  });
  upstream.on("connect", (request, clientSocket, head) => {
    if (upstreamAuth && request.headers["proxy-authorization"] !== basic("upstream", "upstream-pass")) {
      clientSocket.end("HTTP/1.1 407 Proxy Authentication Required\r\nProxy-Authorization: Basic realm=\"upstream\"\r\n\r\n");
      return;
    }
    const [host, port] = String(request.url ?? "").split(":");
    const upstreamSocket = netConnect(Number(port), host, () => {
      clientSocket.write("HTTP/1.1 200 Connection Established\r\n\r\n");
      if (head?.length) upstreamSocket.write(head);
      clientSocket.pipe(upstreamSocket);
      upstreamSocket.pipe(clientSocket);
    });
    upstreamSocket.once("error", () => clientSocket.destroy());
    clientSocket.once("error", () => upstreamSocket.destroy());
  });
  const upstreamPort = await listen(upstream);

  return {
    targetPort,
    upstreamPort,
    async close() {
      await close(upstream);
      await close(target);
    },
  };
}

test("guard proxy rejects requests without its per-start credentials", async () => {
  const servers = await fixture();
  const guard = await startGuardProxy({
    profile: {
      protocol: "http",
      host: "127.0.0.1",
      port: servers.upstreamPort,
      username: "upstream",
      password: "upstream-pass",
    },
  });

  try {
    const response = await requestThroughGuard(guard, `http://127.0.0.1:${servers.targetPort}/hello`);
    assert.equal(response.status, 407);
    assert.match(response.body, /authentication required/i);
  } finally {
    await guard.close();
    await servers.close();
  }
});

test("guard proxy chains plain HTTP requests through an authenticated upstream proxy", async () => {
  const servers = await fixture();
  const guard = await startGuardProxy({
    profile: {
      protocol: "http",
      host: "127.0.0.1",
      port: servers.upstreamPort,
      username: "upstream",
      password: "upstream-pass",
    },
  });

  try {
    const response = await requestThroughGuard(
      guard,
      `http://127.0.0.1:${servers.targetPort}/hello`,
      { "Proxy-Authorization": basic(guard.username, guard.password) },
    );

    assert.equal(response.status, 200);
    assert.equal(response.body, "target:/hello");
  } finally {
    await guard.close();
    await servers.close();
  }
});

test("guard proxy accounts for proxied bytes", async () => {
  const servers = await fixture();
  const guard = await startGuardProxy({
    profile: {
      protocol: "http",
      host: "127.0.0.1",
      port: servers.upstreamPort,
      username: "upstream",
      password: "upstream-pass",
    },
  });

  try {
    const response = await requestThroughGuard(
      guard,
      `http://127.0.0.1:${servers.targetPort}/metered`,
      { "Proxy-Authorization": basic(guard.username, guard.password) },
    );

    assert.equal(response.status, 200);
    assert.ok(guard.usage() > 0, "proxied traffic must be counted");
    assert.equal(guard.limitBytes(), null);
  } finally {
    await guard.close();
    await servers.close();
  }
});

test("guard proxy stops traffic once the monthly bandwidth limit is reached", async () => {
  const servers = await fixture();
  const guard = await startGuardProxy({
    profile: {
      protocol: "http",
      host: "127.0.0.1",
      port: servers.upstreamPort,
      username: "upstream",
      password: "upstream-pass",
    },
    usageStartBytes: 0,
    byteLimit: 0,
  });

  try {
    const headers = { "Proxy-Authorization": basic(guard.username, guard.password) };
    const limited = await requestThroughGuard(guard, `http://127.0.0.1:${servers.targetPort}/blocked`, headers);
    assert.equal(limited.status, 429);
    assert.match(limited.body, /bandwidth limit/i);

    guard.setLimitBytes(null);
    const allowed = await requestThroughGuard(guard, `http://127.0.0.1:${servers.targetPort}/allowed`, headers);
    assert.equal(allowed.status, 200);
  } finally {
    await guard.close();
    await servers.close();
  }
});

test("guard proxy tunnels CONNECT traffic through the upstream", async () => {
  const servers = await fixture();
  const guard = await startGuardProxy({
    profile: {
      protocol: "http",
      host: "127.0.0.1",
      port: servers.upstreamPort,
      username: "upstream",
      password: "upstream-pass",
    },
  });

  try {
    const response = await connectThroughGuard(
      guard,
      `127.0.0.1:${servers.targetPort}`,
      { "Proxy-Authorization": basic(guard.username, guard.password) },
    );

    assert.equal(response.status, 200);
    assert.match(response.body, /target:\/tunnel/);
  } finally {
    await guard.close();
    await servers.close();
  }
});

test("guard proxy reports an upstream authentication failure instead of falling back direct", async () => {
  const servers = await fixture();
  const guard = await startGuardProxy({
    profile: {
      protocol: "http",
      host: "127.0.0.1",
      port: servers.upstreamPort,
      username: "upstream",
      password: "wrong-password",
    },
  });

  try {
    const response = await requestThroughGuard(
      guard,
      `http://127.0.0.1:${servers.targetPort}/hello`,
      { "Proxy-Authorization": basic(guard.username, guard.password) },
    );

    assert.equal(response.status, 502);
    assert.match(response.body, /rejected the username or password/i);
  } finally {
    await guard.close();
    await servers.close();
  }
});

test("guard proxy fails closed when the upstream is unreachable", async () => {
  const guard = await startGuardProxy({
    profile: {
      protocol: "http",
      host: "127.0.0.1",
      port: 1,
      username: "",
      password: "",
    },
  });

  try {
    const response = await requestThroughGuard(
      guard,
      "http://127.0.0.1:9/hello",
      { "Proxy-Authorization": basic(guard.username, guard.password) },
    );

    assert.equal(response.status, 502);
    assert.match(response.body, /upstream proxy error/i);
  } finally {
    await guard.close();
  }
});

test("guard proxy chains plain HTTP through a SOCKS5 upstream", async () => {
  const servers = await fixture();
  const socks = socks5Proxy();
  const socksPort = await listen(socks);
  const guard = await startGuardProxy({
    profile: {
      protocol: "socks5",
      host: "127.0.0.1",
      port: socksPort,
      username: "",
      password: "",
    },
  });

  try {
    const response = await requestThroughGuard(
      guard,
      `http://127.0.0.1:${servers.targetPort}/socks`,
      { "Proxy-Authorization": basic(guard.username, guard.password) },
    );

    assert.equal(response.status, 200);
    assert.equal(response.body, "target:/socks");
  } finally {
    await guard.close();
    await close(socks);
    await servers.close();
  }
});

test("guard proxy authenticates to a SOCKS5 upstream and tunnels CONNECT", async () => {
  const servers = await fixture();
  const socks = socks5Proxy({ username: "socks-user", password: "socks-pass" });
  const socksPort = await listen(socks);
  const guard = await startGuardProxy({
    profile: {
      protocol: "socks5",
      host: "127.0.0.1",
      port: socksPort,
      username: "socks-user",
      password: "socks-pass",
    },
  });

  try {
    const response = await connectThroughGuard(
      guard,
      `127.0.0.1:${servers.targetPort}`,
      { "Proxy-Authorization": basic(guard.username, guard.password) },
    );

    assert.equal(response.status, 200);
    assert.match(response.body, /target:\/tunnel/);
  } finally {
    await guard.close();
    await close(socks);
    await servers.close();
  }
});

test("guard proxy preserves SOCKS5 bytes that share the reply packet", async () => {
  const earlyResponse = "HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nContent-Length: 5\r\nConnection: close\r\n\r\nearly";
  const socks = socks5Proxy({ earlyResponse });
  const socksPort = await listen(socks);
  const guard = await startGuardProxy({
    profile: {
      protocol: "socks5",
      host: "127.0.0.1",
      port: socksPort,
      username: "",
      password: "",
    },
  });

  try {
    const response = await requestThroughGuard(
      guard,
      "http://early.test/",
      { "Proxy-Authorization": basic(guard.username, guard.password) },
    );

    assert.equal(response.status, 200);
    assert.equal(response.body, "early");
  } finally {
    await guard.close();
    await close(socks);
  }
});

test("rotating residential calls the provider rotation endpoint", async () => {
  let hits = 0;
  const server = createServer((_request, response) => {
    hits += 1;
    response.writeHead(200, { "content-type": "text/plain" });
    response.end("rotated");
  });
  const port = await listen(server);

  try {
    const result = await rotateProxyProfile({
      rotationUrl: `http://127.0.0.1:${port}/rotate?key=test`,
    });

    assert.equal(hits, 1);
    assert.equal(result.ok, true);
    assert.equal(result.status, 200);
    assert.equal(result.message, "rotated");
    assert.equal(result.error, null);
  } finally {
    await close(server);
  }
});

test("rotation without a URL fails before any request", async () => {
  const result = await rotateProxyProfile({ rotationUrl: "" });

  assert.equal(result.ok, false);
  assert.equal(result.status, null);
  assert.match(result.error ?? "", /no rotation URL/i);
});
