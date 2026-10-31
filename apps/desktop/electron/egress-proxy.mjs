import { createServer } from "node:http";
import { connect as netConnect, isIP } from "node:net";
import { connect as tlsConnect } from "node:tls";
import { randomBytes, timingSafeEqual } from "node:crypto";
import { fetchFiniteDesktopHttp } from "./finite-http-fetch.mjs";


const MAX_HEADER_BYTES = 32 * 1024;
const CONNECT_TIMEOUT_MS = 20_000;

/**
 * A loopback-only HTTP proxy that authenticates every request and forwards it
 * through exactly one upstream. Browser and app sessions point here, so no
 * page can quietly bypass the configured egress path.
 *
 * @param {{ profile: Record<string, any>, host?: string, port?: number, timeoutMs?: number, usageStartBytes?: number, byteLimit?: number | null }} options
 */
export async function startGuardProxy({ profile, host = "127.0.0.1", port = 0, timeoutMs = CONNECT_TIMEOUT_MS, usageStartBytes = 0, byteLimit = null }) {
  const auth = {
    username: "idexal",
    password: randomBytes(18).toString("hex"),
  };
  const sockets = new Set();
  let usedBytes = Math.max(0, Number(usageStartBytes) || 0);
  let limitBytes = byteLimit === null || byteLimit === undefined ? null : Math.max(0, Number(byteLimit));
  const exceeded = () => limitBytes !== null && usedBytes >= limitBytes;
  const addBytes = (count) => {
    usedBytes += Math.max(0, Number(count) || 0);
    if (exceeded()) {
      for (const socket of sockets) socket.destroy();
      sockets.clear();
    }
  };
  /** @type {ReturnType<typeof createUpstreamConnector>} */
  const upstream = createUpstreamConnector(profile, timeoutMs);

  const server = createServer((request, response) => {
    const clientSocket = request.socket;
    if (exceeded()) {
      response.writeHead(429, { "Content-Type": "text/plain; charset=utf-8", Connection: "close" });
      response.end("Proxy bandwidth limit reached.");
      return;
    }
    if (!authorized(request.headers["proxy-authorization"], auth)) {
      response.writeHead(407, {
        "Proxy-Authenticate": 'Basic realm="idexal Os"',
        "Content-Type": "text/plain; charset=utf-8",
        Connection: "close",
      });
      response.end("Proxy authentication required.");
      return;
    }

    let target;
    try {
      target = new URL(String(request.url ?? ""));
      if (target.protocol !== "http:" && target.protocol !== "https:") throw new Error("unsupported scheme");
    } catch {
      response.writeHead(400, { "Content-Type": "text/plain; charset=utf-8", Connection: "close" });
      response.end("This guard only accepts absolute HTTP URLs.");
      return;
    }

    const targetPort = Number(target.port || (target.protocol === "https:" ? 443 : 80));
    upstream.connect(target.hostname, targetPort)
      .then(({ socket, tunnel }) => {
        if (clientSocket.destroyed) {
          socket.destroy();
          return;
        }
        sockets.add(socket);
        socket.once("close", () => sockets.delete(socket));
        request.on("data", (chunk) => addBytes(chunk.length));
        socket.on("data", (chunk) => addBytes(chunk.length));
        // The upstream connection is already a tunnel, so the upstream reply
        // is a raw HTTP message. Write it straight to the client socket; using
        // ServerResponse here would add a second status line and header block.
        const headers = stripHopByHop(request.headers);
        headers.connection = "close";
        const path = tunnel ? `${target.pathname}${target.search}` : target.toString();
        const lines = [`${request.method ?? "GET"} ${path} HTTP/${request.httpVersion}`];
        for (const [name, value] of Object.entries(headers)) {
          if (value === undefined) continue;
          if (Array.isArray(value)) {
            for (const item of value) lines.push(`${name}: ${item}`);
          } else {
            lines.push(`${name}: ${value}`);
          }
        }
        socket.write(`${lines.join("\r\n")}\r\n\r\n`);
        // Do not half-close the upstream when the client request ends: the
        // SOCKS/HTTP proxy may still be delivering the response, and Node's
        // default allowHalfOpen=false would discard it. `Connection: close`
        // above lets the target close the tunnel when it is done.
        request.pipe(socket, { end: false });
        socket.pipe(clientSocket);
        socket.on("error", () => clientSocket.destroy());
      })
      .catch((error) => {
        if (!clientSocket.destroyed) {
          clientSocket.end(
            "HTTP/1.1 502 Bad Gateway\r\nContent-Type: text/plain; charset=utf-8\r\nConnection: close\r\n\r\n"
            + `Upstream proxy error: ${error instanceof Error ? error.message : "unknown"}`,
          );
        }
      });
  });

  server.on("connect", (request, clientSocket, head) => {
    if (exceeded()) {
      clientSocket.end("HTTP/1.1 429 Too Many Requests\r\nConnection: close\r\n\r\nProxy bandwidth limit reached.");
      return;
    }
    if (!authorized(request.headers["proxy-authorization"], auth)) {
      clientSocket.end('HTTP/1.1 407 Proxy Authentication Required\r\nProxy-Authenticate: Basic realm="idexal Os"\r\n\r\n');
      return;
    }

    let target;
    try {
      target = parseHostPort(String(request.url ?? ""));
    } catch {
      clientSocket.end("HTTP/1.1 400 Bad Request\r\n\r\n");
      return;
    }

    upstream.connect(target.host, target.port)
      .then(({ socket }) => {
        sockets.add(socket);
        sockets.add(clientSocket);
        socket.once("close", () => {
          sockets.delete(socket);
          sockets.delete(clientSocket);
        });
        clientSocket.once("close", () => sockets.delete(clientSocket));
        clientSocket.on("data", (chunk) => addBytes(chunk.length));
        socket.on("data", (chunk) => addBytes(chunk.length));
        clientSocket.write("HTTP/1.1 200 Connection Established\r\n\r\n");
        if (head?.length) socket.write(head);
        clientSocket.pipe(socket);
        socket.pipe(clientSocket);
        socket.on("error", () => clientSocket.destroy());
        clientSocket.on("error", () => socket.destroy());
      })
      .catch((error) => {
        clientSocket.end(`HTTP/1.1 502 Bad Gateway\r\n\r\n${error instanceof Error ? error.message : "upstream error"}`);
      });
  });

  server.on("clientError", (_error, socket) => {
    socket.end("HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n");
  });

  await new Promise((resolve, reject) => {
    server.once("error", reject);
    server.listen(port, host, () => {
      server.removeListener("error", reject);
      resolve(undefined);
    });
  });

  const address = server.address();
  const actualPort = typeof address === "object" && address ? address.port : port;

  return {
    host,
    port: actualPort,
    username: auth.username,
    password: auth.password,
    url: `http://${auth.username}:${auth.password}@${formatHostForUrl(host)}:${actualPort}`,
    usage: () => usedBytes,
    limitBytes: () => limitBytes,
    setLimitBytes: (value) => {
      limitBytes = value === null || value === undefined ? null : Math.max(0, Number(value));
    },
    close: () => new Promise((resolve) => {
      for (const socket of sockets) socket.destroy();
      sockets.clear();
      server.close(() => resolve(undefined));
      // Do not wait forever for a keep-alive client that ignored destroy().
      setTimeout(resolve, 1500).unref?.();
    }),
  };
}

