"""Mission cost/reward tiers for Skulliance.

RECOVERED FROM THE LIVE TABLE (453 of 455 rows obey it exactly):
    level 1   : cost 0, reward 10, duration 1      -- the free intro
    level L>=2: duration = cost / 100  (days)
                reward   = cost * (1 + L/10)
    therefore : net per day = 10 * L

The cost ladder is the only free choice. It decides how much CARBON a
mission locks up and for how long -- never the yield rate, which the
multiplier alone sets.
"""

# Clean costs for a WIDE ladder: the 1-2-3-5-7 run the older projects use,
# so a short project still spans cheap to expensive.
WIDE = [200, 300, 400, 500, 600, 700, 800, 1000, 1200, 1500, 2000, 2500, 3000]

def ladder(m, wide_cutoff=12):
    """m paid missions -> m clean costs, low to high."""
    if m <= 0: return []
    if m > wide_cutoff:
        return [100 * (i + 2) for i in range(m)]      # narrow: flat 100 steps
    if m == 1: return [500]
    out, used = [], set()
    for i in range(m):                                 # wide: spread across WIDE
        idx = round(i * (len(WIDE) - 1) / (m - 1))
        while WIDE[idx] in used: idx += 1
        used.add(WIDE[idx]); out.append(WIDE[idx])
    return out

# The cost ladders ACTUALLY IN THE LIVE TABLE, keyed by mission count, so
# the comparison below is against the real thing rather than against a
# reconstruction of it. Counts not listed were never used.
OBSERVED = {1: [], 2: [200], 4: [300, 500, 700], 5: [300, 500, 700, 1000], 6: [300, 500, 700, 1000, 1500], 8: [300, 500, 700, 1000, 1500, 2000, 3000], 10: [200, 300, 500, 700, 1000, 1500, 2000, 2500, 3000], 14: [200, 300, 400, 500, 600, 700, 800, 900, 1000, 1100, 1200, 1300, 1400], 15: [200, 300, 400, 500, 600, 700, 800, 900, 1000, 1100, 1200, 1300, 1400, 1500], 22: [200, 300, 400, 500, 600, 700, 800, 900, 1000, 1100, 1200, 1300, 1400, 1500, 1600, 1700, 1800, 1900, 2000, 2100, 2200], 23: [200, 300, 400, 500, 600, 700, 800, 900, 1000, 1100, 1200, 1300, 1400, 1500, 1600, 1700, 1800, 1900, 2000, 2100, 2200, 2300], 24: [200, 300, 400, 500, 600, 700, 800, 900, 1000, 1100, 1200, 1300, 1400, 1500, 1600, 1700, 1800, 1900, 2000, 2100, 2200, 2300, 2400], 26: [200, 300, 400, 500, 600, 700, 800, 900, 1000, 1100, 1200, 1300, 1400, 1500, 1600, 1700, 1800, 1900, 2000, 2100, 2200, 2300, 2400, 2500, 2600], 28: [200, 300, 400, 500, 600, 700, 800, 900, 1000, 1100, 1200, 1300, 1400, 1500, 1600, 1700, 1800, 1900, 2000, 2100, 2200, 2300, 2400, 2500, 2600, 2700, 2800], 46: [200, 300, 400, 500, 600, 700, 800, 900, 1000, 1100, 1200, 1300, 1400, 1500, 1600, 1700, 1800, 1900, 2000, 2100, 2200, 2300, 2400, 2500, 2600, 2700, 2800, 2900, 3000, 3100, 3200, 3300, 3400, 3500, 3600, 3700, 3800, 3900, 4000, 4100, 4200, 4300, 4400, 4500, 4600]}

def live(n):
    """What the live system produces for n missions."""
    rows = [dict(level=1, cost=0, reward=10, days=1, ratio=None, per_day=10)]
    costs = OBSERVED.get(n) or ladder(n - 1)
    for c, L in zip(costs, range(2, n + 1)):
        rows.append(dict(level=L, cost=c, reward=c * (10 + L) // 10, days=c // 100,
                         ratio=1 + L / 10, per_day=10 * L))
    return rows

