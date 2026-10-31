"""Apply the canonical idexal Os mark everywhere: assets + inline consumers.

Reads the bake artifacts (trace-out/emit/mark.json, mark.svg, mark-mono.svg,
render.png, wordmark-light.png) and then:

  * writes the standalone SVG assets: full-color mark (desktop, landing,
    den-web, workbot favicon), mono sidebar mark, both 1136x380 lockups
    (vector mark + wordmark PNG data URI), and the 1024px AUR app tile;
  * splices the inline consumers (React, TSX, HTML-in-TS);
  * applies the consumer tweaks: dark-mode filters for the now-full-color
    mark, square mark sizes for the 377x380 aspect, PKGBUILD Icon=;
  * regenerates the email logo PNG (128x128) from the render.

Re-runnable: already-applied edits are skipped, stale anchors fail loudly.
"""
import base64
import json
import re
import xml.etree.ElementTree as ET
from pathlib import Path

from PIL import Image

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
EMIT = HERE / "trace-out" / "emit"

DOC = json.loads((EMIT / "mark.json").read_text(encoding="utf-8"))
PATHS = DOC["paths"]
GRADS = DOC["gradients"]
GREEN = ["sliver", "square", "ne", "se"]
INK = DOC["ink"]
NL = r"\r?\n"
OLD_GEOM = ["834 649", "146 7 468 585", "M445.095", "M670.016"]
OLD_ART = OLD_GEOM + ["fill-rule", "fillRule"]

ASSETS = [
    "apps/app/public/idexal-os-mark.svg",
    "apps/app/public/idexal-os-sidebar-mark.svg",
    "apps/app/public/idexal-os-logo-square.svg",
    "ee/apps/landing/public/idexal-os-mark.svg",
    "ee/apps/landing/public/idexal-os-logo.svg",
    "ee/apps/den-web/public/idexal-os-mark.svg",
    "ee/apps/den-web/public/idexal-os-logo-transparent.svg",
    "ee/apps/workbot/src/client/public/favicon.svg",
]

SPLICED = [
    "packages/ui/src/react/idexal-os-mark.tsx",
    "ee/packages/workbot-ui/src/mark.tsx",
    "packages/presentation/src/browser-frame.tsx",
    "ee/apps/den-api/src/capability-sources/oauth-callback-page.ts",
    "ee/apps/den-web/app/(den)/dashboard/_components/org-dashboard-shell.tsx",
    "ee/apps/landing/components/og-image-content.tsx",
]


def fmt(v):
    return f"{v:.3f}".rstrip("0").rstrip(".")


def num(v):
    return f"{v:.7f}".rstrip("0").rstrip(".")


def emit_text(name):
    return (EMIT / name).read_bytes().decode("utf-8").replace("\r\n", "\n")


def write_lf(path, text):
    assert "\r" not in text, path
    path.write_bytes(text.encode("utf-8"))
    print(f"wrote   {path.relative_to(ROOT)}  {path.stat().st_size} bytes")


def read_text(path):
    raw = path.read_bytes()
    return raw.decode("utf-8"), ("\r\n" if b"\r\n" in raw else "\n")


# ---------------------------------------------------------------- builders

def gradient_el(name, indent, jsx):
    g = GRADS[name]
    pad = " " * indent
    attr = "stopColor" if jsx else "stop-color"
    close = " />" if jsx else "/>"
    return [
        f'{pad}<linearGradient id="idexal-os-{name}" gradientUnits="userSpaceOnUse"'
        f' x1="{fmt(g["x1"])}" y1="{fmt(g["y1"])}" x2="{fmt(g["x2"])}" y2="{fmt(g["y2"])}">',
        f'{pad}  <stop offset="0" {attr}="{g["stops"][0]}"{close}',
        f'{pad}  <stop offset="1" {attr}="{g["stops"][1]}"{close}',
        f"{pad}</linearGradient>",
    ]


