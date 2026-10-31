// Scan: standalone token in shell/process command positions where splitting on
// a space would break argument parsing.
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const skipDirs = new Set([
  "node_modules", ".git", "_rebrand", ".next", "dist", "build", ".turbo",
  ".cache", "dist-electron", "out", "coverage", ".vercel",
]);
const T = "(?<![A-Za-z0-9_$])IdexalOs(?![A-Za-z0-9_$])";
const pats = [
  ["open-a", new RegExp(`open\\s+-a\\s+["'\`]?${T}`)],
  ["proc-kill", new RegExp(`(pgrep|pkill|killall|taskkill|Get-Process|Stop-Process|Start-Process)[^\\n]{0,60}${T}`)],
  ["osascript", new RegExp(`osascript[^\\n]{0,100}${T}`)],
  ["shell-word", new RegExp(`(^|[|&;]|\\$\\()\\s*${T}`)],
  ["which", new RegExp(`(which|command\\s+-v|where)\\s+["'\`]?${T}`)],
  ["arg-flag", new RegExp(`(^|\\s)--?[a-z-]+[= ]["'\`]?[^\\n]{0,60}${T}`)],
  ["npx-yarn", new RegExp(`(npx|pnpm|yarn|npm)\\s+(dlx\\s+)?["'\`]?${T}`)],
];
const exts = [".ts", ".tsx", ".js", ".mjs", ".cjs", ".sh", ".ps1", ".py", ".yml", ".yaml", ".md", ".mdx", ".cmd", ".bat", ".json", ".tf", ".toml"];
const out = [];
function* walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) { if (!skipDirs.has(e.name)) yield* walk(p); continue; }
    yield p;
  }
}
for (const p of walk(root)) {
  if (!exts.includes(path.extname(p).toLowerCase())) continue;
  let txt; try { txt = fs.readFileSync(p, "utf8"); } catch { continue; }
  if (!txt.includes("IdexalOs")) continue;
  txt.split("\n").forEach((l, i) => {
    for (const [name, re] of pats) {
      if (re.test(l)) { out.push(`${name} | ${path.relative(root, p)}:${i + 1}: ${l.trim().slice(0, 170)}`); break; }
    }
  });
}
console.log("CMD-POSITION LINES:", out.length);
for (const l of out) console.log(l);
fs.writeFileSync(path.join(root, "_rebrand", "p3-cmd-scan.txt"), out.join("\n"));
