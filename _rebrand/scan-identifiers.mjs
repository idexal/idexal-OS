// Scan for uses of the standalone token "IdexalOs" that look like CODE IDENTIFIERS
// (declarations, imports, JSX tags, object keys, calls) which must NOT be display-renamed.
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const skipDirs = new Set(["node_modules", ".git", "_rebrand", ".next", "dist", "build", ".turbo", ".cache"]);
const patterns = [
  { name: "declaration", re: /(?:function|class|const|let|var|type|interface|enum|namespace)\s+IdexalOs(?![A-Za-z0-9_$])/ },
  { name: "jsx-open", re: /<IdexalOs(?![A-Za-z0-9_$])/ },
  { name: "jsx-close", re: /<\/IdexalOs(?![A-Za-z0-9_$])/ },
  { name: "jsx-dot", re: /<IdexalOs\./ },
  { name: "import", re: /import[^;\n]*?\{[^}]*\bIdexalOs(?![A-Za-z0-9_$])[^}]*\}[^;\n]*from/ },
  { name: "object-key", re: /[{,]\s*IdexalOs(?![A-Za-z0-9_$\s]*:)/ },
  { name: "call", re: /(?<![A-Za-z0-9_$.])IdexalOs\s*\(/ },
  { name: "member", re: /IdexalOs(?![A-Za-z0-9_$])\s*\./ },
  { name: "string-key", re: /"IdexalOs"\s*:/ },
];

const out = [];
function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (skipDirs.has(e.name)) continue;
    const p = path.join(dir, e.name);
    if (e.isDirectory()) walk(p);
    else if (e.isFile()) {
      const ext = path.extname(e.name).toLowerCase();
      if (![".ts", ".tsx", ".js", ".mjs", ".cjs"].includes(ext)) continue;
      let txt;
      try { txt = fs.readFileSync(p, "utf8"); } catch { continue; }
      if (!txt.includes("IdexalOs")) continue;
      const lines = txt.split("\n");
      for (let i = 0; i < lines.length; i++) {
        for (const { name, re } of patterns) {
          if (re.test(lines[i])) {
            out.push({ file: path.relative(root, p), line: i + 1, kind: name, ctx: lines[i].trim().slice(0, 170) });
          }
        }
      }
    }
  }
}
walk(root);
console.log("IDENTIFIER-LIKE HITS:", out.length);
for (const r of out) console.log(`${r.file}:${r.line} [${r.kind}] ${r.ctx}`);