def defs_block(indent, jsx):
    pad = " " * indent
    lines = [f"{pad}<defs>"]
    for name in GREEN:
        lines += gradient_el(name, indent + 2, jsx)
    lines.append(f"{pad}</defs>")
    return lines


def color_paths(indent, jsx):
    pad = " " * indent
    close = " />" if jsx else "/>"
    lines = [f'{pad}<path fill="url(#idexal-os-{n})" d="{PATHS[n]}"{close}' for n in GREEN]
    lines.append(f'{pad}<path fill="{INK}" d="{PATHS["black"]}"{close}')
    return lines


# ------------------------------------------------------------- asset writers

def write_standalone_assets():
    full = emit_text("mark.svg")
    mono = emit_text("mark-mono.svg")
    for rel in (
        "apps/app/public/idexal-os-mark.svg",
        "ee/apps/landing/public/idexal-os-mark.svg",
        "ee/apps/den-web/public/idexal-os-mark.svg",
        "ee/apps/workbot/src/client/public/favicon.svg",
    ):
        write_lf(ROOT / rel, full)
    sidebar = (
        "<!-- Monochrome mark for compact desktop chrome; geometry matches"
        " idexal-os-mark.svg. -->\n" + mono
    )
    write_lf(ROOT / "apps/app/public/idexal-os-sidebar-mark.svg", sidebar)


def lockup_svg():
    uri = "data:image/png;base64," + base64.b64encode(
        (EMIT / "wordmark-light.png").read_bytes()
    ).decode("ascii")
    lines = [
        '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"'
        ' width="1136" height="380" viewBox="0 0 1136 380" fill="none">',
        *defs_block(2, jsx=False),
        *color_paths(2, jsx=False),
        f'  <image x="382" y="73" width="746" height="211" href="{uri}" xlink:href="{uri}"/>',
        "</svg>",
    ]
    return "\n".join(lines) + "\n"


def tile_svg():
    scale = 700 / 380
    tx = (1024 - 377 * scale) / 2
    lines = [
        '<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024">',
        "  <defs>",
    ]
    for name in GREEN:
        lines += gradient_el(name, 4, jsx=False)
    lines += [
        '    <filter id="idexal-os-tile-shadow" x="-8%" y="-8%" width="116%" height="116%"'
        ' color-interpolation-filters="sRGB">',
        '      <feGaussianBlur in="SourceAlpha" stdDeviation="9" result="blur"/>',
        '      <feOffset in="blur" dy="10" result="offset"/>',
        '      <feFlood flood-color="#0F172A" flood-opacity="0.1" result="color"/>',
        '      <feComposite in="color" in2="offset" operator="in" result="shadow"/>',
        "      <feMerge>",
        '        <feMergeNode in="shadow"/>',
        '        <feMergeNode in="SourceGraphic"/>',
        "      </feMerge>",
        "    </filter>",
        "  </defs>",
        '  <rect x="100" y="100" width="824" height="824" rx="184" fill="#F6F9FC"'
        ' filter="url(#idexal-os-tile-shadow)"/>',
        f'  <g transform="translate({num(tx)} 162) scale({num(scale)})">',
        *color_paths(4, jsx=False),
        "  </g>",
        "</svg>",
    ]
    return "\n".join(lines) + "\n"


# -------------------------------------------------------------- splices

def splice(path, pattern, lines):
    text, nl = read_text(path)
    joined = nl.join(lines)
    new, count = re.subn(pattern, lambda _: joined, text, flags=re.DOTALL)
    if count == 1:
        if new == text:
            print(f"skipped {path.relative_to(ROOT)} (already spliced)")
            return
        path.write_bytes(new.encode("utf-8"))
        print(f"spliced {path.relative_to(ROOT)}")
        return
    already = count == 0 and joined in text
    if already:
        print(f"skipped {path.relative_to(ROOT)} (already spliced)")
        return
    raise AssertionError(f"{path}: pattern matched {count} times")


