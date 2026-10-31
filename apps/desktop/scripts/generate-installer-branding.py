"""Regenerate the Windows installer wizard art from the committed brand mark.

The NSIS sidebar and header are bitmaps baked into their own files, so they do
not follow the logo automatically — a product-wide mark or palette change
leaves them showing the previous brand. Run this whenever the mark changes:

    python apps/desktop/scripts/generate-installer-branding.py

Outputs, overwriting in place:
    apps/desktop/build/installerSidebar.bmp   164x314   installer + uninstaller
    apps/desktop/build/installerHeader.bmp    150x57    inner-page header

Both are 32-bit BMPs (the format electron-builder/NSIS already accept) and
carry no version number: the wizard title shows ${VERSION} at build time, so a
pinned number here only goes stale.

Colours are the site tokens: ink-950 #080a08, lime #7dff00. The app icon and
the mark are read from the repository, so a rebrand propagates from one run.

Requires Pillow (`pip install pillow`). Centred text uses Segoe UI where it is
present and falls back to Pillow's default elsewhere.
"""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

HERE = Path(__file__).resolve()
DESKTOP = HERE.parent.parent                 # apps/desktop
ROOT = DESKTOP.parent.parent                 # repository root
BUILD = DESKTOP / "build"
APP_ICON = DESKTOP / "resources" / "icons" / "icon.png"

INK = (8, 10, 8, 255)         # #080a08  --color-ink-950
LIME = (125, 255, 0, 255)     # #7dff00  --color-lime
WHITE = (255, 255, 255, 255)
GREY = (154, 160, 160, 255)
FONT_SEMIBOLD = "C:/Windows/Fonts/seguisb.ttf"
FONT_REGULAR = "C:/Windows/Fonts/segoeui.ttf"


def load_font(path, size):
    try:
        return ImageFont.truetype(path, size)
    except OSError:
        return ImageFont.load_default(size)


def centered(draw, text, fnt, cx, y, fill):
    left, top, right, _ = draw.textbbox((0, 0), text, font=fnt)
    draw.text((cx - (right - left) / 2 - left, y), text, font=fnt, fill=fill)


def paste_icon(canvas, height, cx, y):
    """The product's own app icon — the recognizable rounded plate."""
    icon = Image.open(APP_ICON).convert("RGBA").resize((height, height), Image.LANCZOS)
    canvas.alpha_composite(icon, (round(cx - height / 2), y))


def sidebar():
    w, h = 164, 314
    canvas = Image.new("RGBA", (w, h), INK)
    draw = ImageDraw.Draw(canvas)
    draw.rectangle((0, 0, 3, h), fill=LIME)

    paste_icon(canvas, 78, w / 2, 42)

    centered(draw, "idexal Os", load_font(FONT_SEMIBOLD, 21), w / 2, 136, WHITE)
    centered(draw, "Local-first AI workspace", load_font(FONT_REGULAR, 9), w / 2, 168, GREY)

    draw.line((18, 196, w - 18, 196), fill=(255, 255, 255, 28))
    for index, line in enumerate(("Your files.", "Your models.", "Your rules.")):
        centered(draw, line, load_font(FONT_REGULAR, 9), w / 2, 210 + index * 15, GREY)

    canvas.save(BUILD / "installerSidebar.bmp")
    print("installerSidebar.bmp", canvas.size)


def header():
    w, h = 150, 57
    canvas = Image.new("RGBA", (w, h), INK)
    draw = ImageDraw.Draw(canvas)
    draw.rectangle((0, 0, 2, h), fill=LIME)

    icon_h, x = 28, 11
    paste_icon(canvas, icon_h, x + icon_h / 2, (h - icon_h) // 2)

    fnt = load_font(FONT_SEMIBOLD, 14)
    _, top, _, bottom = draw.textbbox((0, 0), "idexal Os", font=fnt)
    draw.text((x + icon_h + 8, (h - (bottom - top)) / 2 - top), "idexal Os", font=fnt, fill=WHITE)

    canvas.save(BUILD / "installerHeader.bmp")
    print("installerHeader.bmp", canvas.size)


if __name__ == "__main__":
    sidebar()
    header()
