# DHC Arena — Design Doc

Internal planning doc, not a Skull Paper page (nothing has shipped yet). Written
for handoff to whichever session picks up the build. Numbers below were read out
of `dhcrarity.php` and `dhcfighters-config.php`, not estimated — anything still
undecided is marked **OPEN** rather than guessed.

**One-line pitch:** a turn-based 3v3 party RPG where a Fighter's traits *are* its
abilities and stats, so the assembler becomes a deckbuilder, the battle is played
rather than watched, and every other game on the platform becomes a supply line.

---

## 1. Why this exists

The trait economy currently dead-ends. Play eight games → earn traits → assemble
a Fighter → land on a rarity leaderboard. That last step produces a static object
with a score attached. Once a Fighter is saved it never does anything again, which
is why engagement stops at the assembler.

Arena is the sink. It is not a ninth game competing for attention with the other
eight — it is the thing they were all feeding. That framing decides a lot of the
detail below: Arena should be **cheap in new content and rich in consequence**,
because its content is already in the database.

### What it has to accomplish, in priority order

1. **Make trait choices mechanical.** A trait is currently decoration plus a
   points value. In Arena it has to *do* something.
2. **Motivate more Fighters, not one better Fighter.** This is the brief. Every
   mechanic below is judged against it.
3. **Produce talkable moments.** Everything on this platform already announces to
   Discord; Arena's output should be a story, not a score.
4. **Reward in traits, not only CARBON.** Locked — see §6.

---

## 2. The Stable

A player fields a **Stable** of Fighters. Entry to Arena requires **3 available
Fighters**; there is no upper bound on how many you may own.

Three rather than one is the first and cheapest lever on "build more Fighters":
it triples the cost of entry, in an economy where traits are capped at 3/day per
source. See §5 for the second and much stronger lever.

**LOCKED: 3v3 team battle, all six Fighters on the field at once.**

An earlier revision specified best-of-three sequential 1v1s. That was the right
call *for an auto-resolver* — three small fights produce a log a human can read,
where a six-body simultaneous brawl produces soup. The premise is what changed,
not the reasoning: once the player is making decisions every turn (§3b), the
sequential format collapses into three shallow duels, while the team format is
where all the interesting decisions live.

Team battle is what creates:

- **Target selection.** Focus the dangerous one, or the weak one?
- **Protection.** A Fighter built to absorb hits is only meaningful if there is
  someone behind them worth shielding.
- **Cross-Fighter combos.** One Fighter sets up, another capitalises. Nothing in
  a 1v1 can express that.
- **A reason for Stable composition to be a puzzle** rather than three
  independently-optimised Fighters.

That last point matters most for the brief: 1v1 rewards building the same good
Fighter three times. 3v3 rewards building three Fighters that need each other,
which is more traits and more distinct builds.

## 3. Traits are the abilities and the stats

Ten slots, of which three are mandatory (`DHCF_REQUIRED`: background, torso,
head). The split that makes this authorable: **the small pools grant abilities,
the large pools grant stats.**

### Ability slots — 46 traits to author

| Slot | Pool | Grants |
|---|---|---|
| `weapon` | 16 shared | The Fighter's primary active. What you spend most turns doing. Carries a *usable from* and a *reaches* per §3bb. |
| `weaponBack` | ↑ | A second active, or a once-per-battle opener |
| `companion` | 9 | An independent actor that takes its own turns |
| `effects1` / `effects2` | 21 shared | One passive and one activatable, or two passives |

Forty-six abilities is a real authoring job but a finite one, and it is the part
worth hand-designing. Note this inverts an earlier concern in this doc: companion
being the smallest pool at 9 traits was a problem when companions were a stat
multiplier, because the meta would collapse onto the two best. As **nine distinct
summons**, a small pool is a feature — each one can be genuinely characterful.

### Stat slots — generated, not authored

| Slot | Pool | Contributes |
|---|---|---|
| `torso` | 25 | HP and armour — the body takes the hits |
| `head` | 35 | Resistance, and the save against control effects |
| `headgear` | 32 | Crit chance and crit damage |
| `arms` | 17 | Speed, which sets turn order and actions per round |
| `background` | 42 | **Arena terrain** — a global modifier on the whole battle |

