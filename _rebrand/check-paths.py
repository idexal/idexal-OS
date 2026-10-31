"""Cross-check d-strings: mark.json -> svg assets -> JSX consumers.

The provider-icon test asserts renderToStaticMarkup(IdexalOsMark) paths equal
the canonical asset's paths, so the two must be byte-identical.
"""
import json
import re
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent

DOC = json.loads((HERE / "trace-out" / "emit" / "mark.json").read_text(encoding="utf-8"))
CANON = [DOC["paths"][n] for n in ["sliver", "square", "ne", "se", "black"]]

def ds(text):
    return [m.group(1) for m in re.finditer(r'\bd="([^"]*)"', text)]

fails = []
for rel in (
    "apps/app/public/idexal-os-mark.svg",
    "ee/apps/landing/public/idexal-os-mark.svg",
    "ee/apps/den-web/public/idexal-os-mark.svg",
    "ee/apps/workbot/src/client/public/favicon.svg",
):
    got = ds((ROOT / rel).read_text(encoding="utf-8"))
    if got != CANON:
        fails.append((rel, got))

# mono sidebar + JSX consumers carry the same 5 paths (order preserved)
EXACT = [
    "apps/app/public/idexal-os-sidebar-mark.svg",
    "packages/ui/src/react/idexal-os-mark.tsx",
    "ee/packages/workbot-ui/src/mark.tsx",
    "ee/apps/den-api/src/capability-sources/oauth-callback-page.ts",
]
MIXED = [  # also contain unrelated icon paths; require the canonical run intact
    "packages/presentation/src/browser-frame.tsx",
    "ee/apps/den-web/app/(den)/dashboard/_components/org-dashboard-shell.tsx",
    "ee/apps/landing/components/og-image-content.tsx",
]
for rel in EXACT:
    got = ds((ROOT / rel).read_text(encoding="utf-8"))
    if got != CANON:
        fails.append((rel, got))
for rel in MIXED:
    got = ds((ROOT / rel).read_text(encoding="utf-8"))
    if CANON[0] not in got or got[got.index(CANON[0]):][: len(CANON)] != CANON:
        fails.append((rel, got))

# tile + lockups: paths appear inside a transformed group with identical d's
for rel in (
    "apps/app/public/idexal-os-logo-square.svg",
    "ee/apps/landing/public/idexal-os-logo.svg",
    "ee/apps/den-web/public/idexal-os-logo-transparent.svg",
):
    got = ds((ROOT / rel).read_text(encoding="utf-8"))
    if got != CANON:
        fails.append((rel, got))

if fails:
    for rel, got in fails:
        print(f"FAIL {rel}: {len(got)} paths, expected {len(CANON)}")
        for want, have in zip(CANON, got):
            if want != have:
                print("  want:", want[:90])
                print("  have:", have[:90])
    raise SystemExit(1)
print(f"all d-strings byte-identical to mark.json ({len(CANON)} paths, {len(CANON)} checks per file)")