def replace_once(path, old, new, label):
    text, _ = read_text(path)
    count = text.count(old)
    if count == 1:
        path.write_bytes(text.replace(old, new, 1).encode("utf-8"))
        print(f"edited  {path.relative_to(ROOT)}  [{label}]")
        return
    already = count == 0 and new in text
    if already:
        print(f"skipped {path.relative_to(ROOT)} (already applied) [{label}]")
        return
    raise AssertionError(f"{path}: '{label}' anchor matched {count} times")


def splice_ui_mark():
    path = ROOT / "packages/ui/src/react/idexal-os-mark.tsx"
    lines = [
        '    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 377 380" fill="currentColor"'
        " width={size} height={size} className={className} role=\"img\" aria-label=\"idexal Os\">",
        *(f'      <path d="{PATHS[n]}" />' for n in GREEN + ["black"]),
        "    </svg>",
    ]
    splice(path, r'[ \t]*<svg xmlns="http://www\.w3\.org/2000/svg" viewBox="0 0 834 649".*?</svg>', lines)
    replace_once(
        path,
        "with transparent cutouts and the host's themed ink.",
        "drawn in the host's themed ink (currentColor).",
        "docstring",
    )


def splice_workbot_mark():
    path = ROOT / "ee/packages/workbot-ui/src/mark.tsx"
    lines = [
        '    <svg viewBox="0 0 377 380" width={width} height={height} fill="none" aria-hidden'
        ' className={className}>',
        *(f'      <path fill="var(--wb-ink)" d="{PATHS[n]}" />' for n in GREEN + ["black"]),
        "    </svg>",
    ]
    splice(path, r'[ \t]*<svg viewBox="146 7 468 585".*?</svg>', lines)
    text, _ = read_text(path)
    old = (
        r"The idexal Os mark, compact \(the desktop sidebar's crop of idexal-os-mark\.svg\)\."
        r" Inlined so Workbot needs no asset" + NL + r" \* from its host; filled with Workbot's ink\."
    )
    new = "The idexal Os mark, inlined so Workbot needs no asset from its host; filled with Workbot's ink."
    updated, count = re.subn(old, lambda _: new, text)
    if count == 1:
        path.write_bytes(updated.encode("utf-8"))
        print(f"edited  {path.relative_to(ROOT)}  [comment]")
    elif count == 0 and new in text:
        print(f"skipped {path.relative_to(ROOT)} (already applied) [comment]")
    else:
        raise AssertionError(f"{path}: comment anchor matched {count} times")


def splice_browser_frame():
    path = ROOT / "packages/presentation/src/browser-frame.tsx"
    lines = [
        "    <svg",
        '      xmlns="http://www.w3.org/2000/svg"',
        '      viewBox="0 0 377 380"',
        "      width={size}",
        "      height={size}",
        '      fill="none"',
        "    >",
        *defs_block(6, jsx=True),
        *color_paths(6, jsx=True),
        "    </svg>",
    ]
    pattern = (
        r'[ \t]*<svg' + NL + r'      xmlns="http://www\.w3\.org/2000/svg"' + NL
        + r'      viewBox="0 0 834 649"' + r'.*?</svg>'
    )
    splice(path, pattern, lines)


def splice_oauth_page():
    path = ROOT / "ee/apps/den-api/src/capability-sources/oauth-callback-page.ts"
    lines = [
        '<svg aria-hidden="true"',
        '  xmlns="http://www.w3.org/2000/svg"',
        '  viewBox="0 0 377 380"',
        '  fill="none"',
        ">",
        *defs_block(2, jsx=False),
        *color_paths(2, jsx=False),
        "</svg>",
    ]
    splice(path, r'[ \t]*<svg aria-hidden="true".*?</svg>', lines)


