"""Bake the canonical idexal Os mark from the optimized trace.

Loads the optimized primitive params, rebuilds the path data, fits the
per-shape green gradients from the reference icon, validates both draw orders
against the reference (premultiplied RGBA diff), and emits into
trace-out/emit/:

  mark.json           canonical shapes: paths, gradient specs, draw order
  mark.svg            full-color canonical mark (377x380)
  mark-mono.svg       monochrome (ink-only) canonical mark
  render.png          full-color render, chosen draw order, 1x
  render-compare.png  reference | render side by side on white
  wordmark-light.png  dark-ink wordmark crop for light lockups (746x211)
  wordmark-dark.png   white-ink wordmark crop for dark lockups
"""
import json
import sys
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
sys.path.insert(0, str(HERE))
import mark_geom as mg  # noqa: E402

EMIT = HERE / "trace-out" / "emit"
PARAMS_JSON = HERE / "trace-out" / "params.json"
GREEN = ("sliver", "square", "ne", "se")
BLACK_FILL = "#0B0B0B"
BLACK_RGB = (11.0, 11.0, 11.0)
CROP = (382, 73, 1128, 284)  # wordmark region shared by both lockups


def fmt(v):
    return f"{v:.3f}".rstrip("0").rstrip(".")


def load_params():
    raw = json.loads(PARAMS_JSON.read_text(encoding="utf-8"))
    out = {}
    for key, val in mg.PARAMS.items():
        merged = dict(val)
        for field in val:
            if key in raw and field in raw[key]:
                merged[field] = raw[key][field]
        out[key] = merged
    return out


def coverage(items, ss):
    img = Image.new("L", (mg.W * ss, mg.H * ss), 0)
    draw = ImageDraw.Draw(img)
    pts = mg.polygon_points(items)
    draw.polygon([(int(round(p[0] * ss)), int(round(p[1] * ss))) for p in pts], fill=255)
    return np.asarray(img, dtype=np.float64).reshape(mg.H, ss, mg.W, ss).mean(axis=(1, 3)) / 255.0


def fit_green(name, cov, covs, ref):
    """Trimmed least-squares fit r = r0 + gx*x + gy*y over strict interior pixels."""
    near_other = np.zeros((mg.H, mg.W), dtype=bool)
    for other, c in covs.items():
        if other != name:
            near_other |= c > 0.02
    a = ref[:, :, 3]
    g_ch = ref[:, :, 1]
    b_ch = ref[:, :, 2]
    mask = (cov > 0.9995) & (a > 254) & (g_ch >= 252) & (b_ch <= 8) & ~near_other
    ys, xs = np.where(mask)
    r = ref[:, :, 0][mask].astype(np.float64)
    sel = np.ones(len(r), dtype=bool)
    coef = np.zeros(3)
    for _ in range(6):
        count = int(sel.sum())
        if count < 40:
            break
        A = np.stack([np.ones(count), xs[sel].astype(np.float64), ys[sel].astype(np.float64)], axis=1)
        coef, *_ = np.linalg.lstsq(A, r[sel], rcond=None)
        resid = r - (coef[0] + coef[1] * xs + coef[2] * ys)
        keep = np.abs(resid) < 2.0
        if keep.sum() == sel.sum():
            break
        sel = keep
    resid = r[sel] - (coef[0] + coef[1] * xs[sel] + coef[2] * ys[sel])
    rms = float(np.sqrt(np.mean(resid ** 2)))
    gx, gy = float(coef[1]), float(coef[2])
    mag = float(np.hypot(gx, gy))
    return {
        "r0": float(coef[0]), "gx": gx, "gy": gy, "mag": mag,
        "ux": gx / mag, "uy": gy / mag, "rms": rms,
        "g": int(round(float(np.median(g_ch[mask][sel])))),
        "b": int(round(float(np.median(b_ch[mask][sel])))),
        "n_raw": int(mask.sum()), "n_trim": int(sel.sum()),
    }


