import assert from "node:assert/strict";
import { test } from "node:test";
import { runInNewContext } from "node:vm";
import { DaytonaApi, type Sandbox, type Snapshot } from "./api.ts";
import { DAYTONA_BASE_SNAPSHOT } from "./builder.ts";
import { cleanupDaytonaEvidence } from "./cleanup.ts";
import { compiledRecipe } from "@idexal/freestyle/build-recipes";
import { digest } from "@idexal/freestyle/cache";
import { readAsset } from "@idexal/freestyle/assets";
import { DAYTONA_PREVIEW_WORLDS, PREVIEW_KIND, buildDaytonaPreview, daytonaPreviewProgress, hasDaytonaPreview, launchDaytonaPreview, previewDescriptor, previewOrigin, daytonaFreshPreviewMethods } from "./previews.ts";

const sha = "a".repeat(40);
const reportId = "b".repeat(32);
const origin = "https://8080-signedcredential.daytonaproxy01.net";

/** Every provider operation goes through the real REST/toolbox serializer. */
async function witness() {
  const descriptor = await previewDescriptor(sha, "app-web");
  const snapshots = new Map<string, Snapshot>([[DAYTONA_BASE_SNAPSHOT, { id: "base", name: DAYTONA_BASE_SNAPSHOT, state: "active" }]]);
  const sandboxes = new Map<string, Sandbox>();
  const files = new Map<string, string>();
  const commands: string[] = [];
  const requests: { path: string; method: string; body: unknown }[] = [];
  const deleted: string[] = [];
  let mismatch = false;
  let failCommand = "";
  const request: typeof fetch = async (input, init) => {
    const url = new URL(String(input));
    const path = url.pathname.replace(/^\/api/, "");
    const method = init?.method ?? "GET";
    assert.equal(new Headers(init?.headers).get("authorization"), "Bearer host-only-key");
    const body: unknown = typeof init?.body === "string" ? JSON.parse(init.body) : undefined;
    requests.push({ path, method, body });
    const reply = (value: unknown, status = 200) => new Response(JSON.stringify(value), { status });
    if (path === "/sandbox" && method === "POST") {
      assert.ok(typeof body === "object" && body !== null && "name" in body && "labels" in body && "snapshot" in body && "public" in body);
      assert.equal(body.public, false);
      assert.equal(JSON.stringify(body).includes("host-only-key"), false);
      const id = `sandbox-${sandboxes.size + 1}`;
      assert.ok(typeof body.name === "string");
      assert.ok(typeof body.snapshot === "string");
      const labels: Record<string, string> = {};
      assert.ok(typeof body.labels === "object" && body.labels !== null);
      for (const [key, value] of Object.entries(body.labels)) { assert.equal(typeof value, "string"); labels[key] = value; }
      const sandbox: Sandbox = { id, name: body.name, snapshot: body.snapshot, public: false, state: "started", labels, createdAt: new Date().toISOString() };
      sandboxes.set(id, sandbox);
      files.set(`${id}/opt/idexal-os-preview/source-sha`, sha);
      files.set(`${id}/opt/idexal-os-preview/review-controller`, descriptor.controllerHash);
      return reply(sandbox);
    }
    if (path === "/sandbox" && method === "GET") return reply([...sandboxes.values()]);
    if (path === "/snapshots") return reply([...snapshots.values()]);
    if (path.startsWith("/snapshots/")) return reply(snapshots.get(decodeURIComponent(path.slice("/snapshots/".length))) ?? {}, snapshots.has(decodeURIComponent(path.slice("/snapshots/".length))) ? 200 : 404);
    const parts = path.split("/").filter(Boolean);
    if (parts[0] === "sandbox" && parts[1]) {
      const idOrName = parts[1];
      const sandbox = sandboxes.get(idOrName) ?? [...sandboxes.values()].find((entry) => entry.name === idOrName);
      if (!sandbox) return reply({}, 404);
      if (parts[2] === "ports") return reply({ url: origin });
      if (parts[2] === "stop") { sandbox.state = "stopped"; return reply({}); }
      if (parts[2] === "snapshot") {
        assert.ok(typeof body === "object" && body !== null && "name" in body && typeof body.name === "string");
        snapshots.set(body.name, { id: body.name, name: body.name, state: "active" });
        return reply({});
      }
      if (method === "DELETE") { deleted.push(sandbox.id); sandboxes.delete(sandbox.id); return reply({}); }
      return reply(sandbox);
    }
    if (parts[0] === "toolbox" && parts[1]) {
      const id = parts[1];
      if (path.endsWith("/files/download")) {
        const value = files.get(`${id}${url.searchParams.get("path")}`);
        return new Response(value ?? "", { status: value === undefined ? 404 : 200 });
      }
      if (path.endsWith("/files/upload")) {
        assert.ok(init?.body instanceof FormData);
        const file = init.body.get("file");
        assert.ok(file instanceof Blob);
        files.set(`${id}${url.searchParams.get("path")}`, await file.text());
        return reply({});
      }
      if (path.endsWith("/process/execute")) {
        assert.ok(typeof body === "object" && body !== null && "command" in body && typeof body.command === "string");
        commands.push(body.command);
        if (failCommand && body.command.includes(failCommand)) return reply({ exitCode: 1, result: "private failure host-only-key" });
        return reply({ exitCode: 0, result: body.command.includes("git rev-parse HEAD") ? (mismatch ? "c".repeat(40) : sha) : body.command.includes("echo building") ? "ready" : "" });
      }
    }
    throw new Error(`Unexpected fake route ${method} ${path}`);
  };
  const probe: typeof fetch = async (input, init) => {
    const url = new URL(String(input));
    if (url.pathname === "/__idexal_os_launch") return new Response(null, { status: 303, headers: { "set-cookie": `__Host-idexal-os-preview=${url.searchParams.get("token")}; Secure; HttpOnly` } });
    assert.match(new Headers(init?.headers).get("cookie") ?? "", /^__Host-idexal-os-preview=/);
    return url.pathname === "/" ? new Response('<script src="/@vite/client"></script>') : Response.json({ healthy: true });
  };
  return { api: new DaytonaApi({ apiKey: "host-only-key", apiUrl: "https://provider.test/api", region: "us-west-3" }, request), descriptor, snapshots, sandboxes, files, commands, requests, deleted, probe,
    mismatch: () => { mismatch = true; }, fail: (command: string) => { failCommand = command; } };
}