def splice_org_shell():
    path = ROOT / "ee/apps/den-web/app/(den)/dashboard/_components/org-dashboard-shell.tsx"
    lines = [
        "    <svg",
        '      xmlns="http://www.w3.org/2000/svg"',
        '      viewBox="0 0 377 380"',
        '      fill="none"',
        "      className={className}",
        '      aria-label="idexal Os"',
        "    >",
        *defs_block(6, jsx=True),
        *color_paths(6, jsx=True),
        "    </svg>",
    ]
    pattern = (
        r'[ \t]*<svg' + NL + r'      xmlns="http://www\.w3\.org/2000/svg"' + NL
        + r'      viewBox="0 0 834 649"' + NL
        + r'      fill="none"' + NL
        + r'      className=\{className\}' + NL
        + r'      aria-label="idexal Os"' + NL
        + r'    >' + r'.*?</svg>'
    )
    splice(path, pattern, lines)


def splice_og_image():
    path = ROOT / "ee/apps/landing/components/og-image-content.tsx"
    lines = [
        '      <svg width={size} height={(size * 380) / 377} viewBox="0 0 377 380" fill="none"'
        ' xmlns="http://www.w3.org/2000/svg">',
        *defs_block(8, jsx=True),
        *color_paths(8, jsx=True),
        "      </svg>",
    ]
    splice(path, r'[ \t]*<svg width=\{size\} height=\{\(size \* 38\) / 48\} viewBox="0 0 834 649".*?</svg>', lines)


# ---------------------------------------------------------- consumer tweaks

DARK_OLD = "dark:invert"
DARK_NEW = "dark:brightness-0 dark:invert"


def tweak_consumers():
    dark_edits = [
        ("apps/app/src/react-app/shell/engine-migration.tsx",
         'className="size-4 shrink-0 opacity-80 dark:invert"',
         f'className="size-4 shrink-0 opacity-80 {DARK_NEW}"'),
        ("apps/app/src/components/chat/app-builder-step.tsx",
         'className="size-4 shrink-0 opacity-80 dark:invert"',
         f'className="size-4 shrink-0 opacity-80 {DARK_NEW}"'),
        ("apps/app/src/react-app/domains/calendar/calendar-model-summary.tsx",
         'className="size-4 shrink-0 dark:invert"',
         f'className="size-4 shrink-0 {DARK_NEW}"'),
        ("apps/app/src/react-app/domains/apps/built-mcp-app-panel.tsx",
         'className="size-4 dark:invert"',
         f'className="size-4 {DARK_NEW}"'),
        ("apps/app/src/react-app/domains/session/sidebar/account-status-menu.tsx",
         'className="size-6 shrink-0 object-contain dark:invert"',
         f'className="size-6 shrink-0 object-contain {DARK_NEW}"'),
        ("apps/app/src/react-app/domains/onboarding/welcome-page.tsx",
         'className="shrink-0 dark:invert"',
         f'className="shrink-0 {DARK_NEW}"'),
        ("apps/app/src/react-app/domains/cloud/enterprise-activation-gate.tsx",
         'className="max-h-[26px] shrink-0 object-contain object-left dark:invert"',
         f'className="max-h-[26px] shrink-0 object-contain object-left {DARK_NEW}"'),
        ("apps/app/src/react-app/domains/cloud/den-signin-surface.tsx",
         '${props.logoUrl ? "" : "dark:invert"}',
         f'${{props.logoUrl ? "" : "{DARK_NEW}"}}'),
    ]
    for rel, old, new in dark_edits:
        replace_once(ROOT / rel, old, new, "dark filter")

    sizes = [
        ("ee/packages/workbot-ui/src/welcome.tsx",
         '<IdexalOsMark width={40} height={50} className="shrink-0" />',
         '<IdexalOsMark width={40} height={40} className="shrink-0" />'),
        ("ee/packages/workbot-ui/src/screen.tsx",
         '<IdexalOsMark width={size === "hero" ? 36 : 16} height={size === "hero" ? 45 : 20} className="shrink-0" />',
         '<IdexalOsMark width={size === "hero" ? 36 : 16} height={size === "hero" ? 36 : 16} className="shrink-0" />'),
    ]
    for rel, old, new in sizes:
        replace_once(ROOT / rel, old, new, "square mark size")

    email_old = '<Img src={LOGO_URL} width="31" height="24" alt="idexal Os" style={styles.brandLogo} />'
    email_new = '<Img src={LOGO_URL} width="24" height="24" alt="idexal Os" style={styles.brandLogo} />'
    for rel in (
        "packages/email/src/templates/organization-invite.tsx",
        "packages/email/src/templates/lifecycle-reminders.tsx",
    ):
        replace_once(ROOT / rel, email_old, email_new, "email logo size")

    landing = ROOT / "ee/apps/landing/components/idexal-os-mark.tsx"
    replace_once(landing, "width={834}", "width={377}", "next/image width")
    replace_once(landing, "height={649}", "height={380}", "next/image height")

    replace_once(ROOT / "packaging/aur/PKGBUILD", "Icon=idexalos", "Icon=idexal-os", "desktop icon name")


