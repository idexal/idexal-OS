"""Print color-coded ASCII maps of ref and mine for a region, side by side.

Usage: python _rebrand/region-print.py x0 y0 x1 y1 [step]
Chars: ' ' white, '#' black-ish, '+' green, '.' partial (a=32..127), '?' dark-partial
"""
import sys
from pathlib import Path
from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
REF = ROOT / "logo_idexal" / "light_icon_idexal.png"
MINE = Path(__file__).resolve().parent / "trace-out" / "final" / "mine.png"


def cls(r, g, b, a):
    if a < 32:
        return " "
    if a < 224:
        return "." if (r + g + b) / 3 > 110 else "?"
    if g > 120 and r < 110:
        return "+"
    return "#"


def load():
    ref = Image.open(REF).convert("RGBA")
    mine = Image.open(MINE).convert("RGBA")
    return ref.load(), mine.load()


def main():
    x0, y0, x1, y1 = map(int, sys.argv[1:5])
    step = int(sys.argv[5]) if len(sys.argv) > 5 else 2
    rp, mp = load()
    W, H = Image.open(REF).size
    w = x1 - x0
    ncols = (w + step - 1) // step
    print(f"region ({x0},{y0})-({x1},{y1}) step={step}")
    hdr = " " * 4
    for x in range(x0, x1, step * 10):
        hdr += f"{x:<{step*10}}"
    print("REF".ljust(ncols * 2 + 2) + "| MINE")
    print(hdr + " | " + hdr)
    for y in range(y0, y1, step):
        row_r = ""
        row_m = ""
        for x in range(x0, x1, step):
            r, g, b, a = rp[x, y]
            row_r += cls(r, g, b, a)
            r, g, b, a = mp[x, y]
            row_m += cls(r, g, b, a)
        yl = f"{y:3d} "
        print(yl + row_r + "|" + yl + row_m)
    # diff counts
    nd = 0
    for y in range(y0, y1):
        for x in range(x0, x1):
            if abs(rp[x, y][3] - mp[x, y][3]) > 96:
                nd += 1
    print(f"pixels with |ref-mine| alpha >96: {nd}")


if __name__ == "__main__":
    main()
