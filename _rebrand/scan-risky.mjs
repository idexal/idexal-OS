// Scan for functional-looking uses of the standalone token "IdexalOs".
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const skipDirs = new Set(["node_modules", ".git", "_rebrand", ".next", "dist", "build", ".turbo", ".cache"]);
const hasRe = /IdexalOs/;
const riskTerms = [
  "join(", "resolve(", "dirname", "basename", "exec(", "spawn(", "setName(", "setPath(",
  "userData", "productName", "executableName", "artifactName", "protocol", "scheme",
  "registry", "Registry", "HKEY", "installPath", "LOCALAPPDATA", "APPDATA", "appId",
  "/IdexalOs", "IdexalOs/", "IdexalOs\\", "\"IdexalOs\"", "'IdexalOs'", "`IdexalOs`",
  "===", "!==", "includes(\"", "match(", "startsWith(", "endsWith(", "toLowerCase()",
  "toLowerCase", "boolean", "flag", "env", "Env", "ENV",
];

const out = [];
function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (skipDirs.has(e.name)) continue;
    const p = path.join(dir, e.name);
    if (e.isDirectory()) walk(p);
    else if (e.isFile()) {
      const ext = path.extname(e.name).toLowerCase();
      if (![".ts", ".tsx", ".js", ".mjs", ".cjs", ".sh", ".ps1", ".py", ".yml", ".yaml", ".json", ".jsonc", ".md", ".mdx", ".c"].includes(ext)) continue;
      let txt;
      try { txt = fs.readFileSync(p, "utf8"); } catch { continue; }
      if (!hasRe.test(txt)) continue;
      const lines = txt.split("\n");
      for (let i = 0; i < lines.length; i++) {
        const l = lines[i];
        if (!l.includes("IdexalOs")) continue;
        // must contain standalone token
        const reG = /(?<![A-Za-z0-9_$])IdexalOs(?![A-Za-z0-9_$])/;
        if (!reG.test(l)) continue;
        if (riskTerms.some((t) => l.includes(t))) {
          out.push({ file: path.relative(root, p), line: i + 1, ctx: l.trim().slice(0, 200) });
        }
      }
    }
  }
}
walk(root);
fs.writeFileSync("_rebrand/p3-risky-scan.json", JSON.stringify(out, null, 1));
console.log("RISKY LINES:", out.length);
for (const r of out.slice(0, 200)) console.log(r.file + ":" + r.line + ": " + r.ctx);