function ready(fixture: Awaited<ReturnType<typeof witness>>) {
  fixture.snapshots.set(fixture.descriptor.name, { id: "snapshot", name: fixture.descriptor.name, state: "active" });
}

test("preview identities include the exact commit; unsupported worlds and mutable refs fail closed", async () => {
  assert.deepEqual(DAYTONA_PREVIEW_WORLDS, ["app-web"]);
  const first = await previewDescriptor(sha, "app-web");
  assert.notEqual(first.name, (await previewDescriptor("c".repeat(40), "app-web")).name);
  assert.equal(first.name, (await previewDescriptor(sha, "app-web")).name);
  await assert.rejects(previewDescriptor("dev", "app-web"), /full pushed/);
  for (const world of ["desktop", "acme-web", "workbot"]) {
    if (world === "desktop" || world === "acme-web" || world === "workbot") await assert.rejects(previewDescriptor(sha, world), /web only/);
  }
  for (const url of ["https://8080-12345678-1234-1234-1234-123456789abc.daytonaproxy01.net", `${origin}/?token=secret`, "https://attacker.test/", `http://${new URL(origin).host}`]) assert.throws(() => previewOrigin(url));
});

test("build reuses private base/layer machinery and saves a hot exact-source world", async () => {
  const fixture = await witness();
  await buildDaytonaPreview(sha, "app-web", fixture.api, { sourceFetch: async () => Response.json({ tree: [], truncated: false }) });
  assert.equal(await hasDaytonaPreview(sha, "app-web", fixture.api), true);
  const script = [...fixture.files.entries()].find(([path]) => path.endsWith("/review-world.sh"))?.[1] ?? "";
  const appBuild = [...fixture.files.entries()].find(([path]) => path.endsWith("/review-app-build.sh"))?.[1] ?? "";
  assert.ok(appBuild.includes(`git fetch --depth=1 origin ${sha}`));
  assert.ok(appBuild.includes(`test "$(git rev-parse HEAD)" = "${sha}"`));
  assert.ok(script.includes("setpriv --reuid=idexal-os-preview --regid=idexal-os-preview --init-groups --no-new-privs --bounding-set=-all"));
  assert.equal(script.includes("pnpm install"), false);
  const unit = [...fixture.files.entries()].find(([path]) => path.endsWith("/idexal-os-review-runtime.service"))?.[1] ?? "";
  assert.ok(unit.includes("User=idexal-os-preview\nGroup=idexal-os-preview\nNoNewPrivileges=yes\nCapabilityBoundingSet="));
  assert.ok(unit.includes("ProtectSystem=strict") && unit.includes("PrivateTmp=yes"));
  assert.equal(unit.includes("ReadWritePaths=/opt/idexal-os-preview\n"), false);
  assert.ok(fixture.descriptor.scripts.runtime.includes('"/opt/idexal-os-preview/state/services.json"'));
  assert.ok(fixture.descriptor.scripts.health.includes('"/opt/idexal-os-preview/state/outputs.json"'));
  assert.ok(script.includes("node /opt/idexal-os-preview/health.mjs"));
  assert.ok(appBuild.includes("pnpm install --frozen-lockfile --ignore-scripts --ignore-pnpmfile"));
  assert.equal(script.includes("host-only-key"), false);
  assert.equal(appBuild.includes("host-only-key"), false);
  const saves = fixture.requests.filter((entry) => entry.path.endsWith("/snapshot"));
  assert.deepEqual(saves.map((entry) => entry.body), [
    { name: fixture.descriptor.toolsName, includeMemory: false }, { name: fixture.descriptor.name, includeMemory: true },
  ]);
  assert.equal(fixture.sandboxes.size, 0);
  const before = fixture.requests.filter((entry) => entry.method === "POST" && entry.path === "/sandbox").length;
  await buildDaytonaPreview(sha, "app-web", fixture.api);
  assert.equal(fixture.requests.filter((entry) => entry.method === "POST" && entry.path === "/sandbox").length, before);
});

