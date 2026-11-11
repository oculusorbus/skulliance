# DHC Portal — design

**Not in `skullpaper/` on purpose.** The Skull Paper is public and documents what
exists; this describes something unbuilt. When the Portal ships, the player-facing
page goes to `skullpaper/games-dhc-portal.md` and this file is the record of why it
is shaped the way it is.

> **Cheap and fair. A dump and gamble tool. No more. No less.**
>
> Every decision below was measured against that line, and several earlier ones were
> thrown out by it. If a future change makes the Portal richer, cleverer or more
> lucrative, check it against this sentence first.

---

## What it is

Players accumulate traits they will never use. Duplicates, aesthetic mismatches,
things that drag a Crew's deadliness down in the Arena. Nothing on the platform
removes a trait from circulation and nothing lets a player act on a pile of junk.

The Portal takes a hopper of unwanted traits and returns **one** trait. What you
fed never comes back to you, and everything you fed goes to somebody else.

## What it is not

* **Not a way to buy rarity.** There is no price in DIAMOND and no guaranteed
  outcome. Rejected explicitly: *"I don't feel comfortable with players buying
  rarity. A trade in of trash and diamond for the best rarity the ecosystem can
  offer feels unfair and feels like it would begin skewing the collection."*
* **Not a targeting tool.** You cannot aim at a category. *"No tipping the scales."*
  A player with nine weapons and no torso is still at the mercy of the roll, same
  as every drop on the platform.
* **Not a daily chore.** No cap on runs, nothing to visit for. It is a tool that is
  there when a pile has built up.

---

## The rules

| | |
|---|---|
| **No floor** | Charging the meter to the top buys better odds, never a minimum. A top-tier roll can still return a common. |
| **No ceiling** | A hopper of pure commons can hit a mythic. The odds are poor. That is the gamble. |
| **No category control** | Output can be any category regardless of what went in. |
| **Nothing you fed comes back** | Every slug in the hopper is excluded from the output pool for that run. The one hard rule, and the only thing protecting a player from the single most enraging outcome. |
| **Free traits only** | `dhcf_available()` already computes copies minus what is committed to assembled Fighters. A trait wearing a Fighter can never enter the hopper. |
| **No currency** | The traits are the price. |

### Why no DIAMOND

It was in the original pitch and it was cut late. Three reasons, in the order they
mattered:

1. **It is the only thing a player can genuinely lose.** Traits in the hopper cost
   nothing emotionally, that is the premise. Currency does. Paying to lose is a
   different kind of bad from losing things you had written off.
2. **It deters exactly the wrong people.** A DIAMOND price is nothing to a
   leaderboard regular and a wall to everyone else, so it would have gated a
   clean-up tool behind wealth.
3. **The traits are a better price anyway.** Denominated in the thing you have too
   much of, it is self-regulating: you can only run the Portal as often as your
   pile allows, without any cap needing to exist.

**Known trade:** the original pitch wanted the Portal to drive CARBON burning. That
goal is gone with the price. The Portal is a trait sink and a woo-back mechanic,
not a currency sink, and CARBON-to-DIAMOND pressure has to come from elsewhere.

---

## The meter

Volume drives it. Rarity gets you there **faster, not higher** — a shortcut to the
same prize, never a better one.

### The grid IS the meter

**Twenty-five slots, five by five. Twenty-five points to max. A common is one point.**

So a full grid of commons tops the meter exactly, and rarity fills the *meter* faster
than it fills the *grid* — which is "faster, not higher" in the most literal form
available. One mythic takes one slot and sixteen points. The bar and the board are the
same object, and nobody has to be taught how to read it.

| rarity | points | slots to max |
|---|---|---|
| common | 1 | 25 |
| uncommon | 2 | 13 |
| epic | 4 | 7 |
| legendary | 8 | 4 |
| mythic | 16 | 2 |

### Why these numbers are chosen and not derived

The honest version: inverse frequency against the `run` table is the only *derived*
scale, and it puts a mythic at **110 commons** — unusable when the hopper is
twenty-five slots. Any scale that fits a grid is therefore a choice, and it should be
made deliberately rather than dressed up as arithmetic.

A clean doubling was picked against three requirements:

