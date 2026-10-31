"""Shared helpers: contour extraction and circle/line fitting (used by fit-mark.py and fit-circles.py)."""
import math

from PIL import Image

SCALE = 4

im = Image.open("logo_idexal/light_icon_idexal.png").convert("RGBA")
W, H = im.size
big = im.resize((W * SCALE, H * SCALE), Image.BICUBIC)
BW, BH = big.size
px = big.load()


def dark_field(x, y):
    r, g, b, a = px[x, y]
    return (a / 255.0) * (1.0 - max(r, g, b) / 255.0)


def green_field(x, y):
    r, g, b, a = px[x, y]
    if a < 8:
        return 0.0
    return (a / 255.0) * max(0.0, (g - max(r, b))) / 255.0


field_fns = {"dark": dark_field, "green": green_field}


def contour_points(kind, box, level):
    x0, y0, x1, y1 = [v * SCALE for v in box]
    x0, y0 = max(0, x0), max(0, y0)
    x1, y1 = min(BW - 1, x1), min(BH - 1, y1)
    fn = field_fns[kind]
    fw, fh = x1 - x0 + 1, y1 - y0 + 1
    field = [[fn(x0 + i, y0 + j) for i in range(fw)] for j in range(fh)]

    segs = []
    for j in range(fh - 1):
        row, nrow = field[j], field[j + 1]
        for i in range(fw - 1):
            tl = row[i] - level
            tr = row[i + 1] - level
            br = nrow[i + 1] - level
            bl = nrow[i] - level
            idx = (1 if tl > 0 else 0) | (2 if tr > 0 else 0) | (4 if br > 0 else 0) | (8 if bl > 0 else 0)
            if idx in (0, 15):
                continue

            def ip(a, b):
                return a / (a - b) if a != b else 0.5

            top = (i + ip(tl, tr), j + 0.0)
            right = (i + 1.0, j + ip(tr, br))
            bottom = (i + ip(bl, br), j + 1.0)
            left = (i + 0.0, j + ip(tl, bl))
            table = {
                1: [(left, top)], 2: [(top, right)], 3: [(left, right)],
                4: [(right, bottom)], 5: [(left, top), (right, bottom)],
                6: [(top, bottom)], 7: [(left, bottom)], 8: [(bottom, left)],
                9: [(bottom, top)], 10: [(top, right), (bottom, left)],
                11: [(bottom, right)], 12: [(right, left)], 13: [(right, top)],
                14: [(top, left)],
            }
            for a, b in table[idx]:
                segs.append((a, b))

    def key(p):
        return (round(p[0], 3), round(p[1], 3))

    from collections import defaultdict

    by_key = defaultdict(list)
    for k, (a, b) in enumerate(segs):
        by_key[key(a)].append(k)
        by_key[key(b)].append(k)
    nseg = len(segs)
    used = [False] * nseg
    loops = []
    for k0 in range(nseg):
        if used[k0]:
            continue
        used[k0] = True
        a0, b0 = segs[k0]
        chain = [a0, b0]
        for direction in (1, 0):
            while True:
                cur = chain[-1] if direction else chain[0]
                nxt = None
                for k in by_key[key(cur)]:
                    if not used[k]:
                        nxt = k
                        break
                if nxt is None:
                    break
                used[nxt] = True
                a, b = segs[nxt]
                pt = b if key(a) == key(cur) else a
                if direction:
                    if key(pt) == key(chain[0]):
                        chain.append(pt)
                        break
                    chain.append(pt)
                else:
                    if key(pt) == key(chain[-1]):
                        chain.insert(0, pt)
                        break
                    chain.insert(0, pt)
        if len(chain) > 4:
            loops.append(chain)
    if not loops:
        return []
    loop = max(loops, key=len)
    return [(x0 / SCALE + p[0] / SCALE, y0 / SCALE + p[1] / SCALE) for p in loop]


def fit_line(pts):
    n = len(pts)
    mx = sum(p[0] for p in pts) / n
    my = sum(p[1] for p in pts) / n
    sxx = sum((p[0] - mx) ** 2 for p in pts)
    syy = sum((p[1] - my) ** 2 for p in pts)
    sxy = sum((p[0] - mx) * (p[1] - my) for p in pts)
    theta = 0.5 * math.atan2(2 * sxy, sxx - syy)
    dx, dy = math.cos(theta), math.sin(theta)
    a, b = -dy, dx
    c = a * mx + b * my
    return a, b, c


def dist_to_line(a, b, c, p):
    return abs(a * p[0] + b * p[1] - c)


def fit_circle(pts):
    n = len(pts)
    sx = sum(p[0] for p in pts) / n
    sy = sum(p[1] for p in pts) / n
    u = [(p[0] - sx, p[1] - sy) for p in pts]
    suu = sum(q[0] * q[0] for q in u)
    svv = sum(q[1] * q[1] for q in u)
    suv = sum(q[0] * q[1] for q in u)
    suuu = sum(q[0] ** 3 for q in u)
    svvv = sum(q[1] ** 3 for q in u)
    suvv = sum(q[0] * q[1] * q[1] for q in u)
    svuu = sum(q[1] * q[0] * q[0] for q in u)
    a11, a12 = 2 * suu, 2 * suv
    a22 = 2 * svv
    b1 = suuu + suvv
    b2 = svvv + svuu
    det = a11 * a22 - a12 * a12
    if abs(det) < 1e-9:
        return None
    cu = (b1 * a22 - b2 * a12) / det
    cv = (a11 * b2 - a12 * b1) / det
    cx, cy = cu + sx, cv + sy
    r = math.sqrt(cu * cu + cv * cv + (suu + svv) / n)
    return cx, cy, r
