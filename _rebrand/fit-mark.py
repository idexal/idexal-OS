"""Fit clean geometry (lines + fillet arcs) to the idexal Os X-mark contours.

For every component of the source artwork:
  1. build a subpixel boundary field (alpha x color purity),
  2. extract the dense contour (marching squares),
  3. detect straight edges via RANSAC line fitting,
  4. fit circles to the leftover corner clusters,
  5. walk the contour to emit SVG path data (L + A commands),
then write per-shape path JSON + an SVG preview.
"""
import json
import math
import os
import random

from PIL import Image

SRC = "logo_idexal/light_icon_idexal.png"
OUT = "_rebrand/trace-out"
SCALE = 4
debug = True

os.makedirs(OUT, exist_ok=True)

im = Image.open(SRC).convert("RGBA")
W, H = im.size
big = im.resize((W * SCALE, H * SCALE), Image.BICUBIC)
BW, BH = big.size
px = big.load()

SHAPES = {
    "black": ("dark", (0, 0, 265, 380)),
    "square": ("green", (290, 0, 380, 62)),
    "sliver": ("green", (0, 40, 96, 345)),
    "ne": ("green", (205, 65, 362, 209)),
    "se": ("green", (218, 195, 380, 345)),
}


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

    adj = {}
    for a, b in segs:
        adj.setdefault(key(a), []).append((a, b))
    used = set()
    loops = []
    for a, b in segs:
        if (a, b) in used:
            continue
        used.add((a, b))
        loop = [a, b]
        cur = b
        while True:
            nxt = next((s for s in adj.get(key(cur), []) if s not in used), None)
            if nxt is None:
                break
            used.add(nxt)
            cur = nxt[1]
            if key(cur) == key(loop[0]):
                break
            loop.append(cur)
        if len(loop) > 10:
            loops.append(loop)
    loop = max(loops, key=len)
    return [(x0 / SCALE + p[0] / SCALE, y0 / SCALE + p[1] / SCALE) for p in loop]


def fit_line(pts):
    n = len(pts)
    mx = sum(p[0] for p in pts) / n
    my = sum(p[1] for p in pts) / n
    sxx = sum((p[0] - mx) ** 2 for p in pts)
    syy = sum((p[1] - my) ** 2 for p in pts)
    sxy = sum((p[0] - mx) * (p[1] - my) for p in pts)
    # principal axis
    theta = 0.5 * math.atan2(2 * sxy, sxx - syy)
    dx, dy = math.cos(theta), math.sin(theta)
    # normal form: a x + b y = c
    a, b = -dy, dx
    c = a * mx + b * my
    return a, b, c


def dist_to_line(a, b, c, p):
    return abs(a * p[0] + b * p[1] - c)


def ransac_lines(points, tol, min_inliers, max_lines=40):
    idx_left = list(range(len(points)))
    lines = []
    rng = random.Random(7)
    while len(idx_left) > min_inliers and len(lines) < max_lines:
        best = None
        for _ in range(1200):
            i, j = rng.sample(idx_left, 2)
            p1, p2 = points[i], points[j]
            if math.hypot(p1[0] - p2[0], p1[1] - p2[1]) < 40:
                continue
            mx, my = (p1[0] + p2[0]) / 2, (p1[1] + p2[1]) / 2
            dx, dy = p2[0] - p1[0], p2[1] - p1[1]
            ln = math.hypot(dx, dy)
            a, b = -dy / ln, dx / ln
            c = a * mx + b * my
            inl = [k for k in idx_left if dist_to_line(a, b, c, points[k]) <= tol]
            if best is None or len(inl) > len(best[3]):
                best = (a, b, c, inl)
        a, b, c, inl = best
        if len(inl) < min_inliers:
            break
        # refit on inliers
        for _ in range(3):
            a, b, c = fit_line([points[k] for k in inl])
            inl = [k for k in idx_left if dist_to_line(a, b, c, points[k]) <= tol]
        lines.append({"abc": (a, b, c), "inliers": set(inl)})
        idx_left = [k for k in idx_left if k not in set(inl)]
    return lines


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


def solve_intersection(l1, l2):
    a1, b1, c1 = l1
    a2, b2, c2 = l2
    det = a1 * b2 - a2 * b1
    if abs(det) < 1e-9:
        return None
    return ((c1 * b2 - c2 * b1) / det, (a1 * c2 - a2 * c1) / det)