1. **Monotonic.** Rarer is always worth more.
2. **Rares are deliberately undervalued.** A legendary is worth 8 commons here against
   roughly 16 by true scarcity, so feeding one is a *poor trade* for the player. That
   is the point: it discourages dumping rares, which is what protects the collection,
   and it means the meter gets filled with commons, which is what the feature is for.
3. **Readable.** 1, 2, 4, 8, 16 is memorable, and a player can work out their own
   hopper without a calculator.

### Thresholds

The meter selects which of the four existing `DHCF_TIERS` tables the roll happens on.
Nothing new is invented: every table already has every tier reachable and none
guaranteed, which is exactly the shape wanted.

| meter | table | mythic | legendary |
|---|---|---|---|
| 0 | `run` | 0.5% | 3.5% |
| 5 | `placement_10` | 2% | 9% |
| 12 | `placement_3` | 3% | 15% |
| 25 (max) | `placement_1` | 8% | 22% |

### Is it deflationary?

25 commons is roughly 45 drops' worth of play, which at `run` rates would itself have
produced about 0.23 mythics and 1.6 legendaries. The Portal pays 0.08 and 0.22 for the
same material, so converting is **2.8x worse than playing** for a mythic and 7.2x
worse for a legendary. Supply still strictly falls.

This went 120 to 60 to 25 across two passes, and the size kept being decided by the
wrong thing. The first two were set by the deflation margin alone, which is how you end
up with a hopper of sixty tiles that is unreadable as a grid and soup as an animation.
**The grid size is a UI constraint first; the margin only has to clear a bar, not be
maximised.**

| slots | grid | vs playing, mythic |
|---|---|---|
| 16 | 4x4 | 1.8x worse |
| 20 | 4x5 | 2.3x worse |
| **25** | **5x5** | **2.8x worse** |
| 30 | 5x6 | 3.4x worse |
| 36 | 6x6 | 4.1x worse |

Every row is still deflationary — even 4x4 — so the whole range was available and the
choice could be made on how it looks and plays. 5x5 is the largest grid that stays
legible on a phone and about the most objects you can throw into a vortex before it
reads as a smear.

**The margin is thinner here and that is fine.** 2.8x is a smaller reduction than 6.8x,
not an inflation. And the material being fed is residue that would otherwise sit in a
pile doing nothing, so "worse than playing" was never the player's real alternative.

### What still needs calibrating

Nothing above is derived any more — the one derivable scale did not fit a grid, so
the points and the thresholds are both choices, and they should be read as a starting
position rather than an answer.

The unknown behind all of them is the same: whether twenty-five points is a month's
accumulated junk or a year's depends on what players actually hold, which is not
knowable from the code. Ship it, watch it, move it. The owner's position is that
adjustment after launch is expected, and the grid size is the part that should stay
put while the numbers inside it move.

---

## The staging area

A grid laid over the operator art. Slot traits in, watch the meter climb, pull them
back out to tune it. Managing the hopper is the game before the gamble.

### Overshoot is the trap, and the fix is automatic

Twenty-four commons puts the meter at 24 of 25. Dropping a mythic on top takes it to
40 — fifteen points of fuel thrown away, most of another run's worth, with nothing to
warn you.

**Rule: the hopper never holds more leverage than the meter can spend.** When a trait
would overshoot, the staging area returns the **cheapest** traits already in it until
the overshoot is gone. Twenty-four commons plus a mythic becomes nine commons plus the
mythic, exactly at max, and fifteen commons drop back into the pile.

* Cheapest-first preserves the dense item the player just deliberately chose, and
  returns the ones most reusable next time. Ejecting the mythic instead would be the
  system overruling the player's last action.
* **Edge:** nothing is now worth more than the whole meter — a mythic is 16 against a
  max of 25 — so the auto-return can always make room, and the pathological case the
  earlier 110-point mythic created is gone. The rule stays anyway, because the point
  values are the first thing anyone will reach for when tuning.
* **Edge:** accepting is gated on the meter being *below* max, not on whether the
  addition would exceed it. Otherwise an empty hopper could refuse its own first
  trait.

This turns the trap into an assistant: rarity is meant to get you there faster, and
the auto-return is the system saying *you do not need these any more, have them back*.

### Pre-flight

The staging area must refuse to jettison before anything is taken if excluding the
hopper would leave the output pool empty in every category. Refusing *after* the
traits are gone is the worst version of this feature.

---

## The jettison

