"""Render fit.svg + source PNG in a comparison page for pixel diffing."""
import base64
import json
import os

OUT = "_rebrand/trace-out"
src_png = base64.b64encode(open("logo_idexal/light_icon_idexal.png", "rb").read()).decode()
fit_svg = open(f"{OUT}/fit.svg", encoding="utf-8").read()

svg_b64 = base64.b64encode(fit_svg.encode("utf-8")).decode()

html = f"""<!doctype html>
<html>
<head><meta charset="utf-8"><title>idexal Os mark trace compare</title>
<style>
  body {{ margin: 0; background: #fff; font: 12px monospace; }}
  .row {{ display: flex; gap: 8px; padding: 8px; }}
  .cell {{ border: 1px solid #ddd; }}
  canvas {{ display: block; }}
</style></head>
<body>
<div class="row">
  <div class="cell"><canvas id="ref" width="377" height="380"></canvas></div>
  <div class="cell"><canvas id="test" width="377" height="380"></canvas></div>
  <div class="cell"><canvas id="diff" width="377" height="380"></canvas></div>
</div>
<script>
window.__SRC = "data:image/png;base64,{src_png}";
window.__SVG = "data:image/svg+xml;base64,{svg_b64}";
window.loadAll = async () => {{
  const mk = (url) => new Promise((res, rej) => {{
    const im = new Image();
    im.onload = () => res(im);
    im.onerror = () => rej(new Error("load fail " + url.slice(0, 40)));
    im.src = url;
  }});
  const [src, svg] = await Promise.all([mk(window.__SRC), mk(window.__SVG)]);
  const drawRef = document.getElementById("ref").getContext("2d");
  drawRef.clearRect(0, 0, 377, 380);
  drawRef.drawImage(src, 0, 0);
  const drawTest = document.getElementById("test").getContext("2d");
  drawTest.clearRect(0, 0, 377, 380);
  drawTest.drawImage(svg, 0, 0, 377, 380);
  return {{ ok: true }};
}};
window.diffNow = () => {{
  const W = 377, H = 380;
  const get = (id) => document.getElementById(id).getContext("2d").getImageData(0, 0, W, H).data;
  const a = get("ref"), b = get("test");
  const dctx = document.getElementById("diff").getContext("2d");
  const dimg = dctx.createImageData(W, H);
  let sum = 0, max = 0, gt16 = 0, gt48 = 0, gt96 = 0;
  const blocks = new Map();
  for (let y = 0; y < H; y++) {{
    for (let x = 0; x < W; x++) {{
      const i = (y * W + x) * 4;
      // composite each over white
      const ar = 255 - (255 - a[i]) * (a[i + 3] / 255);
      const ag = 255 - (255 - a[i + 1]) * (a[i + 3] / 255);
      const ab = 255 - (255 - a[i + 2]) * (a[i + 3] / 255);
      const br = 255 - (255 - b[i]) * (b[i + 3] / 255);
      const bg = 255 - (255 - b[i + 1]) * (b[i + 3] / 255);
      const bb = 255 - (255 - b[i + 2]) * (b[i + 3] / 255);
      const d = Math.max(Math.abs(ar - br), Math.abs(ag - bg), Math.abs(ab - bb));
      sum += d;
      if (d > max) max = d;
      if (d > 16) gt16++;
      if (d > 48) gt48++;
      if (d > 96) gt96++;
      const bx = (x / 12) | 0, by = (y / 12) | 0;
      const key = by * 32 + bx;
      blocks.set(key, Math.max(blocks.get(key) || 0, d));
      dimg.data[i] = Math.min(255, d * 3);
      dimg.data[i + 1] = Math.max(0, 255 - d * 3);
      dimg.data[i + 2] = 0;
      dimg.data[i + 3] = 255;
    }}
  }}
  dctx.putImageData(dimg, 0, 0);
  const worst = [...blocks.entries()].sort((p, q) => q[1] - p[1]).slice(0, 12)
    .map(([k, v]) => [(k % 32) * 12, ((k / 32) | 0) * 12, v]);
  return {{ mean: sum / (W * H), max, gt16, gt48, gt96, worst }};
}};
</script>
</body>
</html>
"""

with open(f"{OUT}/compare.html", "w", encoding="utf-8") as fh:
    fh.write(html)
print("wrote", os.path.abspath(f"{OUT}/compare.html"))
print(json.dumps({"svg_bytes": len(fit_svg)}))
