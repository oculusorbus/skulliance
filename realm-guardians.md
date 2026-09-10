# Realm Guardians — design notes

**Status: PROTOTYPE PLAYABLE at `/staking/guardians.php`** (2026-09-09). Nothing
links to it — type the URL. It has no database writes at all, hardcoded location
levels, and no rewards. Its only job is answering *is this fun*; see "If you build
this" for what comes after, and only if the answer is yes.

**The wall is loud, and the noise means something.** Crypt Crawl's weapon sounds
are reused from `audio/sounds/` (free — they already ship, and every file was
curl-verified 200 before being referenced). Armed defenders fire guns, unarmed
ones swing fists, so an empty Armory is **audible** before the counter is
noticed. Volley density follows garrison size and is capped; there is a mute in
the HUD that remembers itself. Cosmetic only — never read by the simulation.

**The horde wears real member avatars** (the user's idea, and it lands): each
attacker is a staker from the `users` table, so you watch people from your own
Discord come over the wall, and the log names whoever breaches it. Read-only,
excludes the current player, and deliberately kept OUT of the simulation — who is
drawn varies per load, so feeding it into the sim would break reproducibility.

**The current design, in one paragraph:** the Realms management loop played in
real time under wave pressure. Your seven realm locations supply the defense —
Barracks feeding soldiers, Armory weapons, Crypt resurrections, Tower its
garrison, Factory items, Mine income, Portal your sorties — and you keep them
stocked while a horde escalates. The soldiers are your NFTs: Realms already
enlists, trains, deploys, kills and resurrects them, so the army is not something
this game has to invent. Your realm sets a baseline position on a shared
ladder; skill pushes you above it, bad runs knock you back, and you never fall
below what your realm has earned you. Defensive by nature: you are guarding the
realm from intruders, which is where the name comes from.

Proposed because the user named tower defense — specifically Kingdom Rush — as a
genre they enjoy, and it is the one genre named that the platform doesn't have.

**Read this document in order.** It is a record of the design being worked out,
so later sections supersede earlier ones and say so. The section immediately
below is the FIRST draft — NFTs as towers, placement-based — kept because the
reasoning in it (especially the case for a deterministic server-side engine) still
holds and is load-bearing for everything after. The design proper starts at
"STRONGER IDEA: build it on Realms".

---

## First draft (superseded): NFTs as towers

A Kingdom Rush-style tower defense where **your staked NFTs are the towers**.

---

### The core (of that first draft)

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

### WHAT THE GAME ACTUALLY IS: Realms at speed

The user's framing, 2026-09-09, and it names the game better than anything above:

> "Pretty much the entire realms gameplay loop but in real time with accelerated
> timers like Kingdom Rush while the enemy is coming in waves."

Replacing items on locations as they take damage. Initiating upgrades as levels
drop. Resurrecting from the Crypt when the timer fires. Equipping weapons from the
Armory. Deploying to Portal and Tower. Replenishing the garrison when the Tower is
overwhelmed. Pulling items from the Factory to assign to locations. Spending Mine
output on upgrades.

**Realms is already this game, played in slow motion.** Compressing it and putting
a horde on a timer does not add a system — it changes the tempo of one that
exists and is already tuned. Which also means the tutorial is "you already know
how to play", and every hour a player has put into their realm is hours of
learning they get to reuse.

### Real time AND server-authoritative: replay the inputs

Real time collides with the payout constraint. The resolution, and it works only
because the engine is deterministic:

Do **not** send every action to the server (per-action round trips on mobile make
a real-time game feel terrible), and do **not** trust a client-reported outcome.
Instead the client simulates in real time from the seeded wave schedule while
recording an **action log** — `t=43.2s equip weapon from armory`, `t=51.8s
resurrect 3 from crypt`. It submits the log at the end. The server replays that
log against the same seed through the same deterministic engine and derives the
result itself. Divergence is rejection.

This is the Skull Racer lesson done properly: **do not validate the outcome,
replay the inputs.**

Cost, stated up front: the simulation engine must be deterministic AND shared
between client and server. That is the single biggest piece of work in the build,
and everything else depends on it. It is also why the deterministic core is
non-negotiable — a client-authoritative engine has to be thrown away before any
of this is possible.

### Two risks to hold on record

**UI density.** Kingdom Rush juggles roughly four tower types on one screen. Seven
locations, each with several actions, on a phone, under time pressure, is how
"engaging" becomes "frantic and unreadable". Start the prototype with three or
four active locations and add the rest only once it still reads clearly.

**The floor for realm-less players.** They start from scratch, which is correct —
but scratch has to be *playable*, not hopeless. If a new player's first siege is
unwinnable the game recruits nobody. A starter realm, or early waves scaled low
enough that a bare realm clears them, is the difference between "something to
build toward" and "not for me". **Sharper than it first looked:** no realm means
no enlisted soldiers, so there is nothing to deploy at all — a conscript force is
needed, not just gentler waves. See "the NFTs are the soldiers" below.

### Superseded: the phase structure

The first draft of this document said: choose your setup, press go, watch. Fixed
build nodes along the path (the Kingdom Rush idiom, not free placement on an open
grid — free placement lets players invent geometry you cannot balance against).

**That was too thin, and it was the server-authority requirement driving the
design further than it needed to.** In real Kingdom Rush you build *during* waves,
reacting. Commit-then-watch is not a simplified version of that; it is a
different and much smaller game.

**The answer is phases, and it fits the attrition design far better than
placement ever did:**

The siege runs wave by wave. BETWEEN waves the player decides — sortie through
the Portal, spend Factory items, prioritise who the Crypt brings back, buy
reinforcements with Mine income. On commit, the server resolves that wave
deterministically and shows what happened. Then the player decides again against
a changed board.

That gives repeated decisions with visible consequences while the server still
owns every outcome, and it needs **no netcode at all** — each decision point is
an ordinary request. It is the deliberation of a TD without the reflexes, which
suits a platform people play on a phone between other things.

Kept as the **fallback**, not the plan. The user wants real time (see above), and
the action-log replay makes that achievable without giving up server authority.
Phases remain the right answer if the real-time UI proves unreadable on a phone,
or if the shared deterministic engine turns out to be more than the build can
carry — both are live risks, and this is a working design to retreat to rather
than a dead end.

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
same outcomes, but watchable. Realm Guardians could be *what a raid looks like*.

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

### A REALM IS FAST-FORWARD, not a power level

The user's framing, 2026-09-09, and it supersedes the wave-scaling fix below:

> "Having a realm is like hitting fast forward on the game. You can grind from
> the bottom, or jump to higher levels from your realm curation."

Realm power becomes a **starting position** rather than a power multiplier.
Everyone plays the same ladder; a realm lets you skip ahead on it. This is better
than scaling waves to realm strength because there is one shared ladder, one
comparable set of numbers, and nobody is locked out.

The mechanic that falls out of it:

> **Starting wave = your realm-derived tier.** Nothing is saved between runs; see
> "no saved progress" below, which supersedes the earned-position half of this.

Two roads to the same place. Grinders climb by playing; realm-builders climb by
investing; someone doing both moves fastest. Neither path is a wall. It also
answers the realm-less floor problem noted above without a special case: a new
player starts at wave 1 and it is a real game, just a longer road.

### DECIDED: no saved progress. Every run is its own run.

The user's call, 2026-09-10, and it supersedes the ratchet below:

> "Since every realm eventually falls to the horde, I don't think we should be
> saving progress. I think each run should be a separate thing with different
> starting points depending on realm."

**Starting wave comes from realm power and nothing else.** No earned position is
stored, nothing carries between runs, and every siege begins where your realm
says it begins.

Why this is better than the ratchet:

- **Every realm falls eventually**, so a position that only ever ratchets up
  climbs until everyone sits at their ceiling and then stops moving. The ladder
  would stop being a ladder and become a stored number.
- **Every run stays meaningful.** With nothing banked there is no "I already got
  to 60, why replay" -- the only way to have a good run is to have it now.
- **The realm stays the whole progression.** Wanting a deeper start means
  building the realm, which is exactly the incentive the integration exists for.
  A second, in-game progression track would compete with it.
- **It deletes a pile of tuning.** The climb/slip asymmetry, the stakes-at-the-
  floor problem and its rewards-for-progress fix all evaporate; none of them
  exist without a stored position.

Consequence for the board: rank on **waves survived past your starting wave**.
That is comparable across realm sizes without a handicap, and a small realm
pushing twelve waves past its start still beats a large one pushing eight.

The prototype already behaves this way -- it persists nothing at all -- so this
decision required no code change, only the removal of a plan.

### Superseded: the ratchet, a tug of war with a floor

The user's refinement, and it is the core loop:

> "A game you pick up and play and either make progress or get knocked back...
> If you're slipping at the game, you can't go below your realm baseline. And if
> your realm baseline outpaces the game status, you move the needle forward to
> your new baseline."

> **Ladder position = max(what you have earned, your realm baseline).**
> Earned position drifts UP with good runs and DOWN with bad ones. The realm
> baseline only ever pushes it up.

Why this is better than a one-time head start: realm investment keeps mattering.
Upgrading a location **moves the needle immediately** rather than helping only on
some future run, so realm curation and siege play feed each other continuously.

**PROBLEM: the floor removes stakes at the floor.** A player parked exactly on
their realm baseline cannot lose anything — failure costs nothing, so the game
goes weightless precisely where many players will sit.

*Fix, already implied by the design:* tie **rewards to waves gained**, never to
position. Sitting at the floor is then safe but earns nothing, and the pressure
comes from wanting to climb rather than fear of falling. Stakes without
punishment, which suits a platform people play in the gaps of a day.

**Slipping must be slower than climbing.** If a bad run costs what a good run
gains, one distracted week erases a month and people stop opening the game.
Something like: clear a wave, gain a wave; fail, lose one only after two
failures. The numbers are tuning; the asymmetry is not.

**The ladder stalling is a feature.** Eventually everyone meets a wave they
cannot beat and progress flattens. That is the same shape realms already have —
`realms-locations.md` notes transcendent realms stall because the ±3 attack range
leaves them no worthy challengers. Here you stall at your skill ceiling, and the
only ways on are getting better or raising your baseline. Both are exactly the
behaviours the design wants.

**Decide before building the board: what does it rank?**

- *Furthest wave reached* makes the head start a permanent advantage, so realm
  holders top the board by construction. That is a legitimate choice — it is the
  incentive the whole integration is for — but the board then measures investment
  rather than play.
- *Waves gained this run* means a grinder pushing twelve waves past their start
  beats a whale pushing eight past theirs. The whale still reaches a deeper
  absolute wave, still earns more from depth, still gets "I'm at wave 60" — so
  realm investment pays exactly as intended. It simply is not what wins.

**Recommendation: show both numbers, rank on waves gained.** One comparable
leaderboard, realms strongly incentivised, and a new player can top it in their
first month — which is the difference between a game that recruits and one that
only rewards the people already here.

It also makes the weekly reset coherent: a player's start is stable week to week,
so each week is "how far can I push from where I stand" — a fresh race for
everyone regardless of realm size.

### Superseded: scaling waves to realm power

"Those with the strongest realms would dominate." Correct, and it decides whether
this works at all: if realm power determines the outcome, the board ranks who
bought the most upgrades rather than who played well. Same failure mode as
linking collections in Obscura — the board stops measuring what it claims to.

**Scale the waves to realm power.** Everyone gets a challenge proportionate to
their realm, and the board ranks how far you got *relative to your means*: wave
14 on a level-30 realm beats wave 14 on a level-90 realm. A strong realm buys a
harder and more interesting siege, not a guaranteed win. An absolute board can
run alongside the handicapped one if the whales want a trophy.

**Kept only as background.** The fast-forward model above is better and replaces
this: handicap maths is invisible and hard to explain, and players distrust a
board whose ranking they cannot compute in their head. "You start further up the
same ladder" needs no explanation at all.

Precedent worth reusing: `realms-locations.md` notes realms already self-limit at
the top — the ±3 defense-level attack range means transcendent realms stall for
want of challengers. Wave scaling is that same anti-runaway instinct applied to
PvE.

### Two constraints to hold firm

1. **Realm Guardians READS realm state and never writes it.** Raids already own those
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

### ANSWERED: the NFTs are the soldiers, and Realms already did it

This was left open as "do NFTs still matter once locations are the towers".
They matter more than in the first draft, and no design work is needed —
`realms-soldiers.md:3` already says it:

> "Soldiers are the NFTs you enlist into your Realm. They train in the Barracks,
> defend from the Tower, fight on raids, and can die and be resurrected."

That is the siege loop described verbatim. Enlist, train, deploy to the Tower,
die, resurrect from the Crypt. **Realms already did the NFT integration the first
draft proposed inventing**, and did it better: army composition already carries an
economy, with core project NFTs costing 1 slot and partner NFTs 2.

Two consequences:

**The read-only rule gets sharper.** A soldier dying in a siege must NOT kill the
real one or touch its trained state. The siege takes a **snapshot** of the roster
and plays with copies. Get this wrong and a bad run costs someone their raid army
— the two games fighting over the same NFTs is exactly the failure the read-only
constraint exists to prevent, and soldiers are where it would bite hardest
because they are the part that dies.

**The new-player floor is narrower than first assessed.** No realm means no
enlisted soldiers, so a fresh player does not merely start at wave 1 — they start
with nothing to deploy at all. The baseline needs a conscript force (a few
loaner soldiers, weak and untrained) or the entry-level siege is unplayable
rather than just hard. Same risk as noted above, now with a specific fix.

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
that just shipped rather than a new one, which is why Realm Guardians is the
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
3. Real-time management of those few locations against a wave clock, client-side
   only, no submission yet. **Play it. Stop here and decide.** The question is
   only ever: is keeping locations stocked under time pressure fun? If not, stop
   — realm integration cannot rescue it.
4. Portal sorties and Factory items, the two live decisions, once the base loop
   holds up.
5. Action log + server replay, so a result can be trusted. Nothing pays out
   before this exists.
6. Read real realm levels (read-only) to derive the starting wave. Nothing is
   persisted between runs — see "no saved progress" above. DONE in the prototype.
7. Weekly seed, leaderboard, CARBON, hub, nav, Skull Paper page.

Note: this file sits in the repo, which is pulled to the webroot — it is
technically fetchable at `/staking/realm-guardians.md`. Nothing sensitive here, but
worth knowing before anything commercially sensitive goes in it.