These derive from tier and category with a hand-tune pass over the standouts.
151 traits is too many to author individually and they do not need it — a stat
line does not need personality, it needs to be a number the player can reason
about.

Background is mandatory and the largest pool at 42, which makes it the right home
for terrain: every Fighter brings one, so terrain always varies.

**LOCKED: the defender's front-rank Fighter sets the terrain.** You are invading
their arena, which is intuitive without explanation, and it hands the defender —
otherwise entirely passive under AI control — one real expression of their own
build. It also deepens scouting: you see the battlefield before you commit, so
picking a target is also picking a terrain, and a defensive Fighter's background
becomes a deliberate choice rather than whatever was spare.

---

## 3b. The battle is played, not watched

**LOCKED: turn-based, player-controlled.** Each round you choose what every
Fighter in your team does — which ability, on which target. Speed (from `arms`)
sets the turn order across all six bodies, so a fast Fighter may act twice before
a slow one moves.

This is the decision that separates Arena from a result screen. A player who
loses should be able to point at the turn where they chose wrong, and that is
what makes a win feel earned and a loss worth talking about.

### The async problem, and the standard answer

Turn-based play and asynchronous PvP are in direct tension: if both players act
every round, both have to be online, which means real-time infrastructure this
platform does not have and should not grow. Apache/mod_php with no websockets is
the right stack for everything else here; it is the wrong stack for live PvP.

**The answer: the attacker plays, the defender's Stable is run by the AI.**

You challenge another player's Stable and fight it yourself, turn by turn, at
whatever hour suits you. Their Fighters defend under AI control, with the stats
and abilities their owner built. When they challenge you, the positions reverse.
Nobody ever waits for anybody, and defence happens while you sleep — which is the
same rhythm raids already have.

### The constraint this creates, which shapes every ability

**Every ability has to be something an AI can play competently.** Half of all
Arena battles are your Stable piloted by a machine. If an ability needs a human's
read of the board to be worth anything, then a build using it is strong on
offence and useless on defence, and the meta collapses into "build what the AI
cannot misplay."

Practical rules that follow:

- Prefer abilities with a clear best target — "hit the lowest HP enemy", "shield
  the ally about to be focused" — over ones needing multi-turn setup.
- An ability whose value depends on *timing* needs an obvious trigger condition
  the AI can also read.
- Test every ability by asking: would a simple priority-based AI use this
  correctly? If not, it belongs in a PvE mode, not here.

This is a real design tax and worth accepting deliberately rather than
discovering during balance.

### What a turn looks like

```
ROUND 3                                  terrain: Data Tunnel  (+15% crit, all)

  ENEMY          BACK  Null Sentinel    [####################] 95/95
                  MID  Hellscape Widow  [####################] 77/77  shield 2
                FRONT  Xlon Prime       [######··············] 31/95  focused
                                          ^ intends: Strike -> Bone Harvester
  ─────────────────────────────────────────────────────────────────────────
  YOURS         FRONT  Bone Harvester   [#############·······] 62/90  << acting
                  MID  Ash Revenant     [####################] 88/88
                 BACK  Grim Conductor   [ K.O. ]

  TURN ORDER   Bone Harvester > Xlon Prime > Ash Revenant > Hellscape Widow

  > Skull Krusher     heavy · front only · reaches front      ~34 dmg, kills
  > Krusher Sash      opener · spent
  > Call companion    U-Vigilance Device · reaches back
  > Swap forward      pull Ash Revenant to front
  > Defend
```

Everything a player needs to decide is on that screen: what each side can still
do, who is about to hit whom, whether this ability kills, and what the formation
lets them reach.

---

## 3bb. Formation — position is a mechanic

**Each side's three Fighters stand in a rank line: front, middle, back.** Two
lines facing each other, six bodies, and where a Fighter stands changes what it
can do and what can reach it.

This closes a gap in §2. Team battle was argued for partly because it lets you
protect the Fighter worth protecting — but nothing in the design actually
provided a way to protect anyone. Position is that mechanism, and it is the
cheapest one that produces real decisions.

### What it buys

- **Abilities get a second design axis.** Each of the 46 gains a *usable from*
  and a *can reach* — a heavy weapon that only swings from the front, a companion
  that reaches the enemy back line, a support ability usable only from rank 3.
  That multiplies the depth of the ability set without authoring more abilities.
