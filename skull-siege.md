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
   same seed plus the same placements always produces the same result.
2. A single hardcoded map and three tower types. Ugly is fine.
3. Placement UI + result animation. **Play it.** Stop here and decide.
4. NFT → tower mapping, once the core is known to be fun.
5. Weekly seed, leaderboard, CARBON, hub, nav, Skull Paper page.

Note: this file sits in the repo, which is pulled to the webroot — it is
technically fetchable at `/staking/skull-siege.md`. Nothing sensitive here, but
worth knowing before anything commercially sensitive goes in it.