/**
 * @param {Record<string, any>} profile
 * @param {number} [timeoutMs]
 */
export function createUpstreamConnector(profile, timeoutMs = CONNECT_TIMEOUT_MS) {
  const protocol = String(profile?.protocol ?? "http").toLowerCase();
  const host = String(profile?.host ?? "");
  const port = Number(profile?.port ?? 0);
  const username = String(profile?.username ?? "");
  const password = String(profile?.password ?? "");

  if (!host || !Number.isInteger(port) || port < 1 || port > 65535) {
    throw new Error("The active proxy profile has no valid host and port.");
  }

  return {
    protocol,
    /**
     * @param {string} targetHost
     * @param {number} targetPort
     * @returns {Promise<{ socket: import("node:net").Socket, tunnel: boolean }>}
     */
    async connect(targetHost, targetPort) {
      if (protocol === "socks5") {
        const socket = await connectTcp(host, port, timeoutMs);
        try {
          await socks5Handshake(socket, targetHost, targetPort, username, password, timeoutMs);
          return { socket, tunnel: true };
        } catch (error) {
          socket.destroy();
          throw error;
        }
      }

      if (protocol === "https" || protocol === "http") {
        const socket = protocol === "https"
          ? await connectTls(host, port, timeoutMs)
          : await connectTcp(host, port, timeoutMs);
        try {
          // CONNECT keeps TLS end-to-end for HTTPS targets and lets the guard
          // forward plain HTTP requests through the proxy unchanged.
          const tunnel = await httpProxyConnect(socket, targetHost, targetPort, username, password, timeoutMs);
          return { socket: tunnel, tunnel: true };
        } catch (error) {
          socket.destroy();
          throw error;
        }
      }

      throw new Error(`Unsupported proxy protocol: ${protocol}`);
    },
  };
}

