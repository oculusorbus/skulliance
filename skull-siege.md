# Skull Siege — design notes

**Status: unbuilt idea.** Nothing here exists in code. Written down 2026-09-09 so
it isn't lost; see the "If you build this" section at the bottom for the order I'd
tackle it in.

A Kingdom Rush-style tower defense where **your staked NFTs are the towers**.

Proposed because the user named tower defense — specifically Kingdom Rush — as a
genre they enjoy, and it is the one genre named that the platform doesn't have.

---

## The core

Enemies march a fixed path. You place towers at fixed nodes along it. Waves
escalate. You survive as long as you can.

**Your NFTs are the towers.** Each of the six core projects maps to a tower
archetype, so your holdings determine what you can field. Rarity and traits set
the stats.

This is the part that makes it a *Skulliance* game rather than a generic TD: a
player with forty skulls doesn't just have a bigger number, they have a wider
**toolbox**. Owning across projects becomes interesting in a way that reward-rate
staking never made it — you field a composition, not a pile.

Open question: what happens to a player with two NFTs. Options are a baseline
"militia" tower everyone gets, or accepting that the game is for established
holders (which the Gauntlets precedent supports). Leaning toward a baseline
tower — a game nobody new can play doesn't recruit anybody.

---

## THE LOAD-BEARING DECISION: the server runs the simulation

Waves are generated from a **weekly seed**. The player places towers; the
**server** simulates the outcome deterministically and hands the client a result
it merely animates. The browser never decides anything.

Three things fall out of that, and all three are the reason to do it this way:

1. **It cannot be cheated.** Skull Racer needed ghost-trace validation precisely
   because the browser owned the truth, and that fought us more than once
   (see the commits around ghost trace validation rejecting real crashes). Obscura
   learned the same lesson from the other direction — the CSS crop shipped the
   whole artwork and asked the browser not to look. If the client computes it,
   the client can lie about it. A leaderboard paying CARBON cannot be built on a
   number the client chose.

2. **Everyone gets the same waves each week.** This is the real prize and the
   thing no current game has. A *shared* challenge produces Discord conversation
   in a way per-player randomness never does — "how far did you get on wave 14?"
   is a thread; "I scored 48,200" is not. Obscura structurally cannot do this
   because every puzzle is private to the player.

3. **It is far cheaper to build than real-time.** No netcode, no tick
   synchronisation, no reconciliation. A deterministic simulation is a loop over
   a data structure.

### The honest tradeoff

Commit-your-loadout-and-watch loses the twitch layer: no dragging a hero around,
no panic spell at the last second. What survives is the placement and economy
puzzle, which is arguably Kingdom Rush's actual core, but it is genuinely a
different feel and should be evaluated as such in the prototype.

**Upgrade path if the twitch layer is missed:** lockstep replay. The client sends
actions with tick numbers (`place tower X at node Y at tick T`), the server
replays them against the same seed and authoritative result. The deterministic
engine is what makes that possible later; a client-authoritative engine would
have to be thrown away first. Build the deterministic core either way.

---

## STRONGER IDEA: build it on Realms

Raised by the user 2026-09-09, and it is better than the NFTs-as-towers version
above. Realms is **already a tower defense system that doesn't render**:

- Seven locations per realm — one per core project, plus the Diamond Mine.
  Exactly the tower archetypes the section above proposed inventing.
- Each is upgraded independently with core project points, purchased cap L10,
  pushed past it by successful defense. A tuned upgrade economy, already live.
- They combine into **offense and defense ratings**, and defense is literally
  what a TD scores.

So the tower economy above doesn't need designing. It exists.

**And the sharpest version goes further than sharing a loadout.** A raid today
resolves as an invisible comparison of two ratings. In a TD engine the
defender's realm IS the map and the attacker's raid IS the wave — same numbers,
same outcomes, but watchable. Skull Siege could be *what a raid looks like*.

### It is an ATTRITION SIEGE, not a placement game

The user's mapping of the seven real locations, 2026-09-09, and it changes the
genre. **Only one of the seven is actually a tower.** The rest are supply lines.
So the core question is not *where* you put things — it is *when* you spend
finite resources. That is a better game for this platform because it is native to
what already exists rather than bolted onto it.

Levels become **rates and caps**, not a single defense number:

| Location | What it governs during a siege |
|----------|--------------------------------|
| Barracks | Soldier pool size, and how fast replacements arrive |
| Armory   | Weapon stock — armed soldiers hit harder, and it runs dry |
| Crypt    | Resurrection: what fraction of your dead return, and how fast |
| Tower    | The one true tower. Ranged damage from above, drawing its garrison from Barracks |
| Factory  | Consumables usable DURING the siege |
| Mine     | In-run income, spent on reinforcements mid-fight |
| Portal   | Sortie range — how far forward you can meet them, and how many times |

Why this is worth more than a rating: realm power stops being one number and
becomes a **shape**. A Barracks-heavy realm grinds waves down with bodies. An
Armory/Tower realm kills fewer things but kills them harder. A Crypt-heavy realm
loses constantly and keeps standing anyway. Those are genuinely different ways to
play, and they emerge from upgrade choices players already make for raid reasons.

**The Portal is the best part of the design.** Meeting invaders in the field
before they reach the walls is a real risk/reward decision — sortie early and you
spare your locations but expose soldiers with no tower support behind them. That
one choice does more work than the whole placement layer proposed further up.

