# Crypt Conquest — Research & Planning Handoff

Internal planning doc, not a Skull Paper page (nothing has shipped yet). Written
for handoff to whichever Claude Code session picks up the build — everything
below was pulled and verified in a research pass; nothing is code yet.

**One-line pitch:** a Regicide-inspired solo card game for Skulliance, same
relationship to Regicide that `cryptcrawl.php` (Crypt Crawl) has to Scoundrel —
mechanics-only reimplementation, original code, no fork, no asset reuse.

---

## 1. Naming decision: **Crypt Conquest**

Options considered and why they lost:

| Name | Why not |
|---|---|
| Crypt King | Doesn't alliterate with "Crypt Crawl"; premise-dependent on a "become the king" framing the user later dropped |
| Crypt Crown | Alliterates, carries the "become ruler" payoff, but the payoff framing was dropped |
| Crypt Court | Alliterates, mechanically clever (J/Q/K are literally "court cards" in card game terminology), but doesn't read as a mode-of-play word |
| Crypt Coup | On-theme (a coup targets a monarch) but low recognition, awkward pronunciation |

**Why Crypt Conquest won:** the real pattern in "Crypt Crawl" is that *both*
words are genre vocabulary describing a **mode of play** ("dungeon crawl"),
not a noun naming a character or object. "Conquest" matches that same
category ("world conquest"), where King/Crown/Court all name a *thing* instead.
Matching category > matching narrative payoff. Also alliterates (C-C), same
shape as Crypt Crawl, no collisions found anywhere in the codebase or Skull
Paper for "conquest," "crypt king," or "cryptking."

Word-origin note that came up along the way: **regicide** = Latin *rex/regis*
("king") + *-cide* ("killing") — literally "king-killing," not
"regency-killing" (a regency is unrelated — rule by a stand-in "regent").

---

## 2. Legal clearance — verdict: clear to proceed

Same doctrine that cleared Scoundrel → Crypt Crawl applies here, verified
independently rather than assumed:

- **Idea-expression dichotomy** (US copyright law): game mechanics — turn
  structure, numeric values, what a suit/card does, scoring, win/loss
  conditions — are unprotectable *ideas/methods of operation*. Only the
  specific **expression** is protected: rulebook prose, artwork, named
  characters, flavor text. Upheld directly in the Trivial Pursuit case; this
  is the standard framework courts use for board/card games generally.
- **No enforcement history.** Found no cease-and-desist, DMCA, or takedown
  action from Badgers From Mars (Regicide's publisher) against any fan
  project. A free fan-made digital port has existed on itch.io (by "berru")
  without apparent incident, alongside an official Board Game Arena
  implementation. Contrast this with Nicalis (aggressively DMCA'd fan
  ports of Cave Story) or The Tetris Company (litigious over trade dress,
  see below) — Regicide's ecosystem is permissive in practice.
- **No trademark exposure.** We are not using the name "Regicide" anywhere.
  Same pattern as Crypt Crawl never using "Scoundrel."

**What NOT to copy** (the protected expression layer):
- The rulebook's flavor text verbatim — e.g. *"A sinister corruption has
  spread throughout the four great kingdoms, blackening the hearts of
  once-loved Kings and Queens..."* — write original flavor text instead.
- Sketchgoblin's artwork (obviously; use Crypties art pool per the Crypt
  Crawl pattern in `cryptcrawlGetArtPool()`).
- Minor cosmetic naming like the "Bronze/Silver/Gold Victory" solo-tier
  labels — trivially easy to rename, do it deliberately rather than
  copy-pasting.