/**
 * Open one tunnel through the profile's upstream and ask a neutral endpoint
 * what IP and country the exit node uses.
 *
 * @param {Record<string, any>} profile
 * @param {number} [timeoutMs]
 * @returns {Promise<import("@idexal/types/desktop-ipc").ProxyTestResult>}
 */
export async function testProxyProfile(profile, timeoutMs = 15_000) {
  const startedAt = Date.now();
  let socket = null;
  try {
    const connector = createUpstreamConnector(profile, timeoutMs);
    const result = await connector.connect("ipinfo.io", 443);
    socket = tlsConnect({ socket: result.socket, servername: "ipinfo.io", rejectUnauthorized: true });
    await onceWithTimeout(socket, "secureConnect", timeoutMs);
    const body = await httpGetOverTls(socket, "ipinfo.io", "/json", timeoutMs);
    const parsed = JSON.parse(body);
    return {
      ok: true,
      status: 200,
      latencyMs: Date.now() - startedAt,
      ip: typeof parsed.ip === "string" ? parsed.ip : null,
      country: typeof parsed.country === "string" ? parsed.country : null,
      error: null,
    };
  } catch (error) {
    return {
      ok: false,
      status: null,
      latencyMs: Date.now() - startedAt,
      ip: null,
      country: null,
      error: error instanceof Error ? error.message : "Proxy test failed",
    };
  } finally {
    socket?.destroy();
  }
}

/**
 * Ask a rotating-residential provider to switch the exit IP. The endpoint
 * carries the provider key, so it uses the same process egress as the guard
 * (including HTTP(S)_PROXY) and never logs the URL.
 *
 * @param {Record<string, any>} profile
 * @param {number} [timeoutMs]
 * @param {(url: string, init?: RequestInit) => Promise<Response>} [externalFetch]
 *   Optional fetch implementation for non-loopback rotation URLs. Defaults to
 *   the global fetch. Production callers should pass Electron's `net.fetch`
 *   so external requests retain Chromium's certificate trust and proxy path.
 * @returns {Promise<import("@idexal/types/desktop-ipc").ProxyRotateResult>}
 */
export async function rotateProxyProfile(profile, timeoutMs = 20_000, externalFetch = globalThis.fetch) {
  const rotationUrl = String(profile?.rotationUrl ?? "").trim();
  if (!rotationUrl) {
    return { ok: false, status: null, message: null, error: "This profile has no rotation URL." };
  }
  try {
    const response = await fetchFiniteDesktopHttp(
      rotationUrl,
      {
        method: "GET",
        signal: AbortSignal.timeout(timeoutMs),
        headers: { "user-agent": "idexal-os-proxy-rotate/1.0", accept: "text/plain, application/json" },
      },
      externalFetch,
    );
    const body = await response.text().catch(() => "");
    return {
      ok: response.ok,
      status: response.status,
      message: body.trim().slice(0, 500) || null,
      error: response.ok ? null : `The rotation endpoint returned HTTP ${response.status}.`,
    };
  } catch (error) {
    return {
      ok: false,
      status: null,
      message: null,
      error: error instanceof Error ? error.message : "Rotation request failed.",
    };
  }
}

/**
 * @param {string} host
 * @param {number} port
 * @param {number} timeoutMs
 */