def mark_svg(paths, grads, mono=False):
    if mono:
        root_fill = "#0B0B0B"
    else:
        root_fill = "none"
    lines = [
        '<svg',
        '  xmlns="http://www.w3.org/2000/svg"',
        f'  viewBox="0 0 {mg.W} {mg.H}"',
        f'  fill="{root_fill}"',
        '>',
    ]
    if not mono:
        lines.append('  <defs>')
        for name in GREEN:
            g = grads[name]
            lines.append(
                f'    <linearGradient id="idexal-os-{name}" gradientUnits="userSpaceOnUse"'
                f' x1="{fmt(g["p1"][0])}" y1="{fmt(g["p1"][1])}"'
                f' x2="{fmt(g["p2"][0])}" y2="{fmt(g["p2"][1])}">'
            )
            lines.append(f'      <stop offset="0" stop-color="{g["stops"][0]}"/>')
            lines.append(f'      <stop offset="1" stop-color="{g["stops"][1]}"/>')
            lines.append('    </linearGradient>')
        lines.append('  </defs>')
    for name in GREEN:
        lines.append(f'  <path fill="{BLACK_FILL}" d="{paths[name]}"/>' if mono
                     else f'  <path fill="url(#idexal-os-{name})" d="{paths[name]}"/>')
    lines.append(f'  <path fill="{BLACK_FILL}" d="{paths["black"]}"/>')
    lines.append('</svg>')
    return "\n".join(lines) + "\n"


