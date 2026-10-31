"""Produce side-by-side zoom crops (ref | mine | overlay) for given regions.

Usage: python _rebrand/crops2.py x0 y0 x1 y1 [zoom]
"""
import sys
from pathlib import Path
from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
REF = ROOT / "logo_idexal" / "light_icon_idexal.png"
OUT = Path(__file__).resolve().parent / "trace-out" / "final"

def load_overlay():
    return Image.open(OUT / "overlay.png").convert("RGB")

def panel(x0, y0, x1, y1, zoom, tag):
    ref = Image.open(REF).convert("RGBA")
    w, h = x1 - x0, y1 - y0
    ref_crop = ref.crop((x0, y0, x1, y1)).resize((w * zoom, h * zoom), Image.NEAREST)
    # composite ref on white + grid
    bg = Image.new("RGBA", ref_crop.size, (255, 255, 255, 255))
    ref_c = Image.alpha_composite(bg, ref_crop).convert("RGB")
    # my raster
    mine = Image.open(OUT / "mine.png").convert("RGBA")
    mine_crop = mine.crop((x0, y0, x1, y1)).resize((w * zoom, h * zoom), Image.NEAREST)
    bg2 = Image.new("RGBA", mine_crop.size, (255, 255, 255, 255))
    mine_c = Image.alpha_composite(bg2, mine_crop).convert("RGB")
    # overlay
    ov = load_overlay().crop((x0, y0, x1, y1)).resize((w * zoom, h * zoom), Image.NEAREST)
    # side by side horizontally
    out = Image.new("RGB", (ref_c.width * 3 + 20, ref_c.height), (255, 255, 255))
    out.paste(ref_c, (0, 0))
    out.paste(mine_c, (ref_c.width + 10, 0))
    out.paste(ov, (ref_c.width * 2 + 20, 0))
    # grid lines every 8 px source
    from PIL import ImageDraw
    d = ImageDraw.Draw(out)
    for gx in range(x0 - x0 % 8 + 8, x1, 8):
        px = (gx - x0) * zoom
        for off in (0, ref_c.width + 10, ref_c.width * 2 + 20):
            d.line([(off + px, 0), (off + px, out.height)], fill=(220, 220, 220), width=1)
    for gy in range(y0 - y0 % 8 + 8, y1, 8):
        py = (gy - y0) * zoom
        d.line([(0, py), (out.width, py)], fill=(220, 220, 220), width=1)
    p = OUT / f"cmp_{tag}.png"
    out.save(p)
    print(f"{tag}: {p}  ({x0},{y0})-({x1},{y1}) zoom={zoom}")

if __name__ == "__main__":
    args = sys.argv[1:]
    if len(args) == 5:
        x0, y0, x1, y1, zoom = map(int, args)
        panel(x0, y0, x1, y1, zoom, f"{x0}_{y0}")
    else:
        # named standard set
        panel(190, 95, 260, 175, 6, "ne-left")
        panel(225, 155, 285, 235, 6, "kiss")
        panel(240, 145, 310, 220, 6, "se-top")
        panel(0, 20, 60, 100, 6, "sliver-top")
        panel(55, 150, 115, 240, 6, "v-notch")
        panel(0, 300, 60, 360, 6, "sliver-bottom")