- **Stable composition becomes a positional puzzle.** A high-armour torso belongs
  at the front; a Fighter built around an effects proc belongs behind it. Three
  Fighters that need each other, rather than the same good Fighter three times —
  which was the stated goal and now has teeth.
- **Non-damage abilities matter.** Knockback, pull and swap become real plays:
  dragging their back-line support to the front is as good as damage, and it
  reads instantly to a spectator.
- **The AI gets easier, not harder.** Position gives a simple priority AI clean
  heuristics — reach what you can, focus the exposed. That matters given the
  §3b constraint that every ability must be AI-playable.

### Why a rank line rather than a full tactics grid

A 2D grid with movement, facing and range is a much bigger game — the biggest
thing on the platform by some distance — and most of that complexity buys
positioning decisions a three-rank line already provides. Three Fighters map onto
three ranks exactly. Start there; a wider board is an expansion, not a
prerequisite.

**LOCKED: the attacker sets formation per battle, after scouting. The defender
keeps a stored default.** The attacker adapting to what they can see, against a
defence arranged in advance, is the standard shape for asynchronous RPG combat
and it is standard because it works — it is what converts scouting from flavour
into the primary skill. The defender's stored formation is one more persistent
build decision rather than a chore.

---

## 3c. Presentation — what the fight looks like

### What the art actually is

Worth stating plainly, because it constrains everything: trait art is **static,
square, full-body display art**. Two sizes on disk, 250 and 1000. No sprite
sheets, no frames, no poses, no facing. And per the platform's deploy model the
repo carries no images at all — art reaches the server by FTP — so **the answer
cannot be "draw more art."** Everything below is CSS, JS and existing PNGs.

### Animate the layers, not the Fighter

This is the distinctive move, and it is available because of how the assembler
already works. A Fighter is not one image — it is up to ten `<img>` elements
stacked in DOM order, one per slot, and `paint()` already applies per-layer
transforms (`translateY` for nudge, `clip-path` for the single-arm restore).

So the weapon layer can swing independently of the body. The companion can bob on
its own cycle. An effects layer can flash when its proc fires. The head can
recoil while the torso holds. **Nothing else on the platform can do this, and
nothing else needs new art to do it** — the separation already exists at render
time because the layering rules demanded it.

A short list that would carry a whole battle, all CSS transforms on existing
layers:

| Beat | Treatment |
|---|---|
| Attack | attacker lunges toward target; weapon layer rotates ahead of the body |
| Hit | target recoils, flashes, screen-shakes briefly |
| Crit | harder shake, heavier flash, bigger number |
| Companion acts | companion layer detaches and moves independently of its owner |
| Effect proc | that effects layer pulses on its own |
| Knocked out | whole stack desaturates and drops |

### The background is the arena

`background` is mandatory, is the largest pool at 42, and is already a full-frame
image. It is the terrain (§3), so the battlefield *is* a real trait someone
earned. Free environment art, forty-two variants, and a reason to care which
background your Fighter wears beyond its stat line.

### Spotlight, not six-up

Six full-body square images side by side does not work — not on a desktop, and
certainly not at the ~400px the PWA has to survive. The layout that does:

- **The two rank lines**, top and bottom: six tokens at 250px in formation order
  (§3bb), so the tactical state of the battle is the layout. Enemy front rank
  faces your front rank; the board explains itself without a legend.
- **A spotlight centre stage** where the acting Fighter and its target are drawn
  large at 1000px, for the two or three seconds the action takes.

The formation carries the information; the spotlight carries the art. Neither
does both well alone.

That shows the art at a size where the detail is worth looking at, instead of six
thumbnails where none of it reads. It also fixes the performance problem: six
Fighters at ten layers is sixty images, and sixty 1000px PNGs is not a page the
PWA should load. **Budget: 250 everywhere, 1000 only for the two Fighters in the
spotlight.**

### Bars and readouts

Every token carries its state on its face — a player should never have to open
anything to know where the battle stands:

| Readout | Shows |
|---|---|
| **HP bar** | the primary one, always visible, with the number on hover |
| **Armour** | an overlaid segment on the front of the HP bar, so a buffer is visibly a buffer rather than a hidden number |
| **Cooldown pips** | one per active ability, filling as it recharges — tells you at a glance what a Fighter can still do |
| **Status icons** | with a round counter, so "shielded 2" is legible without a tooltip |
| **Turn-order track** | along one edge, who acts next and how far out |
| **KO state** | the whole token desaturates and drops out of the rank line |

