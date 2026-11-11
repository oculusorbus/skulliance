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

## The board

**A 4x4 inventory grid. Traits take up space. Rows filled is the tier.**

That one sentence is the whole mechanic and there are no numbers in it. The board is
not a meter beside the hopper, it *is* the hopper and it *is* the meter, and a player
can read their own odds by looking at how full it is.

### Pieces

| rarity | shape | takes |
|---|---|---|
| common | 1x1 | a quarter row |
| uncommon | 2x1 | half a row |
| epic | 4x1 | **one row** |
| legendary | 4x2 | **two rows** |
| mythic | 4x4 | **the whole board** |

Every piece is row-aligned, which is the property that makes the rest work. Four
commons make a row, two uncommons make a row, an epic is a row. Nothing can leave an
awkward notch, nothing needs auto-packing, there is no Tetris. You stack.

**An earlier draft drew epic as a 2x2 block** and then spent a page solving the
packing problem that created — a piece that straddles two rows and fills neither.
The shapes have to follow the rows. Four slots is a row, so a four-slot piece is a
row, and the problem disappears.

### Rows are tiers

Four rows, and `DHCF_TIERS` already has exactly four tables. One to one, nothing
invented.

| rows filled | table | mythic | legendary |
|---|---|---|---|
| 1 | `run` | 0.5% | 3.5% |
| 2 | `placement_10` | 2% | 9% |
| 3 | `placement_3` | 3% | 15% |
| 4 | `placement_1` | 8% | 22% |

**One full row minimum to jettison.** That also settles what the minimum hopper size
should be, with the same rule rather than a second one.

### The full board gets the best table

Settled deliberately, because the instinct to nerf it later will be strong and the
reasons need to be on the page.

* **A full board is the maximum commitment the system accepts.** If the maximum
  commitment does not buy the maximum prize, filling the board is anticlimactic and
  the top row is decoration.
* **It is what keeps the Portal a destination rather than a bin.** The owner's reason,
  and the best one: *"If there's a chance they get something worth a damn out of it,
  they'll continue using the feature beyond just taking out the trash."*
* **A mythic fills the board, so the top must be the best table or feeding one is
  incoherent** — and even at 8% it is a dreadful trade, swapping a certainty for a
  one-in-twelve shot at the same thing. Nobody should ever do it, and a mythic
  swallowing all sixteen slots says so more plainly than any warning.

**The objection, and why it resolves.** `placement_1` is what taking *first place*
pays, so handing it to a board of commons looks like cheapening first place. But the
leaderboard gives that table as a **gift** — you win, you roll, you keep everything.
The Portal charges **sixteen traits** for the same odds and destroys fifteen of them.
Same table, completely different price.

### What this costs in rare supply, honestly

Earlier drafts of this page reassured with "converting is worse than playing". That
is true and it is the **wrong comparison**, and it was leaned on three times before
being caught.

The traits being fed are residue from play that **already paid out its rares**.
Without the Portal they produce nothing, ever. So the real counterfactual is zero,
and **every rare the Portal emits is additional supply.**

The honest shape of the feature: **a strong sink for total traits, a weak faucet for
rare ones.** A full board destroys fifteen traits to emit one. It compresses the
collection upward, which is what a re-roll mechanic does by nature.

That is a deliberate trade and not a discovery. It is also small next to the rest of
the design: a full board emits **one** trait on an 8% roll while sending **sixteen**
out to lapsed players at full rarity. The Portal moves far more rarity than it
creates.

### The dial, if it ever needs one

Which table row four reaches. It is a single reference, the easiest change in the
whole design, and it is the only lever on how much rare supply the Portal adds. If
full boards turn out to be far more common than expected, that is the number to pull
— with real data rather than a guess made before launch.

## The staging area

The 4x4 board laid over the operator art, with the player's free traits beside it as
pieces to place. Stack them in, pull them back out, watch the rows fill. Assembling
the board is the game before the gamble, and it is the part a player will actually
spend time on.

### Overshoot, which is now physical

Fifteen slots used, one left, and the player drops in a legendary that needs two
rows. Without a rule that is eleven slots of fuel thrown away — most of another run —
with nothing to warn them.

**Rule: the board never holds more than it can spend.** A piece that does not fit
pushes the **cheapest** pieces already on the board back out until it does. Fifteen
commons plus a legendary becomes eight commons plus the legendary, a full board, and
seven commons drop back into the pile.

* Cheapest-first preserves the dense piece the player just deliberately chose and
  returns the ones most reusable next time. Ejecting the legendary instead would be
  the system overruling their last action.
* Nothing is worth more than the whole board — a mythic is exactly sixteen — so there
  is always room to make.
* The arithmetic version of this rule needed edge cases about traits larger than the
  meter. The physical version does not: things either fit or they push.

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

A board holds sixteen pieces. Sixteen elements flying into a vortex is sixteen
animated DOM nodes, which is where a reveal starts becoming a slideshow on a phone.

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

## Settled, listed because they were argued over