test("report checkout, install and compile run only behind the unprivileged no-new-privileges boundary", async () => {
  const fixture = await witness();
  await buildDaytonaPreview(sha, "app-web", fixture.api, { sourceFetch: async () => Response.json({ tree: [], truncated: false }) });
  const file = (name: string) => [...fixture.files.entries()].find(([path]) => path.endsWith(name))?.[1] ?? "";
  const controller = file("/review-world.sh");
  const appBuild = file("/review-app-build.sh");
  const boundary = "setpriv --reuid=idexal-os-preview --regid=idexal-os-preview --init-groups --no-new-privs --bounding-set=-all env -i HOME=/home/idexal-os-preview USER=idexal-os-preview LOGNAME=idexal-os-preview PATH=/opt/idexal-os-preview/tools/node_modules/.bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin /bin/bash -c '/bin/bash /opt/idexal-os-preview/review-app-build.sh'";
  assert.ok(controller.includes(boundary));
  assert.ok(controller.indexOf("install -o root -g root -m 0600 /dev/null /opt/idexal-os-preview/private/access.json") < controller.indexOf(boundary));
  const socketStart = controller.indexOf("systemctl start idexal-os-review-gateway.socket");
  const gatewayStart = controller.indexOf("systemctl start idexal-os-review-gateway.service");
  const ownershipCheck = controller.indexOf("node /opt/idexal-os-preview/gateway-owner.mjs");
  assert.ok(socketStart > 0 && socketStart < gatewayStart && gatewayStart < ownershipCheck && ownershipCheck < controller.indexOf(boundary), "root listener and gateway ownership must be established before any report code");
  assert.equal(/git (?:init|fetch|checkout)|pnpm |node --input-type/.test(controller), false, "root controller cannot directly run report code");
  assert.ok(appBuild.includes("git fetch --depth=1 origin"));
  assert.ok(appBuild.includes("pnpm --filter @idexal/types build"));
  assert.ok(appBuild.includes("pnpm --config.ignore-pnpmfile=true rebuild"));
  assert.equal(appBuild.includes("/opt/idexal-os-preview/compiled-"), false);
  assert.equal(appBuild.includes("/opt/idexal-os-preview/compiled.tar"), false);
  assert.ok(appBuild.includes("writeFileSync('/home/idexal-os-preview/build/compiled-files'"));
  assert.ok(appBuild.includes("tar --null -T /home/idexal-os-preview/build/compiled-files -cf /home/idexal-os-preview/build/compiled.tar"));
  assert.ok(controller.includes("install -d -o root -g root -m 0755 /opt/idexal-os-preview"));
  assert.ok(controller.includes("install -d -o root -g root -m 0700 /opt/idexal-os-preview/private"), "uploads remain inaccessible even before the final chmod");
  assert.ok(controller.includes("install -d -o idexal-os-preview -g idexal-os-preview -m 0700 /home/idexal-os-preview /home/idexal-os-preview/build /workspace /opt/idexal-os-preview/state"));
  for (const name of ["services.json", "outputs.json"]) {
    assert.ok(fixture.descriptor.scripts.runtime.includes(`/opt/idexal-os-preview/state/${name}`));
    assert.equal(controller.includes(`install -o idexal-os-preview -g idexal-os-preview -m 0600 /dev/null /opt/idexal-os-preview/${name}`), false);
  }
  assert.equal(/chown\s+-R|chmod\s+(?:-R\s+)?(?:777|0777)|install -d -o idexal-os-preview[^\n]*\/opt\/idexal-os-preview(?:\s|$)/.test(controller), false, "controller parent must never be writable by report code");
  for (const name of ["gateway", "origins", "health", "gateway-owner"]) assert.ok(fixture.commands.includes(`chmod 600 '/opt/idexal-os-preview/${name}.mjs'`));
  assert.ok(fixture.commands.includes("chmod 644 '/opt/idexal-os-preview/runtime.mjs'"));
  assert.ok(fixture.commands.includes("chmod 644 '/opt/idexal-os-preview/review-app-build.sh'"));
});

