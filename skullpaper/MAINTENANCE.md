# Skull Paper - Maintenance Guide & Build Plan

This file is the source of truth for keeping the Skull Paper (`/staking/skullpaper.php`)
accurate. It maps each platform feature to its doc page and the code that defines it,
records verified constants, and tracks what still needs to be written.

**When you change a feature in the code, update the mapped `.md` page in the same change.**

---

## Feature → Doc Page → Code Map

| Doc page (`skullpaper/`)            | Feature                | Primary code |
|-------------------------------------|------------------------|--------------|
| overview.md                         | Mission / artists      | Founding & partner artist lists auto-generated from the projects DB via `{{projects:founding:names}}` / `{{projects:partner:names}}` (names only; no X links/logos in the projects table yet). Narrative prose still manual. |
| staking.md                          | Points, store, craft   | db.php (updateBalances, craft/shatter), skulliance.php |
| staking-membership.md               | Member/Elite/Inner     | skulliance.php:145-212 (role IDs) |
| staking-daily-rewards.md            | Daily streak rewards   | db.php:806-830 (getDailyConsumable, getRewardTiers) |
| staking-points.md                   | All points               | db.php getProjects (founding ids 1-6 / partner ids>7,!=15) - point tables auto-generated via `{{projects:founding}}` / `{{projects:partner}}` tokens in skullpaper.php; no manual edits needed |
| staking-crafting.md *(new)*         | Craft/Shatter/Burn     | db.php:3947-3990 |
| missions.md                         | Idle missions          | missions.php, db.php (getMissions, completeMission) |
| missions-consumable-items.md        | 7 consumables          | db.php:2389-2418, consumables table |
| missions-monthly-rewards.md         | Monthly CARBON LB      | db.php:4465-4591 (100,000/rank) |
| realms.md                           | Realms overview        | realms.php, db.php |
| realms-locations.md                  | 7 locations           | db.php:8400-9360 |
| realms-soldiers.md *(new)*          | Soldiers/gear/crypt    | db.php:8444-8760 |
| realms-raids.md                     | Raid offense/defense   | db.php:6428-7797 |
| realms-factions.md                  | Factions               | db.php:4606, 5770 |
| diamond-skulls.md                   | Supply/yield/claims    | db.php:3750-3751 |
| diamond-skulls-carbon-emissions.md  | Delegation/CARBON      | skulliance.php:830-847, db.php:3806-3841 |
| diamond-skulls-skulliverse.md       | Planet activation      | db.php:4161-4189 |
| games.md                            | Games overview         | header.php Play menu |
| games-monstrocity.md *(new)*        | Match 3 RPG campaign   | monstrocity.php, db.php:5509-5634 |
| games-boss-battles.md *(new)*       | Boss encounters        | ajax/get-bosses.php, db.php:5139-5258 |
| games-skull-swap.md *(new)*         | Match-3 score chase    | skullswap.php, db.php:5019-5136 |
| games-gauntlets.md *(new)*          | NFT roguelike          | gauntlets.php, db.php:9874-10341 |
| games-cryptcrawl.md *(new)*         | Scoundrel-style delve  | cryptcrawlgame.php (marketing), cryptcrawl.php (game), cryptcrawl-render.php, cryptcrawl-actions.php, ajax/cryptcrawl-action.php, db.php:10451-10805 |
| games-cryptconquest.md *(new)*      | Regicide-style solo    | cryptconquestgame.php (marketing), cryptconquest.php (game), cryptconquest-render.php, cryptconquest-actions.php, cryptconquest-engine.php, db.php:11343-11800ish (CRYPT CONQUEST block) |
| games-drop-ship.md                  | External game          | madballs.net (external) |
| games-oculus-lounge.md              | External game          | oculuslounge.vip (external) |
| marketplace-store.md *(new)*        | Free member claims     | store.php |
| marketplace-auctions.md *(new)*     | Bid-based NFT sales     | auctions.php, db.php:9379-9577 |
| marketplace-raffles.md *(new)*      | Ticketed raffles        | raffles.php, db.php:9602-9828 |
| platform-dashboard.md *(new)*       | Staking portfolio       | dashboard.php |
| platform-gallery.md *(new)*         | NFT discovery           | gallery.php |
| platform-collections.md *(new)*     | Policy registry         | collections.php |
| platform-leaderboards.md *(new)*    | All leaderboards        | leaderboards.php, db.php:4194-5637 |
| platform-analytics.md *(new)*       | Personal stats          | analytics.php, ajax/analytics-*.php |
| platform-profile.md *(new)*         | Profile + streak cal    | profile.php |
| platform-wallets.md *(new)*         | Multi-wallet            | wallets.php, db.php:575-611 |
| platform-transactions.md *(new)*    | Ledger                  | transactions.php, db.php:4020-4081 |

---

## Verified Constants (from code, confirmed by grep)

### Daily rewards (db.php:806-830)
- Streak point tiers (RANDOM): day 1→1, 2→3, 3→5, 4→10, 5→15, 6→20, 7→30 (total 84).
- Daily consumable awarded per streak day: 1→Random Reward, 2→25% Success, 3→Fast Forward,
  4→50% Success, 5→75% Success, 6→Double Rewards, 7→100% Success.

### Membership Discord role IDs (skulliance.php:145-212)
- Base 949930195584954378 · Elite 949930360681140274 · Inner Circle 949930529841635348.

### Crafting (db.php:3947-3990)
- Craft: burn equal parts of all 6 core points → DIAMOND (1:1 per point type).
- Shatter: DIAMOND → equal parts of all 6 core points.
- Burn: 100 CARBON = 1 DIAMOND. (NOTE: the "minimum batch of 1,000" claim from the old
  GitBook is NOT enforced in code - code only requires multiples of 100. Harmonized in docs.)

### Realms locations (db.php) - location_id / project_id 1-7, all cap at level 10
- 1 Portal: raids_allowed = portal_level; soldiers per raid scale with it.
- 2 Armory: nightly gear drops (L1 = 1; L2+ = rand(1, min(10, level))).
- 3 Tower: garrison up to 10 trained soldiers; TowerScore = (garrison/10)*10.
- 4 Barracks: trains soldiers; training time = (11 - level) * 24 hours; deployment cap = min(100, barracks_level*10).
- 5 Factory: nightly consumable drops (level = items/day).
- 6 Crypt: resurrects dead soldiers; time = (11 - level) * 24 hours.
- 7 Mine: CARBON = level * 100 per night.
- Upgrade cost = next_level * 100 project points (3x cost if paying with non-core points).
- Raid offense = ceil((Armory + Barracks + Crypt + BarracksScore)/4); defense = ceil((Tower + Factory + Mine + TowerScore)/4).
- NOTE: old GitBook "3%/9% loot" wording is not directly verifiable in code; keep loot
  description qualitative until the exact endRaid loot formula is confirmed.