# ------------------------------------------------------------------ email PNG

def email_png():
    src = Image.open(EMIT / "render.png").convert("RGBA")
    height = 128
    width = round(src.width * height / src.height)
    art = src.resize((width, height), Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", (128, 128), (0, 0, 0, 0))
    canvas.paste(art, ((128 - width) // 2, 0), art)
    out = ROOT / "ee/apps/landing/public/email/idexal-os-mark.png"
    canvas.save(out, optimize=True)
    print(f"wrote   {out.relative_to(ROOT)}  {out.stat().st_size} bytes  {canvas.size[0]}x{canvas.size[1]}")


# ------------------------------------------------------------------ verify

def verify():
    for rel in SPLICED:
        text, _ = read_text(ROOT / rel)
        for marker in OLD_GEOM:
            assert marker not in text, f"{rel}: stale marker {marker!r}"
        for name in GREEN + ["black"]:
            assert PATHS[name] in text, f"{rel}: missing path {name!r}"
        assert 'viewBox="0 0 377 380"' in text, f"{rel}: viewBox"
        print(f"ok      {rel}")
    for rel in ASSETS:
        text, _ = read_text(ROOT / rel)
        for marker in OLD_ART:
            assert marker not in text, f"{rel}: stale marker {marker!r}"
        ET.fromstring(text)
        print(f"ok      {rel} (valid XML)")

    lockup = read_text(ROOT / "ee/apps/landing/public/idexal-os-logo.svg")[0]
    assert "data:image/png;base64," in lockup and 'viewBox="0 0 1136 380"' in lockup
    tile = read_text(ROOT / "apps/app/public/idexal-os-logo-square.svg")[0]
    assert "idexal-os-tile-shadow" in tile and "<g transform=" in tile

    # repo-wide leftover scan (warn only; _rebrand/ is the working dir)
    hits = []
    for base in ("apps", "ee", "packages", "packaging"):
        for path in (ROOT / base).rglob("*"):
            if not path.is_file() or "_rebrand" in path.parts:
                continue
            if path.suffix not in {".svg", ".ts", ".tsx", ".html", ".json", ".mdx"}:
                continue
            try:
                text = path.read_bytes().decode("utf-8")
            except UnicodeDecodeError:
                continue
            for marker in ["M445.095", "M670.016", "146 7 468 585", "834 649"]:
                if marker in text:
                    hits.append(f"{path.relative_to(ROOT)}  {marker!r}")
    for hit in hits:
        print(f"WARN leftover: {hit}")
    print(f"leftover scan: {len(hits)} hit(s)")


def main():
    assert DOC["viewBox"] == "0 0 377 380", DOC["viewBox"]
    write_standalone_assets()
    lockup = lockup_svg()
    for rel in (
        "ee/apps/landing/public/idexal-os-logo.svg",
        "ee/apps/den-web/public/idexal-os-logo-transparent.svg",
    ):
        write_lf(ROOT / rel, lockup)
    write_lf(ROOT / "apps/app/public/idexal-os-logo-square.svg", tile_svg())
    splice_ui_mark()
    splice_workbot_mark()
    splice_browser_frame()
    splice_oauth_page()
    splice_org_shell()
    splice_og_image()
    tweak_consumers()
    email_png()
    verify()
    print("apply-svgs: done")


if __name__ == "__main__":
    main()
