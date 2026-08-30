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

## 4b. Design decisions (locked 2026-08-30)

Both weak spots got a "one-time guaranteed save" fix — and on inspection
they're the **same mechanic**, not two: both failure modes cash out as "the
player is about to lose despite playing well," and both are caused by the
Step 4 discard-to-cover check specifically (the King of Spades spike *is* a
Step 4 failure — zero shield means the attack value stays full right when
hand/deck are thinnest; a doomed shuffle's actual death moment is also
always a Step 4 failure, whatever caused it). One mechanic covers both
instead of shipping two overlapping safety nets that would confuse a player
about which one just saved them.

**Last Rally** (Crypt Crawl's precedent is "Last Stand" — deliberately a
different word, not a reused name, same as the tier-name guidance in §2):
the first time a Step 4 discard can't fully cover the current enemy's
(shield-reduced) attack value, Last Rally fires automatically — discard
whatever's in hand toward it, forgive the shortfall, survive with hand and
deck otherwise untouched. No player choice involved (mirrors Last Stand
being automatic, not opt-in). Once per run. Independent of suit immunity —
it doesn't grant a shield or cancel immunity, it just forgives one lethal
shortfall outright, so it still saves a King of Spades finale even though
Spades are immune there.

**Solo win tiers, renamed** (per §2's "don't copy-paste Bronze/Silver/Gold"
guidance) — mapped from `jesters_used` (0/1/2), Last Rally firing doesn't
affect the tier:
- 0 Jesters flipped → **Flawless Conquest**
- 1 Jester flipped → **Hard-Fought Conquest**
- 2 Jesters flipped → **Narrow Conquest**

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

## 6. Build progress (started 2026-08-30)

**Done:** `cryptconquest-engine.php` — the full solo rules engine (turn
structure, all 4 suit powers with correct Hearts-before-Diamonds ordering,
combos, Animal Companion pairing, enemy suit immunity, exact-damage
recovery onto the tavern deck, Jester flip charges, Last Rally, win/loss,
tier naming). Deliberately DB/session-free (see file header) so it's
testable standalone — 50+ assertions covering every mechanic plus a
200-game fuzz sweep, all passing, no committed test harness yet (lives in
the builder's scratchpad pending a decision on where Crypt Conquest's
tests should live long-term, same open question Crypt Crawl's own
scratchpad-only tests have).

**Found while fuzz-testing, not in the original research pass** — a third
edge case, engine-level rather than a design weak spot: a solo player who
burns through their entire hand *and* both Jester charges while the
current enemy is heavily shielded can reach a position where nothing can
ever be played, drawn, healed, or shielded again (Diamonds' draw power
requires playing a card to trigger, and there are none left) -- yielding
forever at 0 damage taken, never dying, never progressing. Raw Regicide
doesn't need to handle this (a tabletop player just concedes); a digital
run needs to resolve it on its own. `cryptconquestYield()` now detects it
(empty hand + `jesters_used >= 2`) and ends the run in a loss immediately
rather than hanging. Confirmed via the fuzz harness both before the fix
(games pegged at the 500-turn cap, hand permanently empty, `pending_attack`
permanently 0) and after (zero stuck games across 200 runs).

**Also done (2026-08-30, same day):** the full vertical slice is wired up
and playable end-to-end.

- `db.php` — new CRYPT CONQUEST block (persistence layer wrapping the
  engine: `cryptconquestStartRun`/`GetActiveRun`/`GetMostRecentRun`/
  `SaveRun`/`AbandonRun`, the `cryptconquestDoPlay`/`DoYield`/`DoSuffer`/
  `DoFlipJester` action wrappers, and the CARBON economy --
  `cryptconquestApplyCarbon`/`PayoutCarbon`, 10x a card's value for every
  card actually resolved -- played to attack, or discarded to cover
  damage -- same formula and spirit as Crypt Crawl's own `carbon_earned`).
- `cryptconquest-actions.php` / `cryptconquest-render.php` -- same split
  as Crypt Crawl's own two files. Render covers all three states (no_run
  rules prompt, active board with a checkbox-selectable hand for
  combos/companion pairings, game_over result panel with tier name) plus a
  dedicated `suffer`-phase view (incoming damage banner, discard-to-cover
  selection) that Crypt Crawl's turn structure never needed.
- `cryptconquest.php` / `ajax/cryptconquest-action.php` -- the page and
  AJAX endpoint. Ported Crypt Crawl's hidden-`#cq-result-overlay` pattern
  directly (including the duplicate-ID lesson: `gameArea.innerHTML = ''`
  before hiding it), since that's exactly the failure mode this build
  wants to avoid repeating. No ambient audio or Ken Burns backdrop this
  round -- no audio/art assets exist for this game yet, and neither was
  part of getting the core loop working.
