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

## 3b. Where the player's agency actually is

"Auto-battler" is an overloaded word and it is worth being blunt about what it
does and does not mean here, because the failure mode — send three Fighters, hope
— would be a slot machine, not a game.

**The battle itself runs without input. Everything that decides it does not.**

| Decision | When | Depth |
|---|---|---|
| What each Fighter *is* — ten slots from 197 traits | Assembler | The main layer. This is deckbuilding. |
| Which three make up the Stable | Before challenging | Covering matchups rather than stacking one archetype |
| Who to challenge, having scouted them | Before challenging | Reading an opponent's build for a weakness |
| Running order against a known Stable | Per battle | Only real if order is fixed — see §2 |
| Who to field given who is benched | Per battle | Resource management under §5 |

That is the same shape as a card game: you do not act during the shuffle, you act
when you build the deck and choose the matchup. Crypt Crawl and Crypt Conquest
put the decisions *inside* the run; Arena puts them *around* it. Both are real,
but they are different games, and a player arriving from the card games will
notice the difference.

### The two things that decide whether this feels like strategy or gambling

**1. Variance has to be low.** If a battle is a coin flip weighted by build, then
scouting is pointless and the correct play is to challenge constantly and let the
maths average out — which is gambling with extra steps. Resolution should be
close to deterministic given two builds: same matchup, same result, near enough
every time. Randomness belongs in *which* traits you draw, not in whether your
build works. The platform already has lottery in the drop tables; Arena should be
the part that rewards playing well with what you drew.

**2. The log has to teach.** A player who loses must be able to read the log and
see *why* — "their resistance rolled over my damage type", "my companion never
got to act because the fight ended in three rounds". That is the loop that turns
a loss into a build change instead of a shrug. An opaque result is
indistinguishable from a random one even when it is not.

### If that is still too passive: banked orders

If pre-battle agency alone reads as thin, the cheapest way to add genuine
in-battle decisions without breaking async is a small **orders** layer: each
Fighter carries one or two conditional instructions, set when you field them.

```
IF hp < 30%      THEN  use companion
IF enemy armour  THEN  lead with weaponBack
```

Programmed rather than live — the player writes the tactics, the sim executes
them. It keeps everything asynchronous and server-resolved, it makes two
identical Stables play differently, and it gives a skilled player something to be
better at beyond collection depth. It also gives the log more to narrate.

**NEEDS A CALL.** Ship without orders and add them if Arena reads as passive, or
build them in from the start? Adding later is harder than it sounds — the combat
resolver has to be written with hook points for it either way, so the decision
should be made before §10 step 3, even if the feature ships later.

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
2. **Knockouts on every fight, longer on a loss.** Reverted from loss-only after
   a merits re-check — see §5 for why loss-only is anti-correlated with the
   brief. Stable-size scaling needs a fresh call as a result. §5
3. **Monthly seasons, full reset.** §8b
4. **Traits per win, CARBON on the season board.** §6
5. **Public per-Fighter W/L record** — with the ladder ranking on *wins*, not win
   rate, so protecting a record costs you the season. §8c

## Still open

These are tuning and detail, not direction — none of them block starting §10.

- The per-trait stat tables (§3). Generate from tier + category, hand-tune after.
- The knockout curve: floor, ceiling, and how it scales (§5).
- Defender running order: fixed at Stable level, or shuffled per battle? (§2)
  This one is load-bearing for §3b — fixed order is what makes scouting and
  counterpicking a skill; shuffled converts that skill back into luck.
- Banked orders: in from the start, or later? The resolver needs hooks either
  way (§3b).
- Whether fielded-Fighters-per-season should be capped to flatten the top (§7).
- Whether a loss shows on a Fighter's record immediately or on recovery (§8c).
