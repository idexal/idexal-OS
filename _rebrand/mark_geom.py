"""Shared geometry + fast numpy raster/diff for the idexal Os mark trace.

The mark is assembled from fitted primitives (lines, circles) as before.
This module exposes:
  * PARAMS           - all tunable primitive numbers (lines: a,b fixed; c free)
  * build_shapes(P)  - path item lists per shape, junction feet recomputed
  * polygon_points() - flatten path items into a polygon point list
  * raster(P)        - numpy alpha mask 0..255 at 8x supersample, BOX down
  * ref_alpha()      - reference PNG alpha as numpy float array
  * diff_stats(P)    - total abs diff + worst 16x16 blocks
"""
import math
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parent.parent
REF_PNG = ROOT / "logo_idexal" / "light_icon_idexal.png"
SS = 8  # supersample factor

# ---------------------------------------------------------------- parameters

PARAMS = {
    # black chevron edges
    "B0": {"a": -0.7746, "b": 0.6325, "c": -3.20},
    "B1": {"a": 0.7708, "b": 0.6371, "c": 250.63},
    "B2": {"a": 0.7816, "b": 0.6237, "c": 323.92},
    "B3": {"a": -0.7717, "b": 0.6360, "c": -75.64},
    "B4": {"a": 0.0003, "b": 1.0000, "c": 376.25},
    "B5": {"a": -0.0011, "b": 1.0000, "c": 2.61},
    # black circles
    "ntc": {"x": 149.54, "y": 195.48, "r": 10.90},  # center notch V
    "tl": {"x": 95.30, "y": 13.89, "r": 11.00},      # top-left fillet
    "brc": {"x": 111.33, "y": 370.35, "r": 5.90},    # bottom-left round
    "tipc": {"x": 228.54, "y": 195.57, "r": 22.90},  # black center tip
    # sliver
    "Sx0": {"a": 1.0000, "b": 0.0005, "c": 5.75},
    "St1": {"a": -0.7694, "b": 0.6387, "c": 29.18},
    "St2": {"a": 0.7645, "b": 0.6446, "c": 220.82},
    "Sx1": {"a": 1.0000, "b": 0.0003, "c": 84.01},
    # square
    "SQ": {"x0": 298.94, "x1": 357.20, "y0": 3.14, "y1": 51.02, "r": 4.20},
    # NE band
    "l0": {"a": 0.7870, "b": 0.6169, "c": 323.82},
    "l1": {"a": 0.7865, "b": 0.6175, "c": 254.19},
    "l2": {"a": 0.0003, "b": 1.0000, "c": 74.67},
    "l3": {"a": -0.7893, "b": 0.6140, "c": -82.51},
    "ntl": {"x": 266.33, "y": 78.56, "r": 3.90},
    "nv": {"x": 218.30, "y": 140.61, "r": 4.40},
    "nose": {"x": 228.42, "y": 195.38, "r": 22.80},
    # SE band
    "m0": {"a": -0.7942, "b": 0.6076, "c": -89.98},
    "m1": {"a": -0.7981, "b": 0.6025, "c": -19.74},
    "m2": {"a": -0.0150, "b": 0.9999, "c": 331.89},
    "m3": {"a": 0.8499, "b": 0.5269, "c": 332.86},
    "setip": {"x": 207.06, "y": 182.34, "r": 59.80},
    "sev2": {"x": 228.59, "y": 265.82, "r": 2.00},
    "sebl": {"x": 277.92, "y": 337.65, "r": 1.50},
    "sebr": {"x": 368.54, "y": 454.57, "r": 118.50},
}

TUNE_LINES = ["B0", "B1", "B2", "B3", "B4", "B5", "Sx0", "St1", "St2", "Sx1",
              "l0", "l1", "l2", "l3", "m0", "m1", "m2", "m3"]
TUNE_CIRCLES = ["ntc", "tl", "brc", "tipc", "ntl", "nv", "nose",
                "setip", "sev2", "sebl", "sebr"]
TUNE_SQUARE = ["x0", "x1", "y0", "y1", "r"]


