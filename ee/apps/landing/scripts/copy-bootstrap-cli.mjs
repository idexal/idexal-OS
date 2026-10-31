// Copies the self-contained idexal-os-bootstrap CLI into the landing app's
// public dir so it can be served statically at /idexal-os-bootstrap.mjs.
//
// install.sh downloads this file and installs it as the `idexal-os-bootstrap`
// command, so the installer never depends on npm/npx or a pinned GitHub ref —
// it always matches the deployed landing build.

import { copyFileSync, mkdirSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const source = resolve(here, "..", "..", "..", "..", "packages", "idexal-os-bootstrap", "bin", "idexal-os.mjs");
const targetDir = resolve(here, "..", "public");
const target = join(targetDir, "idexal-os-bootstrap.mjs");

mkdirSync(targetDir, { recursive: true });
copyFileSync(source, target);
console.log(`[copy-bootstrap-cli] ${source} -> ${target}`);
