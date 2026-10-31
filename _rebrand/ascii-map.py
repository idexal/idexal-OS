"""Print an ASCII map of the reference icon: in/out + color class per pixel.

Usage: python _rebrand/ascii-map.py x0 y0 x1 y1 [step]
Legend: ' ' transparent, '.' partial alpha, K black, G green, ? other solid
"""
import sys

from PIL import Image

im = Image.open("logo_idexal/light_icon_idexal.png").convert("RGBA")
px = im.load()
W, H = im.size

x0, y0, x1, y1 = [int(v) for v in sys.argv[1:5]]
step = int(sys.argv[5]) if len(sys.argv) > 5 else 1


def cls(x, y):
    if x < 0 or y < 0 or x >= W or y >= H:
        return " "
    r, g, b, a = px[x, y]
    if a < 60:
        return " "
    if a < 200:
        return "."
    if g - max(r, b) > 40:
        return "G"
    if max(r, g, b) < 110:
        return "K"
    return "?"


# header with tens/units digits
def ruler(vals):
    tens = "".join(str((v // 10) % 10) if v % 10 == 0 else " " for v in vals)
    units = "".join(str(v % 10) for v in vals)
    return tens, units


xs = list(range(x0, x1 + 1, step))
tens, units = ruler(xs)
print("    " + tens)
print("    " + units)
for y in range(y0, y1 + 1, step):
    row = "".join(cls(x, y) for x in xs)
    print(f"{y:4d}{row}")
