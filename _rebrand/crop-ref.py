"""Crop + zoom regions of the reference icon with coordinate grid overlay."""
from PIL import Image, ImageDraw
import os

os.makedirs("_rebrand/trace-out/crops", exist_ok=True)

im = Image.open("logo_idexal/light_icon_idexal.png").convert("RGBA")
w = Image.new("RGBA", im.size, (255, 255, 255, 255))
w.alpha_composite(im)
rgb = w.convert("RGB")

CROPS = {
    "center-big": ((190, 60, 310, 230), 3),
    "notch": ((148, 184, 180, 208), 14),
    "tips": ((232, 158, 288, 226), 9),
    "br-fillet": ((99, 360, 132, 380), 16),
    "ne-topleft": ((250, 66, 288, 94), 14),
    "square": ((294, 0, 362, 58), 8),
    "black-topleft": ((0, 0, 40, 30), 14),
    "sliver-top": ((0, 40, 92, 160), 5),
    "se-tip": ((250, 188, 292, 226), 10),
}

for name, (box, z) in CROPS.items():
    x0, y0, x1, y1 = box
    c = rgb.crop(box).resize(((x1 - x0) * z, (y1 - y0) * z), Image.NEAREST)
    d = ImageDraw.Draw(c)
    # grid every 10 source px (thick), 5 px (thin)
    for gx in range(x0 - x0 % 5, x1 + 1, 5):
        if gx < x0:
            continue
        xx = (gx - x0) * z
        major = gx % 20 == 0
        col = (255, 0, 255) if major else (255, 160, 255)
        width = 2 if major else 1
        d.line([(xx, 0), (xx, c.height)], fill=col, width=width)
        if major:
            d.text((xx + 3, 2), str(gx), fill=(160, 0, 160))
    for gy in range(y0 - y0 % 5, y1 + 1, 5):
        if gy < y0:
            continue
        yy = (gy - y0) * z
        major = gy % 20 == 0
        col = (255, 0, 255) if major else (255, 160, 255)
        width = 2 if major else 1
        d.line([(0, yy), (c.width, yy)], fill=col, width=width)
        if major:
            d.text((3, yy + 3), str(gy), fill=(160, 0, 160))
    c.save(f"_rebrand/trace-out/crops/{name}.png")
    print(name, c.size)
print("done")
