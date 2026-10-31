"""Connected components + per-component color stats for the idexal Os icon art."""
from collections import Counter, deque
from PIL import Image

im = Image.open("logo_idexal/light_icon_idexal.png").convert("RGBA")
W, H = im.size
px = im.load()

GREEN, DARK = 1, 2
label = [[0] * W for _ in range(H)]
kind = {}


def cls(x, y):
    r, g, b, a = px[x, y]
    if a <= 128:
        return 0
    if g > r + 50 and g > b + 50:
        return GREEN
    if r < 60 and g < 60 and b < 60:
        return DARK
    return 0


for y in range(H):
    for x in range(W):
        label[y][x] = cls(x, y)

comps = []
seen = [[False] * W for _ in range(H)]
for y in range(H):
    for x in range(W):
        if label[y][x] == 0 or seen[y][x]:
            continue
        k = label[y][x]
        q = deque([(x, y)])
        seen[y][x] = True
        pts = []
        while q:
            cx, cy = q.popleft()
            pts.append((cx, cy))
            for nx, ny in ((cx + 1, cy), (cx - 1, cy), (cx, cy + 1), (cx, cy - 1)):
                if 0 <= nx < W and 0 <= ny < H and not seen[ny][nx] and label[ny][nx] == k:
                    seen[ny][nx] = True
                    q.append((nx, ny))
        comps.append({"kind": k, "pts": pts})

print(f"{len(comps)} components")
for i, c in enumerate(comps):
    xs = [p[0] for p in c["pts"]]
    ys = [p[1] for p in c["pts"]]
    print(f"\ncomponent {i}: kind={'GREEN' if c['kind']==GREEN else 'DARK'} area={len(c['pts'])} bbox=({min(xs)},{min(ys)})-({max(xs)},{max(ys)})")
    if c["kind"] == GREEN:
        tones = Counter()
        for (x, y) in c["pts"]:
            r, g, b, a = px[x, y]
            tones[(round(r / 16) * 16, round(g / 16) * 16, round(b / 16) * 16)] += 1
        print("   coarse tones:", tones.most_common(6))
        # tone by quadrant inside bbox
        midx = (min(xs) + max(xs)) / 2
        midy = (min(ys) + max(ys)) / 2
        quads = {"tl": [], "tr": [], "bl": [], "br": []}
        for (x, y) in c["pts"]:
            key = ("t" if y < midy else "b") + ("l" if x < midx else "r")
            r, g, b, a = px[x, y]
            quads[key].append((r, g, b))
        for k2, v in quads.items():
            if v:
                n = len(v)
                avg = tuple(round(sum(vv[j] for vv in v) / n) for j in range(3))
                print(f"   quad {k2}: n={n} avg={avg}")