def main():
    EMIT.mkdir(parents=True, exist_ok=True)
    P = load_params()
    shapes = mg.build_shapes(P)
    paths = {name: mg.shape_path_d(s["items"]) for name, s in shapes.items()}

    ref = np.asarray(Image.open(mg.REF_PNG).convert("RGBA")).astype(np.float64)
    covs8 = {name: coverage(s["items"], 8) for name, s in shapes.items()}

    grads = {}
    for name in GREEN:
        fit = fit_green(name, covs8[name], covs8, ref)
        pts = mg.polygon_points(shapes[name]["items"])
        ts = [p[0] * fit["ux"] + p[1] * fit["uy"] for p in pts]
        tmin, tmax = min(ts), max(ts)
        r1 = fit["r0"] + fit["mag"] * tmin
        r2 = fit["r0"] + fit["mag"] * tmax
        grads[name] = {
            **fit,
            "tmin": tmin,
            "tmax": tmax,
            "r1": r1,
            "r2": r2,
            "stops": (
                f"rgb({int(round(np.clip(r1, 0, 255)))},{fit['g']},{fit['b']})",
                f"rgb({int(round(np.clip(r2, 0, 255)))},{fit['g']},{fit['b']})",
            ),
            "p1": (fit["ux"] * tmin, fit["uy"] * tmin),
            "p2": (fit["ux"] * tmax, fit["uy"] * tmax),
        }
        print(
            f"{name:7s} ang={np.degrees(np.arctan2(fit['gy'], fit['gx'])):7.2f}"
            f" |g|={fit['mag']:.5f} rms={fit['rms']:.2f} px={fit['n_trim']}/{fit['n_raw']}"
            f" gb=({fit['g']},{fit['b']}) t=[{tmin:.1f}..{tmax:.1f}]"
            f" {grads[name]['stops'][0]} -> {grads[name]['stops'][1]}"
        )

    # --- draw order validation (4x supersample, premultiplied diff) ---
    covs4 = {name: coverage(s["items"], 4) for name, s in shapes.items()}
    ys, xs = np.mgrid[0:mg.H, 0:mg.W].astype(np.float64)
    colors = {}
    for name in GREEN:
        fit = grads[name]
        r = fit["r0"] + fit["gx"] * xs + fit["gy"] * ys
        colors[name] = np.stack(
            [np.clip(r, 0, 255), np.full_like(r, fit["g"]), np.full_like(r, fit["b"])], axis=-1
        )
    colors["black"] = np.tile(np.array(BLACK_RGB), (mg.H, mg.W, 1))

    def compose(order):
        acc_rgb = np.zeros((mg.H, mg.W, 3))
        acc_a = np.zeros((mg.H, mg.W))
        for name in order:
            a = covs4[name][..., None]
            acc_rgb = colors[name] * a + acc_rgb * (1 - a)
            acc_a = a[..., 0] + acc_a * (1 - a[..., 0])
        return acc_rgb, acc_a

    ref_pm = ref[:, :, :3] * ref[:, :, 3:4] / 255.0
    results = {}
    for label, order in (("greens-first", list(GREEN) + ["black"]),
                         ("black-first", ["black"] + list(GREEN))):
        rgb, a = compose(order)
        diff = float(np.abs(rgb - ref_pm).mean() + np.abs(a * 255 - ref[:, :, 3]).mean())
        results[label] = diff
        print(f"order {label:12s} premultiplied mean abs diff = {diff:.5f}")
    # Pin the order instead of picking the metric winner: the only place the
    # order matters is the black x ne kiss (~89 px), where the two orders
    # differ sub-pixel and the reference winner flips row-to-row. Greens-first
    # renders the black chevron on top (semantic: the X reads as one mark),
    # which is also what the hand-assembled source art shows.
    chosen = "greens-first"
    order = list(GREEN) + ["black"]
    print(f"chosen draw order: {chosen}  ({order}) [pinned]")

    # --- full-color render at 1x (RGBA, un-premultiplied for saving) ---
    rgb, a = compose(order)
    out = np.zeros((mg.H, mg.W, 4), dtype=np.uint8)
    safe = np.maximum(a, 1e-6)
    out[:, :, :3] = np.clip(rgb / safe[..., None] + 0.5, 0, 255).astype(np.uint8)
    out[:, :, 3] = np.clip(a * 255 + 0.5, 0, 255).astype(np.uint8)
    out[a < 0.004] = 0
    Image.fromarray(out).save(EMIT / "render.png")

    def flatten(img):
        src = img.astype(np.float64)
        al = src[:, :, 3:4] / 255.0
        return np.clip(src[:, :, :3] * al + 255.0 * (1 - al) + 0.5, 0, 255).astype(np.uint8)

    panel = np.full((mg.H, mg.W * 2 + 8, 3), 225, dtype=np.uint8)
    panel[:, :mg.W] = flatten(ref)
    panel[:, mg.W + 8:] = flatten(out)
    Image.fromarray(panel).save(EMIT / "render-compare.png")

    # --- canonical SVGs ---
    (EMIT / "mark.svg").write_text(mark_svg(paths, grads, mono=False), encoding="utf-8")
    (EMIT / "mark-mono.svg").write_text(mark_svg(paths, grads, mono=True), encoding="utf-8")

    # --- mark.json ---
    doc = {
        "viewBox": f"0 0 {mg.W} {mg.H}",
        "width": mg.W,
        "height": mg.H,
        "ink": BLACK_FILL,
        "order": order,
        "paths": paths,
        "gradients": {
            name: {
                "id": f"idexal-os-{name}",
                "x1": round(grads[name]["p1"][0], 3),
                "y1": round(grads[name]["p1"][1], 3),
                "x2": round(grads[name]["p2"][0], 3),
                "y2": round(grads[name]["p2"][1], 3),
                "stops": list(grads[name]["stops"]),
                "angle_deg": round(float(np.degrees(np.arctan2(grads[name]["gy"], grads[name]["gx"]))), 2),
                "mag": round(grads[name]["mag"], 5),
                "r0": round(grads[name]["r0"], 2),
                "t": [round(grads[name]["tmin"], 2), round(grads[name]["tmax"], 2)],
                "r_at_t": [round(grads[name]["r1"], 2), round(grads[name]["r2"], 2)],
                "rms": round(grads[name]["rms"], 2),
                "samples": [grads[name]["n_trim"], grads[name]["n_raw"]],
                "g": grads[name]["g"],
                "b": grads[name]["b"],
            }
            for name in GREEN
        },
        "order_validation": results,
    }
    (EMIT / "mark.json").write_text(json.dumps(doc, indent=1) + "\n", encoding="utf-8")

    # --- wordmark crops ---
    for variant in ("light", "dark"):
        src = Image.open(ROOT / "logo_idexal" / f"{variant}_logo_idexal.png").convert("RGBA")
        alpha = np.asarray(src)[:, :, 3]
        yy, xx = np.where(alpha[:, CROP[0]:] > 0)
        bbox = (int(xx.min()) + CROP[0], int(yy.min()), int(xx.max()) + CROP[0], int(yy.max()))
        expected = (CROP[0], CROP[1], CROP[2] - 1, CROP[3] - 1)
        flag = "ok" if bbox == expected else "MISMATCH"
        print(f"{variant} wordmark ink bbox (inclusive) {bbox} vs expected {expected} [{flag}]")
        crop = src.crop(CROP)
        crop.save(EMIT / f"wordmark-{variant}.png", optimize=True)
        size = (EMIT / f"wordmark-{variant}.png").stat().st_size
        print(f"  saved wordmark-{variant}.png {crop.size[0]}x{crop.size[1]} {size} bytes")


if __name__ == "__main__":
    main()