function connectTcp(host, port, timeoutMs) {
  return new Promise((resolve, reject) => {
    const socket = netConnect({ host, port });
    const timer = setTimeout(() => {
      socket.destroy();
      reject(new Error(`Connection to ${host}:${port} timed out.`));
    }, timeoutMs);
    socket.once("connect", () => {
      clearTimeout(timer);
      socket.setTimeout(0);
      resolve(socket);
    });
    socket.once("error", (error) => {
      clearTimeout(timer);
      reject(error);
    });
  });
}

/**
 * @param {string} host
 * @param {number} port
 * @param {number} timeoutMs
 */
function connectTls(host, port, timeoutMs) {
  return new Promise((resolve, reject) => {
    const socket = tlsConnect({ host, port, servername: host, rejectUnauthorized: true });
    const timer = setTimeout(() => {
      socket.destroy();
      reject(new Error(`TLS connection to ${host}:${port} timed out.`));
    }, timeoutMs);
    socket.once("secureConnect", () => {
      clearTimeout(timer);
      resolve(socket);
    });
    socket.once("error", (error) => {
      clearTimeout(timer);
      reject(error);
    });
  });
}

/**
 * @param {import("node:net").Socket} socket
 * @param {string} targetHost
 * @param {number} targetPort
 * @param {string} username
 * @param {string} password
 * @param {number} timeoutMs
 */
async function httpProxyConnect(socket, targetHost, targetPort, username, password, timeoutMs) {
  const authority = `${formatHostForUrl(targetHost)}:${targetPort}`;
  const lines = [
    `CONNECT ${authority} HTTP/1.1`,
    `Host: ${authority}`,
    "Proxy-Connection: keep-alive",
  ];
  if (username) {
    lines.push(`Proxy-Authorization: ${basicAuth(username, password)}`);
  }
  socket.write(`${lines.join("\r\n")}\r\n\r\n`);
  const header = await readHeaders(socket, timeoutMs);
  const status = Number(header.split("\r\n", 1)[0]?.split(" ")[1]);
  if (status !== 200) {
    throw new Error(status === 407 ? "The upstream proxy rejected the username or password." : `The upstream proxy returned HTTP ${status || "?"}.`);
  }
  return socket;
}

/**
 * @param {import("node:net").Socket} socket
 * @param {string} targetHost
 * @param {number} targetPort
 * @param {string} username
 * @param {string} password
 * @param {number} timeoutMs
 */
async function socks5Handshake(socket, targetHost, targetPort, username, password, timeoutMs) {
  const wantsAuth = Boolean(username);
  const reader = createHandshakeReader(socket, timeoutMs);
  try {
    socket.write(Buffer.from(wantsAuth ? [0x05, 0x02, 0x00, 0x02] : [0x05, 0x01, 0x00]));
    const greeting = await reader.read(2);
    if (greeting[0] !== 0x05) throw new Error("The SOCKS5 proxy returned an invalid greeting.");
    if (greeting[1] === 0x02) {
      const user = Buffer.from(username, "utf8");
      const pass = Buffer.from(password, "utf8");
      if (user.length > 255 || pass.length > 255) throw new Error("The SOCKS5 username or password is too long.");
      socket.write(Buffer.concat([Buffer.from([0x01, user.length]), user, Buffer.from([pass.length]), pass]));
      const reply = await reader.read(2);
      if (reply[1] !== 0x00) throw new Error("The SOCKS5 proxy rejected the username or password.");
    } else if (greeting[1] !== 0x00) {
      throw new Error("The SOCKS5 proxy requires an authentication method this app does not support.");
    }

    const address = isIP(targetHost) === 6
      ? Buffer.concat([Buffer.from([0x04]), ipv6Bytes(targetHost)])
      : isIP(targetHost) === 4
        ? Buffer.from([0x01, ...targetHost.split(".").map(Number)])
        : (() => {
            const domain = Buffer.from(targetHost, "utf8");
            if (domain.length > 255) throw new Error("The target hostname is too long for SOCKS5.");
            return Buffer.concat([Buffer.from([0x03, domain.length]), domain]);
          })();
    const port = Buffer.alloc(2);
    port.writeUInt16BE(targetPort);
    socket.write(Buffer.concat([Buffer.from([0x05, 0x01, 0x00]), address, port]));

    const head = await reader.read(4);
    if (head[0] !== 0x05 || head[1] !== 0x00) {
      throw new Error(head[1] === 0x05 ? "The SOCKS5 proxy refused the connection." : "The SOCKS5 proxy returned an error.");
    }
    const length = head[3] === 0x01 ? 4 : head[3] === 0x04 ? 16 : await reader.read(1).then(([value]) => value);
    await reader.read(length + 2);
  } finally {
    reader.close();
  }
}

