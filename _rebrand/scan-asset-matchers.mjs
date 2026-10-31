// Find code that pattern-matches release/asset names starting with the brand token.
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const skipDirs = new Set(["node_modules", ".git", "_rebrand", ".next", "dist", "build", ".turbo", ".cache", "dist-electron"]);
const res = [
  /startsWith\(\s*["'`]idexal/i,
  /endsWith\(\s*["'`]idexal/i,
  /includes\(\s*["'`]idexal-os-/i,
  /includes\(\s*["'`]idexalos/i,
  /match\([^)]*idexal/i,
  /["'`]idexal-os-[a-z$]/i,
  /["'`]idexalos-[$a-z]/i,
];
const out = [];
function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (skipDirs.has(e.name)) continue;
    const p = path.join(dir, e.name);
    if (e.isDirectory()) { walk(p); continue; }
    if (!e.isFile()) continue;
    const ext = path.extname(e.name).toLowerCase();
    if (![".ts", ".tsx", ".js", ".mjs", ".cjs", ".yml", ".yaml", ".sh", ".ps1", ".py", ".json"].includes(ext)) continue;
    let st; try { st = fs.statSync(p); } catch { continue; }
    if (st.size > 5 * 1024 * 1024) continue;
    let txt; try { txt = fs.readFileSync(p, "utf8"); } catch { continue; }
    if (!/idexal/i.test(txt)) continue;
    const lines = txt.split("\n");
    for (let i = 0; i < lines.length; i++) {
      for (const re of res) {
        if (re.test(lines[i])) {
          out.push(`${path.relative(root, p)}:${i + 1}: ${lines[i].trim().slice(0, 190)}`);
          break;
        }
      }
    }
  }
}
walk(root);
console.log("MATCHER LINES:", out.length);
for (const l of out) console.log(l);