**What IS safe to reuse exactly** (functional/structural mechanics, same
bucket as Scoundrel's weapon-degrade rule): all numeric values, turn
structure, suit-power effects, combo rules, hand sizes, immunity rules —
everything in section 3 below.

**One live caveat, unlike Tetris:** falling-block puzzles carry real trade-
dress risk even for original code (*Tetris Holding v. Xio Interactive*,
2012 — Xio lost despite 100% original code, because board proportions/piece
shapes were ruled "arbitrary" choices copied from Tetris, not functional
necessities). Card game rule sets like Scoundrel/Regicide don't carry this
risk — their mechanics are genuinely abstract/functional, no protectable
trade dress has been asserted over them. Noting this only so it doesn't get
generalized to "all game clones are this safe" — they aren't; this specific
genre is.

---

## 3. Full ruleset (extracted verbatim from the official rules PDF, numbers
   quoted exactly — source: regicidegame.com's `RegicideRulesA4.pdf`)

### Components & aim
52-card deck + 2 Jesters. Cooperative; players defeat 12 enemies (Jack,
Queen, King × 4 suits). Win when the last King is defeated. Lose if a player
can't discard enough to satisfy enemy damage, or can't play a card or yield.

### Setup
- **Castle deck:** shuffle the 4 Kings face-down → 4 Queens shuffled on top →
  4 Jacks shuffled on top of those. Top card flipped face-up starts as the
  first enemy (always a Jack first).
- **Tavern deck:** cards 2–10, the 4 Animal Companions, and a number of
  Jesters based on player count, all shuffled together:

  | Players | Jesters in Tavern deck | Max hand size |
  |---|---|---|
  | 1 | 0 (both set aside for solo power, see §Solo) | 8 |
  | 2 | 0 | 7 |
  | 3 | 1 | 6 |
  | 4 | 2 | 5 |

### Turn structure (4 steps)
1. **Play a card or yield.** Card's number = attack value (face cards only
   appear here if recovered into hand, see below — normally you're playing
   2–10s and Animal Companions from the Tavern deck).
