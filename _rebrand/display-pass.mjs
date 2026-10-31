#!/usr/bin/env node
/**
 * Phase 3 display pass for the idexal Os rebrand.
 * Turns the standalone display token `IdexalOs` into `idexal Os`.
 *
 * Rules (order matters, single left-to-right scan):
 *   1. `IdexalOs` inside a URL token (scheme `://` earlier on the line with no
 *      whitespace/quote/bracket between it and the match) becomes `idexal-os`
 *      so fake fixture URLs and release-asset paths stay valid.
 *   2. Inside a URL, when immediately followed by `.app` (bundle reference,
 *      e.g. file:///Applications/IdexalOs.app) it becomes `idexal Os` so the
 *      real product bundle name is kept.
 *   3. Everywhere else it becomes `idexal Os`.
 *
 * Compound occurrences (IdexalOsMark, IdexalOsWorldInstall, idexalOsScope,
 * @idexal/idexalOs, ...) are protected by identifier-boundary lookarounds and
 * stay untouched.
 *
 * Usage:
 *   node _rebrand/display-pass.mjs --dry
 *   node _rebrand/display-pass.mjs --write
 *
 * Excludes: _rebrand/, .git, node_modules, common build-output dirs, binaries.
 * NOTE: `apps/desktop/build` (electron-builder resources) is intentionally kept
 * in scope; all other `build` dirs are skipped.
 */
import fs from "node:fs";
import path from "node:path";

const ROOT = path.resolve(process.cwd());
const args = new Set(process.argv.slice(2));
const WRITE = args.has("--write");

const EXCLUDE_DIRS = new Set([
  ".git",
  "node_modules",
  "dist",
  "build",
  "out",
  ".turbo",
  ".next",
  ".vercel",
  "coverage",
  "dist-electron",
  ".cache",
  "_rebrand",
  "logo_idexal",
]);

// The electron-builder resources directory is source, not output.
const INCLUDE_DIRS = new Set([path.join(ROOT, "apps", "desktop", "build")]);

const BINARY_EXT = new Set([
  ".png", ".jpg", ".jpeg", ".gif", ".bmp", ".tiff", ".webp", ".avif", ".ico", ".icns",
  ".woff", ".woff2", ".ttf", ".otf", ".eot",
  ".mp3", ".mp4", ".webm", ".ogg", ".wav", ".mov",
  ".zip", ".gz", ".tgz", ".tar", ".7z", ".rar",
  ".exe", ".dll", ".node", ".so", ".dylib", ".wasm",
  ".db", ".sqlite", ".sqlite3",
  ".pem", ".cer", ".crt", ".p12", ".pfx", ".jks", ".keystore", ".key",
  ".pdf", ".psd", ".sketch", ".fig",
  ".class", ".jar", ".pyc",
]);

const RE = /(?<![A-Za-z0-9_$])IdexalOs(?![A-Za-z0-9_$])/g;
const URL_STOP = /[\s"'`<>(){}[\]|\\]/;

function classify(text, index) {
  // returns { replacement, kind }
  const lineStart = text.lastIndexOf("\n", index - 1) + 1;
  const before = text.slice(lineStart, index);
  const schemeAt = before.lastIndexOf("://");
  if (schemeAt !== -1 && !URL_STOP.test(before.slice(schemeAt + 3))) {
    const after = text.slice(index + "IdexalOs".length);
    if (/^\.app(?![A-Za-z0-9_$])/.test(after)) return { replacement: "idexal Os", kind: "url-app" };
    return { replacement: "idexal-os", kind: "url" };
  }
  return { replacement: "idexal Os", kind: "display" };
}

function decodeUtf8(buf) {
  if (buf.length >= 3 && buf[0] === 0xef && buf[1] === 0xbb && buf[2] === 0xbf) {
    return { text: buf.subarray(3).toString("utf8"), encoding: "utf8-bom" };
  }
  if (buf.length >= 2 && buf[0] === 0xff && buf[1] === 0xfe) {
    return { text: buf.subarray(2).toString("utf16le"), encoding: "utf16le-bom" };
  }
  if (buf.length >= 2 && buf[0] === 0xfe && buf[1] === 0xff) {
    const swapped = Buffer.from(buf.subarray(2));
    swapped.swap16();
    return { text: swapped.toString("utf16le"), encoding: "utf16be-bom" };
  }
  const text = buf.toString("utf8");
  if (text.includes("\u0000")) return null;
  const roundtrip = Buffer.from(text, "utf8");
  if (!roundtrip.equals(buf)) return null;
  return { text, encoding: "utf8" };
}

function encode(text, encoding) {
  if (encoding === "utf8-bom") return Buffer.concat([Buffer.from([0xef, 0xbb, 0xbf]), Buffer.from(text, "utf8")]);
  if (encoding === "utf16le-bom") return Buffer.concat([Buffer.from([0xff, 0xfe]), Buffer.from(text, "utf16le")]);
  if (encoding === "utf16be-bom") {
    const le = Buffer.from(text, "utf16le");
    le.swap16();
    return Buffer.concat([Buffer.from([0xfe, 0xff]), le]);
  }
  return Buffer.from(text, "utf8");
}

function* walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.name === ".git" && dir === ROOT) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (EXCLUDE_DIRS.has(entry.name) && !INCLUDE_DIRS.has(full)) continue;
      yield* walk(full);
    } else {
      yield { type: "file", full, name: entry.name };
    }
  }
}