Animate the bars rather than snapping them. A health bar draining over half a
second is most of what makes a hit *land* emotionally, and it costs a CSS
transition.

### Interaction

The half of the question that matters more than the animation:

- **Enemy intent is telegraphed.** The defending AI picks its action, so show it:
  *Xlon Prime will strike Bone Harvester.* This single feature is the difference
  between a turn-based game that feels strategic and one that feels reactive —
  it is what Into the Breach and Slay the Spire are built on, and it costs
  nothing because the AI has already decided.
- **A turn-order track** along one edge, showing who acts next and how far ahead.
  It makes `arms` and speed legible, and turns "act now or set up" into a real
  decision instead of a guess.
- **Damage preview on hover/tap** before committing a target. Being able to see
  that this ability kills and that one does not is most of what feeling in
  control means.
- **Select Fighter → select ability → select target**, with the whole chain
  cancellable. Never commit on a single tap; a misfire on a phone should not cost
  a battle.

### Keep it skippable

A player grinding their daily battles will watch the same lunge a hundred times.
Animations need a speed control and a skip, and the battle must be fully
playable with them off. The Skull Racer note about the user judging feel applies
here too: build it, then tune it by playing it.

---

## 4. Rarity must not decide the fight

This is the single most important constraint in the document.

If `rarity_score` predicts the winner, Arena is a spreadsheet, the players who
got lucky early win permanently, and everyone else stops playing. The platform
already made this exact call once, for scoring, and Arena must inherit it rather
than quietly reverse it.

Measured from the live curve in `dhcf_trait_points()`:

| Category | Worst → best trait | Spread |
|---|---|---|
| torso | 121 → 166 pts | **1.37×** |
| companion | 59 → 120 pts | **2.03×** |
| weapon | 54 → 171 pts | **3.17×** |

That is the house style: the best trait in a category is worth between about 1.4
and 3.2 times the worst, never orders of magnitude. **Combat power should sit
inside that same band.**

The design rule: **rarity sets magnitude, identity sets behaviour.** A legendary
weapon hits harder than a common one — by a little. What makes a build good is
whether its pieces work *together*, and that has to be reachable with common
traits. If a player cannot build a top-quartile Fighter out of commons and
uncommons, the tuning is wrong.

A useful test to run before launch: simulate an all-common stable against an
all-legendary stable. If the commons do not win at least ~35% of the time, the
spread is too wide.

---

## 5. Knockouts — the real engine

**Every Fighter who fights is benched afterwards. A Fighter who *loses* is
benched considerably longer.**

### Why not loss-only

An earlier revision of this section had knockouts apply on a loss only, on the
reasoning that winning should keep you at the keyboard. That is a genuine UX
virtue and it is why the rule is appealing, but it fails the brief, and the
failure is structural rather than a matter of tuning:

**Under loss-only, the strongest player needs the fewest Fighters.** A dominant
build never loses, so it is never benched, so it can run the entire ladder on its
own. The pressure to build more Fighters lands hardest on the players who are
losing — the ones least able to afford more traits — and lifts entirely off the
players who could most easily build them. It is anti-correlated with the thing
Arena is for.

It compounds with two other decisions. The ladder ranks on cumulative wins
(§8c), so the optimal line becomes "field one hyper-optimised Fighter forever,"
which is precisely the single-Fighter endgame Arena exists to prevent. And the
3-available entry requirement only ever bites after a loss, so a winning player
can sit on exactly three indefinitely.

### Why fatigue-on-any-fight, with a loss penalty

Benching on every fight makes the cost of *playing* proportional to how much you
play. The most engaged players fight most, so they need the deepest Stables —
which is the brief stated precisely, the ultimate experience for the most
engaged. Depth converts directly into battles per day.

Keeping a longer bench for a loss preserves what was attractive about the
loss-only rule: winning is still materially better than losing, a good run still
extends your evening, and a bad one still stings. It just no longer hands the
best player an exemption from the economy.

Rough shape, to be tuned: a base bench on any result, roughly doubled on a loss.

### The floor still has to be protected