- **Not yet run anywhere:** the `cryptconquests` table itself -- no
  migrations tooling exists on this project (same as every other schema
  change here), so this is SQL for the site owner to run by hand:

  ```sql
  CREATE TABLE cryptconquests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'active',
    phase VARCHAR(10) NOT NULL DEFAULT 'play',
    pending_attack INT NOT NULL DEFAULT 0,
    castle_deck MEDIUMTEXT NOT NULL,
    current_enemy MEDIUMTEXT NULL,
    tavern_deck MEDIUMTEXT NOT NULL,
    hand MEDIUMTEXT NOT NULL,
    discard MEDIUMTEXT NOT NULL,
    jesters_used TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_rally_used TINYINT UNSIGNED NOT NULL DEFAULT 0,
    enemies_defeated TINYINT UNSIGNED NOT NULL DEFAULT 0,
    carbon_earned INT UNSIGNED NOT NULL DEFAULT 0,
    reward TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_status (user_id, status),
    INDEX idx_user (user_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ```

  (`reward` mirrors `cryptcrawls.reward`'s shape for future weekly-payout
  wiring -- nothing in the current game logic sets it yet.)

**Tested:** the engine's own 50+ assertions/fuzz sweep (see above) plus a
new full-stack guest-mode test (start/play/yield/suffer/flip_jester/
abandon, every render state, div-balance) run 25x against reshuffled
hands with zero failures. Both harnesses live in the builder's scratchpad,
not committed. NOT run against a real `cryptconquests` table or through
an actual browser yet -- no local MySQL was available to test the
logged-in DB path end-to-end, only guest/session-backed play, and no
visual/manual QA pass has happened.

**Also done (same day):** real Crypties art on the 12 court cards
(Jacks/Queens/Kings) -- both the current-enemy badge and any recovered
court card in hand. `cryptconquestGetCardArt()` (db.php) auto-assigns from
whatever Crypties the same owner (`CRYPTCRAWL_ART_USER_ID`) currently
holds, excluding names Crypt Crawl's own `CRYPTCRAWL_CARD_ART` already
claimed, sorted by name for a stable assignment. This is NOT the
hand-curated-by-rarity pass Crypt Crawl's own art got (a full review
session picking WTF/Mythic/Legendary pieces one by one) -- just enough to
get real art on screen quickly, per the owner's own framing ("get my head
into the feel of the game"). Ordinary number cards and Companions stay
plain badges, same "curated art reserved for enemies" call Crypt Crawl
made for its own monster cards. A rarity-aware curation pass can follow
the same way Crypt Crawl's did, if/when there's appetite for that review
session. New test coverage: `cc_conquest_art_test.php` (mocked $conn,
covers key ordering + empty/undersized/overfull pool cases) and
`cc_conquest_art_render_test.php` (full render pass, King of Spades enemy
+ a recovered Jack of Hearts in hand) -- both scratchpad, not committed.

**Also done (same day):** player cards (numbers + Companions) now get real
art too, deliberately from a DIFFERENT Crypties collection than court
cards -- Season 1 (`collection_id = 7`), the owner's own call, so hand and
enemies read as two distinct aesthetics rather than one pool. New
`cryptconquestGetPlayerCardArt()` (db.php), and the original court-card
function is renamed `cryptconquestGetEnemyCardArt()` with an explicit
`collections.id != 7` exclusion so the two pools can't cross-contaminate.
A recovered court card in hand still reads from the ENEMY pool, not the
player pool -- it's the same enemy, just recovered, so it keeps the same
art it had while you were fighting it. Every card (enemy badge and every
hand card) now uses one unified template: art-or-plain-black + top-left/
bottom-right corner index, same as Crypt Crawl's own `.cc-card-corner`
tl/br -- no more special-cased "no art" layout with a big centered badge.
Hand grid is a fixed 4 columns (was auto-fill, which split an 8-card hand
6-then-2 on desktop).

**Still not started:** ambient audio, Discord announce, leaderboard
wiring, nav link, and any Skull Paper page (deliberately deferred until
this ships, per project convention). Not linked from anywhere yet -- reachable only at
`/staking/cryptconquest.php` directly.

## Sources
- [Regicide rules PDF](https://www.regicidegame.com/site_files/33132/upload_files/RegicideRulesA4.pdf)
- [regicidegame.com](https://www.regicidegame.com/)
- [Game On! Copyrightability of Board Games — Copyright Alliance](https://copyrightalliance.org/copyrightability-of-board-games/)
- [Tetris Holding, LLC v. Xio Interactive, Inc. — Wikipedia](https://en.wikipedia.org/wiki/Tetris_Holding,_LLC_v._Xio_Interactive,_Inc.)
- [Nicalis DMCA takedowns of Cave Story fan projects — Nintendo Life](https://www.nintendolife.com/news/2020/11/nicalis_is_issuing_dmca_takedown_notices_to_free_versions_of_cave_story)
- [BGG: Struggling with Solo play](https://boardgamegeek.com/thread/3536302/struggling-with-solo-play)
- [BGG: Initial Review of Regicide](https://boardgamegeek.com/thread/2718239/initial-review-of-regicide)
