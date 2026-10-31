"""Rebuild the diff evidence (overlay, crops) from optimized params.

Loads _rebrand/trace-out/params.json, renders, writes:
  trace-out/final/overlay.png, mine.png, fit.svg, paths.json, worst*.png
"""
import json
import sys
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
import mark_geom as mg  # noqa: E402

FINAL = HERE / "trace-out" / "final"
FINAL.mkdir(parents=True, exist_ok=True)
PARAMS_JSON = HERE / "trace-out" / "params.json"


def load_params():
    with open(PARAMS_JSON, encoding="utf-8") as fh:
        raw = json.load(fh)
    # ensure a,b kept and all keys present
    P = {}
    for k, v in mg.PARAMS.items():
        q = dict(v)
        if k in raw:
            for kk in v:
                if kk in raw[k]:
                    q[kk] = raw[k][kk]
        P[k] = q
    return P


def main():
    P = load_params()
    shapes = mg.build_shapes(P)

    # SVG (flat fills)
    svg = [f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {mg.W} {mg.H}" width="{mg.W}" height="{mg.H}">']
    for name, shape in shapes.items():
        svg.append(f'  <path id="{name}" d="{mg.shape_path_d(shape["items"])}" fill="{shape["fill"]}"/>')
    svg.append("</svg>")
    (HERE / "trace-out" / "fit.svg").write_text("\n".join(svg), encoding="utf-8")

    # raster
    mask = mg.raster(P)
    ref = mg.ref_alpha()
    d = np.abs(ref - mask)
    total = float(d.sum())
    print(f"total abs diff: {total:.0f}  mean {total / (mg.W * mg.H):.3f}")

    # overlay
    ov = np.full((mg.H, mg.W, 3), 248, dtype=np.uint8)
    r_on = ref > 127
    m_on = mask > 127
    ov[r_on & m_on] = (40, 40, 40)
    ov[r_on & ~m_on] = (40, 80, 255)
    ov[~r_on & m_on] = (255, 40, 40)
    Image.fromarray(ov).save(FINAL / "overlay.png")

    # color render
    col = Image.new("RGBA", (mg.W, mg.H), (0, 0, 0, 0))
    mx = Image.fromarray(mask.astype(np.uint8))
    for name, shape in shapes.items():
        im = Image.new("L", (mg.W, mg.H), 0)
        dd = ImageDraw.Draw(im)
        pts = mg.polygon_points(shape["items"])
        tmp = Image.new("L", (mg.W * 2, mg.H * 2), 0)
        t2 = ImageDraw.Draw(tmp)
        t2.polygon([(round(p[0] * 2), round(p[1] * 2)) for p in pts], fill=255)
        tmp = tmp.resize((mg.W, mg.H), Image.BOX)
        fill = (17, 17, 17, 255) if name == "black" else (34, 204, 34, 255)
        col.paste(Image.new("RGBA", (mg.W, mg.H), fill), (0, 0), tmp)
    col.save(FINAL / "mine.png")

    # worst blocks
    blocks = []
    for by in range(0, mg.H, 16):
        for bx in range(0, mg.W, 16):
            blocks.append((float(d[by:by + 16, bx:bx + 16].sum()), bx, by))
    blocks.sort(reverse=True)
    print("worst blocks:")
    for s, bx, by in blocks[:12]:
        print(f"  ({bx:3d},{by:3d}) {s:.0f}")

    # paths.json
    paths = {name: mg.shape_path_d(s["items"]) for name, s in shapes.items()}
    (FINAL / "paths.json").write_text(json.dumps(paths, indent=1), encoding="utf-8")


if __name__ == "__main__":
    main()