test("runtime is an unprivileged confined service with only exact app-owned output paths", async () => {
  const fixture = await witness();
  await buildDaytonaPreview(sha, "app-web", fixture.api, { sourceFetch: async () => Response.json({ tree: [], truncated: false }) });
  const unit = [...fixture.files.entries()].find(([path]) => path.endsWith("/idexal-os-review-runtime.service"))?.[1] ?? "";
  for (const directive of ["User=idexal-os-preview", "Group=idexal-os-preview", "NoNewPrivileges=yes", "CapabilityBoundingSet=", "RestrictSUIDSGID=yes", "ProtectSystem=strict", "ProtectHome=read-only", "PrivateTmp=yes", "UMask=0077", "InaccessiblePaths=/opt/idexal-os-preview/private /root"]) assert.ok(unit.split("\n").includes(directive), directive);
  assert.equal(unit.split("\n").find((line) => line.startsWith("ReadWritePaths=")), "ReadWritePaths=/workspace /home/idexal-os-preview /opt/idexal-os-preview/state");
  ready(fixture);
  await launchDaytonaPreview({ gitSha: sha, reportId, world: "app-web" }, fixture.api, fixture.probe);
  const git = fixture.commands.find((command) => command.includes("git rev-parse HEAD"));
  assert.ok(git?.startsWith("setpriv --reuid=idexal-os-preview --regid=idexal-os-preview --init-groups --no-new-privs --bounding-set=-all env -i HOME=/home/idexal-os-preview "));
});

test("the root socket cannot be squatted across gateway restarts and launch verifies kernel ownership", async () => {
  const fixture = await witness();
  await buildDaytonaPreview(sha, "app-web", fixture.api, { sourceFetch: async () => Response.json({ tree: [], truncated: false }) });
  const file = (name: string) => [...fixture.files.entries()].find(([path]) => path.endsWith(name))?.[1] ?? "";
  const socket = file("/idexal-os-review-gateway.socket");
  assert.ok(socket.includes("ListenStream=8080\nBindIPv6Only=both"));
  assert.ok(file("/idexal-os-review-gateway.service").includes("User=root\nGroup=root\nNoNewPrivileges=yes\nSockets=idexal-os-review-gateway.socket"));
  assert.ok(file("/idexal-os-review-gateway.service").includes("Environment=IDEXAL_PREVIEW_ACCESS_FILE=/opt/idexal-os-preview/private/access.json"));
  const gateway = file("/gateway.mjs");
  assert.ok(gateway.includes("server.listen({ fd: 3 })"));
  assert.ok(gateway.includes("Number(process.env.LISTEN_PID) !== process.pid"));
  const owner = file("/gateway-owner.mjs");
  assert.ok(owner.includes('^Uid:\\s+0\\s+0\\s+0\\s+0$'));
  assert.ok(owner.includes('fields[7] !== "0" || !sockets.has("socket:[" + fields[9] + "]")'));
  assert.ok(owner.includes('fields[1]?.split(":").at(-1) !== "1F90" || fields[3] !== "0A"'));
  assert.ok(owner.includes('command.includes("/opt/idexal-os-preview/gateway.mjs")'));
  ready(fixture);
  await launchDaytonaPreview({ gitSha: sha, reportId, world: "app-web" }, fixture.api, fixture.probe);
  const checks = fixture.commands.filter((command) => command.includes("systemctl is-active --quiet idexal-os-review-gateway.socket idexal-os-review-gateway.service"));
  assert.equal(checks.length, 2, "check ownership both before issuing credentials and immediately before the handshake");
  assert.ok(fixture.commands.some((command) => command.startsWith("setpriv --reuid=idexal-os-preview --regid=idexal-os-preview --init-groups --no-new-privs --bounding-set=-all") && command.includes('test "$(id -u)" -ne 0 && test ! -r /opt/idexal-os-preview/private/access.json')));
});