const kindCounts = { display: 0, url: 0, urlApp: 0 };
const files = [];
const suspicious = [];
const report = [];

function processText(text) {
  let out = "";
  let last = 0;
  let count = 0;
  const kinds = { display: 0, url: 0, urlApp: 0 };
  RE.lastIndex = 0;
  let m;
  while ((m = RE.exec(text))) {
    const { replacement, kind } = classify(text, m.index);
    kinds[kind === "url-app" ? "urlApp" : kind] += 1;
    kindCounts[kind === "url-app" ? "urlApp" : kind] += 1;
    out += text.slice(last, m.index) + replacement;
    last = m.index + m[0].length;
    count += 1;
  }
  out += text.slice(last);
  return { out, count, kinds };
}

function lineOf(text, index) {
  const lineStart = text.lastIndexOf("\n", index - 1) + 1;
  const lineEnd = text.indexOf("\n", index);
  return text.slice(lineStart, lineEnd === -1 ? undefined : lineEnd);
}

for (const item of walk(ROOT)) {
  const ext = path.extname(item.name).toLowerCase();
  if (BINARY_EXT.has(ext)) continue;
  let buf;
  try {
    buf = fs.readFileSync(item.full);
  } catch {
    continue;
  }
  const decoded = decodeUtf8(buf);
  if (!decoded) continue;
  if (!decoded.text.includes("IdexalOs")) continue;

  const { out, count, kinds } = processText(decoded.text);
  if (count === 0) continue;

  const rel = path.relative(ROOT, item.full);
  files.push(`${rel} (${count}; display=${kinds.display} url=${kinds.url} urlApp=${kinds.urlApp})`);

  // record the first few changed lines per file for review
  const samples = [];
  let hint = 0;
  RE.lastIndex = 0;
  while (samples.length < 4) {
    const mm = RE.exec(decoded.text);
    if (!mm) break;
    const line = lineOf(decoded.text, mm.index);
    const { kind, replacement } = classify(decoded.text, mm.index);
    samples.push({
      line: decoded.text.slice(0, mm.index).split("\n").length,
      kind,
      replacement,
      text: line.trim().slice(0, 200),
    });
  }
  report.push({ file: rel, count, kinds, samples });

  // suspicious patterns for manual review
  RE.lastIndex = 0;
  let sm;
  while ((sm = RE.exec(decoded.text))) {
    const prev = sm.index > 0 ? decoded.text[sm.index - 1] : "";
    const next = decoded.text[sm.index + sm[0].length] ?? "";
    const { kind } = classify(decoded.text, sm.index);
    const isPathy = kind === "display" && (prev === "-" || next === "-" || next === "." || prev === "." || prev === "\\" || prev === "/" || next === "\\" || next === "/");
    if (isPathy) {
      suspicious.push(`${rel}:${decoded.text.slice(0, sm.index).split("\n").length}: last=${kind} | ${lineOf(decoded.text, sm.index).trim().slice(0, 200)}`);
    }
  }

  if (WRITE) fs.writeFileSync(item.full, encode(out, decoded.encoding));
}

console.log(`mode: ${WRITE ? "WRITE" : "DRY RUN"}`);
console.log(`changed files: ${files.length}`);
console.log(`replacements: display=${kindCounts.display} url=${kindCounts.url} urlApp=${kindCounts.urlApp} total=${kindCounts.display + kindCounts.url + kindCounts.urlApp}`);
console.log(`suspicious (path-like display replacements): ${suspicious.length}`);
files.forEach((f) => console.log("  " + f));
console.log(`\n=== suspicious lines ===`);
suspicious.forEach((s) => console.log("  " + s));

fs.mkdirSync(path.join(ROOT, "_rebrand"), { recursive: true });
fs.writeFileSync(
  path.join(ROOT, "_rebrand", WRITE ? "display-write-report.json" : "display-dry-report.json"),
  JSON.stringify({ kindCounts, files, suspicious, report }, null, 1),
);
console.log(`\nreport written to _rebrand/${WRITE ? "display-write-report.json" : "display-dry-report.json"}`);