/**
 * A SOCKS reply can share a TCP packet with the next field. Read from one
 * accumulated buffer so bytes past the requested size are never discarded.
 *
 * @param {import("node:net").Socket} socket
 * @param {number} timeoutMs
 */
function createHandshakeReader(socket, timeoutMs) {
  let buffer = Buffer.alloc(0);
  /** @type {{ count: number, resolve: (value: Buffer) => void, reject: (error: Error) => void } | null} */
  let pending = null;
  /** @type {Error | null} */
  let failure = null;
  /** @type {ReturnType<typeof setTimeout> | null} */
  let timer = null;

  const settle = () => {
    if (!pending || buffer.length < pending.count) return;
    const { count, resolve } = pending;
    pending = null;
    if (timer) clearTimeout(timer);
    timer = null;
    const value = buffer.subarray(0, count);
    buffer = buffer.subarray(count);
    resolve(value);
  };
  const fail = (error) => {
    failure = error;
    if (!pending) return;
    const { reject } = pending;
    pending = null;
    if (timer) clearTimeout(timer);
    timer = null;
    reject(error);
  };
  const onData = (chunk) => {
    buffer = Buffer.concat([buffer, chunk]);
    settle();
  };
  const onError = (error) => fail(error);
  const onClose = () => fail(new Error("The proxy closed the connection during the handshake."));
  socket.on("data", onData);
  socket.once("error", onError);
  socket.once("close", onClose);

  return {
    /** @param {number} count */
    read(count) {
      if (failure) return Promise.reject(failure);
      if (buffer.length >= count) {
        const value = buffer.subarray(0, count);
        buffer = buffer.subarray(count);
        return Promise.resolve(value);
      }
      return new Promise((resolve, reject) => {
        pending = { count, resolve, reject };
        timer = setTimeout(() => fail(new Error("The proxy handshake timed out.")), timeoutMs);
      });
    },
    close() {
      if (timer) clearTimeout(timer);
      timer = null;
      pending = null;
      socket.off("data", onData);
      socket.off("error", onError);
      socket.off("close", onClose);
      // Bytes can arrive in the same TCP packet as the final SOCKS reply.
      // Put them back on the socket before pausing, otherwise the first
      // tunneled bytes would be lost between the handshake and the pipe.
      if (buffer.length) {
        const rest = buffer;
        buffer = Buffer.alloc(0);
        try {
          socket.unshift(rest);
        } catch {
          // The peer already closed; there is no tunnel left to preserve.
        }
      }
      // Park the socket until the caller pipes it, so bytes that arrive
      // between the handshake and the tunnel cannot be dropped.
      socket.pause();
    },
  };
}

/**
 * @param {import("node:net").Socket} socket
 * @param {number} timeoutMs
 */
function readHeaders(socket, timeoutMs) {
  return new Promise((resolve, reject) => {
    let buffer = "";
    const timer = setTimeout(() => {
      cleanup();
      reject(new Error("The upstream proxy did not answer in time."));
    }, timeoutMs);
    const onData = (chunk) => {
      buffer += chunk.toString("latin1");
      if (buffer.length > MAX_HEADER_BYTES) {
        cleanup();
        reject(new Error("The upstream proxy sent too many headers."));
        return;
      }
      if (buffer.includes("\r\n\r\n")) {
        cleanup();
        resolve(buffer);
      }
    };
    const onError = (error) => {
      cleanup();
      reject(error);
    };
    const cleanup = () => {
      clearTimeout(timer);
      socket.off("data", onData);
      socket.off("error", onError);
    };
    socket.on("data", onData);
    socket.once("error", onError);
  });
}

/**
 * @param {import("node:tls").TLSSocket} socket
 * @param {string} host
 * @param {string} pathName
 * @param {number} timeoutMs
 */