Entry requires 3 available Fighters, so a 3-Fighter player fights three battles
and then waits. That is a legible rhythm — a session length — rather than a
punishment for failing, which is what made the loss-only lockout feel harsh.

**Hard rule: knockouts are temporary. Never destroy a Fighter.** Against a
3-traits-per-day cap, losing a Fighter permanently is losing weeks of play. A
permadeath mode could exist later as an opt-in high-stakes bracket; it must never
be the default.

### LOCKED: flat base bench, scaled loss penalty, and a daily allowance

Three numbers, and one of them fixes a flaw in an earlier decision.

**Base bench is flat: 4 hours after any battle, win or lose.** Scaling the base
with Stable size was right while knockouts were loss-only, and is wrong now —
throughput is `StableSize / benchDuration`, so raising duration with size
flattens throughput into a ceiling and deletes the reason to build deep.

**The loss penalty scales with Stable size**, adding roughly 4 further hours at
the 3-Fighter floor rising to about 12 at a deep Stable. That keeps a brake on
the top without taxing the thing we want to encourage, and it puts the brake
where the runaway actually is.

**And a daily battle allowance: 6 battles per day, the same for everyone.**

### Why the allowance, and the flaw it fixes

§8c ranks the ladder on cumulative *wins* rather than win rate, to stop players
protecting a good record by benching their best Fighter. That is right on its own
but it has a hole: with unlimited battles, ranking on total wins means the
grindiest player wins rather than the best one, and a deep Stable grinds more
simply by having bodies available.

An equal daily allowance closes it. Everyone gets the same number of attempts, so
ranking on wins inside a fixed budget *is* ranking on win rate — but expressed in
a way that still punishes hiding your best Fighter, because an unspent attempt is
a wasted one.

It also gives depth the right job. The allowance says how many battles you may
fight; knockouts decide whether you have anyone available to fight them with. A
3-Fighter Stable on a bad run cannot spend its six; a deep Stable always can.
**Depth buys the ability to use your allowance, not a bigger one** — which is
exactly the distinction between resilience and runaway.

Six is a starting number, to be tuned against how long a battle actually takes.
It wants to be enough for a satisfying session and few enough that missing a day
is not catastrophic.

---

## 6. Rewards — traits first, CARBON second

**LOCKED: traits per win, CARBON on the season board.**

The trait is the per-battle hook — it is what an Arena player actually wants,
because it is what builds the next Fighter. CARBON rides on the monthly ladder
the way it already does for every other board on the platform, so Arena pays out
in the shape stakers already understand rather than inventing a third pattern.

This closes the loop and is the whole reason Arena is self-sustaining:

> play Arena → earn traits → build a deeper Stable → survive more knockouts →
> play more Arena

**Implementation:** add `arena` to `$GLOBALS['DHCF_GAMES']` in
`dhcfighters-config.php`. It must be:

- **`'category' => 'wildcard'`** — all eight body categories are already
  assigned to other sources, and more importantly Arena is *gated*: it needs 3
  saved Fighters to enter. The config's own rule is that a gated source pays the
  wildcard only, so being locked out costs a bonus and never a slot. Same
  treatment as Boss Battles and Realm Raids.
- **`'gated' => true`**, for the same reason.
- **`'base' => 'run'`**, with `bands` on something that measures how well you
  did. A win against a deeper or higher-rated Stable should band better, the way
  Raids band on how far up you punched.
- Capped at the default 3/day via `DHCF_DAILY_CAP` — no override.

Note this makes Arena the **first source whose reward feeds its own entry
requirement**. Watch for a runaway: the best players earn the most traits, field
the deepest Stables, and take fewer knockouts. §7 is the counterweight.

---

## 7. Matchmaking, brackets and the runaway problem

**Bracket by Stable depth, not only by rating.** A new staker can build roughly
one Fighter a fortnight. If their first three meet a veteran's optimised twelve,
they quit, and the brief was to bring people *in*.

Countermeasures against the rich-get-richer loop in §6:

- Fighting far below your bracket pays little or nothing.
- Band the trait reward on relative strength, so beating a weaker Stable is worth
  less than holding off a stronger one.
- **Knockout scaling (§5) is the main counterweight** and is now locked: a deep
  Stable serves longer benchings, so depth buys resilience rather than immunity.
