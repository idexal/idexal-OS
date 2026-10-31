import assert from "node:assert/strict";
import { test } from "node:test";

import { workspacePathPresentInServerRegistry } from "./runtime.mjs";

const chatPath = "C:\\Users\\qa\\AppData\\Roaming\\com.idexal.os.dev\\home\\idexal Os Chat";

test("finds a registered workspace by resolved path", () => {
  assert.equal(
    workspacePathPresentInServerRegistry([{ path: chatPath }], chatPath, "win32"),
    true,
  );
});

test("matches Windows paths regardless of casing and separators", () => {
  assert.equal(
    workspacePathPresentInServerRegistry(
      [{ path: "c:/users/qa/appdata/roaming/com.idexal.os.dev/home/idexal os chat" }],
      chatPath,
      "win32",
    ),
    true,
  );
});

test("reports a selected workspace the server never registered", () => {
  assert.equal(
    workspacePathPresentInServerRegistry(
      [{ path: "C:\\Users\\qa\\another project" }],
      chatPath,
      "win32",
    ),
    false,
  );
});

test("an empty registry is missing any selected workspace", () => {
  assert.equal(workspacePathPresentInServerRegistry([], chatPath, "win32"), false);
  assert.equal(workspacePathPresentInServerRegistry(undefined, chatPath, "win32"), false);
});

test("no selected path needs no registration", () => {
  assert.equal(workspacePathPresentInServerRegistry([], "", "win32"), true);
  assert.equal(workspacePathPresentInServerRegistry([], null, "win32"), true);
});