### Monthly/weekly reward pools (db.php)
- Missions monthly LB: 100,000 CARBON / rank (db.php:4466).
- Realms/Raids monthly LB: 1,000,000 CARBON fair-share (db.php:4606).
- Streaks LB: 10,000 CARBON (db.php:4902).
- Monstrocity monthly LB: 30,000 CLAW + 30,000 CARBON / rank (db.php:5510-5511).
- Skull Swap weekly LB: 25,000 CARBON (db.php:5020).
- Gauntlets weekly LB: 25,000 CARBON (db.php:5264).
- Boss Battles weekly LB: CLAW/CARBON split by damage (db.php:5139-5258).

### Games constants
- Gauntlets (db.php:9877-9888): hand size 6, win at 3 wins (no loss = "sweep"), 100 points/win.
  Consumables: 100/75/50/25% Success = +4/+3/+2/+1% win chance; FF swaps card; Double Rewards 2x; Random Reward redirects points.
  Matchup (db.php:9916-9931): circular chain 6>1, 5>2, 4>3, 2>4, 1>5, 3>6. Strong 70%, weak 30%, neutral/same/Diamond 50%. Partner NFTs wildcard to random core.
- Skull Swap (ajax/save-swap-score.php): 25 matches/game, max score 25,000, min 60s anti-cheat.
- Monstrocity: 28 campaign levels, 35+ NFT themes; character traits health/strength/speed/tactics/size/powerup.
- CLAW is a real point type (Monstrocity/Boss reward), separate from CARBON/DIAMOND.
- Crypt Crawl (db.php:10451-10805): 44-card deck (26 monsters clubs/spades 2-14, 9 weapons
  diamonds 2-10, 9 medkits hearts 2-10), max HP 20. Weapon degrades to "equal or lesser" rank
  after each kill. First medkit per crypt heals full rank; any after that in the same crypt
  heal half (floor, min 1) instead of nothing. Last Stand: first hit that would hit 0 HP per
  delve instead clamps to 1 HP, once per delve, automatic (internal column/var name stays
  second_wind_used - display-only rename, not worth a migration).
- Crypt Crawl per-delve CARBON (db.php `cryptcrawlPayoutCarbon`, accrual in `cryptcrawlPlayCard`):
  every card resolved (any type) adds `10 * rank` to `carbon_earned` on the run row, regardless
  of outcome. Paid out via `updateBalance()` + `logCredit()` (project_id 15 = CARBON) in one lump
  the moment the run actually ends (status guard, so it's a no-op on every other card played
  while still active) -- called from both a natural win/loss in `cryptcrawlPlayCard` and a
  deliberate `cryptcrawlAbandonRun`. Guest runs still accrue `carbon_earned` for the game_over
  screen's display, but the payout itself is gated on a real DB row (`id > 0`) and never fires
  for them. Requires a `carbon_earned` INT column on `cryptcrawls` (see the migration note in
  the commit that added it).
- Crypt Crawl leaderboard (db.php `checkCryptCrawlLeaderboard`/`resetCryptCrawls`, same shape
  as `checkGauntletsLeaderboard`): ranks by wins DESC, best single-run rooms_cleared ("crypt
  depth", 0-15) DESC, losses ASC. Only status won/lost runs count (not in-progress). Weekly
  pool 50,000 CARBON, `round(50000/rank)` per rank same as Gauntlets' formula (rank 1 = 50,000
  CARBON = 500 DIAMOND, rank 2 = 25,000 = 250 DIAMOND, etc.), paid via rewards.php?cryptcrawl=1
  (cron-triggered, same convention as every other weekly leaderboard here). All-time view has
  no reward. Requires a `reward` TINYINT column on `cryptcrawls` (see the migration note in the
  commit that added it) - not yet run on the live table as of this writing. Weekly results post
  to the default/notifications webhook (same as Gauntlets' own weekly summary - no channel
  passed), not the "cryptcrawl" channel.
