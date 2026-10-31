"""Multi-region zoom gallery: ref vs trace pairs for each region."""
import base64
import json

OUT = "_rebrand/trace-out"
src_png = base64.b64encode(open("logo_idexal/light_icon_idexal.png", "rb").read()).decode()
fit_svg = open(f"{OUT}/fit.svg", encoding="utf-8").read()
svg_b64 = base64.b64encode(fit_svg.encode("utf-8")).decode()

REGIONS = [
    ("center-tip", 200, 175, 95, 45, 6),
    ("black-bottom", 90, 340, 45, 40, 6),
    ("black-top", 65, 2, 55, 40, 6),
    ("ne-top", 250, 60, 115, 45, 6),
    ("sliver-right", 55, 120, 45, 140, 5),
    ("square", 285, 0, 90, 60, 6),
    ("se-end", 215, 240, 100, 105, 4),
    ("black-notch", 140, 175, 50, 45, 6),
]

html = f"""<!doctype html>
<html>
<head><meta charset="utf-8"><title>trace gallery</title>
<style>
 body {{ margin: 0; background: #fff; font: 10px monospace; }}
 .grid {{ display: grid; grid-template-columns: repeat(3, auto); gap: 10px; padding: 8px; }}
 .pair {{ display: flex; gap: 4px; }}
 .cell {{ position: relative; border: 1px solid #777; }}
 canvas {{ display: block; }}
 .lbl {{ position: absolute; top: 1px; left: 3px; color: #f00; z-index: 2; text-shadow: 0 0 2px #fff; }}
</style></head>
<body>
<div class="grid" id="grid"></div>
<script>
window.__SRC = "data:image/png;base64,{src_png}";
window.__SVG = "data:image/svg+xml;base64,{svg_b64}";
const REGIONS = {json.dumps(REGIONS)};
window.renderGallery = async (only) => {{
  const mk = (url) => new Promise((res, rej) => {{ const im = new Image(); im.onload = () => res(im); im.onerror = rej; im.src = url; }});
  const [src, svg] = await Promise.all([mk(window.__SRC), mk(window.__SVG)]);
  const full = (which) => {{
    const c = document.createElement("canvas"); c.width = 377; c.height = 380;
    c.getContext("2d").drawImage(which === "ref" ? src : svg, 0, 0, 377, 380);
    return c;
  }};
  const refC = full("ref"), testC = full("test");
  const grid = document.getElementById("grid");
  grid.innerHTML = "";
  for (const [name, x, y, w, h, z] of REGIONS) {{
    if (only && !name.includes(only)) continue;
    const wrap = document.createElement("div");
    const p = document.createElement("div"); p.className = "pair";
    for (const [id, c] of [["ref", refC], ["trace", testC]]) {{
      const cell = document.createElement("div"); cell.className = "cell";
      const lab = document.createElement("span"); lab.className = "lbl"; lab.textContent = name + " " + id;
      const cv = document.createElement("canvas");
      cv.width = w * z; cv.height = h * z;
      const g = cv.getContext("2d");
      g.imageSmoothingEnabled = false;
      g.drawImage(c, x, y, w, h, 0, 0, w * z, h * z);
      g.strokeStyle = "rgba(0,0,160,0.35)"; g.lineWidth = 1;
      for (let i = 1; i < w; i++) {{ g.beginPath(); g.moveTo(i*z,0); g.lineTo(i*z,h*z); g.stroke(); }}
      for (let j = 1; j < h; j++) {{ g.beginPath(); g.moveTo(0,j*z); g.lineTo(w*z,j*z); g.stroke(); }}
      cell.appendChild(lab); cell.appendChild(cv);
      p.appendChild(cell);
    }}
    wrap.appendChild(p);
    grid.appendChild(wrap);
  }}
  return "ok";
}};
</script>
</body>
</html>
"""

with open(f"{OUT}/gallery.html", "w", encoding="utf-8") as fh:
    fh.write(html)
print("wrote gallery.html")
