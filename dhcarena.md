# DHC Arena — Design Doc

Internal planning doc, not a Skull Paper page (nothing has shipped yet). Written
for handoff to whichever session picks up the build. Numbers below were read out
of `dhcrarity.php` and `dhcfighters-config.php`, not estimated — anything still
undecided is marked **OPEN** rather than guessed.

**One-line pitch:** a squad-based asynchronous auto-battler where a Fighter's
traits *are* its combat kit, so the assembler becomes a deckbuilder and every
other game on the platform becomes a supply line.

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

**LOCKED: best-of-three sequential 1v1s.** Your three face theirs in order, first
to two wins takes the battle. Chosen over a six-Fighter team battle because the
resolver is far simpler, the log reads as a story rather than a soup, and
counterpicking stays legible — a player can see which of their builds answered
which of the opponent's, which is the feedback that teaches the game.

```
ROUND 1  Bone Harvester   vs  Xlon Prime        -> WIN
ROUND 2  Ash Revenant     vs  Data Tunnel       -> LOSS
ROUND 3  Grim Conductor   vs  Hellscape Widow   -> WIN

RESULT   2-1 challenger
KO       Ash Revenant (benched 6h)
```

**OPEN:** whether the defender's running order is fixed at Stable level or
shuffled per battle. Fixed rewards scouting; shuffled prevents a hard counter.

---

## 3. Traits are the stats

Ten slots, of which three are mandatory (`DHCF_REQUIRED`: background, torso,
head). The mapping has to cover all ten or some slots become dead weight:

| Slot | Contributes | Pool |
|---|---|---|
| `torso` | HP and armour — the body is what takes the hits | 25 |
| `head` | resistance, and the save against control effects | 35 |
| `background` | **arena terrain** — a global modifier on the whole fight | 42 |
| `weapon` | primary damage and damage *type* | 16 shared |
| `weaponBack` | secondary damage, or an opener that fires once | ↑ |
| `arms` | attack speed / actions per round | 17 |
| `headgear` | crit chance and crit damage | 32 |
| `companion` | an independent actor that takes its own turns | 9 |
| `effects1` | passive proc | 21 shared |
| `effects2` | second passive proc | ↑ |

Two things fall out of the real pool sizes that the mapping has to respect:

- **Companion is the scarcest slot at 9 traits, and has no commons and no
  mythics** — every companion is uncommon-to-legendary. A slot that narrow
  cannot carry a build-defining mechanic or the meta collapses onto the two or
  three good ones. Companions should be *additive* (an extra actor) rather than
  multiplicative.
- **Background is the largest pool at 42 and is mandatory.** That makes it the
  best home for terrain: every Fighter has one, and there is enough variety that
  terrain stays varied without anyone being locked out.

**OPEN:** the per-trait stat tables themselves. That is a large authoring job
(197 traits) and should be generated from tier + category as a baseline, then
hand-tuned for the traits that deserve identity. Do not hand-author 197 entries
up front.

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

**A Fighter that loses a battle is knocked out and unavailable for a period.
A Fighter that wins is not.**

This is the mechanic that does the work, and it is better than fatigue-on-any-
fight for three reasons:

1. **Winning keeps you playing.** A hot streak is uninterrupted, which is exactly
   when you want someone at the keyboard.
2. **Losing costs bench depth, not progress.** You never go backwards; you just
   need someone else to send in. That is the good kind of pressure.
3. **It converts "I want a better Fighter" into "I want more Fighters,"** which
   is the brief. Depth is what lets you keep playing tonight.

Because entry requires 3 available Fighters, a player with exactly 3 is locked
out of Arena the moment one of them loses. That is the moment the game asks them
to go build a fourth — and the answer is to go play the other eight games.

**Hard rule: knockouts are temporary. Never destroy a Fighter.** Against a
3-traits-per-day cap, losing a Fighter permanently is losing weeks of play. A
permadeath mode could exist later as an opt-in high-stakes bracket; it must never
be the default.

**LOCKED: knockout duration scales with Stable size — shorter for small Stables,
longer for large ones.**

An earlier draft of this section had it backwards, and the error is worth
recording because it is easy to make again: scaling the duration *down as the
Stable grows* rewards depth twice, once with more bodies on the bench and again
with faster recovery. The small Stable it was meant to protect gets nothing. The
rule has to run the other way.

A 3-Fighter player is back in the game quickly; a 12-Fighter player eats the full
penalty and leans on the bench they built. Depth still wins — it just buys
resilience rather than compounding into immunity, which is the §7 runaway
problem answered at its source.

**OPEN:** the actual curve. Start with something like 3h at the 3-Fighter floor
rising to 8h for deep Stables, and tune from live data. The floor matters more
than the ceiling: a new player locked out overnight by their first loss is a new
player who does not come back.

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

## 8. Resolution, and why it is asynchronous

Battles resolve **server-side from a seed**, producing a deterministic
round-by-round log. No real-time component, no matchmaking queue, no scheduling.

`endRaid()` in `db.php` is the working precedent for the whole shape: the server
decides the outcome, writes the result, and posts a Discord embed. Two lessons to
carry over rather than rediscover:

- **Resolve on a real trigger, not lazily on page render.** `endRaid()` resolves
  when somebody happens to load the raids list, which is what produced the
  session bug fixed in `621f5f31`. Arena should resolve on the challenger's
  action.
- **Award by `user_id`, never by session.** Both players' rewards are written
  from one request, and only one of them is at the keyboard. `dhcf_award()`
  already scopes its reveal modal correctly for this case.

The log is the shareable artefact. Fighters already have names and serials, so a
log reads as a story: *DHC2F424 "Bone Harvester" opened on Xlon Implant…*

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

1. Trait → stat derivation, generated from tier + category, with a simulator.
2. Run the all-commons vs all-legendaries test in §4. Tune before anything else.
3. Battle resolution + log format.
4. Stable, knockouts, entry requirement.
5. Challenge flow and Discord announce.
6. Trait rewards (`DHCF_GAMES['arena']`) — last, so the economy only opens once
   the combat maths is settled.
7. Skull Paper page, `$skullpaper_nav` entry, and a `MAINTENANCE.md` row.

---

## Decisions locked (2026-09-23)

1. **Best-of-three sequential 1v1s**, not a team battle. §2
2. **Knockouts scale with Stable size** — shorter for small Stables. §5
3. **Monthly seasons, full reset.** §8b
4. **Traits per win, CARBON on the season board.** §6
5. **Public per-Fighter W/L record** — with the ladder ranking on *wins*, not win
   rate, so protecting a record costs you the season. §8c

## Still open

These are tuning and detail, not direction — none of them block starting §10.

- The per-trait stat tables (§3). Generate from tier + category, hand-tune after.
- The knockout curve: floor, ceiling, and how it scales (§5).
- Defender running order: fixed at Stable level, or shuffled per battle? (§2)
- Whether fielded-Fighters-per-season should be capped to flatten the top (§7).
- Whether a loss shows on a Fighter's record immediately or on recovery (§8c).
