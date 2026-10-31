// Broad scan: the bare lowercase token `idexalos` (not part of `idexal-os`,
// `idexalosSomething`, etc.) in any text file, with context, to catch
// mechanical-rename mismatches (globs, prefixes, matchers).
import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const skipDirs = new Set([
  "node_modules", ".git", "_rebrand", ".next", "dist", "build", ".turbo",
  ".cache", "dist-electron", "out", "coverage", ".vercel", "logo_idexal",
]);
const BIN = new Set([
  ".png", ".jpg", ".jpeg", ".gif", ".bmp", ".tiff", ".webp", ".avif", ".ico", ".icns",
  ".woff", ".woff2", ".ttf", ".otf", ".eot", ".mp3", ".mp4", ".webm", ".ogg", ".wav",
  ".mov", ".zip", ".gz", ".tgz", ".tar", ".7z", ".rar", ".exe", ".dll", ".node", ".so",
  ".dylib", ".wasm", ".db", ".sqlite", ".sqlite3", ".pem", ".cer", ".crt", ".p12",
  ".pfx", ".jks", ".keystore", ".key", ".pdf", ".psd", ".sketch", ".fig", ".class",
  ".jar", ".pyc",
]);
// bare token: lowercase `idexalos` not followed by [a-z0-9-] and not preceded by [a-z0-9_-]
const BARE = /(?<![A-Za-z0-9_-])idexalos(?![a-z0-9-])/;
// ignore common legit uses:
const LEGIT = [
  /idexalos\.com/,                 // domain
  /idexalos:\/\//,                 // scheme
  /idexalos-dev:\/\//,
  /"idexalos"|'idexalos'|`idexalos`/,
  /idexalos_/,                     // snake identifiers
  /idexalos[A-Z]/,
  /github\.com\/idexal\/idexalos/, // repo path (case-insensitive GitHub)
  /idexal\/idexalos/,
  /releases\.idexal-os\.test/,
  /idexalos\.test/,
  /\.idexalos\./,
  /idexalos[0-9]/,
  /idexalos\.(?:json|sha256|yml|yaml|dmg|exe|tar\.gz|AppImage|deb|rpm)/,
  /\/idexalos\b/,
  /^[-\s]*#/,
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
  if (buf.subarray(0, 8192).includes(0)) continue;
  const txt = buf.toString("utf8");
  if (!BARE.test(txt)) continue;
  const lines = txt.split("\n");
  for (let i = 0; i < lines.length; i++) {
    const l = lines[i];
    BARE.lastIndex = 0;
    let m;
    let has = false;
    const re = new RegExp(BARE.source, "g");
    while ((m = re.exec(l))) {
      if (!LEGIT.some((r) => r.test(l))) has = true;
    }
    const re2 = new RegExp(BARE.source, "g");
    if (re2.test(l) && has) {
      const rel = path.relative(root, p);
      // extra guard: skip the raw domain strings and known fixture urls
      if (/idexalos\.com/.test(l) && !/["'`(]/.test(l)) continue;
      out.push(`${rel}:${i + 1}: ${l.trim().slice(0, 190)}`);
    }
  }
}
console.log("BARE idexalos LINES:", out.length);
for (const l of out) console.log(l);