**Atomic or nothing.** Delete the hopper rows, roll the output, insert the award,
queue the redistribution. A half-completed run that eats traits and pays nothing is
the failure that costs trust permanently.

Output is written to `dhc_trait_drops` with `source = 'portal'`, which makes it
distinguishable from an earned drop for free and announces to Discord through
`dhcf_commit_drop()` like every other award.

---

## Redistribution

Everything swallowed goes out to other players at **full rarity**. No split, no
degradation, no holding back the good ones.

> *"A more rare trait randomly hitting a player who fell off is precisely the type of
> excitement to woo them back. And if they do come back, they'll need all the traits
> they can get to play catch up."*

### The cascade

Target the longest-lapsed first and widen until somebody qualifies. Immediate, not on
a schedule — the Discord ping in real time is the entire point.

```
1 month  →  3 weeks  →  2 weeks  →  1 week  →  days  →  any active player
```

* Reuses `RETURN_GIFT_DAYS` (30) from `lib/return-gift.php` and the lapsed population
  `admin-lapsed.php` already identifies, rather than inventing a second definition of
  "gone quiet".
* Recipient must already hold at least one trait.
* **The submitter is excluded at every rung, including the last.** Without this, the
  fall-through to active players can hand somebody their own jettisoned trait, which
  breaks the one hard rule.
* Nothing is ever destroyed for want of a recipient. *"I don't want the traits going
  into the ether."*

---

## The reveal

Stated as a requirement, not a nice-to-have: **the jettison has to be worth watching.**
It is the payoff of the whole feature and the only moment the art gets to do anything.

### The sequence

1. **Commit.** The operator's arms sweep the grid. Traits lift off their slots.
2. **Feed.** They travel to the chute under the green deposit sign and go down.
3. **Take.** The vortex accelerates and swallows them.
4. **Stillness.** A held beat with nothing moving. This is not dead time, it is the
   anticipation — the chest reveal needed exactly the same thing, and 40ms of
   stillness before the lid is what made the pop land.
5. **Return.** The vortex reverses and something rises out of it.
6. **Reveal.** The trait arrives and is named.

### What it reuses

`crafting-modal.php` already has a canvas particle system with additive blending, a
12-stop colour ramp pre-rendered into tinted sprites, and per-particle turbulence that
makes a column taper instead of spray. A portal is that engine pointed inward and then
outward. **Do not write a second one.**

### The one technical decision

A hopper holds twenty-five traits. Twenty-five elements flying into a vortex is
twenty-five animated DOM nodes, which is where a reveal starts becoming a slideshow on
a phone.

**The hopper is DOM until it commits, then it becomes particles.** You interact with
elements — slot them, pull them back, read their rarity — and the moment the jettison
fires they are handed to the canvas as particle sources and the elements go. Elements
for the part you touch, canvas for the part that is spectacle.

### Rules carried from the reveals already built

* **Only `transform` and `opacity` animate.** Anything else is a layout thrash, and
  the chest harness already fails on layout properties inside keyframes.
* **Any looping state must start on the value it takes over from.** The chest's idle
  glow snapped because its first keyframe disagreed with the state before it by 0.18
  of opacity, a second and a half after the reveal.
* **Reduced motion keeps the information and drops the travel.** The result is
  legible instantly; only the journey goes.
* **One clock.** Every step is an offset from the moment the portal takes the hopper,
  not an absolute timestamp. Three hardcoded timings is how a sequence drifts the
  first time any one of them moves.
* **A glowing object seats itself by lighting what it stands on.** The returned trait
  should light the floor it lands on rather than casting a shadow onto it.

---

## Open

* The point values and thresholds, pending real holdings. The grid size is settled at
  5x5; everything inside it is the tuning dial.
* Minimum hopper size, if any.
* Whether portal provenance is surfaced to players or stays an internal `source`.

## Art

Two pieces, supplied:

* **The portal** — red and black vortex, green deposit sign with a down-arrow, flames
  beneath, machinery packed around the frame. Industrial, not mystical. A chute.
* **The staging area** — the skeletal operator with articulated arms. The grid is laid
  over this, so the operator is holding the slots you fill.

The reveal gets a whole page rather than a dialog, which is why the Portal is its own
page rather than a section: the crafting ring is 276px inside a 392px dialog and the
chest reveal is 210px, and neither could sell "this is a machine you feed".
