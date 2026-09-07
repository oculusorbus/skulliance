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
| games-cryptcrawl.md *(new)*         | Scoundrel-style crawl  | cryptcrawlgame.php (marketing), cryptcrawl.php (game), cryptcrawl-render.php, cryptcrawl-actions.php, ajax/cryptcrawl-action.php, db.php:10451-10805 |
| games-cryptconquest.md *(new)*      | Regicide-style solo    | cryptconquestgame.php (marketing), cryptconquest.php (game), cryptconquest-render.php, cryptconquest-actions.php, cryptconquest-engine.php, db.php:11343-11800ish (CRYPT CONQUEST block) |
| games-skullracer.md *(new)*         | Pseudo-3D racer        | skullracer.php (nav wrapper, inlines racing/index.html's style+body server-side -- no iframe), racing/index.html (game, client-side, also works visited standalone), ajax/skullracer-finalize.php, db.php SKULL RACER block (end of file) |
| games-drop-ship.md                  | NFT battler, now in-platform | dropship/ (migrated from madballs.net; requires Skulliance login) |
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
- Skull Racer weekly LB: 50,000 CARBON (db.php SKULL RACER block, end of file).

### Games constants
- Gauntlets (db.php:9877-9888): hand size 6, win at 3 wins (no loss = "sweep"), 100 points/win.
  Consumables: 100/75/50/25% Success = +4/+3/+2/+1% win chance; FF swaps card; Double Rewards 2x; Random Reward redirects points.
  Matchup (db.php:9916-9931): circular chain 6>1, 5>2, 4>3, 2>4, 1>5, 3>6. Strong 70%, weak 30%, neutral/same/Diamond 50%. Partner NFTs wildcard to random core.
- Skull Swap (ajax/save-swap-score.php): 25 matches/game, max score 25,000, min 60s anti-cheat.
- Monstrocity: 28 campaign levels, 35+ NFT themes; character traits health/strength/speed/tactics/size/powerup.
- CLAW is a real point type (Monstrocity/Boss reward), separate from CARBON/DIAMOND.
- Skull Racer (db.php SKULL RACER block, racing/index.html): 3 laps/race, 1,000 CARBON flat
  per finished race (SKULLRACER_CARBON_PER_RACE) on top of the weekly LB pool above. Leaderboard
  ranks by each user's own best (lowest) total_time, not wins -- opposite direction from every
  other game's leaderboard here. Server-side sanity floor (SKULLRACER_MIN_TOTAL_TIME=300s,
  SKULLRACER_MIN_LAP_TIME=100s) rejects an obviously fabricated submission without re-simulating
  the run; derived from racing/index.html's own trackLength/maxSpeed constants and must be
  updated by hand if those ever change (same manual-sync caveat as common.js's SPRITES object,
  see racing/common.js's own comment on that one). "skullracer" Discord channel not yet
  configured in credentials/webhooks_credentials.php -- see webhooks.php's function_exists guard.
  Client-side only (racing/index.html): crest jump is a screen-space sprite offset that never
  touches steering/speed, but DOES suppress the car-collision loop while `jumping` is true (added
  so a well-timed jump can save a boost run from a car in your lane) -- off-road sprite collision
  is untouched by it, only traffic cars. Boost pads sit on the 4 longest genuine
  straightaways (real curve===0 runs, excluding the start/finish straight), random lane each
  race, BOOST_SPEED=18000 (displays as exactly "180" via the existing speed HUD formula) held
  until that straightaway ends. BOOST_SPEED deliberately exceeds maxSpeed's own "at most one
  segment per frame" collision-detection invariant -- accepted as a rare/minor trade-off rather
  than restructuring the movement loop, see that block's own comment in racing/index.html.
  Jump ramps (resetJumpRamps(), runs after resetBoostPads() since it reads pad.padStart/
  straightEndIndex): one per boost pad, same lane, placed ~40% of the way down the remaining
  straight. Triggers the identical jump state machine as a hill crest (playerSegment.jumpRamp
  check in update()), gated on speedPercent >= JUMP_MIN_SPEED_FRAC but deliberately NOT on
  jumpCooldownTimer the way the natural crest branch is -- that cooldown used to be shared across
  both, and one of the 4 ramps sits right after addBumps2()'s run of 8 natural crests on the same
  boosted straight, so landing off one of those bumps could leave the cooldown still counting down
  by the time you reached the ramp and block its supposedly-guaranteed trigger outright. Distance
  to the ramp from the last bump is fixed, not time -- covering it FASTER (boosted) left LESS real
  time for that cooldown to expire, not more, so it reproduced as "never triggers boosted, works
  unboosted" rather than intermittently. Safe to bypass: JUMP_MIN_SPEED_FRAC alone requires enough
  speed that JUMP_DURATION's airtime always covers well past RAMP_LENGTH*segmentLength, so there's
  no realistic way to still be on the ramp's own segments by the time you land and could re-fire.
  Drafting (DRAFT_* constants/draftTimer/draftActive in racing/index.html): sustained lane-overlap
  with a car across a DRAFT_WINDOW_SEGMENTS-segment lookahead for DRAFT_BUILD_TIME seconds adds
  DRAFT_SPEED_BONUS to the effective top-speed cap (ignored while boostActive, which already
  exceeds it); breaks instantly on losing the overlap or on any crash.
  Brake lights (Render.player()'s `braking` param in racing/common.js, passed `keySlower` from
  racing/index.html's own render() call): swaps to a _BRAKE-suffixed sprite for whichever angle/hill
  variant would otherwise be drawn (PLAYER_LEFT -> PLAYER_LEFT_BRAKE, etc.) -- looked up by name
  string (`if (braking) name += '_BRAKE'`) rather than a parallel if/else chain, so it's one flag
  applied after the existing steer/updown branch picks the base name, not a second copy of it. The
  6 new _BRAKE sprites (images/sprites/player_*_brake.png) are pixel-identical to their normal
  counterparts except the two tail-light housings recolored brighter (found via connected-component
  detection on the exact 3 tail-light red/orange tones, NOT a blanket color swap -- the skull grille's
  eye-glow highlight and a roof-beacon dot happen to reuse one of those same 3 tones, and a blanket
  swap brightened those too before this was caught). Solid on/off with the brake key, not a timed
  flash/strobe. This is the first sprite ADDED to images/sprites/ since the original art replacement
  (see SPRITE-REPLACEMENT-BRIEF.md) rather than one of the existing 34 being edited -- ran an actual
  `rake resprite` (SpriteFactory's :packed layout reflows EVERY sprite's x/y when the file set
  changes, not just appends new ones at the end) and did the full by-hand re-sync of common.js's
  SPRITES object this project's own resprite-sync convention requires (see that var's own comment)
  -- every coordinate in it changed, not just the 6 new entries.
  Gamepad (gamepadIndex/pollGamepad() in racing/index.html): standard Gamepad API, polled once a
  tick from update() (no press/release events for held buttons/axes, only connect/disconnect) and
  written straight into the same keyLeft/keyRight/keyFaster/keySlower flags keyboard/touch already
  use -- a third input source, not a separate code path downstream. D-pad (buttons[14]/[15]) or left
  stick (axes[0], STICK_DEADZONE=0.25) steers; (Square) buttons[2] or R2 buttons[7] is gas; (Cross)
  buttons[0] or L2 buttons[6] is brake -- Square/Cross over the triggers specifically so a thumb on
  the face buttons can rock straight between them for quick on/off braking, triggers left live too
  as alternates. Standard mapping button indices (W3C spec), same as any other browser gamepad
  support -- PS4/PS5 controllers register under that mapping on current iOS/Android/desktop browsers,
  not anything sniffed or negotiated here. gamepadconnected sets keyFaster = false (turns OFF
  mobile's own auto-gas the instant a real gas button exists) and swaps #mobile-hint's text;
  gamepaddisconnected sets keyFaster = true again ONLY if the touch-controls container is actually
  visible (same display-check the touch-controls setup itself uses) so a controller disconnecting on
  DESKTOP can't start auto-accelerating a keyboard player. The API only reveals a controller after a
  button on it is pressed once, even after Bluetooth pairing succeeds -- browser-level privacy
  behavior, not a bug to chase here if a freshly-paired controller doesn't do anything yet.
  Gamepad audio unlock (Game.onFirstInteraction() in racing/common.js, shared by every page in
  racing/ that calls it -- index.html/v4.final.html, dev.html, v1-v3 -- not just the live game):
  that function's own browser-autoplay-unlock listener only covered
  keydown/click/touchstart -- a controller-only player (paired over Bluetooth, never taps/clicks the
  page or touches a keyboard) never fired any of the 3, so music/engine sound stayed silent until
  they manually hit the mute icon, whose click was what ACTUALLY unlocked audio, nothing about the
  icon itself. Added `gamepadconnected` as a 4th listener -- that event only ever fires after a
  genuine physical button press on the controller (the same browser requirement noted in the Gamepad
  entry above), so it's backed by a real user gesture, just one the other 3 had no way to see.
  Whether the browser treats that gesture as sufficient to actually unlock audio (not just fire the
  event) is a platform/browser-engine question outside this code's control -- solid on Chrome/Android
  as of recent versions; unconfirmed on iOS Safari without an actual device test.
  Landscape-phone layout (racing/index.html's own `@media (orientation: landscape) and
  (max-height: 500px)`, placed AFTER the max-width:768px query so its overrides win where the two
  overlap on a narrower phone turned sideways): max-height not max-width, because a modern phone's
  landscape WIDTH (812-932px+) sails past the 768px query entirely, dropping back to the full
  desktop layout otherwise -- box art columns and a canvas width formula (85vh*4/3 minus box-column
  reserves) built assuming abundant vh from a tall portrait phone. 500px matches real phones in
  landscape (roughly 320-430px tall) without ever matching a desktop/laptop window, even a short
  one. Portrait mobile's whole layout assumes width is scarce and height is abundant; landscape
  flips that, so #racer's width formula here is constrained by height instead of width -- the
  opposite of every other mobile rule -- via `min(100%, calc((100dvh - 56px) * 4/3))` (a vh-based
  version of the same declaration precedes it, for a browser that doesn't understand dvh -- an
  invalid calc() drops the WHOLE declaration for it, so it just keeps the vh line instead of losing
  sizing entirely). dvh not vh, and #frame reduced to border:none/padding:4px (was 8px, matching
  #frame's own separate max-width:768px reduction) rather than just its width -- both fixes for the
  same report on an iPhone 16 ("almost fits, have to scroll a bit"): (1) 100vh on iOS Safari reports
  the viewport as if the address bar weren't there, taller than what's actually visible -- dvh tracks
  the real, currently-visible height; (2) the reserve constant (56px: real chrome of body+#frame's 4px
  padding doubled = 16px, PLUS a deliberate 40px of extra slack) was, in an earlier version of this
  fix, accidentally a couple px SMALLER than the actual chrome then in play -- backwards, since
  undershooting the reserve makes the COMPUTED canvas larger, not smaller, so it overflowed instead
  of leaving margin. The 40px of slack is deliberate headroom now, not an accident, partly to hedge
  against whatever the embedded skullracer.php context adds above this that racing/index.html has no
  visibility into (see that file's own entry below for the matching dvh fix at ITS level -- a
  min-height set from an overestimated 100vh forces the whole page taller than the real visible area
  regardless of how well the canvas fits inside it). #instructions is hidden outright (no room for
  hint text, and see the Gamepad/touch-controls entries above/below for why the control scheme is
  usually already obvious in this mode); #hud/#minimap revert to their normal desktop overlay-on-canvas placement (the portrait-mobile
  minimap-as-separate-block treatment exists to stop it covering the right lane on a NARROW canvas --
  a width problem, and width is exactly what landscape has back).
  This query only reaches racing/index.html's OWN markup though -- visited through skullracer.php
  (the real path most players use, Play > Skull Racer, not the standalone file), header.php's site
  nav (#burger-menu/#navbar), #app-version-banner, and #back-to-top-button all sit OUTSIDE what
  $racing_style/$racing_body extract from racing/index.html entirely (see skullracer.php's own
  comment on that split), so none of the above touches them -- confirmed missing when reported
  (game screen fit, but the site's own menu still sat there taking the rest of the height). Fixed
  with a SEPARATE copy of the same `@media (orientation: landscape) and (max-height: 500px)`
  condition in skullracer.php's own `<style>` block (after $racing_style is echoed), forcing all 4
  of those to `display: none !important` and bumping #skullracer-embed's min-height from 85vh (sized
  assuming the nav above it is visible) to 100vh, then a SECOND `#skullracer-embed { min-height:
  100dvh; }` declaration right after it -- same vh-overestimates-the-visible-area / dvh-tracks-it-for-
  real fix as racing/index.html's own landscape query, and for the same reason it matters at this
  outer level too: a min-height sized off the overestimate forces the whole EMBEDDING page taller
  than what's actually visible, forcing a scroll regardless of how well the canvas fits inside it.
  Deliberately duplicated rather than shared -- the condition needs to independently exist in both
  places, since each file hides a different set of elements the other has no reference to.
  Landscape touch controls (same `@media (orientation: landscape) and (max-height: 500px)` block,
  racing/index.html -- same #btn-left/#btn-right/#btn-brake elements and touchstart/touchend
  listeners portrait mobile already set up, none of that JS duplicated, only repositioned via CSS,
  PLUS one new element/binding, #btn-brake-2, covered below): height-constraining the canvas leaves
  real WIDTH to spare on either side of it on a landscape phone (opposite of portrait, where width is
  what's scarce) -- #touch-controls becomes a position:fixed, inset:0 full-viewport overlay
  (pointer-events:none on the overlay itself, re-enabled per .touch-btn, so the empty middle over the
  canvas never eats a tap meant for something else) instead of portrait's normal-flow height:30vh row
  below the canvas, which has no floor to sit on here.
  Layout is literal left-edge/right-edge steering (◀ left, ▶ right, matching hand to side) -- an
  earlier version grouped both under one thumb with a single dedicated brake on the other side
  instead, reported back as unintuitive. Each steering button takes the bottom 3/4 of its side
  (`top:25%; bottom:0`); the top 1/4 of EACH side (`top:0; bottom:75%`) is its own brake button --
  #btn-brake on the left, a new twin #btn-brake-2 on the right, both bound to the identical
  keySlower/keyFaster flip in the setup IIFE (literally the same two callback functions passed to
  bindHold() twice) so braking is reachable from whichever thumb is free at the moment, not locked to
  one particular side. #btn-brake-2 stays `display:none` at the base rule (right next to
  #touch-controls's own) so it never joins the portrait row as a stray 4th button -- this landscape
  block is the only place that turns it on.
  display:block is set explicitly on #touch-controls, not left to fall through from the 768px/1300px
  queries (neither applies on a phone this wide in landscape) -- matters beyond the visual: the
  portrait setup IIFE's own auto-gas/listener-binding both gate on this exact computed display value
  being non-'none', checked ONCE at load, so a player opening the game already sideways would
  otherwise get invisible, unbound buttons. Same gate is also why a disconnected gamepad correctly
  falls back to a fully playable touch scheme in this mode now, not just auto-gas with no way to
  steer (see the Gamepad entry's own gamepaddisconnected -- it already checked touch-controls'
  visibility before this existed, it was just never true in landscape before now).
  .touch-btn's actual look (background/border/color/font-size/etc.) is now set ONCE, unconditionally,
  right next to #touch-controls's own base `display:none` rule -- it used to live only inside the
  max-width:768px query, alongside the portrait-row layout rules that were its only neighbors at the
  time. Once landscape started showing these same buttons too, that stopped being safe: a landscape
  phone routinely exceeds 768px WIDTH (that query's whole reason for existing, see the Landscape-
  phone-layout entry above), so on a real phone that query never fires there, and landscape's own
  position/size overrides had no visual styling to layer on top of -- reported exactly that way, "I
  see the word brake in the corner, that's it, no UI buttons." The 768px query now keeps only what's
  actually specific to the portrait row (flex:1/height:100%, #btn-brake's smaller font-size to fit
  that row's narrower per-button width); landscape's own block is unaffected, it was already
  layering its own position/size rules on the (now correctly unconditional) base look.
  Crash reaction (CRASH_* constants/crashReactTimer in racing/index.html): purely cosmetic, fires
  whenever `crashed` is set by either collision check in update() -- a decaying canvas-translate
  screen shake (crashShakeX/Y, applied via ctx.save()/translate()/restore() wrapping the whole of
  render()) plus a spawnParticles() burst. No new sprite art -- rotating the existing rear-view-
  only player frames past a few degrees looks broken (no side/front art to turn into), so this
  fakes a hit with shake+dust instead of true rotation. A third trick (rapidly swapping the
  existing left/right/straight frames to fake a fishtail wobble) was tried and removed -- felt
  wrong in practice, per direct user feedback.
  Particles (particles array, spawnParticles()/updateParticles()/renderParticles() in
  racing/index.html): generic screen-space system, spawn origin is a fixed (width/2, height *
  PARTICLE_SPAWN_Y_FRAC) rather than the real projected player position -- close enough since
  Render.player() itself draws at a near-fixed screen spot (steering moves the world, not the
  car's own screen X). Used by the crash burst above and by continuous tire smoke/dust while hard
  braking or off-road.
  Passing-car sound (playPassSound() in racing/index.html): no dedicated "whoosh" sound file
  exists, so this reuses the same decoded engineBuffer as the player's own engine hum through its
  own one-shot BufferSourceNode -> GainNode (fade envelope) -> StereoPannerNode chain, pitched up
  (1.6x) so it doesn't just sound like a second copy of your own engine. Panned by the car's lane
  offset relative to playerX, clamped to StereoPannerNode's -1..1 range. One-shot per car per
  approach via car.lastPassSoundAt + PASS_SOUND_COOLDOWN, not full enter/exit tracking.
  No iframe (skullracer.php): used to iframe racing/index.html because header.php's nav links
  are root-relative while racing/'s own asset references were folder-relative -- incompatible in
  one document. Fixed by making every asset reference in racing/index.html and racing/common.js
  root-absolute (/staking/racing/...) instead, so the same file resolves correctly either visited
  directly or read server-side. skullracer.php now file_get_contents()s racing/index.html at
  request time and re-serves its <style> and <body> content directly (not a copy kept in sync by
  hand -- one canonical file). racing/index.html's own body{} rule (flex-centered,
  min-height:100vh -- correct when it IS the whole page) is renamed to #skullracer-embed via
  str_replace before being echoed, since applying that to the REAL <body> here would blow away
  the shared header/nav layout; #skullracer-embed wraps the echoed body content and gets its own
  min-height:85vh override afterward (roughly the old iframe's proportions). Checked for id="..."
  and top-level function name collisions between racing/index.html and header.php before doing
  this -- none found, but worth re-checking if either file gains a very generic new one (things
  like #mute, #frame, #stage were the real risk).
  Version-check/auto-refresh (racing/index.html): this file has no PHP/session, so it never got
  header.php's site-wide version-check banner -- added its own equivalent (#racer-version-banner,
  guarded by `if (document.getElementById('app-version-banner')) return;` so it's a no-op when
  inlined into skullracer.php, which already has header.php's own). Polls /staking/version.php on
  the same 30s-then-5min cadence; no baked-in version to compare against (no PHP to render a meta
  tag), so the first poll just establishes a baseline instead of comparing against a page-load
  value -- same net effect. Never force-reloads while visible/mid-race, only silently reloads on
  the next visibilitychange-to-visible after a mismatch was found while backgrounded, matching
  header.php's own restraint.
  Ghost, personal (ghostRecording/ghostPlayback/ghostFrameIndex in racing/index.html): best-lap
  replay, 100% client-side. Records [position, playerX] every update() tick during the lap in
  progress; at the same lap-crossing check that already updates Dom.storage.fast_lap_time, if this
  lap beat the stored best the just-finished recording becomes both the in-memory playback buffer
  AND Dom.storage.ghost_lap (JSON) -- takes over immediately, not just next race. Rendered in
  render()'s existing per-segment car-drawing loop (same +playerZ convention playerSegment already
  uses, same Render.sprite() traffic cars use, just alpha 0.4 and always SPRITES.PLAYER_STRAIGHT --
  no per-frame steer-direction inference). Deliberately has zero interaction with update()'s
  collision logic -- it's a recorded trace, not a simulated car, so it can't crash or be crashed
  into, and doesn't know or care whether this race's boost pad/jump ramp landed in the same lane
  the recorded lap saw. All three ghosts' frameIndex only advance once position > playerZ (the same
  threshold raceElapsed/currentLapTime already gate on for "the race has actually begun") --
  update() runs continuously from page load regardless of input, so advancing unconditionally meant
  a ghost's clock was already ticking the whole time a player sat at the start line before touching
  a key, making it look like it launched ahead of them. Recording itself stays unconditional (fine
  to record the standing-still start too); only playback advancement is gated.
  Ghost, weekly/all-time leader (weeklyGhost/alltimeGhost in racing/index.html, skullRacerGetGhosts()/
  skullRacerValidateGhostTrace()/skullRacerFinalizeRun() in db.php, ajax/skullracer-ghosts.php):
  same rendering technique as the personal ghost (gold via ctx.filter =
  'sepia(1) saturate(6) hue-rotate(-15deg) brightness(1.15)'). All three tiers ALWAYS get their own
  icon drawn at the sprite's own top edge (🎖️ personal, 🥇 weekly, 🏆 all-time) -- destH/offsetY math
  copied from Render.sprite()'s internal formula so it tracks the sprite at any distance -- that's
  what stays visible tracking a ghost through terrain/over a hill/past the draw-distance horizon, so
  it's never replaced by anything, only ever added to. On top of that, weekly/alltime ALSO decal that
  racer's own avatar onto the car when they have one: skullRacerGetGhosts() returns avatar_url
  (discord_id+avatar -> CDN URL, same convention as every other leaderboard, except null instead of
  the usual skull.png fallback -- the car's own rear grille already IS a skull, see GHOST_AVATAR_BOX
  below), loaded client-side via a plain `new Image()` (no promise -- the render loop just checks
  img.complete/naturalWidth each frame and simply doesn't draw a decal until/unless it loads).
  GHOST_AVATAR_BOX ([0.32,0.37,0.66,0.83], fraction of PLAYER_STRAIGHT's own w/h) is the car's built-in
  faceplate panel behind its skull eyes/teeth, measured directly off the live sprite sheet -- sits in
  effectively the same spot on PLAYER_STRAIGHT/LEFT/RIGHT, so one box covers every lean, and stops
  above the jagged teeth edge so they stay visible below the decal. Drawn in its OWN save/restore, full
  alpha and no filter -- deliberately NOT tinted/translucent like the car body, so it reads as an
  actual recognizable photo up close instead of another gold shape. Personal never gets a decal (no
  server row/avatar of its own -- it's always you).
  Every stored trace is re-based through normalizeGhostTrace() (subtracts frame 0's own
  [position, playerX] from every sample) before it's used for playback, both when a freshly-completed
  lap becomes someone's new best AND when an already-stored trace is loaded (localStorage for
  personal, the ghosts fetch for weekly/alltime) -- Util.increase() wraps `position` at a lap boundary
  to a small positive remainder, not a clean 0, so any trace whose fastest lap wasn't lap 1 otherwise
  has that remainder plus whatever playerX was held at that exact tick baked into frame 0 forever,
  showing up as the ghost sitting visibly ahead/to one side of you at the start of every loop.
  weeklyGhost/alltimeGhost also carry a `rate` (<=1, playback-length-in-seconds divided by
  total_time/totalLaps, clamped via Util.limit) -- the leaderboard ranks by TOTAL race time but the
  trace is only ever that run's single fastest LAP (looped every lap, same convention as the personal
  ghost), so looping it at a flat 1 frame/tick could finish faster than the total_time it's supposed
  to represent whenever the run's other 2 laps were slower than its best one. `rate` stretches
  playback (frameProgress tracks the fractional position frameIndex alone can't) so 3 loops take
  exactly total_time/totalLaps each, matching the real total_time. Data is server-side though: skull_racer_runs
  gained a nullable ghost_trace LONGTEXT column (ALTER TABLE needed on an existing install -- see
  this file's own SKULL RACER comment block for the exact statement), written by
  skullRacerFinalizeRun() ONLY when a just-inserted run is immediately the new all-time best or the
  new best among this week's reward=0 runs -- checked against every OTHER row that ALSO has a
  ghost_trace, not literally every row in the table (rows from before this column existed can never
  have one; comparing against the true all-time min would let an old traceless fast run permanently
  block any new run from ever qualifying). This means the first race submitted after the ALTER
  TABLE automatically becomes both ghosts -- do NOT truncate skull_racer_runs to "start fresh," that
  destroys real race/CARBON history for nothing, the scoped comparison already handles it. Every
  other row's ghost_trace stays NULL, so storage grows only with actual NEW records, not every race.
  The weekly slot rotates out on its own when resetSkullRacerRuns()
  flips reward to 1 (drops the row from every "AND reward = 0" query, ghost included) -- no separate
  reset job. skullRacerValidateGhostTrace() gates what's ever allowed to become a leader-ghost
  BEFORE it's stored (sample count vs. claimed lap duration at SKULLRACER_GHOST_TICK_RATE=60,
  playerX within the in-game clamp, forward position deltas capped at 500/tick) since these traces
  get rendered to EVERY player, not just shown back to whoever submitted them -- a bad trace here is
  everyone's problem, unlike a bad personal-ghost which only ever affects that one browser. The
  delta cap is FORWARD-only, not symmetric -- a real collision (hitting a car or an off-road sprite,
  see position = Util.increase(car.z, -playerZ, ...) in racing/index.html) resets position to just
  behind whatever was hit, which can be a large BACKWARD jump in a single tick if you were going
  fast. That's normal, honest gameplay, not fabrication; a symmetric bound here rejected real
  human races that crashed even once during their best lap, silently, with no error surfaced
  anywhere. Forward progress has no legitimate reason to jump like that (BOOST_SPEED is ~300
  units/tick), so only that direction needed capping.
  Client uploads ITS OWN race's fastest lap as ghost_trace/ghost_lap_time on every finalize call
  (raceFastestLapTrace/-Time, tracked separately from the personal-best-ever check above, and
  deliberately NOT reusing the existing fastest_lap POST field -- that one is this browser's
  all-time PB as of now, not necessarily from this race, so validating the trace's sample count
  against it would frequently mismatch); the server decides whether it's actually leader-worthy,
  the client doesn't get to assume. ajax/skullracer-ghosts.php is a public GET (same visibility as
  the leaderboard) returning {weekly, alltime}, each null if nobody's set a qualifying time with a
  valid trace yet. Client dedupes weekly against alltime by row id if the same run holds both records
  at once, so only the higher tier renders, not both stacked on the same spot; personal has no row id
  to match by, so it's dedupe against weekly/alltime by trace content instead (JSON.stringify equality
  on the already-normalized arrays) -- catches the same-run case (your own localStorage best IS the
  server's stored trace) without needing to know it's the same run in advance.
- Crypt Crawl (db.php:10451-10805): 44-card deck (26 monsters clubs/spades 2-14, 9 weapons
  diamonds 2-10, 9 medkits hearts 2-10), max HP 20. Weapon degrades to "equal or lesser" rank
  after each kill. First medkit per crypt heals full rank; any after that in the same crypt
  heal half (floor, min 1) instead of nothing. Last Stand: first hit that would hit 0 HP per
  crawl instead clamps to 1 HP, once per crawl, automatic (internal column/var name stays
  second_wind_used - display-only rename, not worth a migration).
  **"Delve" -> "Crawl" rename** (2026-09-03, same convention as the Jester -> Joker rename
  above): "Delve"/"Start Delve"/"Delve Again" read as a mismatch against the game's own name,
  so every PLAYER-VISIBLE instance across cryptcrawl-render.php, cryptcrawl-actions.php,
  cryptcrawl.php, cryptcrawlgame.php, homepage.php, and games-cryptcrawl.md is now "crawl"/
  "Start Crawl"/"Crawl Again". Nothing internal changed -- there was no column or session key
  to preserve this time (unlike jesters_used above), just prose. Left alone on purpose: the
  `#cc-start-delve-form` DOM id (cryptcrawlgame.php, still referenced by its own onclick
  handler), and the JSON-LD `alternateName` "Crypt Crawl Dungeon Delve" (SEO-indexed structured
  data -- not a place to make silent judgment calls). Code comments throughout still say
  "delve" in places; this pass didn't touch them, matching the Jester/Joker precedent of
  leaving non-player-visible text alone.
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
  cards is mapped to one specific NFT by exact `nfts.name`, not a shuffled pool - re-curated
  2026-09-03 from real on-chain metadata (crypties-s2-rarity-report.php, run against the
  owner's actual Crypties: Season 2 holdings - 108 pieces; see cryptcrawl-s2-art-curation.md
  for the full dataset/methodology). Real on-chain `attributes.rarity` tiers, confirmed not
  assumed: WTF > Mythic > Legendary > Epic > Uncommon > Common. Unlike Crypt Conquest's S1
  pool, Crypt Crawl's monster suits (Clubs/Spades, the only two that ever render Crypties art
  - see below) are also species-aligned: the undocumented `carcass` trait was verified by
  visual inspection + cnft.tools to encode skull species. Clubs = monkey, Spades = ram (picked
  as the two suits since bear only has 10 owned pieces, too few for a 13-rank suit). Every
  Ace/King/Queen/Jack on both suits is one of the collection's 8 real WTF pieces (WTF pieces
  carry no carcass/species trait at all - special collab/chimera 1-of-1s - so they were split
  onto a suit by visual fit: ram-skulled ones to Spades, animal-less ones to Clubs). Rank 10 on
  both suits is a deliberate species exception: the collection's only two Mythic-tier pieces are
  both TIGER, one seeded as a guest card in each suit since neither monkey nor ram has a native
  Mythic of its own (Spades does also have ram's own Mythic, at rank 9). Everything below that
  is each suit's own Legendary (then Epic to round out Spades), sorted by Cryptie #. Diamonds
  (weapons) and Hearts (potions) never render Crypties art at all in-game (cryptcrawl.php's
  `.cc-card-icon-face` - plain black card face plus a generic weapon/medkit icon, "curated
  Crypties art is reserved for enemies"), so those 18 slots carry no rarity/species curation,
  just leftover monkey/ram pieces. Update CRYPTCRAWL_CARD_ART directly to change any card's art.
  Each monster's on-card label (`.cc-card-label`, cryptcrawl-render.php's `$type_label`) is a
  matching flavor name, not the generic "enemy" text every other type keeps: db.php
  `cryptcrawlMonsterName()` returns CRYPTCRAWL_MONSTER_NAMES' override for a card if one exists,
  else the per-suit default ("Cursed Ape" / "Cursed Ram"). The 8 WTF cards and both guest-tiger
  10s are the only overrides - 4 ram-skulled WTFs share "Chimera" (they're the real `subset:
  chimera` pieces), the 4 animal-less WTFs each get a bespoke name from their own on-chain
  `variant`/`project` (Boombox, Horny, Mardi Gras, Static), and both Mythic guest tigers are
  "Cursed Tiger". Update CRYPTCRAWL_MONSTER_NAMES directly to change any card's name.
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
  shield (Hearts resolves before Diamonds when both trigger). 2 **Jokers** (discard hand + refill,
  once each) -- renamed from "Jester" in every player-facing string (2026-09-03) but NOT
  internally: the function is still `cryptconquestFlipJester()`/`cryptconquestDoFlipJester()`, the
  DB column is still `jesters_used`, the POST action value is still `flip_jester`, the session keys
  are still `cryptconquest_jester_drawn`/`cryptconquest_guard_taught`-adjacent naming, the SFX key/
  audio files are still `jester`/`jester.mp3`/`jester.m4a`. Deliberate: renaming the DB column is a
  migration, and none of this plumbing is user-visible, so there was nothing to gain by touching it
  -- but it means "Jester" in code/schema and "Joker" in the UI are the SAME mechanic, not a
  regression. `cryptconquest.md` (the private design doc citing actual Regicide rules, which really
  does use "Jester" as a card name) is also deliberately untouched -- it's citing source material,
  not describing this platform's UI.
  win tier `cryptconquestTier()` keyed off jesters_used (0=Flawless, 1=Hard-Fought,
  **Undocumented-mechanics audit** (2026-09-02): a player asked why an 8 played with a 1-value
  Diamond Companion drew 9 cards. It was correct -- but auditing cryptconquest-engine.php against
  the docs turned up five real rules that existed in code and appeared NOWHERE in the in-game rules
  panel or this page. All five verified against the engine before writing, and all now documented in
  both cryptconquest-render.php's rules panel and games-cryptconquest.md:
    1. Suit powers scale with the COMBINED play total (`$attackValue` = sum of the whole play,
       cryptconquest-engine.php:227), not the value of the card carrying the suit. So a Companion
       (value 1) hands its suit the whole play's value -- the core Regicide combo, and the single
       most consequential rule in the game. Clubs doubles FIRST, so the others scale off the
       doubled number too.
    2. Spades shield ACCUMULATES and never wears off for that fight (`shield +=` at :255, only
       ever subtracted at :283/:311, reset only on a new enemy at :207). Verified: 9 then 8 vs a
       King leaves pending_attack at 3. The docs said "next attack", implying one-shot.
    3. A Companion pair is EXEMPT from the 10-card-combo cap -- cryptconquestValidatePlay returns
       null at :154 before the `$sum > 10` check. Verified: 10 + Companion (total 11) is legal
       where 6+6 (total 12) is rejected. Companion + Companion is legal too, also undocumented.
    4. A recovered court card is worth its ATTACK stat in hand (cryptconquestCardValue:59) --
       Jack 10, Queen 15, King 20, bigger than any number card -- and triggers its suit at that
       value. The docs called exact kills "a small edge"; they're the biggest payoff in the game.
    5. A Jester can be flipped during the 'suffer' phase (cryptconquestFlipJester:425 accepts
       'play' OR 'suffer'), and pending_attack survives the flip -- so it's a genuine escape from
       an otherwise-fatal hit, with a fresh 8 cards to cover it. Verified. The docs described a
       Jester only as a turn replacement, which hid the best defensive option in the game.
  Also recorded (already correct, now stated): the yield dead-end auto-loss at :304 (empty hand +
  both Jesters spent is unrecoverable, so yielding resolves the run instead of looping).
  **Last Stand rally** (added 2026-09-02, CRYPTCONQUEST_LAST_STAND_REFILL = 4): Last Stand used to
  forgive the killing blow but leave the hand EMPTY, so it "saved" the player into a position with
  nothing to play -- 26.2% of runs ended within 2 turns of it firing, which reads as no save at
  all. It now also draws the hand back up to 4. Measured, 2,500 games/variant: refill-to-4 cuts
  death-within-2-turns to 9.9% while barely moving the win rate (11.9% -> 12.4%), i.e. it fixes the
  anticlimax without making the game easier; refill to 6 or 8 jumps it to 17.9%/21.4%, a different
  game. Deliberately a DRAW, not a return of the cards just spent: rolling those over tested WORSE
  than doing nothing (10.0% win, death-within-2 rising to 37.8%) because they are by definition the
  cards that already failed to cover the hit -- and a fresh draw is also the whole drama of the
  moment. Verified against the shipped engine: 13.0% win, 9.0% death-within-2.
  **Perfect Guard** (added 2026-09-02, cryptconquestSufferDamage): covering an attack EXACTLY
  returns the player's two highest-value spent cards to hand; everything else spent still goes to
  the discard, and overpaying returns nothing. Defensive twin of the exact-kill rule. The "two"
  is a measured balance point, not a guess -- 2,500 simulated games per variant, identical
  offense, only defense differing: no rule 0.0% win / depth 5.21; return highest 1 = 1.0% / 7.15;
  **return top 2 = 11.4% / 8.51**; return all = 15.0% / 8.72. Returning everything makes defense
  free ~68% of turns and guts the core "cards spent defending are cards you can't attack with"
  tension; returning one barely moves the needle. Also measured and rejected: RANDOM two (exacts
  average ~1.6 cards and only ~8% use 3+, so it's identical to top-two 92% of the time, costs
  1.4pp, adds confusion) and DIMINISHED returns, i.e. give an 8 back as a 4 (0.0% win / depth
  5.21 -- literally no better than having no rule, and it poisons the hand: 13.7% of exacts then
  need 3+ cards vs 7.9%, because this game's scarcity is total VALUE not card count; it would
  also break card identity since art keys are suit+rank, minting a duplicate 4 of spades).
  UI is two-tier by design: exact defenses land on ~68% of turns, so a blocking modal every time
  reads as nagging -- full modal on the FIRST perfect guard of a run only
  ($_SESSION['cryptconquest_guard_taught'] keyed to run id), then the returned cards just glow
  gold in hand with a SAVED badge ($_SESSION['cryptconquest_saved'], read-and-cleared per render).
  **Diamonds draw is capped by HAND SPACE, not by the card's value** (cryptconquestDraw:
  min(count, 8 - hand, deck)). Undrawn excess is forfeited but NOT lost from the deck -- it stays
  in the tavern deck. So a 9 of Diamonds played on a full hand draws exactly 1. Documented in the
  in-game rules, marketing page and games-cryptconquest.md as of 2026-09-02 since it's a real
  strategic rule that was previously invisible. Drawn cards are marked
  ($_SESSION['cryptconquest_drawn'], same read-and-clear lifetime) so they file into the hand
  staggered with a "♦ NEW" badge rather than silently blending in.
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
  schedules cron itself, see rewards.php's own comment on that line).
  **An EMPTY monthly board right after a payout is expected, not a bug** (confirmed live
  2026-09-01 after it was reported as one). The monthly view filters on `cq.reward = 0`, i.e.
  "runs since the last payout" -- not "runs this calendar month". `resetCryptConquests()` flips
  every counted run to `reward = 1` as part of paying out, so the board is empty by design until
  someone completes a new run, and refills immediately when they do. The all-time view
  (`filterby=cryptconquest`, no reward filter) still shows the full history throughout, which is
  the quickest way to confirm nothing was actually lost. Same reward-flag pattern as Crypt Crawl,
  Gauntlets, Skull Swap and Missions, so the same "looks broken, isn't" applies to all of them.
  Genuinely worth checking if this ever looks wrong: that the crontab entry is really monthly --
  a daily schedule would pay the full 100,000 pool out every day and wipe the board each time. Live-play announcements
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
  `NaN`; caught via a synthetic-timestamp Node harness before shipping, not by manual testing.
  **Deliberately used only for transitions
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
- Crypt Crawl/Crypt Conquest Web Audio volume routing (`setElementVolume()`/`getElementVolume()`/
  `unlockAudioCtx()`, top of each file's own outer `(function(){...})()`, added 2026-09-03 -
  "the PWA is unplayable unless the sound is off... some of the weapons [are] twice as loud as the
  music," per the user): iOS Safari/WKWebView (so any home-screen-installed PWA too) silently
  ignores `HTMLMediaElement.volume` outright - always plays at 1.0 no matter what it's set to, no
  error, only the hardware buttons/silent switch actually affect it, by Apple's own deliberate
  design. Every volume control on both game pages (SFX_LEVEL scaling, the music crossfade ramp,
  the slider, mute) used to work by setting `el.volume` directly, so on iOS every sound with a
  deliberately low SFX_LEVEL (`kill`, `machinegun`, `artillery`, `exactmatch`, etc.) played at full
  blast instead of sitting under the music, while louder ones (`fist`, `laststand`) sounded about
  right since there wasn't much attenuation lost. Fixed by routing every `<audio>` element (both
  music players, every SFX element, including Conquest's cloned `sfxPool` elements - each clone is
  its own distinct element, wrapped lazily the first time it's actually used) through a Web Audio
  `GainNode` instead - iOS DOES respect `GainNode.gain`, unlike element volume (the standard
  workaround, same one Howler.js uses). `setElementVolume(el, vol)`/`getElementVolume(el)` are
  drop-in replacements for every `el.volume = x` / `el.volume` site in both files; once an element
  is wrapped (lazily, memoized in a `WeakMap` keyed by the element - `createMediaElementSource()`
  must only ever be called once per element) its own `.volume` is pinned to 1 permanently and the
  `GainNode` becomes the sole source of truth for it, including across the crossfade's own
  `.src`/`.load()` swaps (same graph, new audio through it, no re-wrapping needed). Falls back to
  plain `el.volume` if Web Audio isn't available at all, or a specific element fails to wrap
  (defensive - shouldn't actually happen, everything here is same-origin). `AudioContext` starts
  suspended until a trusted user gesture resumes it (same restriction unmuted autoplay already
  works around elsewhere in both files) - `unlockAudioCtx()` is called defensively at the top of
  every function that actually plays a sound (both `tryPlay()`s, every `playNamedSfx()`/
  `playCardSfx()`/`playStinger()`, the volume slider/mute handlers, and each file's own
  first-gesture `unlockAudio` listener), not just once, since a player with music off but SFX on
  never reaches the music player's own unlock path. Verified with a standalone Node harness
  (mocked `AudioContext`) before shipping, not just `php -l`/`node --check` - confirms element
  wrapping is idempotent (one `MediaElementSourceNode` per element, ever) and the no-Web-Audio
  fallback path actually falls back.
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