def build_shape(name, kind, box):
    level = 0.32 if kind == "dark" else 0.30
    pts = contour_points(kind, box, level)
    n = len(pts)
    tol = 0.55
    min_inliers = 90
    lines = ransac_lines(pts, tol, min_inliers)

    # Merge near-duplicate lines (same normal & offset) that RANSAC split apart.
    merged = []
    for line in lines:
        a, b, c = line["abc"]
        hit = None
        for m in merged:
            a2, b2, c2 = m["abc"]
            if abs(a * a2 + b * b2) > 0.9995 and abs(c - c2) < 2.5:
                hit = m
                break
        if hit is None:
            merged.append({"abc": line["abc"], "inliers": set(line["inliers"])})
        else:
            hit["inliers"] |= line["inliers"]
    # refit each merged line on all its inliers
    for m in merged:
        inl = sorted(m["inliers"])
        for _ in range(3):
            a, b, c = fit_line([pts[k] for k in inl])
            inl_new = [k for k in inl if dist_to_line(a, b, c, pts[k]) <= tol]
            if len(inl_new) < len(inl) * 0.9:
                break
            inl = inl_new
        a, b, c = fit_line([pts[k] for k in inl])
        m["abc"] = (a, b, c)
        m["inliers"] = set(inl)
    merged.sort(key=lambda m: -len(m["inliers"]))
    lines = merged

    assign = [-1] * n
    for li, line in enumerate(lines):
        for k in line["inliers"]:
            assign[k] = li

    # merge tiny gaps of corner points between the SAME line
    for i in range(n):
        if assign[i] != -1:
            continue
        prev = assign[(i - 1) % n]
        nxt = assign[(i + 1) % n]
        if prev == nxt and prev != -1:
            assign[i] = prev

    # walk: collect runs
    start = next((i for i in range(n) if assign[i] != -1), 0)
    runs = []
    cur = assign[start]
    run_start = start
    for step in range(1, n + 1):
        i = (start + step) % n
        if assign[i] != cur:
            runs.append((cur, run_start, (start + step - 1) % n))
            cur = assign[i]
            run_start = i
    runs.append((cur, run_start, (start + n - 1) % n))
    # merge first/last if same line
    if len(runs) > 1 and runs[0][0] == runs[-1][0] and runs[0][0] != -1:
        _c, s0, _e0 = runs[0]
        _cl, sl, el = runs.pop()
        runs[0] = (_c, sl, _e0)

    def run_indices(r):
        _v, s, e = r
        out = []
        i = s
        while True:
            if assign[i] == _v:
                out.append(i)
            if i == e:
                break
            i = (i + 1) % n
        return out

    # geometry for each run
    segs = []
    for r in runs:
        li, _s, _e = r
        if li == -1:
            idxs = run_indices(r)
            cluster = [pts[i] for i in idxs]
            segs.append({"type": "corner", "idx": idxs, "pts": cluster})
        else:
            segs.append({"type": "line", "line": li, "idx": run_indices(r)})

    if debug:
        print(f"== {name} runs ==")
        for i, seg in enumerate(segs):
            if seg["type"] == "line":
                idxs = seg["idx"]
                p0, p1 = pts[idxs[0]], pts[idxs[-1]]
                print(f"  {i:2d} line{seg['line']} n={len(idxs):4d} "
                      f"({p0[0]:7.2f},{p0[1]:7.2f}) -> ({p1[0]:7.2f},{p1[1]:7.2f})")
            else:
                cl = seg["pts"]
                print(f"  {i:2d} corner n={len(cl):4d} "
                      f"({cl[0][0]:7.2f},{cl[0][1]:7.2f}) -> ({cl[-1][0]:7.2f},{cl[-1][1]:7.2f})")

    # build path items
    items = []
    for i, seg in enumerate(segs):
        if seg["type"] == "line":
            items.append({"kind": "line", "line": seg["line"]})
        else:
            cluster = seg["pts"]
            prev = segs[(i - 1) % len(segs)]
            nxt = segs[(i + 1) % len(segs)]
            if prev["type"] != "line" or nxt["type"] != "line" or prev["line"] == nxt["line"]:
                items.append({"kind": "line", "line": None, "raw": cluster})
                continue
            l1 = lines[prev["line"]]["abc"]
            l2 = lines[nxt["line"]]["abc"]
            arc_pts = [p for p in cluster if dist_to_line(*l1, p) > 0.45 and dist_to_line(*l2, p) > 0.45]
            circle = fit_circle(arc_pts) if len(arc_pts) >= 8 else None
            ok = False
            if circle:
                cx, cy, r = circle
                res = max(abs(math.hypot(p[0] - cx, p[1] - cy) - r) for p in arc_pts)
                if 1.0 <= r <= 90 and res < 0.75:
                    def project(l):
                        a, b, c = l
                        t = a * cx + b * cy - c
                        return (cx - a * t, cy - b * t)
                    t1 = project(l1)
                    t2 = project(l2)
                    e1, e2 = arc_pts[0], arc_pts[-1]
                    if (math.hypot(t1[0] - e1[0], t1[1] - e1[1]) < 12 and
                            math.hypot(t2[0] - e2[0], t2[1] - e2[1]) < 12):
                        items.append({"kind": "arc", "center": (cx, cy), "r": r, "t1": t1, "t2": t2,
                                      "arc_pts": arc_pts, "res": res, "lines": (prev["line"], nxt["line"])})
                        ok = True
            if not ok:
                inter = solve_intersection(l1, l2)
                items.append({"kind": "vertex", "p": inter, "raw_pts": cluster, "lines": (prev["line"], nxt["line"])})

    # ---- assemble a closed path with a single meet point per transition ----
    m = len(items)
    # points between consecutive items
    meet = [None] * m  # meet[i] = point between items[i-1] and items[i]
    for i in range(m):
        cur_item = items[i]
        prev_item = items[i - 1]
        # point closest to the start of cur_item
        if cur_item["kind"] == "arc" and prev_item["kind"] == "arc":
            meet[i] = ((prev_item["t2"][0] + cur_item["t1"][0]) / 2, (prev_item["t2"][1] + cur_item["t1"][1]) / 2)
        elif cur_item["kind"] == "arc":
            meet[i] = cur_item["t1"]
        elif cur_item["kind"] == "vertex":
            meet[i] = cur_item["p"]
        else:
            # cur is line: derive from prev geometry
            if prev_item["kind"] == "arc":
                meet[i] = prev_item["t2"]
            elif prev_item["kind"] == "vertex":
                meet[i] = prev_item["p"]
            else:
                meet[i] = None  # resolved below (line-line intersection)

    # resolve line-line intersections
    for i in range(m):
        if meet[i] is not None:
            continue
        cur_item = items[i]
        prev_item = items[i - 1]
        if cur_item["kind"] == "line" and prev_item["kind"] == "line":
            if cur_item["line"] is not None and prev_item["line"] is not None:
                meet[i] = solve_intersection(lines[prev_item["line"]]["abc"], lines[cur_item["line"]]["abc"])
            if meet[i] is None:
                # fall back: midpoint between the two runs
                a = cur_item.get("raw") or [pts[k] for k in sorted(lines[cur_item["line"]]["inliers"])]
                b = prev_item.get("raw") or [pts[k] for k in sorted(lines[prev_item["line"]]["inliers"])]
                meet[i] = ((a[0][0] + b[-1][0]) / 2, (a[0][1] + b[-1][1]) / 2)

    # raw line runs (no fitted line) may still be missing meets: any None -> clipped raw endpoints
    for i in range(m):
        if meet[i] is None:
            cur_item = items[i]
            raw = cur_item.get("raw") or cur_item.get("raw_pts") or []
            meet[i] = raw[0] if raw else (0.0, 0.0)

    path = []
    for i, item in enumerate(items):
        start_pt = meet[i]
        end_pt = meet[(i + 1) % m]
        if item["kind"] == "line":
            if item["line"] is None and "raw" in item:
                raw = item["raw"]
                start_pt = raw[0]
                end_pt = raw[-1]
            path.append({"kind": "line", "from": start_pt, "to": end_pt})
        elif item["kind"] == "vertex":
            if item["p"] is None:
                # keep the meet points so the outline stays closed
                path.append({"kind": "line", "from": start_pt, "to": end_pt})
            else:
                path.append({"kind": "vertex", "p": item["p"]})
        else:
            path.append({"kind": "arc", "center": item["center"], "r": item["r"], "t1": start_pt, "t2": end_pt,
                         "arc_pts": item["arc_pts"], "res": item["res"]})

    return {"name": name, "level": level, "n_points": n, "lines": [
        {"abc": l["abc"], "n": len(l["inliers"])} for l in lines], "path": path}


