// Scan: standalone `IdexalOs` in matcher/test/attribute positions where a
// display-pass replacement to "idexal Os" could change behavior if the other
// side of the comparison is not transformed the same way.
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const skipDirs = new Set([
  "node_modules", ".git", "_rebrand", ".next", "dist", "build", ".turbo",
  ".cache", "dist-electron", "out", "coverage", ".vercel", "logo_idexal",
]);
const BIN = new Set([
  ".png", ".jpg", ".jpeg", ".gif", ".bmp", ".tiff", ".webp", ".avif", ".ico", ".icns",
  ".woff", ".woff2", ".ttf", ".otf", ".eot", ".mp3", ".mp4", ".webm", ".ogg", ".wav", ".mov",
  ".zip", ".gz", ".tgz", ".tar", ".7z", ".rar", ".exe", ".dll", ".node", ".so", ".dylib",
  ".wasm", ".db", ".sqlite", ".sqlite3", ".pem", ".cer", ".crt", ".p12", ".pfx",
  ".jks", ".keystore", ".key", ".pdf", ".psd", ".sketch", ".fig", ".class", ".jar", ".pyc",
]);
const T = "(?<![A-Za-z0-9_$])IdexalOs(?![A-Za-z0-9_$])";
const Q = "[\"'`]";
const matchers = [
  ["includes", new RegExp(`includes\\(\\s*${Q}${T}`)],
  ["startsWith", new RegExp(`startsWith\\(\\s*${Q}${T}`)],
  ["endsWith", new RegExp(`endsWith\\(\\s*${Q}${T}`)],
  ["eq", new RegExp(`(?<![A-Za-z0-9_$])(===|!==|==|!=)\\s*${Q}${T}`)],
  ["assert", new RegExp(`to(Be|Equal|Contain|Match|Throw)\\(\\s*${Q}${T}`)],
  ["assertOtherSide", new RegExp(`${T}[^\\n]{0,40}(toEqual|toBe|toContain|toMatch)\\(`)],
  ["regexLiteral", new RegExp(`/[^/\\n]*${T}[^/\\n]*/`)],
  ["ariaLabel", new RegExp(`aria-label=\\{?["\`][^"\`}]*${T}`)],
  ["stateLiteral", new RegExp(`(data-|id=|name=|title=)["\`][^"\`]*${T}`)],
  ["stringKey", new RegExp(`${Q}${T}${Q}\\s*[:)]`)],
];

const out = [];
function* walk(dir) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) { if (!skipDirs.has(e.name)) yield* walk(p); continue; }
    yield p;
  }
}
for (const p of walk(root)) {
  const ext = path.extname(p).toLowerCase();
  if (BIN.has(ext)) continue;
  let buf; try { buf = fs.readFileSync(p); } catch { continue; }
  if (buf.includes(0)) continue;
  const txt = buf.toString("utf8");
  if (!txt.includes("IdexalOs")) continue;
  const lines = txt.split("\n");
  for (let i = 0; i < lines.length; i++) {
    for (const [name, re] of matchers) {
      if (re.test(lines[i])) {
        out.push(`${name} | ${path.relative(root, p)}:${i + 1}: ${lines[i].trim().slice(0, 180)}`);
        break;
      }
    }
  }
}
console.log("MATCHER-POSITION LINES:", out.length);
for (const l of out) console.log(l);
fs.writeFileSync(path.join(root, "_rebrand", "p3-matcher-scan.txt"), out.join("\n"));
