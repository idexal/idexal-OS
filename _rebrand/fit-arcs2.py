"""Robust refits for the problem arcs + exploration of small unknowns."""
import math

from fit_mark_helpers import contour_points, fit_circle, dist_to_line

BLACK = {
    "l0": (-0.7746, 0.6325, -3.20),
    "l1": (0.7708, 0.6371, 250.63),
    "l2": (0.7816, 0.6237, 323.92),
    "l3": (-0.7717, 0.6360, -75.64),
    "l4": (0.0003, 1.0000, 376.25),
    "l5": (-0.0011, 1.0000, 2.61),
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


def robust_fit(name, kind, box, lines, tol, extra=None):
    pts = contour_points(kind, box, 0.32 if kind == "dark" else 0.30)
    sel = [p for p in pts
           if all(dist_to_line(*l, p) > tol for l in lines)
           and (extra is None or extra(p))]
    if len(sel) < 8:
        print(f"{name}: too few points ({len(sel)})")
        return
    cur = sel
    for it in range(6):
        circ = fit_circle(cur)
        if not circ:
            print(f"{name}: fit failed")
            return
        cx, cy, r = circ
        res = [abs(math.hypot(p[0] - cx, p[1] - cy) - r) for p in cur]
        keep = [p for p, d in zip(cur, res) if d < max(0.5, max(res) * 0.6)]
        if len(keep) == len(cur) or len(keep) < 8:
            break
        cur = keep
    cx, cy, r = fit_circle(cur)
    res = [abs(math.hypot(p[0] - cx, p[1] - cy) - r) for p in cur]
    rms = math.sqrt(sum(d * d for d in res) / len(res))
    angs = sorted(math.degrees(math.atan2(p[1] - cy, p[0] - cx)) for p in cur)
    print(f"{name:14s} n={len(pts):4d}->{len(sel):4d}->{len(cur):4d} c=({cx:7.2f},{cy:7.2f}) r={r:6.2f} "
          f"maxres={max(res):5.2f} rms={rms:5.3f} ang[{angs[0]:7.1f}..{angs[-1]:7.1f}]")
    for i, line in enumerate(lines):
        a, b, c = line
        s = a * cx + b * cy - c
        foot = (cx - a * s, cy - b * s)
        print(f"     line{i}: d={abs(s):6.2f} d-r={abs(s) - r:6.2f} foot=({foot[0]:7.2f},{foot[1]:7.2f})")


robust_fit("black-tip", "dark", (230, 166, 262, 218), [BLACK["l2"], BLACK["l3"]], 0.8)
robust_fit("ne-nose", "green", (200, 166, 256, 216), [NE["l3"], NE["l0"]], 0.9, extra=lambda p: p[1] < 208.5)
robust_fit("se-tip", "green", (248, 192, 276, 226), [SE["l0"], SE["l3"]], 0.9, extra=lambda p: p[0] > 252.0)
robust_fit("se-br", "green", (350, 325, 376, 347), [SE["l2"], SE["l0"]], 0.7)
robust_fit("black-p2", "dark", (4, 362, 26, 380), [BLACK["l1"], BLACK["l4"]], 0.5)