**The Crypt finally gets a job.** Resurrection-as-recycling is a classic attrition
pressure valve, and it turns "where the dead are stored" from flavour into the
mechanic deciding whether a long siege is survivable at all.

The economic chain the user described — Mine produces CARBON, CARBON burns to
DIAMOND, DIAMOND shatters to core project points, points upgrade locations — is
the *between-sieges* loop and already exists on the platform. Inside a siege the
Mine is simply income. Do not wire the in-run currency to real balances; see the
read-only constraint below, which the user arrived at independently ("all this
activity wouldn't actually affect the location levels, soldiers, weapons, items").

**THE RISK THIS DESIGN CARRIES:** if everything is supply-driven, the player may
have nothing to *do* — press deploy, watch numbers resolve. It becomes a
spreadsheet with animation. There must be at least one live decision axis.
Candidates, and probably enough between them: Portal sortie timing, Factory item
usage, and Crypt resurrection priority. **This is the thing the prototype exists
to prove.** If placing and spending isn't fun in a hardcoded scenario, no amount
of realm integration will save it.

### The problem the user spotted, and the fix

"Those with the strongest realms would dominate." Correct, and it decides whether
this works at all: if realm power determines the outcome, the board ranks who
bought the most upgrades rather than who played well. Same failure mode as
linking collections in Obscura — the board stops measuring what it claims to.

**Scale the waves to realm power.** Everyone gets a challenge proportionate to
their realm, and the board ranks how far you got *relative to your means*: wave
14 on a level-30 realm beats wave 14 on a level-90 realm. A strong realm buys a
harder and more interesting siege, not a guaranteed win. An absolute board can
run alongside the handicapped one if the whales want a trophy.

Precedent worth reusing: `realms-locations.md` notes realms already self-limit at
the top — the ±3 defense-level attack range means transcendent realms stall for
want of challengers. Wave scaling is that same anti-runaway instinct applied to
PvE.

### Two constraints to hold firm

1. **Skull Siege READS realm state and never writes it.** Raids already own those
   levels. Two systems writing the same numbers will fight, and the bug will
   present as "my realm randomly changed" — which is unfalsifiable from a bug
   report and miserable to chase.
2. **A player with no realm must still be able to play.** A baseline map, or some
   starter garrison. A game nobody new can start does not recruit anyone.

### Order of work

Standalone and PvE first, reading realms read-only. If the siege is fun,
converting raids to run on the same engine is the natural second act. Doing it
the other way round means changing a live, tuned, player-facing system before
knowing whether the core loop is any good.

Open question left deliberately unanswered: whether NFTs still matter here once
locations are the towers. Options are NFTs as garrison capacity, as consumable
one-shot abilities, or not at all. Decide it after the prototype, not before.

## Leaderboard shape

Ranked on **waves survived**, with something like towers-lost or gold-unspent as
the tie-break.

The weekly seed makes the board directly comparable in a way the other games
aren't — "wave 14 with these towers" is a like-for-like comparison, where
"score 48,200" from a random Skull Swap board is not. This is the strongest
argument for the whole design.

Follow the established weekly pattern: a `reward = 0` flag on the runs table,
paid and flipped by a `rewards.php?skullsiege=1` cron, pool divided down the
board by rank. See the OBSCURA block in `db.php` for the closest template —
board function, reset function, registry entry, both `leaderboards.php`
exclusion chains, Activity source, hub dispatch.

---

## Why it fits the portfolio

Every current game is a **solo session**. Boss Battles is the only communal one
and it's a shared damage pool rather than a shared *challenge* — players
contribute to one total, they don't attempt the same problem.

A weekly seeded siege would be the first thing where players are demonstrably
working on **the same puzzle at the same time**. That's a category the platform
is missing, and it's the category that generates community activity rather than
just leaderboard positions.

---

## The alternative held in reserve

**Daily Obscura.** One shared puzzle for everyone, one guess, posted at a fixed
hour. A fraction of the work — reuses `ajax/obscura-crop.php` as-is — and would
produce daily channel chatter immediately.

It's the safer play and worth doing regardless. But it's an extension of a game
that just shipped rather than a new one, which is why Skull Siege is the
recommendation when there's appetite for a real build.

---

## If you build this

Start the way Obscura started: **a playable prototype with no CARBON, no
leaderboard, no hub entry, no nav entry.** Just enough to answer the only
question that matters — *is placing the towers actually fun?* — before anything
expensive gets built around it. Obscura spent its whole first pass answering
"are the crops fair?" and that was the right call; everything else was cheap to
add afterwards and would have been expensive to tune blind.

Rough order:

1. Deterministic wave/simulation engine, server-side, seeded. No UI. Verify the
   same seed plus the same inputs always produces the same result.
2. A single hardcoded scenario with **hardcoded location levels** — no realm
   reading yet. Barracks/Armory/Crypt/Tower only; skip Factory, Mine and Portal.
   Ugly is fine.
3. The decision layer and a result animation. **Play it. Stop here and decide.**
   The question is only ever: is spending finite supply against escalating waves
   fun? If not, stop — realm integration cannot rescue it.
4. Portal sorties and Factory items, the two live decisions, once the base loop
   holds up.
5. Read real realm levels (read-only), and scale waves to total realm power.
6. Weekly seed, leaderboard, CARBON, hub, nav, Skull Paper page.

Note: this file sits in the repo, which is pulled to the webroot — it is
technically fetchable at `/staking/skull-siege.md`. Nothing sensitive here, but
worth knowing before anything commercially sensitive goes in it.
