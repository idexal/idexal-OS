#!/usr/bin/env node
/**
 * idexal Os rebrand tool (lives in _rebrand/, excluded from its own walk).
 * Usage:
 *   node _rebrand/rebrand.mjs --dry                 # report only
 *   node _rebrand/rebrand.mjs --write               # rename files/dirs + replace content
 *   node _rebrand/rebrand.mjs --write --step=rename # only rename files/dirs
 *   node _rebrand/rebrand.mjs --write --step=content
 */
import fs from "node:fs";
import path from "node:path";

const ROOT = path.resolve(process.cwd());
const args = new Set(process.argv.slice(2));
const WRITE = args.has("--write");
const stepArg = [...args].find((a) => a.startsWith("--step="));
const STEP = stepArg ? stepArg.split("=")[1] : "all";

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
  "_rebrand",
  "logo_idexal",
]);

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

/* ------------------------------------------------------------------ */
/* Replacement rules — ORDER MATTERS                                   */
/* ------------------------------------------------------------------ */
const RULES = [
  // --- 1. emails (before domain rules) ---
  [/support@openworklabs\.com/gi, "os@idexal.com"],
  [/team\+security@openworklabs\.com/gi, "info@idexal.com"],
  [/\b(team|sales|founders|ben|security|hello)@openworklabs\.com/gi, "info@idexal.com"],
  [/@openworklabs\.com/gi, "@idexal.com"],

  // --- 2. bundle ids (before generic domain rules like openwork.dev) ---
  [/com\.differentai\.openwork/gi, "com.idexal.os"],

  // --- 3. domains (most specific first) ---
  [/api\.app\.openworklabs\.com/gi, "api.os.idexal.com"],
  [/preview\.openwork\.software/gi, "preview.idexal.com"],
  [/staging-api\.openworklabs\.com/gi, "staging-api.idexal.com"],
  [/diagnostic\.openworklabs\.com/gi, "diagnostic.idexal.com"],
  [/models\.openworklabs\.com/gi, "models.idexal.com"],
  [/inference\.openworklabs\.com/gi, "inference.idexal.com"],
  [/chat\.openworklabs\.com/gi, "chat.idexal.com"],
  [/gateway\.openworklabs\.com/gi, "gateway.idexal.com"],
  [/schemas\.openworklabs\.com/gi, "schemas.idexal.com"],
  [/app\.openworklabs\.com/gi, "os.idexal.com"],
  [/www\.openworklabs\.com/gi, "os.idexal.com"],
  [/web\.openworklabs\.com/gi, "os.idexal.com"],
  [/api\.openworklabs\.com/gi, "api.idexal.com"],
  [/openworklabs\.com/gi, "os.idexal.com"],
  [/openwork\.software/gi, "idexal.com"],
  [/openworkapp\.com/gi, "idexal.com"],
  [/openwork\.studio/gi, "idexal.com"],
  [/openworkco\.com/gi, "idexal.com"],
  [/openwork\.dev/gi, "os.idexal.com"],
  [/openworklabs/gi, "idexal"],

  // --- 4. github / docker registry / org names ---
  [/github\.com\/different-ai\/openwork/gi, "github.com/idexal/idexalOs"],
  [/raw\.githubusercontent\.com\/different-ai\/openwork/gi, "raw.githubusercontent.com/idexal/idexalOs"],
  [/github\.com\/different-ai\/owpenbot/gi, "github.com/idexal/owpenbot"],
  [/ghcr\.io\/different-ai\/openwork/gi, "ghcr.io/idexal/idexal-os"],
  [/different-ai\/openwork-plugins/gi, "idexal/idexalOs-plugins"],
  [/different-ai/gi, "idexal"],
  [/Different AI\b/g, "Idexal"],
  [/differentai/gi, "idexal"],

  // --- 5. protocol / schemes ---
  [/openwork-dev/gi, "idexalos-dev"],
  [/openwork:\/\//gi, "idexalos://"],

  // --- 6. npm scopes & workspace names ---
  [/@different-ai\/openwork-workspace/gi, "@idexal/idexal-os-workspace"],
  [/@openwork-ee\//g, "@idexal-ee/"],
  [/@openwork\//g, "@idexal/"],

  // --- 7. special compounds ---
  [/opencode-openwork/gi, "opencode-idexal-os"],
  [/npx openwork\b/gi, "npx idexal-os-bootstrap"],
  [/openwork doctor\b/gi, "idexal-os doctor"],
  [/install-openwork/gi, "install-idexal-os"],
  [/LicenseRef-OpenWork-EE/gi, "LicenseRef-Idexal-Os-EE"],
  [/OpenWork-EE/g, "Idexal-Os-EE"],

  // --- 8. env vars & constants ---
  [/OPENWORK_/g, "IDEXAL_"],
  [/OPENWORK/g, "IDEXAL_OS"],

  // --- 9. http headers ---
  [/X-OpenWork-/g, "X-Idexal-Os-"],
  [/x-openwork-/g, "x-idexal-os-"],

  // --- 10. identifier case passes ---
  [/openWork/g, "idexalOs"],
  [/Openwork/g, "IdexalOs"],
  [/OpenWork/g, "IdexalOs"],
  [/openwork(?=[A-Z])/g, "idexalOs"],
  [/openwork(?=_)/g, "idexal_os"],
  [/openwork(?=-)/g, "idexal-os"],
  [/openwork(?=\.)/g, "idexal-os"],
  [/openwork/g, "idexalos"],
];

/* Map a file/dir basename the same way as content. */
function mapName(name) {
  let out = name;
  for (const [re, to] of RULES) out = out.replace(re, to);
  return out;
}

function* walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.name === ".git" && dir === ROOT) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (EXCLUDE_DIRS.has(entry.name)) continue;
      yield { type: "dir", full, name: entry.name };
      yield* walk(full);
    } else {
      yield { type: "file", full, name: entry.name };
    }
  }
}

