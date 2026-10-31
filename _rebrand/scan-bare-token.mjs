// Strict scan: code pattern-matching the BARE lowercase token "idexalos" (no hyphen)
// in string-matcher position — the risky mechanical-rename artifacts.
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const skipDirs = new Set(["node_modules", ".git", "_rebrand", ".next", "dist", "build", ".turbo", ".cache", "dist-electron"]);
const res = [
  { name: "startsWith-bare", re: /startsWith\(\s*["'`]idexalos["'`)]/i },
  { name: "startsWith-bare-u", re: /startsWith\(\s*["'`]idexalos/i },
  { name: "endsWith-bare", re: /endsWith\(\s*["'`]idexalos/i },
  { name: "includes-bare", re: /includes\(\s*["'`]idexalos/i },
  { name: "bare-literal", re: /["'`]idexalos["'`]/i },
  { name: "regex-bare", re: /\/\^?idexalos[\^\\/]/i },
];
const out = [];
function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (skipDirs.has(e.name)) continue;
    const p = path.join(dir, e.name);
    if (e.isDirectory()) { walk(p); continue; }
    if (!e.isFile()) continue;
    const ext = path.extname(e.name).toLowerCase();
    if (![".ts", ".tsx", ".js", ".mjs", ".cjs", ".yml", ".yaml", ".sh", ".ps1", ".py"].includes(ext)) continue;
    let st; try { st = fs.statSync(p); } catch { continue; }
    if (st.size > 5 * 1024 * 1024) continue;
    let txt; try { txt = fs.readFileSync(p, "utf8"); } catch { continue; }
    if (!txt.includes("idexalos")) continue;
    const lines = txt.split("\n");
    for (let i = 0; i < lines.length; i++) {
      for (const { name, re } of res) {
        if (re.test(lines[i])) {
          out.push(`${name} | ${path.relative(root, p)}:${i + 1}: ${lines[i].trim().slice(0, 180)}`);
          break;
        }
      }
    }
  }
}
walk(root);
console.log("BARE-TOKEN MATCHER LINES:", out.length);
for (const l of out) console.log(l);
