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

**OPEN:** whether the 3 fight as a team in one battle, or as a best-of-three of
sequential 1v1s. Best-of-three is simpler to resolve, easier to read as a log,
and makes counterpicking legible. Leaning best-of-three.

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

**OPEN:** knockout duration. Wants to be long enough that depth matters and short
enough that a 3-Fighter player is not locked out overnight. Start around 6-8
hours and tune from live data. Consider scaling it down as the Stable grows, so
the punishment does not compound for the smallest Stables.

---

## 6. Rewards — traits first, CARBON second

Locked: Arena pays **traits**, not only CARBON. CARBON is the platform's existing
currency and already flows from every leaderboard; a trait is the thing an Arena
player actually wants, because it is what builds the next Fighter.

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
- **OPEN:** consider capping how many Fighters from a Stable may be *fielded* per
  season, so depth buys resilience against knockouts but not an unbounded roster
  advantage. This preserves the "build more" motivation while flattening the top.

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

## 9. Schema sketch

Nothing here creates tables; like the rest of DHC this is a documented migration
run once. Roughly:

- `dhc_arena_battles` — challenger, defender, seed, outcome, log JSON, fought_at.
  Append-only, the same way `dhc_trait_drops` is: the record of what happened
  must survive later rule changes.
- `dhc_arena_status` — per Fighter: knocked_out_until, season W/L.
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

## Open questions for the owner

1. Team battle or best-of-three sequential 1v1? (Leaning best-of-three.)
2. Knockout duration, and should it scale with Stable size?
3. Seasons — length, and does the ladder reset?
4. Is CARBON paid alongside traits, or is Arena traits-only?
5. Should a Fighter accumulate a visible W/L record? It is great for attachment
   and talkability, but it also makes people afraid to field their best Fighter.