def inter(l1, l2):
    a1, b1, c1 = l1["a"], l1["b"], l1["c"]
    a2, b2, c2 = l2["a"], l2["b"], l2["c"]
    det = a1 * b2 - a2 * b1
    return ((c1 * b2 - c2 * b1) / det, (a1 * c2 - a2 * c1) / det)


def foot(c, line):
    a, b, cc = line["a"], line["b"], line["c"]
    s = a * c[0] + b * c[1] - cc
    return (c[0] - a * s, c[1] - b * s)


def apt(c, r, ang_deg):
    a = math.radians(ang_deg)
    return (c[0] + r * math.cos(a), c[1] + r * math.sin(a))


def center(d):
    return (d["x"], d["y"])


def build_shapes(P=PARAMS):
    shapes = {}

    # ---- black chevron ----
    P1 = inter(P["B5"], P["B0"])
    P2 = inter(P["B1"], P["B4"])
    ntc = center(P["ntc"])
    tl = center(P["tl"])
    brc = center(P["brc"])
    tipc = center(P["tipc"])
    black = [
        ("line", P["B5"], foot(tl, P["B5"]), P1),
        ("line", P["B0"], P1, foot(ntc, P["B0"])),
        ("arc", ntc, P["ntc"]["r"], foot(ntc, P["B0"]), foot(ntc, P["B1"])),
        ("line", P["B1"], foot(ntc, P["B1"]), P2),
        ("line", P["B4"], P2, foot(brc, P["B4"])),
        ("arc", brc, P["brc"]["r"], foot(brc, P["B4"]), foot(brc, P["B2"])),
        ("line", P["B2"], foot(brc, P["B2"]), foot(tipc, P["B2"])),
        ("arc", tipc, P["tipc"]["r"], foot(tipc, P["B2"]), foot(tipc, P["B3"])),
        ("line", P["B3"], foot(tipc, P["B3"]), foot(tl, P["B3"])),
        ("arc", tl, P["tl"]["r"], foot(tl, P["B3"]), foot(tl, P["B5"])),
    ]
    shapes["black"] = {"items": black, "fill": "#111111"}

    # ---- sliver (sharp quad) ----
    V1 = inter(P["Sx0"], P["St1"])
    V2 = inter(P["St1"], P["Sx1"])
    V3 = inter(P["Sx1"], P["St2"])
    V4 = inter(P["St2"], P["Sx0"])
    sliver = [
        ("line", P["Sx0"], V4, V1),
        ("line", P["St1"], V1, V2),
        ("line", P["Sx1"], V2, V3),
        ("line", P["St2"], V3, V4),
    ]
    shapes["sliver"] = {"items": sliver, "fill": "#22cc22"}

    # ---- square ----
    SQ = P["SQ"]
    sqx0, sqx1, sqy0, sqy1, sqr = SQ["x0"], SQ["x1"], SQ["y0"], SQ["y1"], SQ["r"]
    square = [
        ("line", None, (sqx0 + sqr, sqy0), (sqx1 - sqr, sqy0)),
        ("arc", (sqx1 - sqr, sqy0 + sqr), sqr, (sqx1 - sqr, sqy0), (sqx1, sqy0 + sqr)),
        ("line", None, (sqx1, sqy0 + sqr), (sqx1, sqy1 - sqr)),
        ("arc", (sqx1 - sqr, sqy1 - sqr), sqr, (sqx1, sqy1 - sqr), (sqx1 - sqr, sqy1)),
        ("line", None, (sqx1 - sqr, sqy1), (sqx0 + sqr, sqy1)),
        ("arc", (sqx0 + sqr, sqy1 - sqr), sqr, (sqx0 + sqr, sqy1), (sqx0, sqy1 - sqr)),
        ("line", None, (sqx0, sqy1 - sqr), (sqx0, sqy0 + sqr)),
        ("arc", (sqx0 + sqr, sqy0 + sqr), sqr, (sqx0, sqy0 + sqr), (sqx0 + sqr, sqy0)),
    ]
    shapes["square"] = {"items": square, "fill": "#22cc22"}

    # ---- NE band ----
    ntl = center(P["ntl"])
    nv = center(P["nv"])
    nose = center(P["nose"])
    TR = inter(P["l2"], P["l0"])
    ne = [
        ("line", P["l2"], TR, foot(ntl, P["l2"])),
        ("arc", ntl, P["ntl"]["r"], foot(ntl, P["l2"]), foot(ntl, P["l1"])),
        ("line", P["l1"], foot(ntl, P["l1"]), foot(nv, P["l1"])),
        ("arc", nv, P["nv"]["r"], foot(nv, P["l1"]), foot(nv, P["l3"])),
        ("line", P["l3"], foot(nv, P["l3"]), foot(nose, P["l3"])),
        ("arc", nose, P["nose"]["r"], foot(nose, P["l3"]), foot(nose, P["l0"])),
        ("line", P["l0"], foot(nose, P["l0"]), TR),
    ]
    shapes["ne"] = {"items": ne, "fill": "#22cc22"}

    # ---- SE band ----
    setip = center(P["setip"])
    sev2 = center(P["sev2"])
    sebl = center(P["sebl"])
    sebr = center(P["sebr"])

    def circ_line_hits(c, r, line, prefer="left"):
        f = foot(c, line)
        d = math.hypot(f[0] - c[0], f[1] - c[1])
        if d > r:
            return None
        half = math.sqrt(max(0.0, r * r - d * d))
        ux, uy = -line["b"], line["a"]
        p_a = (f[0] + ux * half, f[1] + uy * half)
        p_b = (f[0] - ux * half, f[1] - uy * half)
        if prefer is None:
            return p_a, p_b
        return p_a if (p_a[0] < p_b[0]) == (prefer == "left") else p_b

    A = circ_line_hits(setip, P["setip"]["r"], P["m0"], prefer="right")
    T = foot(setip, P["m3"])
    BR1 = circ_line_hits(sebr, P["sebr"]["r"], P["m2"], prefer="left")
    BR2 = circ_line_hits(sebr, P["sebr"]["r"], P["m0"], prefer="left")
    se = [
        ("line", P["m0"], BR2, A),
        ("arc", setip, P["setip"]["r"], A, T),
        ("line", P["m3"], T, foot(sev2, P["m3"])),
        ("arc", sev2, P["sev2"]["r"], foot(sev2, P["m3"]), foot(sev2, P["m1"])),
        ("line", P["m1"], foot(sev2, P["m1"]), foot(sebl, P["m1"])),
        ("arc", sebl, P["sebl"]["r"], foot(sebl, P["m1"]), foot(sebl, P["m2"])),
        ("line", P["m2"], foot(sebl, P["m2"]), BR1),
        ("arc", sebr, P["sebr"]["r"], BR1, BR2),
    ]
    shapes["se"] = {"items": se, "fill": "#22cc22"}
    return shapes


