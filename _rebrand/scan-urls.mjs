// Check: is the standalone token "IdexalOs" ever inside a URL or a markdown link target?
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const skipDirs = new Set(["node_modules", ".git", "_rebrand", ".next", "dist", "build", ".turbo", ".cache", "dist-electron"]);
const urlRe = /https?:\/\/\S*IdexalOs\S*/g;
const mdLinkRe = /\]\([^)]*IdexalOs[^)]*\)/g;
const pathRe = /(?:^|[\s"'`(:=])[A-Za-z]:\\[^\n"']*IdexalOs[^\n"']*|\/(?:Applications|usr|opt|etc|var|home)[^\n"'`]*IdexalOs[^\n"'`]*/g;
const out = [];

function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (skipDirs.has(e.name)) continue;
    const p = path.join(dir, e.name);
    if (e.isDirectory()) walk(p);
    else if (e.isFile()) {
      let st; try { st = fs.statSync(p); } catch { continue; }
      if (st.size > 5 * 1024 * 1024) continue;
      let buf; try { buf = fs.readFileSync(p); } catch { continue; }
      if (buf.subarray(0, 8192).includes(0)) continue;
      const txt = buf.toString("utf8");
      if (!txt.includes("IdexalOs")) continue;
      const lines = txt.split("\n");
      for (let i = 0; i < lines.length; i++) {
        for (const [kind, re] of [["url", urlRe], ["mdlink", mdLinkRe], ["fspath", pathRe]]) {
          re.lastIndex = 0;
          const m = re.exec(lines[i]);
          if (m) out.push({ file: path.relative(root, p), line: i + 1, kind, hit: m[0].slice(0, 160) });
        }
      }
    }
  }
}
walk(root);
console.log("URL/LINK/PATH hits:", out.length);
for (const r of out) console.log(`${r.file}:${r.line} [${r.kind}] ${r.hit}`);