- **The daily battle allowance (§5) is the strongest counterweight** and is now
  locked: everyone fights the same number of battles a day, so no amount of depth
  buys more chances at the ladder. A cap on how many Fighters may be fielded per
  season is therefore unnecessary — dropped rather than held in reserve, because
  the allowance already does its job more simply.

---

## 8. Architecture

A live turn-based battle with server-authoritative state is **already a solved
pattern on this platform**, twice. Do not invent a third.

`cryptconquest-engine.php` is the model to copy, and its own header says why:

> Deliberately isolated from the DB/session layer: every function here takes
> and/or returns a plain `$run` array, no `$conn`, no `$_SESSION`. […] lets the
> whole ruleset be exercised by a standalone PHP test harness with zero setup.

That split is worth more here than it was there, because Arena's combat has to be
balanced, and balance means running ten thousand simulated battles without a
browser or a database in the loop. The §4 all-commons-vs-all-legendaries test is
not optional and it is only cheap if the engine is pure.

Four files, mirroring Conquest:

- `dhcarena-engine.php` — pure rules. Takes a `$battle` array, returns one.
  No `$conn`, no `$_SESSION`. Both the player's moves and the defending AI's
  choices go through it.
- `dhcarena-actions.php` — persistence wrapper, turn validation.
- `dhcarena-render.php` — the battle view.
- `ajax/dhcarena-action.php` — one turn per request.

### Two lessons to carry over rather than rediscover

**Keep the slow work out of the turn request.** `ajax/cryptcrawl-action.php` does
nothing but game logic, save and render — CARBON payout and the Discord announce
were moved to a separate fire-and-forget `cryptcrawl-finalize.php` because
anything slow in the turn request delays or breaks the confirmation reaching the
browser. That was the actual fix for a recurring "loss screen doesn't show" bug
after three failed same-request attempts. Arena's end-of-battle rewards and
announcement belong in the same shape from day one.

**Award by `user_id`, never by session.** A battle writes results for two
players and only one is at the keyboard. `dhcf_award()` already scopes its reveal
modal correctly for this; see the session bug fixed in `621f5f31` for what
happens when out-of-band awards assume the session is the recipient.

### Determinism

Seed the battle and store the seed. Given the same seed and the same sequence of
player choices, the battle must replay identically — that is what makes a log
trustworthy, a bug reproducible, and the balance harness meaningful.

---

## 8a. Collusion, and why the cap contains it

Arena lets you choose your opponent, which means a player can build an alt, give
it three deliberately terrible Fighters, and farm it. Worth writing down plainly,
because it looks alarming and is in fact bounded.

**It is not introduced by live battles.** Async is the easier version of the same
attack: the alt never has to be online, the AI defends it around the clock, and
no accomplice has to stay willing. Any rule written here has to cover async
first; live is the harder case for an attacker, not the softer one.

**The daily cap is the ceiling.** `DHCF_DAILY_CAP` is 3 per source per day and
Arena uses it unmodified. So collusion cannot produce more traits than honest
play — it can only make the same three reliable rather than earned. That is the
entire prize, and it reframes the problem from "economy exploit" to "someone
skipped the queue."

**Setup is not free.** A Fighter needs three mandatory traits (`DHCF_REQUIRED`),
so a minimum Stable is nine — three days of the alt actually playing the games at
the cap, and repeated every time one is banned.

Three defences, two of which are in this document already for other reasons:

1. **Reward banding on relative strength (§7).** Beating a much weaker Stable
   pays little. Written to stop the rich-get-richer runaway; it happens to be the
   exact counter to farming a target built to lose.
2. **LOCKED: one rewarded battle per opponent per day.** Beat the same Stable
   twice and the second win pays no traits and no ladder points. Chosen over a
   decay curve because it is a rule a player can hold in their head, and because
   it makes farming a single target worthless rather than merely inefficient.
   Same principle as `getRecentRaidedRealms()` blocking a re-loot, and it also
   pushes players to range across the ladder, which makes the whole population
   feel more alive.
3. **Live battles carry no stake (§8d).** No ladder, no traits, so the question
   does not arise for them at all.

Net: three days building an alt to slightly improve the odds of hitting a cap you
would probably hit anyway. Not worth doing, which is the correct place for an
exploit to land — deterred by economics rather than by policing.