# ---------------------------------------------------------------- path maths

def mid_point(c, r, p0, p1):
    u0 = ((p0[0] - c[0]) / r, (p0[1] - c[1]) / r)
    u1 = ((p1[0] - c[0]) / r, (p1[1] - c[1]) / r)
    mx, my = u0[0] + u1[0], u0[1] + u1[1]
    ml = math.hypot(mx, my)
    if ml < 1e-6:
        ml = 1.0
    return (c[0] + r * mx / ml, c[1] + r * my / ml)


def svg_flags(c, r, p0, p1, mid):
    a0 = math.atan2(p0[1] - c[1], p0[0] - c[0])
    a1 = math.atan2(p1[1] - c[1], p1[0] - c[0])
    sweep_pos = (a1 - a0) % (2 * math.pi)
    sweep_neg = 2 * math.pi - sweep_pos
    best = None
    for sweep, sw in ((1, sweep_pos), (0, sweep_neg)):
        am = a0 + (sw / 2 if sweep else -sw / 2)
        mp = (c[0] + r * math.cos(am), c[1] + r * math.sin(am))
        err = math.hypot(mp[0] - mid[0], mp[1] - mid[1])
        if best is None or err < best[0]:
            best = (err, sweep, sw)
    large = 1 if best[2] > math.pi else 0
    return large, best[1]


