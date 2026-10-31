// Extension-agnostic sweep: which file types contain the standalone token "IdexalOs"?
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const skipDirs = new Set(["node_modules", ".git", "_rebrand", ".next", "dist", "build", ".turbo", ".cache", "dist-electron"]);
const byExt = new Map();
const nonUtf = [];

function walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    if (skipDirs.has(e.name)) continue;
    const p = path.join(dir, e.name);
    if (e.isDirectory()) { walk(p); continue; }
    if (!e.isFile()) continue;
    let st; try { st = fs.statSync(p); } catch { continue; }
    if (st.size > 5 * 1024 * 1024) continue;
    let buf; try { buf = fs.readFileSync(p); } catch { continue; }
    // binary check: NUL byte in first 8k
    const head = buf.subarray(0, 8192);
    if (head.includes(0)) continue;
    const txt = buf.toString("utf8");
    if (!txt.includes("IdexalOs")) continue;
    const reG = /(?<![A-Za-z0-9_$])IdexalOs(?![A-Za-z0-9_$])/g;
    const n = (txt.match(reG) || []).length;
    const ext = path.extname(e.name).toLowerCase() || "(noext)";
    const rec = byExt.get(ext) ?? { files: 0, hits: 0, samples: [] };
    rec.files += 1;
    rec.hits += n;
    if (rec.samples.length < 3) rec.samples.push(path.relative(root, p));
    byExt.set(ext, rec);
    if (!txt.includes("\uFFFD")) {} // noop
  }
}
walk(root);
const rows = [...byExt.entries()].sort((a, b) => b[1].hits - a[1].hits);
for (const [ext, rec] of rows) console.log(`${ext}\tfiles=${rec.files}\thits=${rec.hits}\t${rec.samples.join(", ")}`);
console.log("TOTAL EXT TYPES:", rows.length, "TOTAL HITS:", rows.reduce((a, [, r]) => a + r.hits, 0));
