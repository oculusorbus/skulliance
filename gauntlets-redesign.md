# Gauntlets -- redesign record

**NOT BUILT. This is a design in progress, written down from a brainstorm on
2026-11-27 so the reasoning is not lost.** Where it disagrees with the code,
the code is what ships today and this file is the proposal.

The live game is `gauntlets.php`, `gauntletCalculateWinChance()` /
`gauntletResolveEncounter()` / `gauntletStartRun()` / `gauntletPickHandNFTs()`
/ `gauntletPlanHand()` in `db.php`, and `gauntlets-harness.php`.

---

## The complaint, and what the code says about it

REPORTED: players name Gauntlets their least favourite game on the platform,
because it is luck and they have almost no control over the outcome.

That is not a perception problem. `gauntletResolveEncounter()` computes

    effective_wc = min(100, base_wc + consumable_bonus + weapon_level + armor_level)

and flips once. Three consequences, all of them the complaint:

**EVERYTHING COLLAPSES TO ONE INTEGER.** Matchup, consumables and gear all
add into a single number which drives a single coin flip. There is no play,
only a loadout screen and a die.

**A WEAPON AND A SUIT OF ARMOUR ARE THE SAME ITEM.** Both contribute their
`level` to the same variable. A shotgun and a breastplate are
mathematically indistinguishable, so there is no reason to prefer either.

**THE RUN IS PUNISHING BEFORE ITEMS ARE CONSIDERED.** `GAUNTLET_MAX_WINS` is
3 and a single loss ends the run. Even on a 70% matchup, sweeping is
0.7^3 = 34%. At 50% it is 12.5%. Items move the number by 1-4 points
(`$consumable_bonuses`), which against that structure is noise.

`base_wc` itself is a rock-paper-scissors matchup between effective project
ids -- `$beats = [6=>1, 5=>2, 4=>3, 2=>4, 1=>5, 3=>6]`, giving 70 / 50 / 30.

---

## The shape being proposed

A turn-based fight instead of a coin flip, with the loadout as the decision.

### Why a fight fixes it, specifically

A single flip has maximum variance. A multi-round HP fight averages many
small rolls, so variance collapses and the better loadout actually shows
through. The answer to "I have no control" is not more control per decision,
it is MORE DECISIONS, so that luck averages out across them.

### The squad already exists

This was the confusion worth clearing up. `gauntlets_nfts` is keyed to
`run_id`, not to the encounter -- `gauntletGetHand()` reads
`WHERE grn.run_id = $rid`. The 6 NFTs (`GAUNTLET_HAND_SIZE`) are drawn ONCE
and stay for all three encounters. A card is spent when it appears in an
encounter row. So the model is already a roster with a one-use-each rule;
nothing is re-dealt per round.

### Reuse with persistent damage, not one-use-each

ALLOW AN NFT TO FIGHT MORE THAN ONCE, and let damage stay on it.

The reason is not flavour. Today, 6 cards for 3 fights means you take the
three best matchups and the other three are decoration -- the choice is
trivial. With persistent damage every card stays live and the decision
becomes two-axis:

> a fresh NFT with a bad matchup, or a damaged one with a good matchup?

That is a real decision built entirely from pieces that already exist.

### HP belongs to the NFT; the matchup modifies the fight

The brainstorm had the luck percentage BECOME the health split (70/30 ->
70hp vs 30hp). That collides with persistence -- what is a 70/30 worth when
the fighter is already at half? -- and makes fights decided before they
start. Today the underdog wins 30% of the time; with HP set that way they
would win almost never, which is MORE deterministic, not less.

So they are separated:

  - **HP lives on the NFT and persists across the run.** It is the resource.
  - **The matchup modifies damage dealt and taken** for that encounter.

A bad matchup is then survivable with armour, which is where armour earns a
slot it does not currently have.

Start the matchup worth roughly ONE GOOD ITEM, not the fight -- around a 20%
swing -- and tune from there.

### The turn

Each turn the player chooses one of:

  - **attack bare** -- always available, deliberately weak. New players must
    be able to play at all.
  - **attack with a weapon** -- effect scales with `weapons.level`, with its
    own sound (a shotgun sounds like a shotgun).
  - **put on armour** -- reduces incoming damage for the rest of the fight,
    costing the turn it is equipped.
  - **heal** -- a mission consumable, costing the turn. This only works as a
    decision BECAUSE it costs a turn; free healing is always correct at low
    HP and therefore not a choice.

### THE RISK THAT WOULD SINK IT

A loadout menu where one option dominates is not a decision. If weapons are
ranked by level and usable every turn, the answer is always "use the best
one" and the game is the old game with more clicks.