function httpGetOverTls(socket, host, pathName, timeoutMs) {
  return new Promise((resolve, reject) => {
    let buffer = "";
    const timer = setTimeout(() => {
      socket.destroy();
      reject(new Error("The IP check timed out."));
    }, timeoutMs);
    const onData = (chunk) => {
      buffer += chunk.toString("utf8");
      if (buffer.includes("\r\n\r\n")) {
        const [head, ...rest] = buffer.split("\r\n\r\n");
        const contentLength = Number((head.match(/\r\ncontent-length:\s*(\d+)/i) ?? [])[1] ?? 0);
        const body = rest.join("\r\n\r\n");
        if (body.length >= contentLength || !contentLength) {
          clearTimeout(timer);
          socket.off("data", onData);
          resolve(body);
        }
      }
    };
    socket.on("data", onData);
    socket.once("error", (error) => {
      clearTimeout(timer);
      reject(error);
    });
    socket.write(
      `GET ${pathName} HTTP/1.1\r\nHost: ${host}\r\nAccept: application/json\r\nConnection: close\r\nUser-Agent: idexal-os-proxy-test/1.0\r\n\r\n`,
    );
  });
}

/**
 * @param {import("node:net").Socket} socket
 * @param {string} event
 * @param {number} timeoutMs
 */
function onceWithTimeout(socket, event, timeoutMs) {
  return new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error("The secure connection timed out.")), timeoutMs);
    socket.once(event, () => {
      clearTimeout(timer);
      resolve(undefined);
    });
    socket.once("error", (error) => {
      clearTimeout(timer);
      reject(error);
    });
  });
}

/**
 * @param {string | string[] | undefined} header
 * @param {{ username: string, password: string }} auth
 */
function authorized(header, auth) {
  const value = Array.isArray(header) ? header[0] : header;
  if (!value) return false;
  const [scheme, encoded] = value.split(" ");
  if (scheme?.toLowerCase() !== "basic" || !encoded) return false;
  try {
    const decoded = Buffer.from(encoded, "base64").toString("utf8");
    const separator = decoded.indexOf(":");
    if (separator < 0) return false;
    return safeEqual(decoded.slice(0, separator), auth.username)
      && safeEqual(decoded.slice(separator + 1), auth.password);
  } catch {
    return false;
  }
}

/**
 * @param {string} left
 * @param {string} right
 */
function safeEqual(left, right) {
  const a = Buffer.from(left, "utf8");
  const b = Buffer.from(right, "utf8");
  return a.length === b.length && timingSafeEqual(a, b);
}

/**
 * @param {string} username
 * @param {string} password
 */
function basicAuth(username, password) {
  return `Basic ${Buffer.from(`${username}:${password}`, "utf8").toString("base64")}`;
}

/**
 * @param {NodeJS.Dict<string | string[]>} headers
 */
function stripHopByHop(headers) {
  const result = {};
  for (const [name, value] of Object.entries(headers)) {
    if (["proxy-authorization", "proxy-connection", "connection", "keep-alive", "transfer-encoding", "upgrade"].includes(name.toLowerCase())) {
      continue;
    }
    result[name] = value;
  }
  return result;
}

/**
 * @param {string} value
 */
export function parseHostPort(value) {
  const input = value.trim();
  const bracketed = input.match(/^\[([^\]]+)\]:(\d+)$/);
  if (bracketed) return { host: bracketed[1], port: Number(bracketed[2]) };
  const lastColon = input.lastIndexOf(":");
  if (lastColon > 0) {
    const host = input.slice(0, lastColon);
    const port = Number(input.slice(lastColon + 1));
    if (Number.isInteger(port) && port > 0 && port <= 65535) return { host, port };
  }
  return { host: input, port: 443 };
}

/**
 * @param {string} host
 */
export function formatHostForUrl(host) {
  return isIP(host) === 6 ? `[${host}]` : host;
}

/**
 * @param {string} host
 */
function ipv6Bytes(host) {
  const sections = host.split("::");
  const left = sections[0] ? sections[0].split(":") : [];
  const right = sections[1] ? sections[1].split(":") : [];
  const missing = 8 - left.length - right.length;
  const full = [...left, ...Array(Math.max(0, missing)).fill("0"), ...right];
  const bytes = [];
  for (const section of full) {
    const value = Number.parseInt(section || "0", 16);
    bytes.push((value >> 8) & 0xff, value & 0xff);
  }
  return Buffer.from(bytes.slice(0, 16));
}
