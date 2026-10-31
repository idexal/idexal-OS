"""Generate idexal Os app icons + web favicons from logo_idexal sources.

Design (in 512-unit space, rendered on a 1024 master):
  * rounded-square light background (#F6F9FC) with soft shadow (prod)
  * rounded-square black background (dev)
  * mark centred at ~68% of the canvas
"""
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter

ROOT = Path(__file__).resolve().parent.parent
ICONS = ROOT / "apps" / "desktop" / "resources" / "icons"
LIGHT_MARK = ROOT / "logo_idexal" / "light_icon_idexal.png"
DARK_MARK = ROOT / "logo_idexal" / "dark_icon_idexal.png"

SS = 2               # master = 2x design units
BASE = 512           # design space
BG_RECT = (50, 50, 462, 462)
BG_RADIUS = 92
LIGHT_BG = (246, 249, 252, 255)   # #F6F9FC
DARK_BG = (0, 0, 0, 255)
MARK_H = 350         # mark height in design units


def rounded_mask(size, box, radius):
    img = Image.new("L", (size, size), 0)
    d = ImageDraw.Draw(img)
    d.rounded_rectangle(box, radius=radius, fill=255)
    return img


def compose(bg, mark_path, mark_h=MARK_H, shadow=True, full_bleed=False,
            canvas_units=BASE):
    """Render one master image. All geometry scales with the canvas size."""
    k = canvas_units / BASE
    size = canvas_units * SS
    box = tuple(v * k * SS for v in BG_RECT)
    radius = BG_RADIUS * k * SS
    if full_bleed:
        box = (0, 0, size, size)
        radius = 0
    canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))

    if shadow and not full_bleed:
        sh = Image.new("L", (size, size), 0)
        d = ImageDraw.Draw(sh)
        d.rounded_rectangle(box, radius=radius, fill=255)
        sh = sh.filter(ImageFilter.GaussianBlur(9 * k * SS))
        sh = sh.point(lambda v: int(v * 0.10))
        layer = Image.new("RGBA", (size, size), (0, 0, 0, 0))
        layer.putalpha(sh)
        canvas.alpha_composite(layer, (0, int(5 * k * SS)))

    if bg is not None:
        bg_layer = Image.new("RGBA", (size, size), bg)
        if full_bleed:
            canvas.alpha_composite(bg_layer)
        else:
            bg_layer.putalpha(rounded_mask(size, box, radius))
            canvas.alpha_composite(bg_layer)

    mark = Image.open(mark_path).convert("RGBA")
    th = int(mark_h * k * SS)
    tw = int(round(mark.width * th / mark.height))
    mark = mark.resize((tw, th), Image.LANCZOS)
    canvas.alpha_composite(mark, ((size - tw) // 2, (size - th) // 2))
    return canvas


def save_png_at(master, size, path):
    path.parent.mkdir(parents=True, exist_ok=True)
    master.resize((size, size), Image.LANCZOS).save(path)
    print(f"  {path.relative_to(ROOT)}  ({size}px)")


def main():
    prod = compose(LIGHT_BG, LIGHT_MARK)
    dev = compose(DARK_BG, DARK_MARK)
    mini = compose(LIGHT_BG, LIGHT_MARK, mark_h=340, shadow=False)
    landing = compose((255, 255, 255, 255), LIGHT_MARK, mark_h=380,
                      shadow=False, full_bleed=True, canvas_units=BASE)

    # --- desktop electron-builder icons ---
    save_png_at(prod, 512, ICONS / "icon.png")
    prod.resize((256, 256), Image.LANCZOS).save(
        ICONS / "icon.ico", sizes=[(16, 16), (24, 24), (32, 32), (48, 48),
                                   (64, 64), (128, 128), (256, 256)])
    print(f"  {ICONS.relative_to(ROOT)}/icon.ico")
    prod.resize((512, 512), Image.LANCZOS).save(
        ICONS / "icon.icns",
        sizes=[(16, 16), (32, 32), (64, 64), (128, 128), (256, 256), (512, 512)])
    print(f"  {ICONS.relative_to(ROOT)}/icon.icns")

    save_png_at(dev, 512, ICONS / "dev" / "icon.png")
    save_png_at(dev, 256, ICONS / "dev" / "128x128@2x.png")
    dev.resize((512, 512), Image.LANCZOS).save(
        ICONS / "dev" / "icon-dev.icns",
        sizes=[(16, 16), (32, 32), (64, 64), (128, 128), (256, 256), (512, 512)])
    print(f"  {ICONS.relative_to(ROOT)}/dev/icon-dev.icns")

    for s in (16, 24, 32, 48, 64, 96, 128, 256, 512):
        save_png_at(prod, s, ICONS / "linux" / f"{s}x{s}.png")

    # --- app web favicons / apple touch ---
    APP_PUB = ROOT / "apps" / "app" / "public"
    save_png_at(mini, 16, APP_PUB / "favicon-16x16.png")
    save_png_at(mini, 32, APP_PUB / "favicon-32x32.png")
    save_png_at(mini, 180, APP_PUB / "apple-touch-icon.png")

    # --- landing website icons (full white background) ---
    LAND_APP = ROOT / "ee" / "apps" / "landing" / "app"
    save_png_at(landing, 192, LAND_APP / "icon.png")
    save_png_at(landing, 180, LAND_APP / "apple-icon.png")
    landing.resize((32, 32), Image.LANCZOS).save(
        LAND_APP / "favicon.ico", sizes=[(16, 16), (32, 32), (48, 48)])
    print(f"  {LAND_APP.relative_to(ROOT)}/favicon.ico")


if __name__ == "__main__":
    main()