The fix: **weapons get limited uses per RUN, not per fight.** Spending your
best weapon on encounter one means not having it on encounter three. That is
resource management across the arc, it is the dungeon-crawler feel being
chased, and it uses the three-encounter structure already in place.

### Losing

Today one loss ends the run. With long fights that is brutal -- ten minutes
lost to a bad third encounter.

Instead: **a downed NFT is out for the run, and the run ends when the squad
is wiped.** Three wins still sweeps. You may lose a fight and continue while
you have bodies. This is what makes healing, armour and the chest matter,
and it turns a loss into "I burned my potions too early" rather than "the
dice hated me".

CONSEQUENCE: more runs will complete, so reward rates need retuning. That is
an economy change, not only a game change.

### Everything is contained in the run

OWNER'S DECISION, stated plainly: a downed NFT is dead for THAT RUN only.
There is no recovery timer, no cooldown, no stable, no cross-run NFT state.
If a downed NFT is drawn into a later run it is fresh, and that is fine.

This fits the data model exactly -- `gauntlets_nfts` is already per-run, so
the whole squad model needs ONE new column (`hp`) on a table that exists.

### The setting, and the chest

  - Stage the encounter in the theme of the OPPONENT NFT's project.
    `monstrocity_themes()` already carries a `background` flag per theme,
    keyed by project, so this is mostly wiring rather than new art.
  - TWO CHESTS, doing different jobs -- a run-scoped find between
    encounters, and the payout for a won run. See "Two chests, doing
    different jobs" below.

### The opponent uses items too, as a template

The encounter already stores `opponent_user_id`. Read that player's
inventory to decide WHICH ACTIONS the enemy may take -- never consuming
anything of theirs. A stocked opponent is a harder fight; a dormant account
is an easy one. Free difficulty variety tied to something real.

---

## Open problems

**THE WHALE GAP IS NOW THE WHOLE DIFFICULTY CURVE.** With no cross-run NFT
state, the item cache is the only thing that compounds. A player two months
in does not have better NFTs, they have a deeper bag -- and the bag is now
the game. Two things therefore stop being nice-to-have:

  - the chest is the ONLY on-ramp for someone with nothing;
  - the opponent template probably has to be scaled or capped against the
    player's own inventory, or matchmaking luck alone decides whether a run
    is playable.

Note the direction of the harm: today an under-equipped player loses a coin
flip in two seconds. Under this design they lose a LONG fight they were
never going to win. That is a worse experience unless it is addressed.

**PACING.** A run is three clicks today and would become several minutes.
Some players liked the speed. Fast Forward does NOT solve this -- see below.

**SCOPE.** This is not an improvement to Gauntlets, it is a new game in its
shell. Owner's position: Gauntlets broke ground on the concept and this
reforms it into something more engaging.

---

## Fast Forward keeps its meaning

`GAUNTLET_C_FF` rerolls the NFT you were dealt when you do not like it. That
is unchanged -- it is NOT an auto-resolve.

Under a squad it can do more: let it swap a BEATEN-UP body for a fresh draw
mid-run, which turns a mulligan into a tactical reserve at no new concept
cost.

---

## The draw lockout, measured

`gauntletStartRun()` excludes `gauntletGetUsedNFTIds()` -- every NFT played
in any run where `reward = 0` -- from the next draw.

