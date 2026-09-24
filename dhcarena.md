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
| `weapon` | 16 shared | The Fighter's primary active. What you spend most turns doing. |
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
for terrain: every Fighter brings one, so terrain always varies. **OPEN:** whose
background sets the terrain in a 3v3 — the attacker's lead Fighter, a roll among
the six, or each side's own Fighters carrying their own.

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
ROUND 3            terrain: Data Tunnel (+15% crit, all combatants)

  Bone Harvester   [HP 62/90]   speed 14   -> your move
  Ash Revenant     [HP 88/88]   speed  9
  Grim Conductor   [KO]

  vs

  Xlon Prime       [HP 31/95]   focused
  Hellscape Widow  [HP 77/77]   shielded 2 rounds
  Null Sentinel    [HP 95/95]

  > Skull Krusher        heavy, slow, ignores armour
  > Krusher Sash         once per battle, opener spent
  > Call companion       U-Vigilance Device
  > Defend
```

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

### Knock-on: Stable-size scaling now needs revisiting

Scaling bench duration *up* with Stable size was locked while the rule was
loss-only, where it was the right answer — it stopped a deep Stable from
compounding into immunity.

Under fatigue-on-any-fight it cuts the wrong way. Throughput is already
`StableSize / benchDuration`; if duration rises with size, throughput flattens
toward a constant and there is no reason to build past the point where it levels
off. That deletes the incentive this whole section exists to create.

**NEEDS A CALL.** Three options:

1. **Drop the scaling.** Flat bench for everyone. Depth converts cleanly into
   battles per day, simplest to explain, and §7's runaway is handled by reward
   banding instead.
2. **Scale the loss penalty only.** Base bench flat, the extra for a loss grows
   with Stable size. Keeps a brake on the top without capping throughput.
3. **Keep it as locked.** Accept a throughput ceiling as a deliberate cap on how
   much anyone can farm in a day.

Leaning 2 — it keeps the counterweight where the runaway actually is, without
taxing the thing we are trying to encourage.

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
- **OPEN:** whether to also cap how many Fighters from a Stable may be *fielded*
  per season. Preserves the "build more" motivation while flattening the top, but
  it may be unnecessary once knockout scaling is tuned — hold it in reserve.

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

**OPEN:** whether a knocked-out Fighter's record shows the loss immediately or
on recovery. Cosmetic, but it changes how a bad streak feels.

---

## 9. Schema sketch

Nothing here creates tables; like the rest of DHC this is a documented migration
run once. Roughly:

- `dhc_arena_battles` — challenger, defender, seed, outcome, log JSON, fought_at.
  Append-only, the same way `dhc_trait_drops` is: the record of what happened
  must survive later rule changes.
- `dhc_arena_status` — per Fighter: knocked_out_until, season W/L, career W/L.
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
2. Stat derivation from tier + category for the five stat slots.
3. The 46 abilities. Design each against the "can a simple AI play this?" test
   in §3b as it is written, not afterwards.
4. The defending AI. Build it early — it is half of every battle, and an ability
   the AI cannot use is an ability that is not finished.
5. Run the all-commons vs all-legendaries test in §4. Tune before any UI.
6. Persistence, battle view, one-turn-per-request AJAX.
7. Stable, fatigue, entry requirement.
8. Challenge flow, end-of-battle rewards and Discord announce — in a separate
   fire-and-forget request, per §8.
9. Trait rewards (`DHCF_GAMES['arena']`) — last, so the economy only opens once
   the combat maths is settled.
10. Skull Paper page, `$skullpaper_nav` entry, and a `MAINTENANCE.md` row.

---

## Decisions locked (2026-09-23)

1. **Turn-based 3v3 team battle, played not watched.** The attacker plays every
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

## Still open

These are tuning and detail, not direction — none of them block starting §10.

- The 46 abilities (§3). Hand-authored, each tested against "can a simple AI
  play this correctly?" — see §3b.
- The stat tables for the other 151 traits (§3). Generate from tier + category,
  hand-tune the standouts only.
- Whose `background` sets the terrain in a 3v3 (§3).
- The knockout curve: floor, ceiling, and how it scales (§5).
- How much the attacker can scout before committing. Full Stable visibility makes
  counterpicking a skill; hidden builds make it a gamble. Leaning full.
- Whether the defending AI's difficulty is fixed, or reads its owner's ladder
  rank. Fixed is honest; scaled hides a bad AI.
- Whether fielded-Fighters-per-season should be capped to flatten the top (§7).
- Whether a loss shows on a Fighter's record immediately or on recovery (§8c).
