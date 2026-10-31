"""Fit circles to specific corner clusters of the reference artwork and report
tangency against the known fitted edge lines."""
import math

from fit_mark_helpers import contour_points, fit_circle, dist_to_line

# normalized lines (a x + b y = c) from geometry.txt
BLACK = {
    "l0": (-0.7746, 0.6325, -3.20),
    "l1": (0.7708, 0.6371, 250.63),
    "l2": (0.7816, 0.6237, 323.92),
    "l3": (-0.7717, 0.6360, -75.64),
    "l4": (0.0003, 1.0000, 376.25),
    "l5": (-0.0011, 1.0000, 2.61),
}
SLIVER = {
    "x0": (1.0000, 0.0005, 5.75),
    "t1": (-0.7694, 0.6387, 29.18),
    "t2": (0.7645, 0.6446, 220.82),
    "x1": (1.0000, 0.0003, 84.01),
}
NE = {
    "l0": (0.7870, 0.6169, 323.82),
    "l1": (0.7865, 0.6175, 254.19),
    "l2": (0.0003, 1.0000, 74.67),
    "l3": (-0.7893, 0.6140, -82.51),
}
SE = {
    "l0": (-0.7942, 0.6076, -89.98),
    "l1": (-0.7981, 0.6025, -19.74),
    "l2": (-0.0150, 0.9999, 331.89),
    "l3": (0.8499, 0.5269, 332.86),
}
SQ = {
    "xt": (0.0011, 1.0000, 3.14),
    "xb": (0.0000, 1.0000, 51.02),
    "xr": (1.0000, 0.0011, 357.20),
    "xl": (1.0000, 0.0004, 298.94),
}

CLUSTERS = [
    ("black-tip", "dark", (237, 172, 261, 216), [BLACK["l2"], BLACK["l3"]]),
    ("black-notch", "dark", (153, 185, 171, 205), [BLACK["l0"], BLACK["l1"]]),
    ("black-tl", "dark", (86, 0, 114, 22), [BLACK["l3"], BLACK["l5"]]),
    ("black-br", "dark", (102, 366, 126, 380), [BLACK["l4"], BLACK["l2"]]),
    ("black-p1", "dark", (0, 0, 16, 14), [BLACK["l5"], BLACK["l0"]]),
    ("black-p2", "dark", (6, 366, 24, 380), [BLACK["l1"], BLACK["l4"]]),
    ("ne-nose", "green", (204, 170, 250, 212), [NE["l3"], NE["l0"]]),
    ("ne-tl", "green", (255, 70, 276, 84), [NE["l2"], NE["l1"]]),
    ("ne-v", "green", (206, 131, 222, 148), [NE["l1"], NE["l3"]]),
    ("se-tip", "green", (252, 193, 272, 224), [SE["l0"], SE["l3"]]),
    ("se-v2", "green", (216, 256, 236, 276), [SE["l3"], SE["l1"]]),
    ("se-bl", "green", (268, 328, 290, 346), [SE["l1"], SE["l2"]]),
    ("se-br", "green", (352, 327, 374, 345), [SE["l2"], SE["l0"]]),
    ("sq-tl", "green", (293, 0, 310, 14), [SQ["xt"], SQ["xl"]]),
    ("sq-bl", "green", (293, 40, 310, 58), [SQ["xb"], SQ["xl"]]),
    ("sq-br", "green", (346, 40, 362, 58), [SQ["xb"], SQ["xr"]]),
    ("sq-tr", "green", (346, 0, 362, 14), [SQ["xt"], SQ["xr"]]),
    ("sliver-top", "green", (0, 42, 22, 68), [SLIVER["x0"], SLIVER["t1"]]),
    ("sliver-bot", "green", (0, 322, 22, 348), [SLIVER["t2"], SLIVER["x0"]]),
    ("sliver-right-top", "green", (72, 136, 96, 160), [SLIVER["t1"], SLIVER["x1"]]),
    ("sliver-right-bot", "green", (70, 232, 96, 256), [SLIVER["t2"], SLIVER["x1"]]),
]


def foot(center, line):
    cx, cy = center
    a, b, c = line
    s = a * cx + b * cy - c
    return (cx - a * s, cy - b * s), abs(s)


for name, kind, box, lines in CLUSTERS:
    pts = contour_points(kind, box, 0.32 if kind == "dark" else 0.30)
    sel = [p for p in pts if all(dist_to_line(*l, p) > 0.45 for l in lines)]
    if len(sel) < 6:
        print(f"{name:16s} n={len(pts):4d} cluster={len(sel):4d}  -> too few points")
        continue
    circ = fit_circle(sel)
    if not circ:
        print(f"{name:16s} n={len(pts):4d} cluster={len(sel):4d}  -> circle fit failed")
        continue
    cx, cy, r = circ
    res = max(abs(math.hypot(p[0] - cx, p[1] - cy) - r) for p in sel)
    rms = math.sqrt(sum((math.hypot(p[0] - cx, p[1] - cy) - r) ** 2 for p in sel) / len(sel))
    angs = sorted(math.degrees(math.atan2(p[1] - cy, p[0] - cx)) for p in sel)
    print(f"{name:16s} n={len(pts):4d} cluster={len(sel):4d}  c=({cx:7.2f},{cy:7.2f}) r={r:6.2f} res={res:5.2f} rms={rms:5.3f} angles[{angs[0]:7.1f}..{angs[-1]:7.1f}]")
    for i, line in enumerate(lines):
        (fx, fy), d = foot((cx, cy), line)
        print(f"     line{i}: d={d:6.2f}  d-r={d - r:6.2f}  foot=({fx:7.2f},{fy:7.2f})")
