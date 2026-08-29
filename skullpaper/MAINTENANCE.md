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
| games-cryptcrawl.md *(new)*         | Scoundrel-style delve  | cryptcrawl.php, cryptcrawl-render.php, cryptcrawl-actions.php, ajax/cryptcrawl-action.php, db.php:10451-10805 |
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
  ties for 1st are a known simplification, only one tied user gets credited).
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
- Crypt Crawl is a standalone page with a public marketing landing (cryptcrawl.php, added
  2026-08-29 - "build a public facing marketing page in the same vein as Skull Swap... integrate
  with the homepage," per the user): dropped `include 'header.php'` entirely - own full
  `<!doctype html>` document (title/meta/OG/Twitter/JSON-LD VideoGame+BreadcrumbList schema,
  matching skullswap.php's exact pattern) instead of the shared site nav, same treatment as
  skullswap.php/match3rpg.php. Unlike Skull Swap (no server state, landing always shown, JS
  reveals the game), Crypt Crawl has a persistent multi-session run to respect - the landing only
  renders when `$state === 'no_run'` in `cryptcrawlRenderGameArea()` (a first-ever visit, or
  every run finished with none in progress); an active or just-finished run skips straight to the
  game/result screen, unaffected. Landing content: hero + `/staking/images/cryptcrawl.png`
  screenshot (clickable, `requestSubmit()`s the same `#cc-start-delve-form` the button uses) +
  feature cards + a "dueling" two-row counter-scrolling marquee (`.cc-strip`/`.cc-strip-track`,
  same technique as skullswap.php's icon strip - reversed direction on the second row, duplicated
  track for the seamless -50% loop) covering **every** Crypties NFT actually used as card art -
  built by iterating `CRYPTCRAWL_CARD_ART` against `cryptcrawlGetCardArt($conn)`'s resolved URLs
  (the exact same lookup the game itself uses, so the marquee can never drift out of sync with
  what's really in the deck) + mechanics/tips/FAQ sections + a final CTA. Deliberately breaks out
  of `.cc-inner`'s 720px cap into its own wider `.cc-landing`/`.cc-land-wrap` (1000px) - a hero
  page reads better roomier; `.cc-inner` closes before it and reopens empty right after so the
  function's single final `</div><!-- /cc-inner -->` stays correctly balanced either way (same
  balanced-open/close-per-branch technique the Ken Burns refactor above already established).
  **"Go Back"** (`data-go-back`, one delegated `click` listener on `document` - survives every
  AJAX swap without re-attaching): a full-width row under every state's own bottom buttons
  (landing, active's flee-row, game_over), since this page has zero site nav to fall back on
  otherwise. Prefers real browser history (`history.back()`, only when `document.referrer` is
  same-origin and there's actually history to go back to) over a fixed destination; falls back to
  `dashboard.php` for a logged-in visitor (`.cc-wrap`'s `data-logged-in` attribute, read once) or
  the public homepage for a guest. Homepage integration (homepage.php): a third `.hp-game` card
  alongside Monstrocity/Skull Swap, a third `VideoGame` entry in the JSON-LD `ItemList`, a
  standalone `.hp-shot-card` in the platform screenshots grid (hardcoded full path, not folded
  into the `$hp_shots` loop - that loop's shared `$hp_shot_base` points at `images/screenshots/`,
  and this screenshot lives directly under `images/` instead), and a footer link.
- Crypt Crawl actions are AJAX, not full page reloads (cryptcrawl-render.php, cryptcrawl-actions.php,
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