test("gateway ownership accepts IPv4-only guests without weakening required procfs checks", async () => {
  const descriptor = await previewDescriptor(sha, "app-web");
  const code = descriptor.scripts["gateway-owner"].replace(/^import[^\n]+\n/, "");
  const execute = (ipv6: "absent" | "present" | "denied", uid = "0", missingIpv4 = false) => runInNewContext(`(async () => { ${code} })()`, {
    process: { argv: ["node", "gateway-owner.mjs", "123"] },
    readdir: async () => ["3"], readlink: async () => "socket:[42]",
    readFile: async (path: string) => {
      if (path.endsWith("/status")) return `Uid:\t${uid}\t${uid}\t${uid}\t${uid}\n`;
      if (path.endsWith("/cmdline")) return "node\0/opt/idexal-os-preview/gateway.mjs\0";
      if (path === "/proc/net/tcp6") {
        if (ipv6 !== "present") throw Object.assign(new Error("optional IPv6 table"), { code: ipv6 === "absent" ? "ENOENT" : "EACCES" });
        return "header\n";
      }
      if (path === "/proc/net/tcp" && !missingIpv4) return "header\n0: 00000000:1F90 00000000:0000 0A 0:0 0:0 0 0 0 42\n";
      throw Object.assign(new Error("required table missing"), { code: "ENOENT" });
    },
  });
  await execute("absent");
  await execute("present");
  await assert.rejects(execute("denied"), /optional IPv6 table/);
  await assert.rejects(execute("absent", "0", true), /required table missing/);
  await assert.rejects(execute("absent", "1000"), /not root-owned/);
});

test("production review factory rejects unsafe credential authorities without any HTTP request", async (context) => {
  const previousUrl = process.env.DAYTONA_API_URL;
  const previousKey = process.env.DAYTONA_API_KEY;
  let calls = 0;
  context.mock.method(globalThis, "fetch", async () => { calls++; throw new Error("No credential may leave the server"); });
  process.env.DAYTONA_API_KEY = "host-only-key";
  try {
    for (const authority of ["http://localhost:1234/api", "https://user:password@example.test/api", "https://example.test/api?key=secret", "https://example.test/api#fragment"]) {
      process.env.DAYTONA_API_URL = authority;
      assert.throws(() => daytonaFreshPreviewMethods().hasSnapshot(sha, "app-web"), /requires HTTPS/);
    }
    assert.equal(calls, 0);
  } finally {
    if (previousUrl === undefined) delete process.env.DAYTONA_API_URL; else process.env.DAYTONA_API_URL = previousUrl;
    if (previousKey === undefined) delete process.env.DAYTONA_API_KEY; else process.env.DAYTONA_API_KEY = previousKey;
  }
});

test("the security generation cannot reuse a root-executing v1 world snapshot", async () => {
  const descriptor = await previewDescriptor(sha, "app-web");
  const oldScripts = { runtime: descriptor.scripts.runtime, gateway: await readAsset("gateway.mjs"), origins: descriptor.scripts.origins, health: descriptor.scripts.health };
  const oldHash = digest(JSON.stringify({ version: 1, base: DAYTONA_BASE_SNAPSHOT, scripts: oldScripts, tools: descriptor.tools, dependencies: descriptor.dependencies, compile: compiledRecipe("app-web") }));
  assert.notEqual(descriptor.controllerHash, oldHash);
  const oldName = `ow-dy-review-v1-${digest(`${sha}\napp-web\n${oldHash}`)}`;
  assert.notEqual(descriptor.name, oldName);
});