* **Row four reaches `placement_1`.** Fill the board, get the rarest table on the
  platform. Not open — see *The full board gets the best table*. The note under *The
  dial* is a post-launch tuning line, not an undecided question.
* **One full row is the minimum to fire.** Same rule as the tier, not a second one.

## Open

* **Does a staged board survive leaving the page?** A player who assembles sixteen
  pieces and gets interrupted should probably not lose the arrangement, but a
  persisted board is state that can go stale.
* **What happens if a staged trait stops being free?** The board holds uncommitted
  traits, and nothing stops a player assembling a Fighter in another tab with one of
  them. The board has to notice rather than discovering it at the jettison.
* **Is portal provenance surfaced to players, or does `source = 'portal'` stay
  internal?**

## Art

Two pieces, supplied:

* **The portal** — red and black vortex, green deposit sign with a down-arrow, flames
  beneath, machinery packed around the frame. Industrial, not mystical. A chute.
* **The staging area** — the skeletal operator with articulated arms. The grid is laid
  over this, so the operator is holding the slots you fill.

The reveal gets a whole page rather than a dialog, which is why the Portal is its own
page rather than a section: the crafting ring is 276px inside a 392px dialog and the
chest reveal is 210px, and neither could sell "this is a machine you feed".


## Correction: what "quiet" means (2026-11-11)

Reported from the live site: a player dumping heavily, and **active players
including the owner receiving the gifts**.

Falling through to active players is by design -- the cascade's last rung is
"anybody who holds a trait", so nothing goes into the ether. But it was
reaching that rung far too easily, because `dhcp_pick_recipient()` measured
quiet with

```sql
GROUP BY user_id HAVING MAX(awarded_at) <= DATE_SUB(NOW(), INTERVAL N DAY)
```

over `dhc_trait_drops` -- which is not an activity signal. It is *when did we
last give them something*. Three faults followed:

- **Our own gift marked them active.** The instant a lapsed player received a
  piece their `MAX(awarded_at)` was `NOW()`, so they dropped out of *every*
  rung. Each lapsed player absorbed exactly one trait, the pool drained one
  each, and a big jettison fell straight through to the floor.
- **And it persisted.** Everyone gifted today looked active for the next
  `RETURN_GIFT_DAYS`, so the next jettison had a smaller lapsed pool and leaned
  harder on active players still. The feature eroded itself with use.
- **The inverse too.** Somebody who opened the site daily but had not *earned*
  a trait in a month read as lapsed.

Now `users.last_login`, which the welcome-back feature already uses for this
exact question. Despite the name it is not an auth event -- `skulliance.php`
updates it on any signed-in page load, once a day -- so a persisted session
popping in to look at a realm counts as being here. That distinction is the
reason the fix works: players rarely re-authenticate, and if it had been an
auth event it would have been no better than what it replaced. NULL counts as
maximally lapsed.

**Gifts can now land more than once on the same returner**, since a gift no
longer removes them from the pool. Kept deliberately: `ORDER BY RAND()` per
piece spreads them anyway, and somebody opening Discord to three traits is a
better woo-back than one.

Pinned by driving the real cascade through a connection that records every
query and returns no rows, so all seven rungs run and each rung's SQL is
inspected. A grep would have passed the broken version too -- it also mentioned
days and a cascade. Seven mutations.


## Correction: the board could hang off the grid (2026-11-11)

Reported from the live site: *"Certain combos of rarity on the portal hang off
the grid. Like 2 commons and an uncommon or 2 commons and legendary. No
possible combination should result in the grid being compromised like that."*

The library's note said every piece is row-aligned so nothing can leave a
notch, and there is no packing problem. **That is only true if the sizes arrive
in non-increasing order**, and the client laid them in the order the player
CLICKED. Two commons then a legendary puts the legendary at column 2, four
columns wide, two columns off the edge of the board.

Enumerated rather than spot-checked, and it was far worse than reported:
**422 of the 628 boards** that fit in sixteen slots straddled. Sorting the
layout largest-first fixes all of them -- the sizes are 1, 2, 4, 8 and 16, so a
four-wide always starts on a multiple of four, a two-wide always on an even
slot, and a one fits anywhere.

Purely visual. Slots, rows and the table are sums over the same set in any
order, and the eject rule is the server's.

`dhcp_layout()` and `dhcp_layout_fits()` are a pure PHP mirror of the client's
`layout()`, added so the harness can EXECUTE the rule rather than read it; the
harness also pins the client to the same sort, since the board the player sees
is the JS one. It walks every board that fits and asserts two separate
properties: that nothing hangs off the grid, and that nothing crosses a row
boundary. They are not the same -- a two-wide at column 1 covers columns 1 and
2, which is inside the grid and still straddles the middle of a row. I asserted
that one the wrong way round first.

`dhcp_cell_fits()` is split out for a reason worth keeping: with the sort
working, both of its conditions are unreachable through `dhcp_layout_fits()`,
so deleting either changed no outcome and failed no assertion. Reached
directly it can be handed the straddles the sort exists to prevent.