def dump_fits(fits):
    with open(f"{OUT}/geometry.txt", "w") as fh:
        for fit in fits:
            print(f"==== {fit['name']} (points={fit['n_points']})", file=fh)
            for li, l in enumerate(fit["lines"]):
                a, b, c = l["abc"]
                print(f"  line {li}: {a:.4f} x + {b:.4f} y = {c:.2f}  (n={l['n']})", file=fh)
            for item in fit["path"]:
                k = item["kind"]
                if k == "line":
                    f, t = item["from"], item["to"]
                    if "l" in item:
                        a, b, c = item["l"]
                        print(f"  line  l=({a:.4f},{b:.4f},{c:.2f}) from={f} to={t}", file=fh)
                    else:
                        print(f"  line  from={None if f is None else (round(f[0],2), round(f[1],2))} "
                              f"to={None if t is None else (round(t[0],2), round(t[1],2))}", file=fh)
                elif k == "vertex":
                    p = item["p"]
                    print(f"  vertex p={None if p is None else (round(p[0],2), round(p[1],2))}", file=fh)
                else:
                    cx, cy = item["center"]
                    print(f"  arc c=({cx:.2f},{cy:.2f}) r={item['r']:.2f} "
                          f"t1={tuple(round(v,2) for v in item['t1'])} t2={tuple(round(v,2) for v in item['t2'])} "
                          f"res={item['res']:.2f} npts={len(item['arc_pts'])}", file=fh)