---

## 8b. Seasons

**LOCKED: monthly, full reset.** Matches the cadence every other board already
runs on, including the monthly CARBON payouts — `checkRaidsLeaderboard()` and
friends are the precedent, and Arena should not invent a second rhythm for
stakers to track.

The reset is what keeps Arena open to someone coming back. A permanent ladder
would let the earliest entrants build a lead nobody can close, and a returning
player would arrive already beaten. A month is short enough that drifting away
for a few weeks costs one season, not the game.

---

## 8c. Fighter records

**LOCKED: every Fighter carries a public win/loss record.**

Fighters already have names and serials; a record turns one into a character
people recognise, which is most of the talkability the brief asked for. A veteran
with a real history is worth screenshotting.

**The risk this introduces, and the fix.** A public record gives players a reason
to protect it — bench the good Fighter, field the fodder, keep the shiny number
clean. That is the exact opposite of what Arena wants, and it would hollow the
game out from the top down.

The counter is in how the ladder is scored: **rank on wins, not win rate.** If
standings are cumulative wins, then sitting on a 12-0 Fighter earns nothing and
costs you the season. The player who fields their best constantly and goes 40-15
beats the player protecting a perfect record. Win rate can still be *displayed*
— it is interesting — but it must not be what the ladder sorts on.

Combined with loss-knockouts this is self-correcting: you cannot field one
Fighter repeatedly anyway, because losing benches it and entry needs three
available.

**LOCKED: a loss lands on the record immediately.** Deferring it to recovery is
a small dishonesty, and a record people cannot trust is worth less than no record
at all.

---

## 8d. Live battles — phase two

Two players who are both online, or who arrange a time, fight each other directly
with no AI on either side. Deliberately **not** in the first release, but the
engine must not close the door on it.

**Polling, not sockets.** Turn-based synchronous needs each player to act and
then wait, which ordinary AJAX polling covers — the platform already polls
elsewhere. None of this needs infrastructure the stack does not have.

**Invited, not matchmade.** The live boards run three or four players logging a
handful of runs a week (see the note on `DHCF_DAILY_CAP`), so a matchmaking queue
would essentially never fire. A challenge the other player accepts inside a
window works at any population, including two — and players already coordinate
in Discord, which is the lobby.

That framing is also what live *is*: grudge matches and organised tournaments,
announced to Discord. A community event, not a daily mode.

**LOCKED: live battles never touch the economy.** No ladder points, no traits,
not even inside an owner-run event. An absolute rule cannot be accidentally
broken by a later feature, where "unless it is an official tournament" is exactly
the exception someone eventually builds a hole through. Events can award prizes
by hand; the code never pays out for a battle two players arranged themselves.

Two things it needs before shipping:

- **A turn clock**, so nobody can stall. On expiry, hand that turn to the AI —
  which already exists for async, so it costs almost nothing.
- **Disconnect handling**, answered the same way: the AI takes that side over and
  the battle finishes. Nobody should lose a Fighter to a dropped connection.

### The one requirement this places on phase one

**The engine must take a turn from a caller, not fetch one itself.** If
`dhcarena-engine.php` calls the AI internally, live means unpicking the core
later. If the turn is a parameter, the only difference between the modes is who
supplies the defender's move — the AI function, or the other player's request.
Same engine, same state, same validation.

Costs nothing to build this way now. Expensive to retrofit.

---

## 9. Schema sketch

Nothing here creates tables; like the rest of DHC this is a documented migration
run once. Roughly:

- `dhc_arena_battles` — challenger, defender, seed, outcome, log JSON, fought_at.
  Append-only, the same way `dhc_trait_drops` is: the record of what happened
  must survive later rule changes.
- `dhc_arena_status` — per Fighter: knocked_out_until, season W/L, career W/L.
- Per player: battles spent today (the §5 allowance) and the stored default
  formation (§3bb). Counted off the same CURDATE() boundary the trait cap uses,
  so the two limits reset together and a player has one daily rhythm, not two.
  Career totals are stored rather than derived because the battle log is
  append-only and seasons reset; recomputing a career from every battle ever
  fought gets slower forever.
- Season/ladder standings can be derived from `dhc_arena_battles` rather than
  stored, following the existing leaderboard pattern.