IT IS HARD, NOT SOFT. `gauntletPickHandNFTs()` applies it as a plain
`AND n.id NOT IN (...)` on BOTH queries and never retries without it. The
harness pins this twice ("already-played NFTs are still excluded", "the
exclusion is applied to BOTH queries").

What it does instead of bypassing is DEGRADE. `gauntletPlanHand()` deals one
card per collection per pass and stops when the pool is empty, so a thin
pool yields a SHORT HAND rather than an error. `gauntletStartRun()` only
returns false when nothing at all is available.

For a holder of exactly 6 NFTs, today:

  - run 1 -- all 6 dealt, 3 played, 3 locked;
  - run 2 -- 3 available, so a THREE-CARD HAND for a three-encounter run.
    No spare choice at all; the matchup decision is gone;
  - run 3 -- nothing available, no run, until the reward cycle flips
    `reward` to 1.

So small holders already get a degraded second run and then a wall. That is
likely part of why the game reads as luck to exactly the players least
equipped to enjoy it.

**THE REDESIGN DEFUSES THIS BY ACCIDENT.** With reuse inside a run, a player
who rotates one or two favourites through three encounters locks one or two
NFTs rather than three, so the pool drains far more slowly.

PROPOSED, and small: give the draw a FLOOR. If the available pool is under
`GAUNTLET_HAND_SIZE`, top the hand up from the locked set rather than
dealing short. Nobody should ever play a three-card run. That is the soft
behaviour the lockout was assumed to have and does not.

---

## Seeing and hearing the hit -- all of it already exists

VERIFIED 2026-11-27, not assumed. Nothing here needs new art or audio.

### The weapons are ONE shared vocabulary

`getAllWeapons()` reads `weapons (id, name, level)`, and BOTH Crypt Crawl
and Gauntlets read that same table -- `cryptcrawlWeaponName()` calls
`getAllWeapons()`, and so does `db.php:13978`. So Crypt Crawl's sound work
is already keyed to the exact weapon names the Gauntlet hands out.

`cryptcrawlWeaponSfxName()` in `cryptcrawl-render.php` is the map, and its
comment notes it is "keyed by the SAME hyphenated slug the weapon icon path
already uses", so icon and sound are derived from one lookup and cannot
point at different weapons:

    melee, tactical-katana, pistol, grenade, sniper-rifle,
    machine-gun, demolition, flamethrower, rocket-launcher, artillery

An unmapped name returns null and simply loses the weapon-specific layer,
keeping the shared `kill` cue -- so a new weapon degrades rather than
errors.

Icons are live and served: `icons/fist.png`, `icons/pistol.png`,
`icons/machine-gun.png`, `icons/artillery.png` all return 200.

### Sounds on hand

`sounds/` carries artillery, death, demolition, equip, **fist**,
flamethrower, flee, grenade, heal, kill, laststand, machinegun, melee,
pistol, rocketlauncher, sniperrifle, tacticalkatana, victory.

Three of those cover actions beyond the weapons:

  - **fist** -- the bare attack, with `icons/fist.png` already used for
    Crypt Crawl's "Fist Fight" / "Bare-handed" button;
  - **heal** -- the heal turn;
  - **equip** -- putting armour on.

### The firing mechanism is liftable too, not just the files

Crypt Crawl drives audio declaratively: `data-sfx="fist kill"` on a button,
read at CLICK time by the submit handler (`cryptcrawl.php:879`), plus a
`data-sfx` queue on `#cc-mood` for sounds the server decides
(`cryptcrawl-render.php:272`). That is a pattern to copy rather than a
helper to import, but it means the "play a list of cues for what just
happened" problem is solved and can be re-solved the same way.

### Hit animations

Two existing sets, and the DHC one is the better base:

  - **DHC Arena** -- `@keyframes hit` and `@keyframes hitBig`
    (`dhcarena.php:636` and `:678`). Both are percentage-based translateX,
    so they scale to any element size, and the two tiers map cleanly onto
    bare attack versus weapon attack.
  - **Monstrocity** -- `.glow-first-attack` / `-second-attack` /
    `-special-attack` / `-last-stand` / `-power-up` / `-recoil`, a coloured
    `box-shadow` per action type, driven by
    `applyAnimation($img, $shiftX, $glowClass, $duration)` which also does
    the sideways recoil shift scaled by damage.

Colour-per-action-type is worth keeping: it is how a player reads WHAT hit
them without text.

CAUTION IF LIFTING MONSTROCITY'S CODE: `.character img` there still carries
`transition: transform 0.1slinear, filter 0.5sease`. The missing spaces make
the WHOLE declaration invalid, so neither property transitions. It was fixed
once and the fix was reverted along with the impact layer, so the typo is
live. Do not copy it.

## The item economy

The turn loop: the two NFTs trade attacks until one falls, with healing,
armour and weapons played as the player judges.

### DURABLE VERSUS SPENT -- the decision that makes or breaks it

Armour degrades as it takes hits; a weapon degrades after a number of
swings. THE QUESTION THAT MATTERS IS WHETHER THAT DESTROYS THE ITEM OR ONLY
WEARS IT OUT FOR THE RUN.

If gear is permanently consumed, players hoard it and never use it. It is
the oldest problem in RPG inventories -- everyone finishes with 99 unused
elixirs, because there is always a worse fight later. That returns the game
to having no decisions in it, which is the thing being fixed.

So: **degrade within the run, restore at the end.** The decision becomes
tactical (when, in this run) rather than economic (whether, ever). It maps
onto the existing tables exactly:

  - `gear` (weapons, armour; quantity-based) -- **durable**. Wears down
    across a run, resets after.
  - `amounts` (consumables) -- **spent**. Genuinely gone.

One rule, easy to explain and easy to balance: GEAR IS TACTICAL,
CONSUMABLES ARE ECONOMIC.

### The heals are already named for it

`GAUNTLET_C_100` / `_75` / `_50` / `_25` are the mission consumables "100%
Success" through "25% Success". As heals they should read as **heal BY that
percentage of max HP**, not restore TO it -- restore-to makes a 25% potion
useless above 25% health, while heal-by gives a clean ladder with 25% as
common filler and 100% as the rare full heal.

### Keep the two reward items as REWARD items

`GAUNTLET_C_DOUBLE` (Double Rewards) and `GAUNTLET_C_RANDOM` (Random
Reward) are the only two items that touch the PAYOUT rather than the
OUTCOME. Converting Double Rewards into double damage for a turn would make
every item a combat button and leave the game with no economic decision at
all.

It also improves under the new design rather than needing changing: with a
multi-turn fight, let the doubler be played MID-FIGHT, once the player can
see it going well. A blind bet becomes a read on the fight in front of
them, at no new concept cost, and the risk stays real because a won-looking
fight can still be lost.

`GAUNTLET_C_RANDOM` should be tied to the setting already chosen: the
encounter is staged in the OPPONENT NFT's project theme, so pay the reward
in **the opponent's project currency**. You fought in their world, you take
their coin. It stops being arbitrary and gives the backdrop a mechanical
reason to exist.

### Fast Forward stays a card swap

Unchanged, and covered above: it rerolls an NFT you do not want, and under
a squad it can also swap out a beaten body mid-run.

### Safe to redefine

`GAUNTLET_C_*` is referenced only in `db.php` and `gauntlets.php`, so what
these items DO inside a gauntlet can change without touching anything else.
Their display NAMES are platform-wide -- they are earned through missions
-- so the names have to keep making sense in both places.

## Music, and the architecture it forces

### The tracks are already the state machine

`audio/tracks/` holds **Crypt Crawl Theme / Frantic / Doom / Death /
Triumph / Reprise**. Audio is git-tracked (unlike images, which deploy by
FTP), so these are already in the repository.

They map onto the run without anything new being written:

  - **Theme** -- an ordinary encounter;
  - **Frantic** -- health low, or the squad thinning;
  - **Doom** -- the last NFT standing;
  - **Death** -- squad wiped;
  - **Triumph** -- a sweep;
  - **Reprise** -- spare; the run-over screen is the obvious home.

### THIS FORCES THE TURN LOOP TO BE AN AJAX FRAGMENT SWAP

Not a polish item. Gauntlets today is POST-and-full-reload -- the `$state`
machine, `$_SESSION['gauntlet_flash']`, the POST handler at the top of
`gauntlets.php`. A turn-based fight has many more actions than today's
three clicks, so on the current architecture the music restarts on EVERY
TURN and the per-action sounds fight the page reload as well.

Crypt Crawl hit this exact wall and solved it. From `cryptcrawl-render.php`:

> every action used to be a full page reload, which tore down and rebuilt
> the `<audio>` element every single time, audibly stuttering the ambient
> music player

The fix, to be copied wholesale:

  1. `ajax/cryptcrawl-action.php` returns a RENDERED FRAGMENT, so the
     client swaps `#cc-game-area` in place with no real navigation;
  2. the `<audio>` element sits OUTSIDE the swapped region --
     `cryptcrawl.php:599` calls that out as "what actually keeps the music
     playing continuously across actions".

And `cryptcrawl-render.php` is shared by BOTH the AJAX path and the no-JS
POST->redirect->GET fallback, from one copy of the markup, so the game
still works without JavaScript and only loses continuous audio. That is the
shape Gauntlets wants.

Note this does not weaken server authority: the fragment endpoint still
resolves everything server-side and returns rendered state, exactly as
Crypt Conquest does one AJAX call per turn.

### Volume balance transfers

`cryptcrawl.php:652-662` records that per-sound levels were already tuned
AGAINST the music -- quieter cues sit under it, with `fist` and `laststand`
called out as the loud ones. That tuning carries over rather than being
redone.

### Open: crossfade

Swapping `src` is a hard cut. Moving Theme -> Frantic smoothly needs two
audio elements and a short gain ramp. Small, but not free.

## Two chests, doing different jobs

REVISED TWICE. The chest was first sketched as an occasional encounter in
place of an enemy, then moved to being the reward for a won run. It ends
up as BOTH, because they solve different problems -- but only if they are
kept distinct.

  - **The mid-run find** is RELIEF. It keeps a run that is going badly
    alive.
  - **The end-of-run chest** is INCOME. It refills what the run consumed.

### The mid-run find

BETWEEN encounters, not instead of one. The original "chest instead of an
enemy" shortens the run to effectively two fights and puts a gap in the
tension; as a find between fights it is pure upside and that objection
goes away.

NO MODAL. `missions-claim-modal.php` is a moment, and a moment that fires
twice in a run stops being one. The find is a quick grab with the `equip`
cue; the full reveal belongs to the payout.

RUN-SCOPED. What you find is usable in THIS run and does not survive it.
That makes the two chests mechanically different rather than merely
differently sized, it reinforces the "everything is contained in the run"
principle, and it settles a question that would otherwise come up later:
find a chest, then get wiped, and you keep nothing. Losing stays a real
loss, and the find still did its job.

WEIGHTED TOWARD PLAYERS WHO ARE STRUGGLING -- raise the chance when the
squad is beaten up or the player is out of consumables. That aims relief
at exactly the people the on-ramp problem is about with no special case
for "new players", and it is invisible, so nobody feels handled. Keep it
subtle: rubber-banding a player can FEEL reads as patronising.

### The end-of-run chest

It is the INCOME half, and it is the only chest whose contents leave the
run:

  - **It closes the economy loop.** Consumables drain during a run and a
    win refills them, so the resource cycle sustains itself instead of
    being a one-way drain.
  - **A win gets a moment**, which a run does not currently have -- and
    keeping the full reveal for this one is what protects that moment from
    the mid-run find.

### WHICH FILE, because the obvious one is wrong

`missions-chest.php` is NOT the performance -- it is a two-state status
icon, closed when there is something to take and open once taken,
deliberately with no idle animation and `aria-hidden`.

`missions-claim-modal.php` owns every moving part: a 240px chest with the
REAL reward icons flying out of its mouth, plus the currency and amount.
That is the thing to reuse.

### SCALE IT TO WINS BANKED, or it locks out the people it is for

Paying only on a 3-win sweep is a closed loop for the under-equipped: no
items, so no win; no win, so no chest; no chest, so no items. That shuts
out exactly the players the chest exists to help.

The fix falls out of a decision already made -- the run ends on SQUAD WIPE
rather than first loss, so partial success is already a thing the system
understands. So the chest scales:

  - 1 win  -- scraps;
  - 2 wins -- a decent cache;
  - 3 wins -- the full chest, with the animation.

A struggling player can usually take one win, so the loop never fully
closes. It also turns the three-win target from a cliff into a gradient: a
two-win run reads as progress rather than failure, which helps everybody
and not only newcomers.

### Claiming it should release the lockout

`gauntlets.reward` is what the draw lockout keys off --
`gauntletGetUsedNFTIds()` returns NFTs played in any run where
`reward = 0`. So make CLAIMING THE CHEST the thing that flips `reward` to
1, and one action closes both loops: you take your cache, and your NFTs go
back into the pool for the next draw.

## Slain opponents drop what they used

OWNER'S FRAMING, and it settles the design question: the drops are there
"to help them through the gauntlet". That makes them SUSTAIN, not income,
which puts them on the same side of the line as the mid-run find --
RUN-SCOPED. One rule still holds: only the end-of-run chest leaves the
run. Project points for a slaying are already paid and are unaffected.

### Why tether them to what the opponent USED

Not for variety. Today a stocked opponent is pure downside -- a harder
fight for the same reward -- which is what makes an unlucky draw feel
like a wasted run. If beating them drops what they swung at you, THE
SCARY DRAW BECOMES THE VALUABLE ONE, and the complaint inverts: the whale
stops being a punishment and becomes the prize.

It also composes with the cap already built into the engine
(`gaunt_cap_kit()`): an opponent is never armed above the player's own
best, so drops pull a player UP toward the next tier rather than handing
a newcomer artillery on their first run.

### Shape

  - Dropped from what the opponent actually PLAYED in that fight, not
    from their inventory -- so the drop is earned against the thing that
    hurt you, and an opponent who never got to swing drops nothing.
  - A chance, not a certainty, or three encounters would flood a run.
  - Usable immediately, gone at the end of the run.
  - What was taken during the run should feed the END CHEST, so a run
    spent beating well-armed opponents pays better. That keeps all three
    income ideas -- find, drop, chest -- inside one loop instead of three
    separate taps.

## Still to settle

  - Damage numbers, HP scale, and what a weapon level is worth.
  - Whether armour gets its own sound set, or reuses `equip` for all of it.
  - How many swings a weapon gets per run, and how fast armour wears.
  - Whether the chest replaces an encounter or is extra (replacing it also
    gives pacing relief).
  - Whether the opponent template is capped, scaled, or raw.
  - Retuned reward rates for a run that ends on squad wipe.
  - Exactly which thresholds move Theme -> Frantic -> Doom (health, squad
    size, or both).