def emit_svg(fits):
    dump_fits(fits)
    parts = []
    for fit in fits:
        d = []
        first = True
        for item in fit["path"]:
            if item["kind"] == "line":
                f, t = item["from"], item["to"]
                if f is None or t is None:
                    continue
                if first:
                    d.append(f"M {f[0]:.2f} {f[1]:.2f}")
                    first = False
                d.append(f"L {t[0]:.2f} {t[1]:.2f}")
            elif item["kind"] == "vertex":
                if item["p"] is None:
                    continue
                if first:
                    d.append(f"M {item['p'][0]:.2f} {item['p'][1]:.2f}")
                    first = False
                d.append(f"L {item['p'][0]:.2f} {item['p'][1]:.2f}")
            else:
                cx, cy = item["center"]
                r = item["r"]
                t1, t2 = item["t1"], item["t2"]
                # choose flags by testing arc midpoint direction
                a1 = math.atan2(t1[1] - cy, t1[0] - cx)
                a2 = math.atan2(t2[1] - cy, t2[0] - cx)
                mid_target = item["arc_pts"][len(item["arc_pts"]) // 2]
                best = None
                for large in (0, 1):
                    for sweep in (0, 1):
                        # sample midpoint of candidate sweep
                        if sweep == 0:
                            am = a1 - ((a1 - a2) % (2 * math.pi)) / 2
                        else:
                            am = a1 + ((a2 - a1) % (2 * math.pi)) / 2
                        mp = (cx + r * math.cos(am), cy + r * math.sin(am))
                        err = math.hypot(mp[0] - mid_target[0], mp[1] - mid_target[1])
                        if best is None or err < best[0]:
                            best = (err, large, sweep)
                _e, large, sweep = best
                if first:
                    d.append(f"M {t1[0]:.2f} {t1[1]:.2f}")
                    first = False
                d.append(f"L {t1[0]:.2f} {t1[1]:.2f} A {r:.2f} {r:.2f} 0 {large} {sweep} {t2[0]:.2f} {t2[1]:.2f}")
        d.append("Z")
        parts.append({
            "name": fit["name"],
            "d": " ".join(d),
            "fill": "#111111" if fit["name"] == "black" else "#22cc22",
        })    
    svg = [f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">']
    for p in parts:
        svg.append(f'  <path id="{p["name"]}" d="{p["d"]}" fill="{p["fill"]}" fill-rule="evenodd"/>')
    svg.append("</svg>")
    with open(f"{OUT}/fit.svg", "w") as fh:
        fh.write("\n".join(svg))
    with open(f"{OUT}/fit.json", "w") as fh:
        json.dump([{"name": f["name"], "path": [
            {k: v for k, v in item.items() if k not in ("arc_pts", "raw_pts")} for item in f["path"]]} for f in fits], fh,
            indent=1, default=str)


if __name__ == "__main__":
    all_fits = []
    for name, (kind, box) in SHAPES.items():
        fit = build_shape(name, kind, box)
        all_fits.append(fit)
        nline = sum(1 for i in fit["path"] if i["kind"] == "line")
        narc = sum(1 for i in fit["path"] if i["kind"] == "arc")
        nvert = sum(1 for i in fit["path"] if i["kind"] == "vertex")
        print(f"{name}: points={fit['n_points']} lines={len(fit['lines'])} items: line={nline} arc={narc} vertex={nvert}")

    emit_svg(all_fits)
    print("wrote", f"{OUT}/fit.svg")