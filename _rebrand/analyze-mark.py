"""Measure the idexal Os X-mark geometry from logo_idexal/light_icon_idexal.png.

Dumps full-resolution scanlines for the green mask and the dark mask so the
straight edges and corner arcs can be reconstructed as clean SVG paths.
"""
import sys
from PIL import Image

SRC = "logo_idexal/light_icon_idexal.png"

im = Image.open(SRC).convert("RGBA")
W, H = im.size
px = im.load()


def classify(x, y):
    r, g, b, a = px[x, y]
    if a <= 128:
        return "."
    if g > r + 50 and g > b + 50:
        return "G"
    if r < 60 and g < 60 and b < 60:
        return "K"
    return "?"


def runs(y, want):
    out = []
    start = None
    for x in range(W):
        c = classify(x, y)
        if c == want and start is None:
            start = x
        elif c != want and start is not None:
            out.append((start, x - 1))
            start = None
    if start is not None:
        out.append((start, W - 1))
    return out


out = open("_rebrand/mark-scan.txt", "w", encoding="utf-8")
print(f"# size {W}x{H}", file=out)
print("# --- horizontal scans (y, K runs, G runs) ---", file=out)
for y in range(0, H, 6):
    k = runs(y, "K")
    g = runs(y, "G")
    if k or g:
        print(f"y={y:3d} K={k} G={g}", file=out)

print(file=out)
print("# --- vertical scans (x, K runs, G runs) ---", file=out)


def vruns(x, want):
    out = []
    start = None
    for y in range(H):
        c = classify(x, y)
        if c == want and start is None:
            start = y
        elif c != want and start is not None:
            out.append((start, y - 1))
            start = None
    if start is not None:
        out.append((start, H - 1))
    return out


for x in range(0, W, 6):
    k = vruns(x, "K")
    g = vruns(x, "G")
    if k or g:
        print(f"x={x:3d} K={k} G={g}", file=out)
out.close()