- Crypt Crawl live updates (db.php `cryptcrawlAnnounceResult`, called from both
  `cryptcrawlPlayCard` on a natural win/loss and `cryptcrawlAbandonRun`): posts to the
  "cryptcrawl" webhooks.php channel (`getCryptCrawlWebhook()`, alongside `getGauntletsWebhook()`
  and the rest - defined in credentials/webhooks_credentials.php, not in this repo) every time
  a real account's delve ends, win or loss, showing crypt depth reached. This is the channel
  for live play; the weekly leaderboard summary above goes elsewhere. Guests and in-progress
  runs never announce. Embed image is the theme art for the room reached (`cryptcrawlRoomThemeFile()`,
  shared with cryptcrawl.php's own active-room backdrop so there's one theme list, not two -
  clamped rather than wrapped at rooms_cleared=15, the value a completed win passes, so a win
  shows the final crypt's art instead of wrapping back to the first room's). Also flags, checked
  against the state including this very run: `cryptcrawlIsNewBestDepth()` (strictly deeper than
  this user's prior best among their other completed runs; ties don't count) and
  `cryptcrawlLeaderboardLeaderUserId()` (checked once for all-time, once for weekly - exact
  ties for 1st are a known simplification, only one tied user gets credited). **Footer shows the
  CARBON earned that delve** (added 2026-08-30, requested directly by the user) - `discordmsg()`
  (`webhooks.php`) gained a 9th, optional `$footer` parameter (`["text" => ..., "icon_url" => ...]`,
  renders as a small icon + line of text at the very bottom of the embed - a slot distinct from
  both `$thumbnail` and `$author`'s own `icon_url`, so it doesn't collide with Crypt Crawl's
  existing use of both for the player's avatar) - every other `discordmsg()` call site is
  unaffected, none pass a 9th argument. `cryptcrawlAnnounceResult()` passes
  `"+" . number_format($run['carbon_earned']) . " CARBON earned"` with `icons/carbon.png` as the
  icon, on both the win and loss embeds - the same figure and icon the player's own result screen
  already shows them.
- Crypt Crawl card art (db.php CRYPTCRAWL_CARD_ART, `cryptcrawlGetCardArt`): each of the 44
  cards is mapped to one specific NFT by exact `nfts.name`, not a shuffled pool - curated
  2026-08-28 from the owner's Crypties - Season 2 holdings (~108 candidates reviewed, 27 of
  them confirmed rare: 8 WTF, 2 Mythic, 17 Legendary - some via pool.pm attributes, the rest
  per the owner's own identification of their collection). Rarity ladder, confirmed by the
  owner as WTF > Mythic > Legendary > ...: all 8 top monster slots (rank 11-14, both suits)
  carry WTF art; the next tier down (rank 6-10 across all four suits - both remaining monster
  ranks, plus the top of the weapon and medkit ranges) carries the 17 Legendary pieces plus
  both Mythic pieces (Spades 6, Diamonds 6). Update CRYPTCRAWL_CARD_ART directly to change any
  card's art.
- Crypt Crawl counts toward platform Activity leaderboards (db.php `checkActivityLeaderboard()`,
  added 2026-08-29 - "Players should be recognized for their attempts within here as well," per
  the user, re: the top-level All-Time/Monthly/Weekly Activity dropdown options, distinct from
  Crypt Crawl's own game-specific leaderboard): a `'crawl'` source counts completed delves (won or
  lost - matches `checkCryptCrawlLeaderboard()`'s own "completed" definition, not every in-progress
  row, so starting-and-abandoning runs for Activity points isn't a thing), weighted 5 alongside
  mission/skullswap/gauntlet (a delve's roughly that same class of single-session attempt; nothing
  more precise than that judgment call). **Requires a migration not yet run on the live table** -
  `cryptcrawls` has no date/timestamp column today (`cryptcrawlGetMostRecentRun()` orders by
  `id DESC` instead, not a date, which is the tell): `ALTER TABLE cryptcrawls ADD COLUMN
  date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER carbon_earned;` - no PHP-side
  change needed to populate it, the DEFAULT covers every existing INSERT
  (`cryptcrawlStartRun()`) automatically. All-time is unaffected either way (never date-filters);
  monthly/weekly will silently show zero crawl activity until this migration actually runs.
  Verified via a dedicated PHP harness mocking all 8 sources' `$conn->query()` calls and checking
  the merged per-user totals/ranking/stats output, not just that the code parses.
- Crypt Conquest (built 2026-08-30, directly off Crypt Crawl's own architecture -- see
  cryptconquest.php's own header comment): Regicide-style solo card game, table `cryptconquests`.
  12 court cards (4 suits x Jack/Queen/King, Jacks first then Queens then Kings, shuffled within
  rank), enemy stats `cryptconquestEnemyStats()` (Jack 10atk/20hp, Queen 15/30, King 20/40).
  Tavern deck: 2-10 of all 4 suits + 4 Animal Companions (always worth 1, can pair with at most
  one other card, never a bigger combo). Suit powers on the non-enemy-suit cards played:
  Clubs double attack, Hearts heal (return cards from discard to tavern), Diamonds draw, Spades
  shield (Hearts resolves before Diamonds when both trigger). 2 Jesters (discard hand + refill,
  once each); win tier `cryptconquestTier()` keyed off jesters_used (0=Flawless, 1=Hard-Fought,
  2=Narrow Conquest). 1 Last Stand (renamed from "Last Rally" per the owner -- Last Stand is
  already a Skulliance-wide term, Monstrocity and Crypt Crawl both use it, and platform
  consistency won out over the original "deliberately a different word" reasoning in
  cryptconquest.md §4b; internal field stays `last_rally_used`, same no-migration
  display-only-rename precedent as Necropolis/Mausoleum/Crypt above -- once per run: a
  whole-hand discard that still doesn't cover the attack survives instead of ending the run;
  the *next* such failure is a real loss).
  CARBON (`cryptconquestApplyCarbon`, same project_id 15 / `updateBalance`+`logCredit` shape as
  Crypt Crawl): every card resolved (played or discarded to cover damage) earns `10 * its value`
  (a Companion's value is 1), paid out in one lump the moment the run ends
  (`cryptconquestPayoutCarbon`, status guard so it's a no-op mid-run) -- guests accrue
  `carbon_earned` for display only, payout gated on a real DB row same as Crypt Crawl.
  Leaderboard (`checkCryptConquestLeaderboard`/`resetCryptConquests`, same shape as
  `checkCryptCrawlLeaderboard`): ranks by wins DESC, best single-run `enemies_defeated` (court
  cards defeated, 0-12) DESC, losses ASC. **Deliberately monthly, not weekly** (explicit user
  instruction) -- 100,000 CARBON pool, `round(100000/rank)` per rank, paid via
  `rewards.php?cryptconquest=1` (needs its own monthly crontab entry -- nothing in this repo
  schedules cron itself, see rewards.php's own comment on that line). Live-play announcements
  (`cryptconquestAnnounceResult`) post to the "cryptconquest" webhooks.php channel
  (`getCryptConquestWebhook()`) -- **deliberately guarded with `function_exists()`** in
  webhooks.php (unlike every other channel case there), since that credential function does not
  exist yet in `credentials/webhooks_credentials.php` (not in this repo, can't be added by
  Claude) -- every other channel case calls its `getXWebhook()` unconditionally, this one no-ops
  to an empty webhook URL instead of fataling until the user adds the real function. Counts
  toward Activity leaderboards (`checkActivityLeaderboard`, source `'conquest'`, weight 5,
  matching Crypt Crawl's own `'crawl'` weight) -- **same migration gap as Crypt Crawl, flagged
  but not run**: `cryptconquests` has no date/timestamp column, so monthly/weekly Activity
  filtering silently shows zero Conquest activity until `ALTER TABLE cryptconquests ADD COLUMN
  date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER reward;` actually runs on the
  live table; all-time is unaffected. Card art (`cryptconquestGetCardArtPools`): auto-assigned
  from the owner's current Crypties holdings each render (NOT hand-curated like
  `CRYPTCRAWL_CARD_ART`) -- court + number cards pull from the Crypties Season 1 collection
  (`CRYPTCONQUEST_S1_COLLECTION_ID`, the primary art wallet's holdings exhausted first, then
  `CRYPTCONQUEST_S1_EXTRA_USER_ID`'s), Animal Companions pull from the same wallet's Season 2
  holdings excluding whatever `CRYPTCRAWL_CARD_ART` already claimed by name, so the two games
  never show identical art. Reuses Crypt Crawl's own audio files/mood-track machinery verbatim
  (`#cq-mood` mirrors `#cc-mood`'s frantic/doom/death/triumph shape, computed from whether the
  current hand can cover the current/pending attack, and whether Last Stand is still available).
- Crypt Crawl ambient player (`#cc-audio-player`/`#cc-audio-el`, markup lives in cryptcrawl.php,
  OUTSIDE `#cc-game-area` - see the AJAX entry below for why that placement matters): two tracks
  committed straight into the repo (`audio/tracks/Crypt Crawl Theme.mp3`,
  `audio/tracks/Crypt Crawl Reprise.mp3` - URL-encoded to `%20` for the spaces when referenced),
  a deliberate one-off exception to this project's usual FTP-deployed-images convention, per the
  user. Rendered unconditionally, in the same spot below the bottom buttons on every state.
  On/off, current track index, `currentTime`, and volume persist in `sessionStorage` (not the
  PHP session) - covers a fresh page load/reload/no-JS visit; continuity *within* a session no
  longer depends on this at all now that actions are AJAX (see below), since the `<audio>`
  element itself just never gets destroyed between actions any more. Defaults on and attempts to
  autoplay; browsers that block autoplay without a prior user gesture just leave it paused until
  the toggle is tapped, or (a one-time capturing listener on `window` for
  `pointerdown`/`keydown`/`touchstart`) the very first real interaction anywhere on the page -
  not bypassable from JS any further than that (synthetic clicks/`dispatchEvent` don't count,
  `AudioContext` is gated identically - hard browser policy, confirmed, not a gap in this code).
  That listener skips entirely (still self-removes, just doesn't call `tryPlay()`) when the
  gesture's `e.target` is inside `#cc-audio-player` - fixed a real bug where clicking the toggle
  button itself as the very first interaction started playback on `pointerdown` (`audio.paused`
  flips to `false` synchronously inside `.play()`, before the promise even settles), so the
  button's own `click` handler then saw `paused` already `false` and immediately paused it right
  back, thinking it was already playing. Every other first-interaction spot (Start Delve, a card,
  anywhere outside the player) still unlocks exactly as before - only the player's own controls,
  which always manage their own play state correctly on a genuine trusted click, are excluded.
  Auto-advances (cycles) to the other track (crossfading - see below) shortly before it actually
  ends, not on `ended` itself. Volume slider (`#cc-audio-volume`, plain range input, 0-100)
  defaults to 50 - the source tracks are mixed loud.
- Crypt Crawl situational music (`#cc-mood`, added 2026-08-29 ahead of the actual audio files
  existing - tracks TBD from the user, wired as if they're already there): `cryptcrawlRenderGameArea()`
  computes `$cc_mood` (`normal`/`frantic`/`doom`/`death`/`triumph`) from the same state it already
  has and emits it as a `data-mood` attribute on a hidden `#cc-mood` marker div, the very first
  thing the function echoes (stable across every state's own div-open/close dance further down).
  `game_over`: `triumph` on a win, `death` on a loss. `active`: for every monster still in the
  room, best-case damage = rank minus equipped weapon's power if the weapon can beat it (current
  gear only, no lookahead into playing the room's own weapon card first - deliberately a simple
  "can't survive this with what I've got right now" check, not a full solver over which 3 of 4
  cards to resolve and in what order) else the full rank; if any monster's best-case damage >=
  current HP, that's an unavoidable Last-Stand-triggering hit either way you play it -
  `frantic` if `second_wind_used` is still 0 (the safety net is about to fire), `doom` if it's
  already 1 (no net left, next hit like that is real). Client-side (cryptcrawl.php's audio-player
  IIFE): `MOOD_TRACKS` is a separate map from the normal-loop `TRACKS` array on purpose - prev/
  next only ever touch `TRACKS`, so a mood track is never reachable by cycling, only by the game
  itself demanding it. `syncMood()` (exposed to the outer `initGameArea()` via a closure variable,
  called after every AJAX swap - see the actions entry above) diffs the new `data-mood` against a
  `currentMood` JS var and only switches when it actually changed, so it never interrupts
  something already playing for no reason. Frantic/Doom loop natively (`audio.loop = true`) since
  they're an ongoing state, not an event; Death/Triumph are one-shot (`ended` falls back to
  resuming the normal loop, not silence). An `error` listener falls back to the normal loop too,
  guarded against retrying forever - covers a mood file that's 404 (not generated/uploaded yet)
  without leaving the player on dead silent audio. Manually pressing prev/next while a mood track
  is playing hands control back to the normal loop immediately (`loadTrack()` always resets
  `currentMood` to `normal`) and stays there until the mood value itself next changes. Escaping
  `frantic`/`doom` back to `normal` without the delve actually ending (healed up, geared up) lands
  specifically on the Reprise (`TRACKS[1]`), not whatever the normal loop's last-saved track
  happened to be - picking back up after a close call should feel like a reprise, not the intro
  theme restarting. A genuine restart (Start Delve / Delve Again) is its own separate, higher-
  priority signal though, not just another `normal` transition: `cryptcrawlHandleAction()` sets a
  one-shot `$_SESSION['cryptcrawl_just_started']` on `start_run`, read (and cleared) by
  `cryptcrawlRenderGameArea()` into `#cc-mood`'s `data-restarted="1"`. `syncMood()` checks that
  *before* the mood-diffing logic and, if set, unconditionally crossfades to the Theme specifically
  (from 0:00, `TRACKS[0]`) regardless of `currentMood` or what the normal loop's last-saved track
  happened to be - covers both the AJAX path and the no-JS full-reload fallback the same way,
  since it's driven by session state read at render time either way.
- Crypt Crawl music crossfading (`crossfadeTo()`, two `<audio>` elements `#cc-audio-el-a`/`-b`,
  added 2026-08-29 - "it's a bit jarring for the music to cut off and switch to another track,
  especially if you're in and out of frantic", per the user): a single `<audio>` element can only
  ever hold one `src`, so overlapping an outgoing and incoming track at once (one ramping down
  while the other ramps up) needs two. `players = [a, b]` + `activeIdx` track which one is
  currently "active" (`active()`/`inactive()` helpers); `crossfadeTo(src, {name, loop, resumeAt})`
  loads `src` into `inactive()` at volume 0, flips `activeIdx` immediately (so `active()` reflects
  the incoming track right away, even mid-fade-in), calls `.play()`, then ramps both players'
  volume in a `requestAnimationFrame` loop over `FADE_MS` (1200) toward/away from `targetVolume`
  (the user's slider setting, re-read live each frame rather than captured once - moving the
  slider mid-fade is reflected immediately) before pausing and resetting the old `outgoing`. The
  first `step()` call **must** go through `requestAnimationFrame` (never invoked directly) - a
  direct call passes no timestamp, making `startTs` (and therefore every volume in that frame)
  `NaN`, which `.volume` rejects outright (`IndexSizeError`); caught via a synthetic-timestamp
  Node harness before shipping, not by manual testing. **Deliberately used only for transitions
  the game forces on its own** (a mood change via `syncMood()`, a forced restart, the normal
  loop's own advance) - manual prev/next still goes through the older hard-cut `loadTrack()`
  unchanged, per the user: a deliberate skip should feel instant, not fade into place. The normal
  Theme/Reprise loop's own advance no longer waits for `ended` to switch tracks - `timeupdate`
  (`maybeAdvanceNearEnd()`) proactively crossfades once less than `FADE_MS` of a non-looping track
  remains, since waiting for `ended` would mean the outgoing side is already silent with nothing
  left to fade from; `ended` is still wired on both players as a hard-cut safety net in case
  `duration` is ever unavailable. Frantic/Doom never reach either path since they loop natively
  (`audio.loop = true`). If audio is currently paused/off, `crossfadeTo()` skips the whole two-
  player dance and just silently repoints `active()` at the new track (nothing audible to fade),
  so turning audio back on later resumes the right thing instead of something stale.
- Crypt Crawl theme-art Ken Burns drift (`#cc-theme-bg`, `.cc-theme-bg::before`, `--kb-*` custom
  properties, `#cc-audio-zoom-toggle`, re-added 2026-08-29 now that actions are AJAX, **then
  restructured the same day** - see below): background image lives on a `::before` pseudo (driven
  by a `--theme-img` custom property, not a plain inline `background-image`, so the pseudo can
  read it) `inset: -5%` of its own container, giving `transform: scale()+translate()` room to
  pan/zoom without ever exposing an edge - `.cc-theme-active` clips that oversized margin via
  `overflow: hidden`. JS (`randomizeKenBurns()`) picks a fresh random scale range (1.00-1.04 ->
  1.08-1.16), pan angle (fully random 0-2π, `dist` 1.5%-3.5% - comfortably inside the 5% buffer
  even at the smallest scale) and duration (20s-34s). `animation-direction: alternate` (CSS,
  `.cc-zoom` class) is what makes each pick loop seamlessly (ping-pongs back to its start) instead
  of snapping. Gated on two things ANDed together via `updateZoomClass()`: the
  `#cc-audio-zoom-toggle` on/off setting (`cc_zoom_enabled` in `sessionStorage`, defaults **on** -
  deliberate, so it's noticed once before anyone turns it off) and whether a track is actually
  playing right now (`!audio.paused`) - "max ambience when media is playing," per the user,
  meaning pausing the music also stops the drift, not just muting it.
  **`#cc-theme-bg` is a PERMANENT element** (cryptcrawl.php markup, right after `.cc-wrap` opens,
  wrapping `#cc-game-area`) - it used to be `cryptcrawlRenderGameArea()` itself that emitted/omitted
  the `.cc-theme-bg` markup per state (open before game_over-lost/active, closed after), which
  meant every single AJAX swap destroyed and recreated it, restarting the Ken Burns animation on
  every card played, not just when the scene actually changed - reported directly by the user.
  Fixed by making it a static element JS reconciles instead: `cryptcrawlRenderGameArea()` computes
  `$cc_theme_active`/`$cc_theme_img` the same way it used to decide whether to open the wrapper,
  and writes them as `data-theme-active`/`data-theme-img` on `#cc-mood` (the same hidden marker
  div `#cc-mood`'s mood/restart signals already use). `applyThemeState()` (cryptcrawl.php, exposed
  to `initGameArea()` via a closure var same as `syncMood()`) reads those after every render,
  toggles `.cc-theme-active` on the permanent element, and - the actual fix - only calls
  `randomizeKenBurns()` when the incoming `data-theme-img` differs from what's already applied
  (`themeBg.dataset.currentImg`), not on every call. Same image (most actions, since
  `cryptcrawlRoomThemeFile()` is keyed off `rooms_cleared`, which only advances on a room refill)
  leaves the running animation completely untouched; a genuinely new image (room refill, or into/
  out of game_over) picks a fresh direction. `sizeTheme()` no longer does double duty applying the
  zoom too - `.cc-theme-active` also gates whether it forces the viewport-filling height at all
  (skipped entirely in "bare" mode - no_run/game_over-won - where `#cc-theme-bg` just sizes
  naturally around `#cc-game-area`'s own content, same as if it weren't there).
  **Regression this same restructuring caused, fixed same day:** `#cc-game-area` (the AJAX swap
  target, sitting between `#cc-theme-bg` and `.cc-inner`) had no CSS of its own. Harmless in "bare"
  mode (plain block flow), but `.cc-theme-active` makes `#cc-theme-bg` a flex container
  (`display:flex; justify-content:center`) to center its content - and a flex item with no
  explicit width shrinks to its own content's size rather than stretching to fill available space,
  so `.cc-inner`'s `max-width:720px` never actually got 720px of container to be 100% of. Real
  desktop/wide-browser views quietly collapsed down toward `.cc-room`'s minimum column width
  instead - reported directly by the user ("full browser view... shrinking down like it's in
  mobile when it isn't"; mobile itself, sized off a *fixed* `.cc-room` column count rather than
  `.cc-inner`'s own width, was never affected). Fixed with one rule: `#cc-game-area { width:
  100%; }`. Confirmed live via Chrome DevTools before and after (injected the rule with
  `javascript_tool`, screenshotted both states) rather than only reasoning about it - a plain
  static-code read of the diff didn't make the flex-item sizing behavior obvious.
- Crypt Crawl suppressible flow pop-ups (`#cc-audio-notif-toggle`, `cryptcrawlFlash()`'s optional
  `$source` param, added 2026-08-29): `cryptcrawlFlash($msg, $type, $source = null)` in
  cryptcrawl-actions.php tags the 3 specific flashes the user wanted a mute for -
  `'flee'` (both the success and the "can't flee twice" messages), `'medkit'` (diminished-heal
  notice), `'laststand'` - leaving everything else (e.g. Abandon Run's "Run abandoned.")
  untagged/`null`, which is never suppressible. `cryptcrawlRenderGameArea()` writes it straight
  through as `data-source` on each `.cc-flash-modal`. Client-side, purely cosmetic/local like the
  zoom toggle: `cc_flow_notifs_enabled` in `sessionStorage` (default **on** - opt out, not opt in),
  checked in `initGameArea()` right after finding `#cc-flash-backdrop` - removes just the tagged
  `.cc-flash-modal` children whose `data-source` is in `SUPPRESSIBLE_FLASH_SOURCES`, then removes
  the whole backdrop too only if that emptied it out entirely (an untagged flash queued alongside
  a suppressed one, if that ever happens, still shows). Doesn't retroactively touch a flash modal
  already on-screen when the button is toggled - only affects what shows up starting next render.
- Crypt Crawl's marketing page and game are two separate files; the game itself is a normal
  nav'd page again after a standalone-page architecture was tried, caused a real mess, and got
  reverted - full history below since it's a useful cautionary case, but the short version: don't
  try again without a much stronger reason. `cryptcrawlgame.php` (added 2026-08-29, "build a public
  facing marketing page in the same vein as Skull Swap... integrate with the homepage," per the
  user) is a standalone page (no site nav, own full `<!doctype html>` document with SEO/OG/Twitter/
  JSON-LD, same treatment as skullswap.php/match3rpg.php) with zero session/login/game-state logic
  of any kind - hero + screenshot, feature cards, a "dueling" two-row counter-scrolling marquee
  covering every Crypties NFT actually used as card art (`CRYPTCRAWL_CARD_ART` against
  `cryptcrawlGetCardArt($conn)`), mechanics/tips/FAQ, a final CTA, and a footer (matching
  skullswap.php's `.ss-footer`) rather than a "Go Back" button - it's the front door of the funnel,
  not a page a visitor needs an escape hatch from. Its `#cc-start-delve-form` is a real
  `<form method="post" action="cryptcrawl.php">` (no fetch/JS interception) that POSTs
  `action=start_run` straight into the actual game. `header.php`'s nav link and `homepage.php`'s
  Crypt Crawl references both point at this file - the marketing funnel is shown to everyone,
  logged in or not.
  **`header.php`'s nav link briefly bypassed the marketing page entirely, 2026-08-30** - per
  explicit user request at the time ("nix the display of the public crypt crawl marketing page when
  logged into the staking platform and clicking the game... take the player straight to the game"),
  since the marketing page was suspected of contributing to that day's session chaos. Reverted the
  same day once the actual root cause was found (see the `user_id` entry below) and confirmed fixed
  - the marketing page itself was never the problem, so there was no longer a reason to skip it for
  logged-in players.

  **`cryptcrawl.php` itself, however, went through a failed detour.** It was originally (and is
  again now) a normal page: `include 'header.php'`, full site nav, no special standalone chrome.
  The marketing-page work changed that - made it standalone too (no header.php, its own SEO tags,
  `noindex,follow` once `cryptcrawlgame.php` became canonical), which meant it needed its own way
  back to the rest of the site with no nav to fall back on: a "Go Back" button
  (`data-go-back`/`ccIsSameSite()`), which then needed its own same-site-referrer fix, then a
  dashboard-vs-history-preference fix, then got replaced with a PHP-baked `IS_LOGGED_IN` constant
  after the DOM-attribute version still misbehaved. Separately, going standalone made a new
  navigation pattern normal for the first time (leaving `cryptcrawl.php` and coming back, e.g. via
  the marketing page's Start Delve) that triggers browser back/forward-cache (bfcache) restores and
  PWA background/foreground suspension - both surfaced as real reports (a loss sometimes skipping
  straight to a live-looking game with frozen progress), each needing its own reload-on-restore
  fix (`pageshow`/`persisted`, then a `visibilitychange` threshold for the PWA case specifically).
  On top of that, a real, separately-confirmed bug (a stale `$_SESSION['cryptcrawl_guest_run']`
  from playing as a guest at some point, never cleared, silently resurfacing and masking a real
  account's actual run) got tangled up with all the standalone-page noise and took a live DB query
  from the user to actually pin down. And a leaderboard link change made along the way, hardcoding
  `https://www.skulliance.io/...`, broke login entirely for any visitor whose session cookie is
  host-only-scoped to the bare `skulliance.io` domain (confirmed: `process-oauth.php` sets the
  session cookie with no `domain` parameter, so `www.skulliance.io` and `skulliance.io` never share
  a session) - which read as "clicking the leaderboard signs me out."

  None of that individually was unreasonable, but the accumulation - "This has NEVER happened
  before and everything was working fine before this evening," "something is disastrous with how
  the session is being handled," "I've had enough" - was the user's own read on it, and the right
  call. **Reverted 2026-08-30, explicit user instruction**, back to the exact pre-marketing-page
  baseline (commit `609e2a10`) for both `cryptcrawl.php` and `cryptcrawl-render.php`: header.php
  restored, no Go Back button, no bfcache/visibility reload hacks, no standalone SEO tags. Exactly
  two changes survive on top of that baseline, both independently-proven bug fixes unrelated to
  page architecture at all, kept deliberately minimal per the user's explicit ask ("do minimal
  changes to the session as possible"): `cryptcrawl.php`'s own SessionCookie restore merges instead
  of replacing (`array_merge((array)$_SESSION, $cookieData)`, matching the platform-wide fix
  below), and `cryptcrawlRenderGameArea()` still purges a stale `cryptcrawl_guest_run` the instant
  a real `user_id` is seen. `cryptcrawlgame.php` itself needed no changes and wasn't touched by the
  revert - it never had any session logic to begin with, so it was never actually the source of any
  of this. The Weekly/View Leaderboard links are back to relative URLs
  (`leaderboards.php?filterby=weekly-cryptcrawl`), which was already the fix for the www/non-www
  cookie issue and survives the revert unchanged. **The root cause of that last one is still
  unfixed at the source** - the login cookie's missing `domain` parameter is why www/non-www don't
  share a session at all; a proper fix would add one in `process-oauth.php` (or wherever else
  establishes the login session) so no future link anywhere has to stay carefully relative to avoid
  this - flagged to the user, not done, since it's a login/session-config change bigger than
  anything Crypt Crawl itself needed.
- **The actual root cause of "logged in but treated as a guest": `process-oauth.php` never set
  `$_SESSION['userData']['user_id']` at all** - found and fixed 2026-08-30, after a long chase
  through session-replace bugs, a `/tmp` session-storage theory, and a host-only-cookie theory, none
  of which were wrong exactly but none of which were *this*. Root-caused from a live session dump
  the user pulled via a temporary `debug-session.php` tool: right after a completely fresh login,
  `$_SESSION` correctly showed `logged_in => 1` and a fully populated `userData` (real
  `discord_id`/`name`/`avatar`/`roles`) - genuinely, correctly logged in - but `userData` had **no
  `user_id` key at all**. Every Crypt Crawl file computes its login state as
  `$user_id = isset($_SESSION['userData']['user_id']) ? intval(...) : 0;` - with the key simply
  absent, that's unconditionally `0`, guest, regardless of session/cookie health. The only function
  that ever backfills `user_id` into the session is `checkUser($conn)` (`db.php`) - a discord_id ->
  users-table lookup - and it's called from `skulliance.php` (the shared login gate every *normal*
  gated page includes), never from `process-oauth.php` (the actual OAuth callback) itself. So
  `user_id` only ever entered the session as a side effect of visiting some other page that happened
  to include `skulliance.php` first - Crypt Crawl (deliberately guest-playable, never includes
  `skulliance.php`) had no such page to piggyback on. This explains the user's own precise
  isolation exactly: "if a device was signed in during the whole of this development, everything is
  fine" (an earlier page visit had already backfilled `user_id` once, and it just persisted for the
  rest of that live session) - "it's the logging out and back in that destroys everything" (every
  fresh login starts a session with no `user_id` again, and if Crypt Crawl - now linked straight
  from the nav, skipping the marketing page, for a logged-in visitor - is the first or only page
  visited afterward, it never gets backfilled at all). Fixed by calling `checkUser($conn)` directly
  in `process-oauth.php`, right after `$_SESSION['userData']` is set and before the redirect to
  `profile.php`, so `user_id` is present from the very first request after login onward, matching
  what every `skulliance.php`-gated page already had. `db.php` is included in `process-oauth.php`
  for the first time to get `checkUser()`/`$conn` - deliberately placed *after* `session_start()`
  (not before), so `db.php`'s own conditional `session_start()` can never fire ahead of and undo
  this file's `session.gc_maxlifetime`/`session_set_cookie_params()` calls just above it. Does not
  explain the Missions/leaderboard "merch error page" reports on their own (`skulliance.php`'s hard
  login gate checks only `$_SESSION['logged_in']`, never `user_id`) - those remain most plausibly
  explained by the session-replace/host-only-cookie fixes already made, though not re-confirmed
  after this fix specifically.
- **Loss-screen-doesn't-show, fixed for real 2026-08-30 by removing the race entirely instead of
  tuning its timing.** Long chase: with the `user_id` bug fixed and confirmed working on desktop
  through a full logout/login cycle, the user reproduced this same symptom again, mobile-only (PWA
  and mobile Safari, even from freshly cleared site data) - CARBON paid out and the Discord
  notification posted correctly, but the loss screen never appeared, jumping straight to what looked
  like a new game. Audited the ambient-audio system specifically per the user's own hypothesis
  ("auto clicks for the music") - the autoplay-unlock listener is one-shot, registered only once at
  initial page load, long gone by the time of a loss reached several rooms in, and every `.play()`
  call is already wrapped in `.catch()` so a blocked mobile autoplay attempt fails silently rather
  than throwing - ruled out. A first attempted fix (swapping the fetch handler's `.catch()` fallback
  from `form.submit()` to `window.location.reload()`, on the theory that a mobile connection hiccup
  was losing the *response* after the server had already finished processing) was reverted the same
  day after the user reported it broke the loss screen on desktop too while testing a "speed
  running" pattern - rapid, deliberate fight-clicking, not double-tapping. That pointed at the real
  mechanism: the smooth AJAX swap held the board inert for a flat 400ms after any action
  specifically so a fast second tap couldn't land on whatever rendered next (e.g. "Delve Again")
  before the result was ever perceived - but a **fixed time delay is a race with a beatable
  deadline, not a guarantee**, and fast enough repeated input can in principle always find the edge
  of any such window. Speed-running was reliably fast enough to find it in practice.
  **The actual fix removes the race instead of widening it**: `cryptcrawl.php`'s fetch handler now
  checks the response HTML for `class="cc-result ` (present only in the game_over win/loss panel,
  verified against real rendered output for all four states - `no_run`/`active`/both game_over
  outcomes - matching exactly the two game-ending ones and neither other) and, if found, calls
  `window.location.reload()` instead of the normal in-place `innerHTML` swap. A real page navigation
  has no timing window to beat at all - the browser cannot process another click until the new page
  has genuinely finished loading, and what loads is always a fresh, direct server read of the true
  (already-committed) state, never a DOM patched in place under a countdown. Ordinary (non-game-
  ending) actions are completely unaffected - the smooth swap and its 400ms guard stay exactly as
  they were, since a race on those is a smaller/different concern and the smooth, uninterrupted-
  music experience is worth keeping there. The one accepted, deliberate cost: ambient music restarts
  for this one specific transition (a full navigation tears down the `<audio>` element the smooth-
  swap design otherwise protects) rather than crossfading through it like every other action -
  reliability over smoothness for the one moment that must never be skippable.
  **That reload fix immediately regressed further, same day - it removed the only guard that used to
  exist for this exact moment instead of strengthening it.** The user reproduced it again and
  captured the actual Network tab evidence: the response for the "final" fight was a completely
  fresh `start_run` result (`HP 20/20`, `Last Stand ready`, `Crypts cleared: 0`) - the server had
  correctly processed a *new game*, not the loss. Root cause: a full page reload has **zero
  cooldown once it finishes loading** - a freshly-loaded page is immediately, fully interactive,
  unlike the old in-place swap which stayed locked (`pointer-events: none`) for 400ms *after*
  rendering specifically to absorb a rapid follow-up tap. The user's actual testing method - fighting
  rapidly and repeatedly until Last Stand triggers, then immediately again - meant a tap landing the
  instant the reloaded game_over page became interactive went straight through to "Delve Again"
  completely unguarded, immediately starting a fresh run. The reload fix solved the *timing-window*
  race only to reintroduce the exact *no-guard-at-all* version of the same problem on the page it
  reloads to.
  **Fixed by extending the same lock to a fresh page load, not just the AJAX swap** - a shared
  `lockGameAreaBriefly()` (400ms `pointer-events: none`, same as before) is now called both after an
  ordinary in-place swap *and*, once, on the very first `initGameArea()` call if the page's own
  initial markup already contains a `.cc-result` element - covering the game-ending reload above,
  a plain manual refresh landing on a result screen, and the no-JS POST->redirect fallback, all with
  one check. Also surfaced but not yet fixed while investigating: the fatal action's own request took
  **2.82 seconds** in the user's own Network tab capture, roughly 7-8x slower than an ordinary
  action's ~350-400ms - almost certainly the live, synchronous Discord "run ended" webhook call
  (`cryptcrawlAnnounceResult()` -> `discordmsg()` -> `curl_exec()`), which only ever fires on a
  win/loss, executed inline inside the same PHP request building the player's own response. Worth
  its own fix (making that notification non-blocking) independent of this bug - a multi-second stall
  before any result appears is a bad experience even with the interaction race now closed.
  **Still not fixed - the "reload" itself turned out to be resubmitting a stale POST, not a tap
  race at all.** The user reproduced it again after a genuinely clean start (fully closed and
  reopened the app, one careful deliberate tap, no rapid play) and captured what actually rendered:
  a completely fresh `start_run` result, byte-for-byte the same shape as the very first mystery
  capture (`HP 20/20`, `Last Stand ready`, `Crypts cleared: 0`). That ruled out every tap-timing
  theory outright - there was no second tap to race. Root cause: `window.location.reload()` reloads
  whatever this exact document's own navigation actually *was*, and this page can genuinely be
  reached via a real POST (Start Delve on `cryptcrawlgame.php`, or an earlier Delve Again) - this
  server does correctly redirect POST->GET (`cryptcrawl.php`'s own `if ($_SERVER['REQUEST_METHOD'] === 'POST')`
  branch always ends in `header('Location: cryptcrawl.php'); exit;`), but some engines - mobile
  WebKit and PWA/standalone contexts especially, matching every device this bug showed up on - can
  still resubmit the *original* POST body on `reload()` instead of doing a clean GET, despite the
  redirect. Fixed by replacing `window.location.reload()` with an explicit
  `window.location.href = 'cryptcrawl.php';` - an unambiguous navigation to a fixed path can never
  be mistaken for a form resubmission, regardless of how the current document was originally
  reached, sidestepping the whole ambiguity instead of relying on "reload" meaning the same thing
  everywhere.
  **Rebuilt from scratch 2026-08-30, same day, on the user's own suggested architecture, after the
  explicit-navigation fix was confirmed working and then the user asked directly why this wasn't
  just a local DOM swap in the first place.** Both attempts before this one (the in-place swap with
  a timed lock, then the forced navigation) were still fundamentally "wait for some network- or
  timing-dependent step to resolve correctly" - a navigation in particular is *reliable* (nothing
  can race it) but not *simple*, and turned out to have its own real, unrelated failure mode. The
  user's proposal removes the dependency entirely: keep a permanent, hidden `#cc-result-overlay` as
  a sibling of `#cc-game-area` (declared once in `cryptcrawl.php`'s markup, alongside the other
  permanent siblings - `#cc-theme-bg`, the `<audio>` elements - never touched by an ordinary
  in-place swap). On a game-ending response (same `class="cc-result '` detection as the navigation
  version used), the fetch handler drops that HTML into the overlay (a synchronous, always-succeeds
  DOM write - the server already computed everything, including CARBON earned, in the exact same
  single response that already told us the game ended, so no second request is needed for the
  dynamic details either) and reveals it with a plain `style.display` flip - not a network round
  trip, not a navigation, nothing with a timing window at all. "Delve Again"/"Weekly Leaderboard"
  inside the overlay are deliberately *outside* `#cc-game-area`'s own DOM subtree, so the delegated
  AJAX submit listener (scoped to `gameArea.contains(form)`) never intercepts them at all - clicking
  either is a perfectly ordinary link/POST, the same kind of real navigation Start Delve itself
  already is, which is completely fine for "start a fresh game" (there's no music-continuity or
  race concern for that transition the way there is for "did you even see you died"). Also
  incidentally fixes the music-restart cost the navigation version accepted on purpose - since
  there's no real navigation anymore, the `<audio>` elements are never torn down, so `syncMood()`
  crossfades into Death/Triumph exactly like every other mood change instead of hard-restarting.
  `initGameArea()` (called on the overlay exactly like it's called after any other swap) works
  unmodified here since none of its internal queries are scoped to `#cc-game-area` specifically -
  they're all plain `document.` lookups, so they find the right elements regardless of which
  container the fresh content actually lives in. The existing lock-on-fresh-page-load check (a few
  entries above) is still correct and still needed for a narrower case this doesn't cover: a genuine
  fresh page load/refresh/no-JS-fallback landing *directly* on a game_over state renders straight
  into `#cc-game-area` (PHP always renders there on a full page load, never into the overlay, which
  is a purely client-side JS construct for the AJAX path specifically) - so "Delve Again" *is*
  inside `#cc-game-area` and *is* subject to AJAX interception in that specific scenario, same as
  before.
  **Bug found immediately after this landed, same day: Doom kept playing instead of Death on a
  loss.** Root cause: revealing the overlay only ever *hid* `#cc-game-area` (`style.display = 'none'`),
  never cleared its contents - so the stale `#cc-mood` from the in-delve room (e.g.
  `data-mood="doom"`, from the lethal-threat-with-Last-Stand-spent state right before the fatal
  blow) was still sitting in the DOM the whole time, just invisible, alongside the fresh
  `#cc-mood` (`data-mood="death"`) the new response HTML dropped into the overlay. Two elements
  sharing an ID isn't valid HTML, and `document.getElementById('cc-mood')` - used by both
  `syncMood()` and `applyThemeState()` - returns whichever comes first in document order, which was
  the stale one in `#cc-game-area` (it sits before `#cc-result-overlay` in the markup), not the
  correct one in the overlay. Same latent risk existed for the themed backdrop and anything else ID
  based, not just the mood track - not confirmed as also visibly wrong, but the same fix covers it
  either way. Fixed by clearing `gameArea.innerHTML = ''` immediately before hiding it, not just
  setting `display: none` - once there's nothing left inside it, there's no possibility of a
  duplicate-ID collision with whatever the overlay now holds, for `#cc-mood` or anything else.
- **Platform-wide session-restore hazard, all `SessionCookie` restores now merge instead of
  replace** - fixed 2026-08-29, same day, after the user reported the *identical* symptom
  (bounced to an error/404 page, staking session apparently killed) on `missions.php` - a page with
  zero connection to Crypt Crawl or any commit from this whole saga. That ruled out the marketing
  split as the cause of this specific report and pointed at shared platform code instead: every
  gated staking page includes `skulliance.php`, whose login-restore branch
  (`if(!isset($_SESSION['logged_in']))`) used to do `$_SESSION = $cookie;` - an outright
  *replacement* of the entire session, and with **no validation** that `json_decode($_COOKIE['SessionCookie'], true)`
  actually produced an array first. A malformed/stale/corrupted `SessionCookie` value would silently
  null out the *entire* session (`$_SESSION = null`), not just fail to restore login - any other page
  that had already written other session state this request would lose it too, and `extract($_SESSION['userData'])`
  right after would operate on `null`. Fixed with an `is_array()` guard before touching `$_SESSION`
  at all, and `array_merge($_SESSION, $cookie)` instead of a raw assignment, so a restore only adds
  the cookie's own keys on top of whatever's already there rather than wiping everything else.
  The exact same unguarded/replacing pattern, copy-pasted across the codebase's other independent
  `SessionCookie` restores, got the same fix for consistency: `cryptcrawl.php`,
  `ajax/cryptcrawl-action.php`, `skullswap.php`, `match3rpg.php`, `monstrocity.php`,
  `monstrocity-test.php`, `skullpaper.php`, `wallet-ajax.php`, `ajax/get-nft-assets.php`,
  `ajax/get-monstrocity-assets.php` - all of these already had the `is_array()` guard (only
  `skulliance.php` was missing it), but still did a full replace, which is real for any of them
  since Crypt Crawl's own `cryptcrawl_flash`/`cryptcrawl_guest_run` session keys (or any other
  page's own session state) could be silently wiped by a restore on a *different* page entirely,
  same browser session. The user's initial ask, given how far this had spread ("something is
  disastrous with how the session is being handled... can we just revert all the way back"), was
  to revert Crypt Crawl's marketing split back to before it existed - talked through instead once
  `header.php`'s only touch in that whole split (one line, the nav link's `href`) couldn't explain
  an unrelated page breaking the same way: fixing the actual shared root cause directly, without a
  revert, was the path taken.
  ajax/cryptcrawl-action.php, added 2026-08-29): every action (start_run/play_card/flee/abandon)
  used to be a real `<form method="post">` submit -> full page navigation, which tore down and
  rebuilt the `<audio>` element above on every single click, audibly stuttering the ambient
  player - confirmed the actual cause after ruling out a competing theory (a CSS zoom effect on
  the theme art, added and then fully reverted first) via direct evidence: the page's own
  `header('Location: cryptcrawl.php'); exit;` pattern on every POST. Fixed by splitting what was
  one monolithic render block in cryptcrawl.php into `cryptcrawlRenderGameArea($conn, $user_id)`
  (cryptcrawl-render.php - echoes the `#cc-game-area` fragment: flash modal + whichever of
  no_run/game_over/active applies; computes `$active_run`/`$recent_run`/`$state`/`$flashes`
  itself now, not the caller) and `cryptcrawlHandleAction($conn, $user_id, $post)`
  (cryptcrawl-actions.php - the actual action logic + `cryptcrawlFlash()`, unchanged from before,
  just extracted). Both cryptcrawl.php's own POST branch (still a real redirect - the no-JS/
  fetch-failure fallback) and ajax/cryptcrawl-action.php (the JS path: handles the action, then
  calls the render function directly in the *same* request and returns just that HTML as the
  response body - no redirect) call these same two functions, so the logic itself lives in
  exactly one place either way. Client-side, cryptcrawl.php delegates a `submit` listener on
  `document` (checks `e.defaultPrevented` first, so Abandon Run's own `confirm()` still works
  exactly as before) that `fetch()`s the AJAX endpoint and swaps `#cc-game-area`'s `innerHTML`
  with the response, then re-runs `initGameArea()` (flash/instructions-modal wiring, HP bar
  reveal, theme sizing, card tilt/spin - everything that touches elements the swap just
  recreated) - falls back to a real `form.submit()` if the fetch itself fails. The `#cc-audio-player`
  markup being a sibling of `#cc-game-area`, never inside it, is what actually keeps the `<audio>`
  element continuously alive across actions now - the whole reason this refactor exists.
  `#cc-game-area` stays inert (`pointer-events: none` + dimmed) for 400ms after a swap completes,
  not just for the fetch's own duration - a fast response (very plausible, local/small) otherwise
  leaves a window where a rapid second tap lands on whatever the swap just rendered in that same
  screen position. Reported failure mode this fixed: a lethal hit's response (the loss screen)
  rendering and getting immediately overtaken by an instinctive second tap on the fatal-attack
  button's now-occupied spot, which had become "Delve Again" - starting a new run before the
  player ever saw they'd died. 400ms comfortably covers a double-tap gesture (~300ms) without
  reading as a delay on one deliberate tap.

---

## Build Status

- [x] Phase 1: 17 GitBook pages migrated (faithful copy).
- [x] Phase 2: Fix inaccuracies (CARBON burn ratio harmonized; daily consumable mapping added).
- [x] Phase 3: Expand Realms (realms-locations covers all 7; realms-soldiers added; raids math added).
- [x] Phase 4: Add 4 game pages (Monstrocity, Boss Battles, Skull Swap, Gauntlets) + games overview.
- [x] Phase 5: Add Marketplace section (Store, Auctions, Raffles; Merch page removed 2026-06-06 - never launched).
- [x] Phase 6: Add Platform section (Dashboard, Gallery, Collections, Leaderboards, Analytics, Profile, Wallets, Transactions).
- [x] Phase 7: nav array updated; CLAUDE.md directive + pre-commit reminder hook added.

Total: 38 doc pages across 8 sections.
