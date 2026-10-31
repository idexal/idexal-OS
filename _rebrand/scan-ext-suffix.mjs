// List every standalone `IdexalOs` that the display pass would classify as
// display-kind ("idexal Os") while being immediately followed by a dotted
// extension-like suffix (IdexalOs.app, IdexalOs.dmg, ...). These pair-risk with
// URL-kind replacements ("idexal-os") for the same file/asset names.
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const skipDirs = new Set([
  "node_modules", ".git", "_rebrand", ".next", "dist", "build", ".turbo",
  ".cache", "dist-electron", "out", "coverage", ".vercel", "logo_idexal",
]);
const RE = /(?<![A-Za-z0-9_$])IdexalOs(?![A-Za-z0-9_$])/g;
const URL_STOP = /[\s"'`<>(){}[\]|\\]/;
const out = [];

function* walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) { if (!skipDirs.has(e.name)) yield* walk(p); continue; }
    yield p;
  }
}
for (const p of walk(root)) {
  let buf; try { buf = fs.readFileSync(p); } catch { continue; }
  if (buf.subarray(0, 8192).includes(0)) continue;
  const text = buf.toString("utf8");
  if (!text.includes("IdexalOs")) continue;
  RE.lastIndex = 0;
  let m;
  while ((m = RE.exec(text))) {
    const after = text.slice(m.index + 8);
    if (!/^\.[A-Za-z]/.test(after)) continue;
    const lineStart = text.lastIndexOf("\n", m.index - 1) + 1;
    const before = text.slice(lineStart, m.index);
    const schemeAt = before.lastIndexOf("://");
    const isUrl = schemeAt !== -1 && !URL_STOP.test(before.slice(schemeAt + 3));
    const ext = after.match(/^\.[A-Za-z0-9]+/)[0];
    out.push(`${isUrl ? "URL" : "DISPLAY"} | ${path.relative(root, p)}:${text.slice(0, m.index).split("\n").length} | ${ext} | ${text.slice(lineStart, text.indexOf("\n", m.index) === -1 ? undefined : text.indexOf("\n", m.index)).trim().slice(0, 170)}`);
  }
}
console.log("EXT-SUFFIX LINES:", out.length);
for (const l of out) console.log(l);