function decodeUtf8(buf) {
  // returns { text, encoding } or null when binary
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
  if (!roundtrip.equals(buf)) return null; // not valid UTF-8
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

const ruleHits = RULES.map(() => 0);
const changedFiles = [];
const renamed = [];
const skippedBinary = [];
const leftoverRe = /openwork|Openwork|OpenWork|OPENWORK|different-ai|differentai|Different AI/gi;

function runContentPass() {
  for (const item of walk(ROOT)) {
    if (item.type !== "file") continue;
    const ext = path.extname(item.name).toLowerCase();
    if (BINARY_EXT.has(ext)) continue;
    let buf;
    try {
      buf = fs.readFileSync(item.full);
    } catch {
      continue;
    }
    const decoded = decodeUtf8(buf);
    if (!decoded) {
      skippedBinary.push(path.relative(ROOT, item.full));
      continue;
    }
    let text = decoded.text;
    let fileChanged = 0;
    RULES.forEach(([re, to], i) => {
      const matches = text.match(new RegExp(re.source, re.flags));
      if (matches && matches.length) {
        ruleHits[i] += matches.length;
        fileChanged += matches.length;
        text = text.replace(re, to);
      }
    });
    if (fileChanged > 0) {
      changedFiles.push(`${path.relative(ROOT, item.full)} (${fileChanged})`);
      if (WRITE) fs.writeFileSync(item.full, encode(text, decoded.encoding));
    }
  }
}

function runRenamePass() {
  // deepest first so directory renames do not invalidate child paths
  const all = [...walk(ROOT)].filter((i) => /openwork/i.test(i.name));
  all.sort((a, b) => b.full.length - a.full.length);
  for (const item of all) {
    const newName = mapName(item.name);
    if (newName === item.name) continue;
    const dir = path.dirname(item.full);
    renamed.push(`${path.relative(ROOT, item.full)}  ->  ${path.join(path.relative(ROOT, dir), newName)}`);
    if (WRITE) fs.renameSync(item.full, path.join(dir, newName));
  }
}

function scanLeftovers() {
  const hits = [];
  for (const item of walk(ROOT)) {
    if (item.type !== "file") continue;
    const ext = path.extname(item.name).toLowerCase();
    if (BINARY_EXT.has(ext)) continue;
    if (/openwork|different-ai|differentai/i.test(item.name)) {
      hits.push({ file: path.relative(ROOT, item.full), name: item.name, count: 0 });
    }
    let buf;
    try { buf = fs.readFileSync(item.full); } catch { continue; }
    const decoded = decodeUtf8(buf);
    if (!decoded) continue;
    const matches = decoded.text.match(leftoverRe);
    if (matches && matches.length) {
      hits.push({ file: path.relative(ROOT, item.full), count: matches.length });
    }
  }
  return hits;
}

if (STEP === "rename" || STEP === "all") runRenamePass();
if (STEP === "content" || STEP === "all") runContentPass();

console.log(`mode: ${WRITE ? "WRITE" : "DRY RUN"} step=${STEP}`);
console.log(`\n=== rule hits (content) ===`);
RULES.forEach(([, to], i) => {
  if (ruleHits[i] > 0) console.log(`${String(ruleHits[i]).padStart(7)}  ${RULES[i][0].source}  ->  ${to}`);
});
console.log(`\nchanged files: ${changedFiles.length}`);
console.log(`\n=== renames (${renamed.length}) ===`);
renamed.slice(0, 400).forEach((r) => console.log("  " + r));
if (renamed.length > 400) console.log(`  ... +${renamed.length - 400} more`);

// leftovers only meaningful after a write
if (WRITE) {
  const hits = scanLeftovers();
  console.log(`\n=== leftovers (${hits.length}) ===`);
  hits.slice(0, 200).forEach((h) => console.log(`  ${h.count || ""}\t${h.file}`));
  if (hits.length > 200) console.log(`  ... +${hits.length - 200} more`);
}

fs.mkdirSync(path.join(ROOT, "_rebrand"), { recursive: true });
fs.writeFileSync(
  path.join(ROOT, "_rebrand", WRITE ? "last-write-report.json" : "last-dry-report.json"),
  JSON.stringify({ ruleHits, renamed, changedFiles, skippedBinary }, null, 1),
);
console.log(`\nreport written to _rebrand/${WRITE ? "last-write-report.json" : "last-dry-report.json"}`);
