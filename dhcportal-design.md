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

### Point values

Derived from the `run` table in `DHCF_TIERS` (common 55, uncommon 28, epic 13,
legendary 3.5, mythic 0.5) as inverse frequency relative to a common, so a trait is
worth what it cost to come by:

| rarity | drop rate | points |
|---|---|---|
| common | 55% | 1 |
| uncommon | 28% | 2 |
| epic | 13% | 4 |
| legendary | 3.5% | 16 |
| mythic | 0.5% | 110 |

Derived rather than chosen, because any hand-picked set is an exchange rate between
tiers and a wrong one is a laundering vector.

### Thresholds

The meter selects which of the four existing `DHCF_TIERS` tables the roll happens on.
Nothing new is invented: every table already has every tier reachable and none
guaranteed, which is exactly the shape wanted.

| meter | table | mythic | legendary |
|---|---|---|---|
| 0 | `run` | 0.5% | 3.5% |
| 12 | `placement_10` | 2% | 9% |
| 30 | `placement_3` | 3% | 15% |
| 60 (max) | `placement_1` | 8% | 22% |

**60 points is the ceiling of the meter and the size of one run.**

### Is it deflationary, and how cheap can it be?

60 commons is roughly 109 drops' worth of play, which at `run` rates would itself have
produced about 0.55 mythics and 3.8 legendaries. The Portal pays 0.08 and 0.22 for the
same material, so converting is **6.8x worse than playing** for a mythic and 17x worse
for a legendary. Nothing skews.

The first draft of this put the ceiling at 120, and the arithmetic is what argued it
down. The safety margin is enormous, so the question stops being "is it safe" and
becomes "how cheap can it be while staying safe":

| max | vs playing, mythic | vs playing, legendary |
|---|---|---|
| 40 | 4.5x worse | 11.6x worse |
| **60** | **6.8x worse** | **17.4x worse** |
| 80 | 9.1x worse | 23.1x worse |
| 120 | 13.6x worse | 34.7x worse |

Every row is deflationary. Given *cheap and fair*, the right move is the low end of
the safe range rather than the middle, and 60 leaves room to come down again.

**One consequence at 60:** a mythic is worth 110, so feeding one maxes the meter and
wastes 50 points with nothing for the auto-return to eject. That is correct rather
than a bug. Dumping a mythic should be a bad idea, and the staging area says so
plainly. The Portal is for commons, uncommons and epics; a legendary is a real
sacrifice at 16; a mythic is a mistake the UI warns you about.

### The one number that needs calibrating

The **ratios** above are derived and defensible. The **scale** is not: whether 120
points is a month's accumulated junk or a year's depends on what players actually
hold, and that is not knowable from the code. Ship it, watch it, move it. The owner's
position is that adjustment after launch is expected.

---

## The staging area

A grid laid over the operator art. Slot traits in, watch the meter climb, pull them
back out to tune it. Managing the hopper is the game before the gamble.

### Overshoot is the trap, and the fix is automatic

Ninety-nine commons puts the meter at 99. Dropping a mythic on top takes it to 209
against a ceiling of 120 — eighty-nine points of fuel thrown away, enough for most of
another run, with nothing to warn you.

**Rule: the hopper never holds more leverage than the meter can spend.** When a trait
would overshoot, the staging area returns the **cheapest** traits already in it until
the overshoot is gone.

* Cheapest-first preserves the dense item the player just deliberately chose, and
  returns the ones most reusable next time. Ejecting the mythic instead would be the
  system overruling the player's last action.
* **Edge:** a single trait worth more than the whole meter (a mythic at 110 against a
  max of 120 is fine; a lower max would not be). If the first thing in already
  overshoots there is nothing to eject, so it goes in with the overshoot shown
  plainly and the player decides.
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

A hopper can hold sixty traits. Sixty elements flying into a vortex is sixty animated
DOM nodes, which is how a reveal becomes a slideshow on a phone.

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

* The threshold scale (above), pending real holdings.
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
