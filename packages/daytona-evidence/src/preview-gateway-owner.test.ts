import assert from "node:assert/strict";
import { execFile } from "node:child_process";
import { mkdtemp, mkdir, rm, symlink, writeFile } from "node:fs/promises";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { promisify } from "node:util";
import { test } from "node:test";
import { previewDescriptor } from "./previews.ts";

const execute = promisify(execFile);
const listener = (uid: string, inode: string) => `0: 00000000:1F90 00000000:0000 0A 00000000:00000000 00:00000000 00000000 ${uid} 0 ${inode}\n`;

test("gateway ownership handles an IPv4-only kernel but still rejects a competing listener", async () => {
  const root = await mkdtemp(join(tmpdir(), "review-gateway-owner-"));
  try {
    await mkdir(join(root, "proc/123/fd"), { recursive: true });
    await mkdir(join(root, "proc/net"), { recursive: true });
    await writeFile(join(root, "proc/123/status"), "Uid:\t0\t0\t0\t0\n");
    await writeFile(join(root, "proc/123/cmdline"), "node\0/opt/idexal-os-preview/gateway.mjs\0");
    await symlink("socket:[42]", join(root, "proc/123/fd/3"));
    await writeFile(join(root, "proc/net/tcp"), `header\n${listener("0", "42")}`);
    const descriptor = await previewDescriptor("a".repeat(40), "app-web");
    const script = join(root, "gateway-owner.mjs");
    await writeFile(script, descriptor.scripts["gateway-owner"].replaceAll('"/proc/', `"${root}/proc/`));
    // Daytona's IPv4-only VMs omit tcp6 entirely. An observed root-owned IPv4
    // socket is sufficient, but no observed socket or an unreadable table is not.
    await execute(process.execPath, [script, "123"]);
    await writeFile(join(root, "proc/net/tcp6"), `header\n${listener("1000", "99")}`);
    await assert.rejects(execute(process.execPath, [script, "123"]), /not owned by the trusted gateway/);
    await writeFile(join(root, "proc/net/tcp6"), "header\n");
    await writeFile(join(root, "proc/net/tcp"), "header\n");
    await assert.rejects(execute(process.execPath, [script, "123"]), /Trusted gateway is not listening/);
    await rm(join(root, "proc/net/tcp6"));
    await mkdir(join(root, "proc/net/tcp6"));
    await writeFile(join(root, "proc/net/tcp"), `header\n${listener("0", "42")}`);
    await assert.rejects(execute(process.execPath, [script, "123"]), /EISDIR/);
  } finally {
    await rm(root, { recursive: true, force: true });
  }
});