Do **not** add combat columns to `dhc_fighters`. A Fighter's stats are derived
from its traits, so they are computed, not stored — which means retuning the
combat maths improves every existing Fighter automatically, exactly as the
layering rules already do for the art.

---

## 10. Build order

1. `dhcarena-engine.php` — pure turn engine, plus a CLI harness that runs
   battles with no DB. Everything below depends on being able to simulate.
   **The engine takes each turn as a parameter and never fetches one itself**,
   so live battles (§8d) stay possible without a rewrite.
2. Stat derivation from tier + category for the five stat slots.
3. The 46 abilities, each carrying its *usable from* and *reaches* (§3bb).
   Design each against the "can a simple AI play this?" test in §3b as it is
   written, not afterwards.
4. The defending AI. Build it early — it is half of every battle, and an ability
   the AI cannot use is an ability that is not finished.
5. Run the all-commons vs all-legendaries test in §4. Tune before any UI.
6. Persistence, battle view, one-turn-per-request AJAX. Build the view at the
   §3c presentation budget from the start — 250px roster, 1000px spotlight only;
   sixty full-size layers is not a page the PWA can carry.
7. Stable, fatigue, entry requirement.
8. Challenge flow, end-of-battle rewards and Discord announce — in a separate
   fire-and-forget request, per §8.
9. Trait rewards (`DHCF_GAMES['arena']`) — last, so the economy only opens once
   the combat maths is settled.
10. Skull Paper page, `$skullpaper_nav` entry, and a `MAINTENANCE.md` row.

---

## Decisions locked (2026-09-23)

1. **Turn-based 3v3 team battle in a front/mid/back rank line, played not
   watched.** Position decides what a Fighter can reach and what can reach it.
   §3bb The attacker plays every
   turn; the defending Stable is run by the AI with its owner's builds, so
   nothing is real-time and nobody waits. Supersedes the earlier auto-resolved
   best-of-three. §2, §3b
2. **Knockouts on every fight, longer on a loss.** Reverted from loss-only after
   a merits re-check — see §5 for why loss-only is anti-correlated with the
   brief. Stable-size scaling needs a fresh call as a result. §5
3. **Monthly seasons, full reset.** §8b
4. **Traits per win, CARBON on the season board.** §6
5. **Public per-Fighter W/L record** — with the ladder ranking on *wins*, not win
   rate, so protecting a record costs you the season. §8c

## Design calls made on the owner's delegation (2026-09-23)

The owner does not play RPGs and asked for these to be decided from genre
practice rather than put back to them. Reasoning is in each section; recorded
here so a later reader knows they were decided, not overlooked.

6. **Defender's front-rank Fighter sets the terrain.** §3
7. **Attacker picks formation per battle after scouting; defender stores a
   default.** §3bb
8. **Full scouting** — the attacker sees the defending Stable's builds and
   formation before committing. With the AI piloting defence, hiding the build
   converts the game's main skill into a guess. §3bb
9. **Flat 4h bench on any battle; the loss penalty scales with Stable size.** §5
10. **A daily allowance of 6 battles, equal for everyone** — which also closes a
    hole in ranking on cumulative wins, where unlimited battles would have meant
    the grindiest player wins rather than the best. §5
11. **One rewarded battle per opponent per day.** §8a
12. **Losses appear on a Fighter's record immediately.** §8c
13. **Live battles never pay anything, events included.** §8d
14. **The defending AI has one fixed difficulty.** Scaling it to the owner's rank
    hides a weak AI and makes two players' results incomparable. Build one AI
    properly instead. §3b
15. **Ship the readouts before the animation.** Telegraphed intent, the
    turn-order track and honest bars are the game; the layer animation in §3c is
    polish, and polish is tuned by playing. §3c

## Still open

Work and tuning, not direction. None of it blocks starting §10.

- **The 46 abilities** (§3). Hand-authored, each carrying its *usable from* and
  *reaches*, each tested against "can a simple AI play this correctly?" (§3b).
- **Stat generation** for the other 151 traits from tier and category, with a
  hand-tune pass over the standouts only (§3).
- **Every number above is a starting point**, including the 4h bench, the 6-battle
  allowance and the loss-penalty curve. They are written down so the first build
  has something concrete to be wrong about; tune them against live data and the
  §4 balance harness, not against argument.