def polygon_points(items, step_deg=1.5):
    pts = []
    for item in items:
        if item[0] == "line":
            _, line, p0, p1 = item
            pts.append(p0)
            pts.append(p1)
        else:
            _, c, r, p0, p1 = item
            a0 = math.degrees(math.atan2(p0[1] - c[1], p0[0] - c[0]))
            a1 = math.degrees(math.atan2(p1[1] - c[1], p1[0] - c[0]))
            mid = mid_point(c, r, p0, p1)
            am = math.degrees(math.atan2(mid[1] - c[1], mid[0] - c[0]))

            def norm(a):
                return a % 360

            span = (norm(a1) - norm(a0)) % 360
            mspan = (norm(am) - norm(a0)) % 360
            n = max(2, int(span / step_deg) + 1)
            if mspan <= span:
                for i in range(n + 1):
                    pts.append(apt(c, r, a0 + span * i / n))
            else:
                span = span - 360
                n = max(2, int(-span / step_deg) + 1)
                for i in range(n + 1):
                    pts.append(apt(c, r, a0 + span * i / n))
    return pts


def fmt(v):
    return f"{v:.4f}".rstrip("0").rstrip(".")


def shape_path_d(items):
    d = []
    cur = None
    for item in items:
        if item[0] == "line":
            _, line, p0, p1 = item
            if cur is None:
                d.append(f"M {fmt(p0[0])} {fmt(p0[1])}")
            elif math.hypot(cur[0] - p0[0], cur[1] - p0[1]) > 0.02:
                d.append(f"L {fmt(p0[0])} {fmt(p0[1])}")
            d.append(f"L {fmt(p1[0])} {fmt(p1[1])}")
            cur = p1
        else:
            _, c, r, p0, p1 = item
            if cur is None:
                d.append(f"M {fmt(p0[0])} {fmt(p0[1])}")
            elif math.hypot(cur[0] - p0[0], cur[1] - p0[1]) > 0.02:
                d.append(f"L {fmt(p0[0])} {fmt(p0[1])}")
            mid = mid_point(c, r, p0, p1)
            large, sweep = svg_flags(c, r, p0, p1, mid)
            d.append(f"A {fmt(r)} {fmt(r)} 0 {large} {sweep} {fmt(p1[0])} {fmt(p1[1])}")
            cur = p1
    d.append("Z")
    return " ".join(d)


# ---------------------------------------------------------------- raster

_REF_IMG = Image.open(REF_PNG).convert("RGBA")
W, H = _REF_IMG.size
_REF_A = np.asarray(_REF_IMG.split()[3], dtype=np.float64)


def ref_alpha():
    return _REF_A


def raster(P=PARAMS, ss=SS):
    """Render the mark to a float alpha array (W,H) in 0..255."""
    shapes = build_shapes(P)
    combined = None
    for name, shape in shapes.items():
        img = Image.new("L", (W * ss, H * ss), 0)
        draw = ImageDraw.Draw(img)
        pts = polygon_points(shape["items"])
        xy = [(int(round(p[0] * ss)), int(round(p[1] * ss))) for p in pts]
        draw.polygon(xy, fill=255)
        a = np.asarray(img, dtype=np.float64)
        combined = a if combined is None else np.maximum(combined, a)
    mask = combined.reshape(H, ss, W, ss).mean(axis=(1, 3))
    return mask


def diff_stats(P=PARAMS, ss=SS):
    mask = raster(P, ss)
    d = np.abs(_REF_A - mask)
    total = float(d.sum())
    worst = []
    for by in range(0, H, 16):
        for bx in range(0, W, 16):
            s = float(d[by:by + 16, bx:bx + 16].sum())
            worst.append((s, bx, by))
    worst.sort(reverse=True)
    return total, worst[:12], mask


if __name__ == "__main__":
    import sys
    t0 = None
    import time
    t0 = time.time()
    total, worst, mask = diff_stats()
    dt = time.time() - t0
    print(f"total abs diff: {total:.0f}  mean {total / (W * H):.3f}  ({dt:.2f}s/eval)")
    for s, bx, by in worst:
        print(f"  block ({bx:3d},{by:3d}) {s:.0f}")