2. **Activate the suit power.** Red suits resolve immediately; black suits
   take effect in later steps. Suit powers are mandatory, never skippable.
   - ♥ **Hearts** — Heal from discard: shuffle the discard pile, count out
     cards face-down equal to the attack value played, place under the
     Tavern deck (no peeking), return the rest of the discard pile face-up.
   - ♦ **Diamonds** — Draw cards: draw cards up to the attack value played
     (in multiplayer this rotates clockwise; solo it's all you). Can't draw
     past max hand size; no penalty for an empty Tavern deck.
   - ♣ **Clubs** — Double damage: damage dealt by clubs counts double
     (e.g. 8 of Clubs = 16 damage).
   - ♠ **Spades** — Shield: reduce the current enemy's attack value by the
     attack value played. Cumulative across all spades played against that
     enemy, persists until it's defeated.
3. **Deal damage and check.** Enemy stats:

   | Enemy | Attack | Health |
   |---|---|---|
   | Jack | 10 | 20 |
   | Queen | 15 | 30 |
   | King | 20 | 40 |

   If total damage ≥ health: enemy defeated. If damage dealt exactly equals
   health, the enemy goes **face-down on top of the Tavern deck** instead of
   the discard pile — meaning it can be drawn back into hand later, where it
   counts as an attack card worth its full value (10/15/20) *and* still
   carries its suit power when played or discarded (see §Drawing a defeated
   enemy). This is the game's built-in "perfect play" reward loop.
4. **Suffer damage.** If not defeated, discard cards totaling ≥ the enemy's
   (spade-reduced) attack value. Animal Companions discard as 1, Jester as
   0. Can't cover it → that player dies, everyone loses. Empty hand is fine.

### Yielding
Skip straight to Step 4 instead of playing a card. Can't yield if every
other player yielded on their immediately preceding turn (multiplayer-only
restriction — moot for a solo build).

### Animal Companions
Play alone, or paired with one other card (not the Jester). Count as 1
toward attack total; their own suit power still applies. Paired with a card
of the *same* suit, the suit power only triggers once. Can pair with another
Animal Companion.

### Combos
Instead of one card, play 2, 3, or 4 cards of the *same number*, combined
total ≤ 10 (pairs of 2s/3s/4s/5s, triples of 2s/3s, quadruple 2s). All suit
powers resolve at the combined total. Animal Companions can't join a combo.
Hearts always resolves before Diamonds when both trigger together.

### Enemy immunity
Each enemy is immune to the suit power of cards matching its own suit
(number still counts toward damage). E.g. Spades played against the King of
Spades deal damage but grant **no shield** — see weak spot #1 below.

### The Jester
Attack value 0, always played alone. Cancels the current enemy's suit
immunity for the rest of that engagement (so a Spade played after a Jester
against the Spade King *would* shield, etc. — but note per the rules text,
Clubs played *before* the Jester against a Clubs enemy don't retroactively
count double). Skips Steps 3–4. In multiplayer, the Jester's player picks
who goes next — irrelevant solo.

### Drawing a defeated enemy
J/Q/K recovered into hand (via the exact-damage-kill rule) count as 10/15/20
whether played as an attack or discarded to cover damage, and still trigger
their suit power when played.

### Solo play (the ruleset that actually matters for Crypt Conquest)
- Both Jesters set aside (not shuffled into the Tavern deck).
- Single hand, max size 8. Play turns back to back, no rotation.
- Each Jester can instead be **flipped** to: discard your whole hand and
  refill to 8 (doesn't count as a "draw" for enemy diamond-immunity
  purposes). Usable twice per game (once per Jester). Doesn't cancel enemy
  suit immunity when used this way. Usable at the start of Step 1 (before
  playing) or the start of Step 4 (before taking damage).
- Win tiers: 2 Jesters used = Bronze, 1 used = Silver, 0 used = Gold.
  (Rename these for Crypt Conquest — see §2, don't reuse verbatim.)

### Communication rules
Multiplayer-only (hidden-hand-information restrictions between players) —
not applicable to a solo build, omit entirely.

---

## 4. Design weak-spot pass (solo-scoped)

Same category of analysis that led to Second Wind + diminishing medkits for
Crypt Crawl's Scoundrel base. Two real, citable rough edges in vanilla
Regicide solo play — not yet fixed, just flagged for a house-rule decision
before/during the build:

**Weak spot 1 — King of Spades hard-counters your only defensive tool, at
the worst possible moment.** Per §Enemy immunity, Spades played against the
King of Spades grant zero shield. He is also mechanically guaranteed to be
the *final* fight of the run (Kings are the bottom of the Castle deck, and
within the Kings, whichever is drawn last is drawn last), meaning your hand
and Tavern deck are at their most depleted exactly when your main defensive
lever is switched off. Reviewers call this out as an asymmetric spike rather
than an earned peak — see BGG "Struggling with Solo play" thread and related
discussion.

**Weak spot 2 — no comeback from a genuinely bad shuffle, worse solo than
multiplayer.** With no other players to smooth over a weak individual hand,
a run can become effectively unwinnable purely from Tavern-deck luck
(clumped high-value cards starving combo options, a needed suit clustered
in the wrong place, etc.). BGG solo-play discussion describes this as "a
doomed hand has always spelled doom for the entire run." Vanilla's only
mitigation is the two solo Jesters, which reshuffle your *hand*, not the
underlying deck's composition — so a genuinely bad shuffle stays bad no
matter how you flip them.

Both map onto exactly the categories Crypt Crawl already solved once:
weak spot 2 is Scoundrel's "no recovery from a bad run" problem again
(→ Second Wind precedent); weak spot 1 is "difficulty spike from unfair
design, not earned difficulty" (→ worth its own targeted fix, TBD).

No fixes have been decided yet — this is flagged for design discussion
before implementation, the same way Scoundrel's two weak spots were
identified before Second Wind/diminishing medkits were designed.

---

## 5. Architecture notes for the build

- Same shape as `cryptcrawl.php`: single-player, page/AJAX-driven, no
  real-time loop, no multiplayer session handling needed — Communication
  rules and multiplayer turn-rotation are entirely omittable.
- Table name, route, DB schema, art-pool wiring: unstarted, follow the
  `cryptcrawls` table / `cryptcrawlGetArtPool()` precedent in `db.php` if
  reusing the Crypties art pool (user_id=1, collection_id=8 via
  `CRYPTCRAWL_ART_USER_ID` / `CRYPTCRAWL_ART_COLLECTION_ID`) — decide
  whether Crypt Conquest shares that pool or gets its own.
- Per project convention: update `skullpaper/MAINTENANCE.md` and add a
  Skull Paper page **once this ships**, not before — Crypt Crawl itself
  stayed undocumented in Skull Paper during its own vertical-slice phase.

## Sources
- [Regicide rules PDF](https://www.regicidegame.com/site_files/33132/upload_files/RegicideRulesA4.pdf)
- [regicidegame.com](https://www.regicidegame.com/)
- [Game On! Copyrightability of Board Games — Copyright Alliance](https://copyrightalliance.org/copyrightability-of-board-games/)
- [Tetris Holding, LLC v. Xio Interactive, Inc. — Wikipedia](https://en.wikipedia.org/wiki/Tetris_Holding,_LLC_v._Xio_Interactive,_Inc.)
- [Nicalis DMCA takedowns of Cave Story fan projects — Nintendo Life](https://www.nintendolife.com/news/2020/11/nicalis_is_issuing_dmca_takedown_notices_to_free_versions_of_cave_story)
- [BGG: Struggling with Solo play](https://boardgamegeek.com/thread/3536302/struggling-with-solo-play)
- [BGG: Initial Review of Regicide](https://boardgamegeek.com/thread/2718239/initial-review-of-regicide)
