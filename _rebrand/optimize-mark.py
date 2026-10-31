"""Coordinate-descent optimizer for the idexal Os mark geometry.

Tunes line offsets, circle centers/radii and the square against the reference
PNG by minimizing total abs alpha diff. Writes params.json on every
improvement, prints a log.
"""
import copy
import json
import time
from pathlib import Path

import mark_geom as mg

OUT = Path(__file__).resolve().parent / "trace-out"
OUT.mkdir(exist_ok=True)
PARAMS_JSON = OUT / "params.json"

log_fh = open(OUT / "optimize.log", "w", encoding="utf-8")


def log(msg):
    print(msg, flush=True)
    log_fh.write(msg + "\n")
    log_fh.flush()


def evaluate(P):
    try:
        total, worst, _ = mg.diff_stats(P)
        return total
    except Exception as exc:  # degenerate geometry
        return float("inf")


def save(P, total):
    with open(PARAMS_JSON, "w", encoding="utf-8") as fh:
        json.dump(P, fh, indent=1)
    log(f"  -> saved params.json  total={total:.0f}")


def sweep(P, base_total, get, set_, values):
    """Try values for one parameter, keep the best."""
    best_P = P
    best_total = base_total
    best_v = get(P)
    for v in values:
        if abs(v - best_v) < 1e-9:
            continue
        Q = copy.deepcopy(P)
        set_(Q, v)
        t = evaluate(Q)
        if t < best_total - 1e-9:
            best_total = t
            best_P = Q
            best_v = v
    return best_P, best_total


def seq(center, steps, step):
    return [center + i * step for i in range(-steps, steps + 1)]


def tune_lines(P, total, tag, step, steps, tol):
    """Sweep c for every line, repeat passes until a pass improves < tol."""
    for rnd in range(6):
        pass_start = total
        for name in mg.TUNE_LINES:
            c0 = P[name]["c"]
            P, total = sweep(
                P, total,
                lambda Q, n=name: Q[n]["c"],
                lambda Q, v, n=name: Q[n].__setitem__("c", v),
                seq(c0, steps, step),
            )
        log(f"[{tag} r{rnd}] lines step={step} total={total:.0f} (pass gain {pass_start - total:.0f})")
        if pass_start - total < tol:
            break
    return P, total


def tune_circles(P, total, tag, cstep, csteps, rstep, rsteps, tol):
    for rnd in range(6):
        pass_start = total
        for name in mg.TUNE_CIRCLES:
            for axis, step, steps in (("x", cstep, csteps), ("y", cstep, csteps), ("r", rstep, rsteps)):
                v0 = P[name][axis]
                P, total = sweep(
                    P, total,
                    lambda Q, n=name, a=axis: Q[n][a],
                    lambda Q, v, n=name, a=axis: Q[n].__setitem__(a, v),
                    seq(v0, steps, step),
                )
        log(f"[{tag} r{rnd}] circles total={total:.0f} (pass gain {pass_start - total:.0f})")
        if pass_start - total < tol:
            break
    return P, total


def tune_square(P, total, tag, step, steps, tol):
    for rnd in range(6):
        pass_start = total
        for axis in mg.TUNE_SQUARE:
            v0 = P["SQ"][axis]
            P, total = sweep(
                P, total,
                lambda Q, a=axis: Q["SQ"][a],
                lambda Q, v, a=axis: Q["SQ"].__setitem__(a, v),
                seq(v0, steps, step),
            )
        log(f"[{tag} r{rnd}] square total={total:.0f} (pass gain {pass_start - total:.0f})")
        if pass_start - total < tol:
            break
    return P, total


def main():
    t0 = time.time()
    P = copy.deepcopy(mg.PARAMS)
    total = evaluate(P)
    log(f"start total={total:.0f}")
    save(P, total)

    for round_i in range(3):
        log(f"==== round {round_i} ====")
        if round_i == 0:
            P, total = tune_lines(P, total, "L1", 0.125, 4, 300)
            P, total = tune_circles(P, total, "C1", 0.25, 4, 0.25, 4, 500)
            P, total = tune_square(P, total, "Q1", 0.25, 4, 200)
        else:
            P, total = tune_lines(P, total, "L2", 0.0625, 4, 100)
            P, total = tune_circles(P, total, "C2", 0.125, 4, 0.125, 4, 200)
            P, total = tune_square(P, total, "Q2", 0.125, 4, 100)
        save(P, total)

    log(f"final total={total:.0f} after {time.time() - t0:.0f}s")
    total2, worst, _ = mg.diff_stats(P)
    log("worst blocks:")
    for s, bx, by in worst:
        log(f"  ({bx:3d},{by:3d}) {s:.0f}")


if __name__ == "__main__":
    main()
