// Unify the GitHub repo slug case to canonical `idexal/idexalOs`.
// Replaces the literal `idexal/idexalos` everywhere EXCEPT when preceded by a
// dot (`com.idexal/idexalos` — the MCP registry-style server name, which stays
// lowercase on purpose).
import { readdirSync, readFileSync, writeFileSync } from "node:fs";
import { join, relative } from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = fileURLToPath(new URL("..", import.meta.url));
const SKIP_DIRS = new Set([".git", "_rebrand", "node_modules", ".venv", "dist", "dist-electron"]);
const pattern = /(?<!\.)idexal\/idexalos/g;
const dry = !process.argv.includes("--write");

let files = 0;
let total = 0;
const touched = [];

function walk(dir) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    if (entry.isDirectory()) {
      if (SKIP_DIRS.has(entry.name)) continue;
      walk(join(dir, entry.name));
      continue;
    }
    const path = join(dir, entry.name);
    let text;
    try {
      const buf = readFileSync(path);
      if (buf.includes(0)) continue; // binary
      text = buf.toString("utf8");
    } catch {
      continue;
    }
    const matches = [...text.matchAll(pattern)];
    if (matches.length === 0) continue;
    files += 1;
    total += matches.length;
    touched.push(`${relative(ROOT, path)} (${matches.length})`);
    if (!dry) writeFileSync(path, text.replace(pattern, "idexal/idexalOs"));
  }
}

walk(ROOT);
console.log(`${dry ? "DRY" : "WROTE"}: ${total} replacements in ${files} files`);
for (const line of touched.sort()) console.log(`  ${line}`);
