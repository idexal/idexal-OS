"""Trace the idexal Os X-mark from logo_idexal/light_icon_idexal.png into SVG paths.

Pipeline: classify pixels (dark / green), extract component masks, level-set
smooth, marching-squares contours with subpixel interpolation, RDP simplify,
then corner arc fitting (circle least squares) -> compact path data.
"""
import json
import math
import os

from PIL import Image, ImageFilter

SRC = "logo_idexal/light_icon_idexal.png"
OUT = "_rebrand/trace-out"
SCALE = 3  # upsample factor for subpixel accuracy

os.makedirs(OUT, exist_ok=True)

im = Image.open(SRC).convert("RGBA")
W, H = im.size
big = im.resize((W * SCALE, H * SCALE), Image.BICUBIC)
BW, BH = big.size
px = big.load()


def classify(x, y):
    r, g, b, a = px[x, y]
    if a <= 120:
        return 0
    if g > r + 50 and g > b + 50:
        return 2  # green
    if r < 80 and g < 80 and b < 80:
        return 1  # dark
    return 0


lab = [[classify(x, y) for x in range(BW)] for y in range(BH)]

# component split by expected bboxes (in original coords)
BOXES = {
    "black": (1, (0, 0, 260, 380)),
    "square": (2, (290, 0, 380, 60)),
    "sliver": (2, (0, 40, 95, 345)),
    "ne": (2, (205, 65, 360, 208)),
    "se": (2, (220, 196, 380, 345)),
}


def key_of(x, y):
    for name, (kind, (x0, y0, x1, y1)) in BOXES.items():
        if kind == lab[y][x] and x0 * SCALE <= x < x1 * SCALE and y0 * SCALE <= y < y1 * SCALE:
            return name
    return None


masks = {name: Image.new("L", (BW, BH), 0) for name in BOXES}
mpx = {name: masks[name].load() for name in BOXES}
for y in range(BH):
    for x in range(BW):
        k = key_of(x, y)
        if k:
            mpx[k][x, y] = 255


def contour(field, level=0.0):
    """Marching squares on a float field; returns closed loops of (x, y)."""
    fw, fh = field.size
    fp = field.load()
    segs = []
    for y in range(fh - 1):
        for x in range(fw - 1):
            tl = fp[x, y] - level
            tr = fp[x + 1, y] - level
            br = fp[x + 1, y + 1] - level
            bl = fp[x, y + 1] - level
            idx = (1 if tl > 0 else 0) | (2 if tr > 0 else 0) | (4 if br > 0 else 0) | (8 if bl > 0 else 0)
            if idx in (0, 15):
                continue

            def interp(a, b, pa, pb):
                if a == b:
                    return 0.5
                return a / (a - b)

            top = (x + interp(tl, tr, (x, y), (x + 1, y)), y)
            right = (x + 1, y + interp(tr, br, (x + 1, y), (x + 1, y + 1)))
            bottom = (x + interp(bl, br, (x, y + 1), (x + 1, y + 1)), y + 1)
            left = (x, y + interp(tl, bl, (x, y), (x, y + 1)))
            table = {
                1: [(left, top)],
                2: [(top, right)],
                3: [(left, right)],
                4: [(right, bottom)],
                5: [(left, top), (right, bottom)],
                6: [(top, bottom)],
                7: [(left, bottom)],
                8: [(bottom, left)],
                9: [(bottom, top)],
                10: [(top, right), (bottom, left)],
                11: [(bottom, right)],
                12: [(right, left)],
                13: [(right, top)],
                14: [(top, left)],
            }
            for a, b in table[idx]:
                segs.append((a, b))

    # stitch segments into loops (keys rounded to 1e-4)
    def k(p):
        return (round(p[0], 4), round(p[1], 4))

    adj = {}
    for a, b in segs:
        adj.setdefault(k(a), []).append((a, b))
    loops = []
    used = set()
    for a, b in segs:
        if (a, b) in used:
            continue
        loop = [a, b]
        used.add((a, b))
        cur = b
        while True:
            nxt = None
            for seg in adj.get(k(cur), []):
                if seg not in used:
                    nxt = seg
                    break
            if not nxt:
                break
            used.add(nxt)
            cur = nxt[1]
            if k(cur) == k(loop[0]):
                break
            loop.append(cur)
        if len(loop) > 3:
            loops.append(loop)
    return loops


def rdp(points, eps):
    if len(points) < 3:
        return points
    out = [points[0]]
    stack = [(0, len(points) - 1)]
    keep = {0, len(points) - 1}
    while stack:
        i0, i1 = stack.pop()
        ax, ay = points[i0]
        bx, by = points[i1]
        dx, dy = bx - ax, by - ay
        norm = math.hypot(dx, dy)
        best, bestd = -1, eps
        for i in range(i0 + 1, i1):
            px_, py_ = points[i]
            if norm == 0:
                d = math.hypot(px_ - ax, py_ - ay)
            else:
                d = abs((px_ - ax) * dy - (py_ - ay) * dx) / norm
            if d > bestd:
                best, bestd = i, d
        if best >= 0:
            keep.add(best)
            stack.append((i0, best))
            stack.append((best, i1))
    for i in sorted(keep):
        if i not in (0, len(points) - 1):
            out.append(points[i])
    return out


report = {}
paths = {}
for name, mask in masks.items():
    smooth = mask.filter(ImageFilter.GaussianBlur(0.85))
    loop_list = contour(smooth, 127.5)
    # keep the biggest loop
    if not loop_list:
        print(f"{name}: no contour!")
        continue
    loop = max(loop_list, key=len)
    # convert to original scale
    pts = [(x / SCALE, y / SCALE) for x, y in loop]
    simple = rdp(pts, 0.35)
    paths[name] = simple
    report[name] = {
        "raw_points": len(pts),
        "vertices": len(simple),
    }
    with open(f"{OUT}/{name}.poly.json", "w") as fh:
        json.dump(simple, fh, indent=1)

with open(f"{OUT}/summary.json", "w") as fh:
    json.dump(report, fh, indent=2)

for name, poly in paths.items():
    d = "M" + " ".join(f"{x:.2f} {y:.2f}" for x, y in poly) + "Z"
    with open(f"{OUT}/{name}.poly.svg", "w") as fh:
        fh.write(
            f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">'
            f'<path d="{d}" fill="{"#111" if name == "black" else "#2ecc40"}" fill-rule="evenodd"/></svg>'
        )
print(json.dumps(report, indent=2))