test("a failed world build releases its owned builder and never publishes a ready snapshot", async () => {
  const fixture = await witness();
  fixture.fail("systemd-run --collect --unit=idexal-os-review-world");
  await assert.rejects(buildDaytonaPreview(sha, "app-web", fixture.api, { sourceFetch: async () => Response.json({ tree: [], truncated: false }) }), /guest command failed/);
  assert.equal(await hasDaytonaPreview(sha, "app-web", fixture.api), false);
  assert.equal(fixture.sandboxes.size, 0);
  assert.equal(fixture.deleted.length, 2);
});

test("each launch gets a private fresh VM, exact-source witness, deadline and signed cookie handshake", async () => {
  const fixture = await witness(); ready(fixture);
  const launch = () => launchDaytonaPreview({ gitSha: sha, reportId, world: "app-web" }, fixture.api, fixture.probe);
  const first = await launch();
  const second = await launch();
  assert.notEqual(first.id, second.id);
  assert.notEqual(first.url, second.url);
  assert.equal(first.gitSha, sha);
  assert.equal(first.world, "app-web");
  assert.ok(Date.parse(first.expiresAt) <= Date.now() + 7200_000);
  assert.ok(Date.parse(first.expiresAt) > Date.now() + 7190_000);
  const sandbox = fixture.sandboxes.get(first.id);
  assert.equal(sandbox?.labels.reportId, reportId);
  assert.equal(sandbox?.labels.expiresAt, first.expiresAt);
  assert.equal(sandbox?.labels.kind, PREVIEW_KIND);
  assert.ok(fixture.commands.some((command) => /systemd-run --unit=idexal-os-review-expiry --on-active=\d+s \/usr\/sbin\/poweroff/.test(command)));
  const access = JSON.parse(fixture.files.get(`${first.id}/opt/idexal-os-preview/private/access.json`) ?? "{}");
  assert.deepEqual(access.allowedOrigins, [origin]);
  assert.equal(new URL(first.url).searchParams.get("token"), access.token);
  assert.ok(fixture.commands.some((command) => command === "chmod 600 '/opt/idexal-os-preview/private/access.json'"));
  assert.equal(fixture.deleted.length, 0);
});

for (const mode of ["source", "deadline", "gateway-owner", "health", "external"]) test(`a ${mode} failure deletes only the newly owned preview and exposes no credential`, async () => {
  const fixture = await witness(); ready(fixture);
  if (mode === "source") fixture.mismatch();
  if (mode === "deadline") fixture.fail("idexal-os-review-expiry");
  if (mode === "gateway-owner") fixture.fail("gateway-owner.mjs");
  if (mode === "health") fixture.fail("health.mjs");
  await assert.rejects(launchDaytonaPreview({ gitSha: sha, reportId, world: "app-web" }, fixture.api, mode === "external" ? async () => new Response("not ready", { status: 502 }) : fixture.probe), (error: Error) => !error.message.includes("host-only-key") && /could not be reached/.test(error.message));
  assert.deepEqual(fixture.deleted, ["sandbox-1"]);
  assert.equal(fixture.sandboxes.size, 0);
});

test("progress reads provider-owned durable layer state, not process memory", async () => {
  const fixture = await witness();
  assert.deepEqual(await daytonaPreviewProgress(sha, "app-web", fixture.api), { building: false, steps: [] });
  await buildDaytonaPreview(sha, "app-web", fixture.api, { sourceFetch: async () => Response.json({ tree: [], truncated: false }) });
  const created = fixture.requests.find((entry) => entry.path === "/sandbox" && entry.method === "POST")?.body;
  assert.ok(typeof created === "object" && created !== null && "name" in created && typeof created.name === "string");
  fixture.sandboxes.set("builder", { id: "builder", name: created.name, state: "started", labels: { kind: "idexal-os-daytona-builder-v1" } });
  assert.equal((await daytonaPreviewProgress(sha, "app-web", fixture.api)).building, true);
});

test("independent cleanup deletes an expired active preview, never a still-valid one", async () => {
  const fixture = await witness(); ready(fixture);
  const first = await launchDaytonaPreview({ gitSha: sha, reportId, world: "app-web" }, fixture.api, fixture.probe);
  const second = await launchDaytonaPreview({ gitSha: sha, reportId, world: "app-web" }, fixture.api, fixture.probe);
  const sandbox = fixture.sandboxes.get(first.id);
  assert.ok(sandbox);
  sandbox.labels.expiresAt = new Date(Date.now() - 1).toISOString();
  const result = await cleanupDaytonaEvidence(fixture.api);
  assert.deepEqual(result.deletedSandboxes, [sandbox.name]);
  assert.equal(fixture.sandboxes.has(second.id), true);
});
