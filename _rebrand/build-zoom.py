"""Zoomed ref-vs-trace comparison page: ?x=..&y=..&w=..&h=..&z=.."""
import base64
import sys

OUT = "_rebrand/trace-out"
src_png = base64.b64encode(open("logo_idexal/light_icon_idexal.png", "rb").read()).decode()
fit_svg = open(f"{OUT}/fit.svg", encoding="utf-8").read()
svg_b64 = base64.b64encode(fit_svg.encode("utf-8")).decode()

html = f"""<!doctype html>
<html>
<head><meta charset="utf-8"><title>zoom compare</title>
<style>
 body {{ margin: 0; background: #fff; font: 11px monospace; }}
 .row {{ display: flex; gap: 6px; padding: 6px; }}
 .cell {{ position: relative; border: 1px solid #999; }}
 canvas {{ display: block; }}
 .lbl {{ position: absolute; top: 2px; left: 4px; color: #d00; z-index: 2; }}
</style></head>
<body>
<div class="row">
  <div class="cell"><span class="lbl">ref</span><canvas id="refZ"></canvas></div>
  <div class="cell"><span class="lbl">trace</span><canvas id="testZ"></canvas></div>
</div>
<script>
window.__SRC = "data:image/png;base64,{src_png}";
window.__SVG = "data:image/svg+xml;base64,{svg_b64}";
window.zoom = async (x, y, w, h, z) => {{
  const mk = (url) => new Promise((res, rej) => {{
    const im = new Image();
    im.onload = () => res(im);
    im.onerror = () => rej(new Error("load fail"));
    im.src = url;
  }});
  const [src, svg] = await Promise.all([mk(window.__SRC), mk(window.__SVG)]);
  const full = (which) => {{
    const c = document.createElement("canvas");
    c.width = 377; c.height = 380;
    const g = c.getContext("2d");
    g.drawImage(which === "ref" ? src : svg, 0, 0, 377, 380);
    return g;
  }};
  const refG = full("ref"), testG = full("test");
  for (const [id, g] of [["refZ", refG], ["testZ", testG]]) {{
    const c = document.getElementById(id);
    c.width = w * z; c.height = h * z;
    const ctx = c.getContext("2d");
    ctx.imageSmoothingEnabled = false;
    ctx.drawImage(g.canvas, x, y, w, h, 0, 0, w * z, h * z);
    ctx.strokeStyle = "rgba(0,0,128,0.25)";
    ctx.lineWidth = 1;
    for (let i = 1; i < w; i++) {{ ctx.beginPath(); ctx.moveTo(i * z, 0); ctx.lineTo(i * z, h * z); ctx.stroke(); }}
    for (let j = 1; j < h; j++) {{ ctx.beginPath(); ctx.moveTo(0, j * z); ctx.lineTo(w * z, j * z); ctx.stroke(); }}
    ctx.strokeStyle = "rgba(0,0,128,0.65)";
    for (let i = 0; i < w; i += 10) {{ ctx.beginPath(); ctx.moveTo(i * z, 0); ctx.lineTo(i * z, h * z); ctx.stroke(); }}
    for (let j = 0; j < h; j += 10) {{ ctx.beginPath(); ctx.moveTo(0, j * z); ctx.lineTo(w * z, j * z); ctx.stroke(); }}
  }}
  return JSON.stringify({{ ok: true, x, y, w, h, z }});
}};
</script>
</body>
</html>
"""

with open(f"{OUT}/zoom.html", "w", encoding="utf-8") as fh:
    fh.write(html)
print("wrote zoom.html")