def tiered(n, top_bonus=1.0, floor_bonus=0.2):
    """PROPOSED. The multiplier spans the same range whatever n is, so more
    missions means finer steps -- not more money per day.

        net per day = 100 * (ratio - 1)     (independent of cost)

    Ratios are snapped to 0.1, which keeps every reward a multiple of 10.

    The floor is 1.2, not 1.1: the free level-1 mission already pays 10 a
    day, so a first paid mission at 1.1 would be no better than the one
    you did not have to pay for. 1.2 is also exactly where the live table
    starts, so nothing about the opening move changes.
    """
    m = n - 1
    rows = [dict(level=1, cost=0, reward=10, days=1, ratio=None, per_day=10)]
    costs = ladder(m)
    for i, c in enumerate(costs):
        frac = 1.0 if m == 1 else i / (m - 1)
        bonus = floor_bonus + frac * (top_bonus - floor_bonus)
        # ALWAYS 0.1, never finer. cost is a multiple of 100, so a ratio on
        # a tenth makes reward = cost * (10+k)/10 -- always a multiple of 10.
        # A 0.05 step looks more precise and produces 500 * 1.25 = 625, which
        # is exactly the kind of number this whole scheme exists to avoid.
        # Past nine paid missions the ratios BAND instead: several missions
        # share a tier and are told apart by cost and duration, not yield.
        bonus = round(bonus / 0.1) * 0.1
        ratio = round(1 + bonus, 2)
        reward = round(c * ratio)
        rows.append(dict(level=i + 2, cost=c, reward=reward, days=c // 100,
                         ratio=ratio, per_day=round((reward - c) / (c // 100))))
    return rows

def show(title, rows):
    print(f"\n{title}")
    print(f"  {'lvl':>3} {'cost':>6} {'reward':>7} {'ratio':>6} {'days':>5} {'net/day':>8}")
    for r in rows:
        print(f"  {r['level']:>3} {r['cost']:>6} {r['reward']:>7} "
              f"{(f'{r[chr(114)+chr(97)+chr(116)+chr(105)+chr(111)]:.2f}' if r['ratio'] else '    -'):>6} "
              f"{r['days']:>5} {r['per_day']:>8}")
    paid = [r for r in rows if r['cost']]
    print(f"      totals: {sum(r['cost'] for r in paid):,} spent -> "
          f"{sum(r['reward'] for r in paid):,} back over {sum(r['days'] for r in paid)} days")

if __name__ == '__main__':
    import sys
    for n in [int(a) for a in sys.argv[1:]] or [6, 10, 24]:
        show(f"=== {n} missions - LIVE SYSTEM ===", live(n))
        show(f"=== {n} missions - PROPOSED TIERS ===", tiered(n))


def capped(n, cap=10):
    """APPEND-SAFE. The live level-based multiplier, with a ceiling.

        reward = cost * (1 + min(level, cap)/10)

    Why this and not `tiered()` for an existing project: tiered() sets the
    multiplier from a mission's POSITION in the ladder, so adding one
    mission renumbers the economics of every mission already there. That is
    precisely the rework this is supposed to prevent. A level-based
    multiplier never moves once written -- level 7 pays 1.7x whether the
    project has 8 missions or 80 -- so appending is a pure INSERT.
    """
    rows = [dict(level=1, cost=0, reward=10, days=1, ratio=None, per_day=10)]
    for c, L in zip(ladder(n - 1), range(2, n + 1)):
        k = min(L, cap)
        rows.append(dict(level=L, cost=c, reward=c * (10 + k) // 10, days=c // 100,
                         ratio=1 + k / 10, per_day=10 * k))
    return rows


def append_safe(n, cap=10):
    """EVERY FIELD A FUNCTION OF LEVEL ALONE, so nothing depends on how many
    missions the project has:

        cost     = 100 * level
        duration = level                       (days)
        reward   = cost * (1 + min(level, cap)/10)
        net/day  = 10 * min(level, cap)        -- flat 100 past the cap

    capped() still rewrote rows on append because ladder() switches from the
    wide spread to flat 100-steps once a project passes twelve missions --
    the COST schedule was a function of n even though the multiplier was
    not. Here nothing is. Adding mission 11 to a ten-mission project is one
    INSERT and touches nothing that already exists.

    Fourteen of the thirty-nine live projects already use exactly this cost
    schedule; this adds the cap and nothing else.
    """
    rows = [dict(level=1, cost=0, reward=10, days=1, ratio=None, per_day=10)]
    for L in range(2, n + 1):
        c, k = 100 * L, min(L, cap)
        rows.append(dict(level=L, cost=c, reward=c * (10 + k) // 10, days=L,
                         ratio=1 + k / 10, per_day=10 * k))
    return rows
