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
| missions.md (Daily reward)          | Daily reward claim     | getRandomReward()/dailyRewardAnnounce() in db.php, ajax/daily-reward.php, ajax/daily-reward-announce.php, dailyReward() in skulliance.js, the strip in missions-daily.php, daily-reward-harness.php. REPORTED: "a bit slow and the button doesn't say it is processing". TWO CAUSES. (1) discordmsg() is a synchronous curl POST with CURLOPT_TIMEOUT 8, and it sat inside getRandomReward() between the press and the reply that redraws the strip -- every claim waited on Discord, and a bad minute there looked like a hang. IT CANNOT BE DEFERRED PAST THE RESPONSE ON THIS SERVER: cryptcrawlFlushPendingSideEffects() in db.php records three production failures (fastcgi_finish_request, then X-Accel-Buffering plus flush), most likely a proxy buffering the whole origin response. The only thing that works is a SEPARATE request the browser makes after it has painted, so the claim now leaves a MARKER in $_SESSION['dr_announce'] and ajax/daily-reward-announce.php posts it. That endpoint takes NO parameters -- dailyRewardAnnounce() rebuilds the text from the database and the session, so a client can only ask for its own claim to be announced, never choose what gets posted. It clears the marker BEFORE the curl (the 8 second window is exactly when a double-fire arrives) and re-writes SessionCookie afterwards, because skulliance.php serialised the session into that cookie several lines earlier and on an ITP/PWA session the cookie IS the session -- the identical trap ajax/dhc-seen-drop.php documents. Markers older than 900s are dropped, or a tab closed mid-claim announces yesterday as though it just happened. The 7th-day dhcf_award() STAYS inside the claim: it is a database write that belongs in the transaction that earns it, and the reply carries the drop. (2) The button did nothing visible until the reply arrived and then vanished, and nothing stopped a second press -- two claims racing both pass getDailyRewardEligibility() before either increments the streak. It now pins its own width (it is an <input>, so it cannot carry the ::after spinner .ms-busy uses on the deploy buttons), says Claiming..., refuses a re-entry, and restores itself on timeout, transport error and non-200 -- that last branch used to be empty, so a 500 left it reading Claiming... forever. HARNESS NOTE: the structural checks read db.php through token_get_all with comments stripped and strings blanked. The first version searched raw source and found the word discordmsg() in the COMMENT explaining why it had been removed, reporting the fix as un-applied; blanking strings then erased $_SESSION['dr_announce'] itself, so the marker checks use a comments-only copy. It also asserts the extracted function body contains `return $project;` and is under 4KB, because brace matching over a 14,000-line file is only as good as the stripping. |
| (payout crons)                      | rewards.php    | rewards.php is now a JOB TABLE plus a runner, not fifteen independent if-blocks. `weekly=true` runs swaps, bosses, gauntlets, cryptcrawl, skullracer, obscura; `monthly=true` runs missions, streaks, raids, factions, monstrocity, guardians, cryptconquest, dhcfighters, dhcarena. Every single-board flag still works and dispatches through the SAME table, so a combined run and a single run cannot diverge. THE HAZARD CONSOLIDATION CREATES: none of these payouts is idempotent -- several tables carry no rewarded flag -- so a combined run that dies halfway must NOT be re-run. Each job is therefore isolated (a throw is reported and stepped over, not fatal to the rest), the runner prints a per-board manifest, and on any failure it prints the exact single-board commands to retry only what failed. `skip=a,b` resumes without double-paying. ORDER IS LOAD-BEARING in two jobs: skullracer pays 'race' then 'lap' then resets (the reset closes the week by flipping reward=1, so it must follow both), and obscura pays then resets. guardians resets itself inside its own function and must NOT get a second reset. run_reward_jobs() unwinds output buffers back to the depth it found, not to zero -- `while (ob_get_level() > 0)` destroys the caller's buffers too, which swallowed the failure manifest. DHC FIGHTERS now has a payout at all: checkDHCFightersLeaderboard($conn, $monthly, $rewards), 100,000 CARBON, windowed on NOW() - INTERVAL 1 MONTH at BOTH ends so it settles the month that closed and is safe to run late but not twice. rewards-harness.php and dhc-rewards-harness.php cover both with no database. |
| missions.md                         | Idle missions, bulk launchers | missions.php (REBUILT), missions-lib.php, missions-field.php, missions-ladder.php, missions-news.php, ajax/mission-data.php, ajax/mission-launch.php, missions-harness.php, db.php (getMissions, completeMission). THE REBUILD: missions.php owns its markup and three partials that BOTH the first paint and the ajax refresh include, so a repainted list cannot drift from a landed one. missions-lib.php returns data where db.php's six mission renderers echoed markup; none of them is touched, so the page reverts in a line. FOUR THINGS THE OLD PAGE DID, worth not reintroducing: (1) it only called getMissions() `if(isset($_SESSION['userData']['project_id']))` and nothing set a default, so a first-time staker saw an empty box under the words "Available Missions" -- missions.php now picks a project server-side and there is no branch that shows nothing; (2) choosing a mission was a full page POST (each card was its own <form action='missions.php#inventory'>), which re-ran verify.php and needed a 9s fake progress bar to cover it -- it is a drawer now and the loader is gone; (3) Maximise and Balance were two more POSTs for arithmetic the browser has every input for -- both are client-side against LO.threshold, which mission_loadout() computes; (4) the load-out lived in $_SESSION across four mutating ajax endpoints and told the staker to hard-refresh when it desynced. SECURITY: ajax/process-mission-nft.php writes whatever nft_id the CLIENT names into the session and startMission() inserted it unchecked (same for items). mission_launch() intersects the request against the eligible set and re-derives the level lock and affordability. Those old endpoints still exist and are still reachable -- they are simply no longer used by this page. NEVER-RUN NOTICE: mission_frontier() lists unlocked rungs with no missions row at all, and the band renders ABOVE the Daily deployment bar because pressing Start All Free is exactly what takes the crew a new rung needs. A project with nothing cleared is skipped or day one would list forty level-1s. IDS ARE A CONTRACT: skulliance.js's completeMissions() reveals outcomes in place via mission-row-, mission-result-, mission-reward-, currency-, consumable-; retreat() uses retreat-button-. missions-field.php keeps all of them. The page also REASSIGNS window.loadCurrentMissions after skulliance.js loads, because the bulk launchers and retreat() both call it -- that is what keeps them working without editing a file nine other pages share. CSS trap hit here: `.ms-mcard-stats span` also matched the nested currency span and made it a flex block, breaking "3,600 SKULL" onto two lines -- use `> span`. missions-daily.php replaces renderDailyRewardsSection()'s seven full-width rows (that renderer also RESET A LAPSED STREAK as a side effect -- mission_daily() keeps that write, in the same order); it keeps the five ids dailyReward() writes into (#reward, #claimed, #progress_bar, #remaining, #claimRewardButton), three of which it sets to display:flex, and that path is what reveals the day-7 DHC trait via DHC_SHOW_DROP. PERFORMANCE, all three found on a real account with 179 missions in flight: (a) the page's <style> was at the BOTTOM, so the browser painted every card under flexbox.css alone -- .nft at 25%, art at natural size -- a full FOUC; it is now the first thing after the includes; (b) mission_active() called getMissionConsumables() per row, i.e. 180 queries before first byte -- one grouped query now; (c) 179 cards is ~270KB of markup and 179 images, so mission_active($conn, MS_FIELD_CAP=24) trims IN-FLIGHT ones only (everything ready is always kept) and mission_active_total() feeds a Show-all button that refetches with all=1. mission_levels() memoises getMissionLevels() for the request -- first paint wanted it five times -- and is RESETTABLE, with mission_launch() clearing it; a plain static passed every launch test and silently broke four frontier ones, which is how the staleness hazard was caught. NEW vs NEVER RUN: only cleared+1 is 'New'. A lower unlocked rung with no missions row is 'Never run' -- that happens when a mission is inserted into a ladder below where the player already is. Only the short 'New' goes on the art (84px wide, already carrying the level chip); the long label goes on the action line. ADMIN CAN INSPECT A LOCKED RUNG, NOT LAUNCH IT. mission_is_admin() (user 1) makes locked cards clickable, keeps their real title and art instead of the '??????' + padlock, and lets ajax/mission-data.php?what=loadout return a locked quest -- needed for checking how a ladder is configured. mission_launch() still refuses a locked quest for EVERYONE including the admin, and the drawer disables Launch with 'Locked - inspection only'. This is the split the old page did not make: getMissions() rendered the submit form for locked missions when the discord id matched, the locked card still carried an onclick that pressed it, and startMission() never re-checked -- so one stray click launched it, and a success there jumps getMissionLevels() past every rung underneath. That is the likely origin of the 'unlocked but never run' rows on this account; missions-probe.php tells that case apart from a rung added to the ladder later. The harness asserts the admin refusal and was verified to FAIL when a mission_is_admin() bypass is put back into mission_launch(). CLOSING THE DRAWER STOPS ITS MEDIA. msCloseDrawer() only set drawer.hidden, so an animated mission kept playing -- with sound, on a loop -- for as long as the page stayed open, because a hidden element is still a playing element. It now pauses every video/audio in the body BEFORE emptying it: a detached media element can keep its audio running until it is collected, so removing the markup on its own is not a fix either. Every close path goes through this one function (the x, the backdrop, Escape, and a successful launch). DRAWER CLOSE IS A ROW, NOT AN OVERLAY. It was absolutely positioned top-right of .ms-drawer-box, which is the same corner iOS paints its UNMUTE BADGE in on a muted autoplaying inline video -- so on a phone, reaching for the sound closed the mission. That badge is drawn by the OS over the video and cannot be out-stacked, only avoided, hence .ms-d-bar: a 44px-tall row above the art. Do not move the close control back over the media. QUEST MEDIA AND MARKUP: mp4 quests play in the drawer with `controls autoplay muted loop playsinline preload=metadata` -- controls is what gives the unmute button, and muted is what lets it autoplay at all (no browser autoplays with sound), so the pair is deliberate; .ms-d-art grows to 320px for video because the control bar eats the frame. Quest descriptions carry real markup (links, <br>) and are rendered through mission_rich_text(), an ALLOW-LIST not an un-escape: everything is escaped, then bare br/b/strong/i/em/u are restored (a tag carrying ANY attribute does not match and stays escaped, safe by construction) and anchors are restored only with an href matching ^(https?://|mailto:|/) -- javascript: and data: are dropped keeping their text, and closers are balanced against surviving opens so no stray </a> is left. Two regex traps, both hit: the tag body cannot be [^&]* because the escaped attribute IS ampersands (use a tempered dot), and the href must be captured lazily to its matching closing entity or a query string full of &amp; truncates the URL. The harness covers both plus script/img/onclick refusals. ITEM-ONLY LAUNCHES NEED AN IDLE ROSTER. startMaxMaxiMissions() launches with 'nfts' => array() by design, but renderMaxMaxiMissionsButton() gates on maxMaxiAvailableNfts() > 0 -- the rule is that holding NFTs BACK is what buys the right to spend an item, and once a project's roster is fully deployed you are locked out of it. mission_launch()'s first cut allowed a boost-only load-out with an empty eligible set, which would have let anyone farm missions off item stock forever; it now refuses when $eligible is empty, and the harness asserts both directions. ITEMS vs CREW (drawer): an NFT on a mission is locked for its duration, so crew+boost over 100 is waste. The old processConsumable() enforced it bluntly -- picking item 1 ran clearSuccessRate() (all NFTs + items 1-4) and over-100 raised an alert. The drawer's first cut lost that: it capped the DISPLAYED number and sent the crew anyway. TWO BUGS IN THE FIRST CUT OF THAT, both reported from real use: every item ran the shed, including Fast Forward and Double Rewards which carry boost 0 (so picking Fast Forward deselected NFTs for nothing); and it shed down to `target`, which is the whale-BALANCING share and sits under 100 when a lot of rate is deployed, so a 75% item on a 24% roster drove the budget to zero and cleared a crew that fitted fine at 99. Items now shed against boostBudget() = 100 - boost, never against target, and only when boost > 0. shedByItem records what an item took so removing it returns exactly that rather than a fresh greedy pick; toggling an NFT by hand deletes it from that ledger so a deliberate choice is never undone, and the tool buttons void the ledger entirely. Now toggling an item calls shedToFit() (drop lowest-rated picks until the rest fits target-boost; lowest first frees the most BODIES for the same rate given up) or fillToFit() on removal; a >=100 item also calls clearOtherBoosts(), which spares ids 5/6 since Fast Forward and Double Rewards are not boosts. `target` holds 100 for Maximise / LO.threshold for Balance / 0 for Clear. Tool buttons still do a clean re-pick via applyThreshold. LADDER STATES: unlocked = always openable (you came to read it and see the art; the drawer disables Launch with the reason instead), locked = '??????' title + icons/padlock.png, as before -- showing real titles spoiled every reveal. Art is object-fit:contain everywhere (cover was cropping commissioned pieces); the drawer is 260px tall and contained. BULK LAUNCHER BUSY STATE: do NOT swap the label for "Working...". It shrank the pressed button 155px -> 120px and slid the next one 35px left -- on a phone the row is wrapped, so a button moves under the finger that just pressed it and reads as a misfire. .ms-busy keeps the label in the layout and paints it transparent, with the platform's own btn-spin over it, so the box cannot change size. Measured both ways: old moved 2 of 3 buttons, new moves none. SECTION NAV: the old quick-menu (5 icons, <=700px only, swapped panels by inline style.display) existed because a staker with a hundred missions in flight could not reach anything. It is a sticky .ms-nav now -- every width, nothing hidden, counts on the tabs, IntersectionObserver for the active state (not a scroll listener: this page can be 200 cards tall). The platform navbar is position:relative on desktop so top:0 is free; under 700px the burger is fixed at top RIGHT, hence padding-right:56px. Sections carry scroll-margin-top so a jump does not land under the bar. missions-harness.php covers the writer and the notice with no database. STICKY NAV GEOMETRY, ported back from realms: the bar pins at top:0 and pays env(safe-area-inset-top) as its OWN padding, NOT as `top`. body carries padding-top:env(inset) and that padding SCROLLS AWAY, so top:env(inset) leaves a transparent band above the bar that cards ride up through into the clock and the battery. A/B'd in Chrome against a stand-in 59px inset: old rule painted a mission card 20px down the screen, new rule paints the bar. scroll-margin-top still counts the inset -- the bar is the inset TALLER rather than the inset lower, same total. THE CONTAINING BLOCK IS WHY THIS PAGE NEVER SHOWED REALMS' OTHER BUG: .ms-nav is inside .main, a flex child with auto height (measured 3753px against a 1323px .container), so the pin holds the whole way. Moving it out of .main reintroduces the one-viewport unpin, and missions-harness.php asserts it stays inside. SWIPE: missions.php loads swipe-nav.js and calls SkullSwipe.init('ms-nav', {blocked: drawer-is-open}); the gesture clicks the nav link, so the hash jump and the scroll-spy's pin both happen exactly as they do for a tap and nothing here knows where the sections are. See the realms row for the refusal list and swipe-nav-harness.js. Three bulk launchers, all rendered from getMissions() and therefore only when at least one mission is already in flight: startAllFreeEligibleMissions(), startAutoMissions(), and startMaxMaxiMissions() (+ renderMaxMaxiMissionsButton(), maxMaxiAvailableNfts(), maxMaxiProjectId(); ajax/start-maxmaxi-missions.php; startMaxMaxiMissionsAjax() in skulliance.js). Start Max Maxi launches 2 missions per unlocked Maxingo level from MAX_MAXI_MIN_LEVEL (=2) up (project DHCF_MAXINGO_PROJECT), highest level first, each with consumable 1 (100% Success) and consumables 5/6 when stocked. Consumable ids: 1=100%, 2=75%, 3=50%, 4=25%, 5=Fast Forward, 6=Double Rewards, 7=Random Reward. IMPORTANT ordering: the 100% item requires the project's NFTs to be undeployed but does NOT deploy them, so Start Max Maxi must run BEFORE either Start All -- the button hides itself once the roster is out, which is what enforces it. Two per level (not three) is deliberate: level durations differ, so pairs come due on separate days and 2 + 1 from the free run equals the DHC 3/day cap. Level 1 is excluded because it claims instantly and would spend the day's slots at launch; maxMaxiUnlockedLevel() gates both the launcher and the button on that floor so they cannot disagree. |
| missions-consumable-items.md        | 7 consumables          | db.php:2389-2418, consumables table |
| missions-monthly-rewards.md         | Monthly CARBON LB      | db.php:4465-4591 (100,000/rank) |
| realms.md                           | Realms overview        | realms.php, db.php |
| realms-locations.md                  | 7 locations           | db.php:8400-9360 |
| realms-soldiers.md *(new)*          | Soldiers/gear/crypt    | db.php:8444-8760 |
| realms-raids.md                     | Raid offense/defense, DHC trait drops | db.php:6428-7797; endRaid() in db.php decides the outcome and is the single award point -- BOTH sides pay a DHC Fighters wildcard (attacker on outcome 1, defender on outcome 2) under one shared source key 'raids', so the daily cap is 3/day across all of Realms rather than 3 each way. Tier table is graded on the loser's rating minus the winner's (dhcf_table_for('raids', gap)), using the pre-raid ratings. Note endRaid() resolves LAZILY when anyone loads the raids list, so it routinely runs in the other player's session -- dhcf_award() parks the reveal modal only when the awarded user_id matches the session user. |
| realms-factions.md                  | Factions               | db.php:4606, 5770 |
| diamond-skulls.md                   | Supply/yield/claims    | db.php:3750-3751 |
| diamond-skulls-carbon-emissions.md  | Delegation/CARBON      | skulliance.php:830-847, db.php:3806-3841 |
| diamond-skulls-skulliverse.md       | Planet activation      | db.php:4161-4189 |
| games.md                            | Games overview         | header.php Play menu |
| games-monstrocity.md *(new)*        | Match 3 RPG campaign   | monstrocity.php, db.php:5509-5634 |
| games-boss-battles.md *(new)*       | Boss encounters        | ajax/get-bosses.php, db.php:5139-5258 |
| games-skull-swap.md *(new)*         | Match-3 score chase    | skullswap.php, db.php:5019-5136  MEMBERS SKIP THE LANDING: skullswap.php is one file holding both #ss-landing and #game-container, and the landing rendered for EVERYONE with ssPlay() hiding it on click -- so the Play menu, which only exists for a member, served the pitch on every attempt to play. That is the same annoyance 204f87e7 removed for the other five games; Skull Swap was missed because it has no separate *game.php. $ss_straight_to_game sets the two initial display values server-side (not by calling ssPlay() on load, which would flash the landing first) and hides #ss-exit, the landing-only pill -- GO BACK under the board is a member's way out. The board is safe to start visible: `const game = new Match3Game()` runs unconditionally at script end and ssPlay() only ever reveals an already-initialised game. Audited all 14 Play targets; Skull Swap was the only one with this gate.  BOMB-ON-BOMB MATCHES (skullswap-harness.js, node only -- the scoring lives entirely in the browser so no PHP harness can reach it). handleBombMatches() used to clear every matched tile and then detonate exactly ONE of them, and it computed the match-size bomb and threw it away because only the no-bomb path uses it -- so lining up four Diamonds deleted three for 10 points each and was the worst-scoring move in the game. Now every bomb in the match detonates in turn via handleBombDetonation() (which refills between, which is what makes the second and third worth anything), the match forges its size bomb and detonates that too, and bonusScores.bombComboStep pays 150 per bomb beyond the first -- deliberately below one diamond detonation (~780), because the reward should be the blasts. Measured: 1 diamond 1,025 / 4 diamonds 3,845 / 5 diamonds 5,350. THE CEILING MOVED WITH IT: SWAP_MAX_SCORE was 25,000, derived from '25 matches x ~790' which only held while one match could fire one bomb. An ultra costs ~6 of the 25 matches to set up, so 4 is the practical maximum and a perfect game is ~22,400; the cap is 30,000. A cap below a legitimate run does not catch a cheat, it makes save-swap-score.php answer 'error' and the record is gone. The harness asserts the cap stays above a perfect game and below 3x it. STUB TRAPS, both hit: tiles need a truthy .element or clearBoard()/clearRowAndColumn() count nothing and every scenario scores the same; and showerTiles() must really refill. THE LAST MOVE (skullswap-endgame-harness.js). Two bugs, one cause, both reported from a real round: a bomb earned on match 25 never blasted, and the score shown never matched the one saved. resolveMatches() was SYNCHRONOUS -- it returned true the instant it dispatched, while handleMatches()/handleBombMatches() were still running behind a 300ms setTimeout -- and the swap handler did not await it, so matchCount hit 25 and endGame() set isGrandFinale FIRST. Both handlers refuse to forge a bomb while that flag is up (`if (bombType && !this.isGrandFinale)`, `if (forged && !this.isGrandFinale)`), so the final match silently lost its bomb and the grand finale only caught bombs already on the board. Same cause for the score: endGame() ends with saveSwapScore(this.score), and its own `await this.handleMatches(...)` was awaiting UNDEFINED because handleMatches was not async -- so the save read a score the pending handler had not finished adding to. FIX: handleMatches and resolveMatches are `async`, the 300ms is `await new Promise(...)`, and BOTH call sites await -- the swap site (~1693) and the cascade site (~2051). THE CASCADE SITE IS NOT OPTIONAL: an un-awaited async call returns a Promise, which is always truthy, so `if (hasMatches)` would never be false and matchCheckCount would never reset. The harness drives the real class through move 25 both ways and prints the difference (not awaited: no bomb, 80 points; awaited: carbon, 280 shown and 280 saved), and asserts both methods still return Promises -- which is the exact property endGame() depends on. |
| games-gauntlets.md *(new)*          | NFT roguelike          | gauntlets.php, db.php:9874-10341 |
| games-cryptcrawl.md *(new)*         | Scoundrel-style crawl  | cryptcrawlgame.php (marketing), cryptcrawl.php (game), cryptcrawl-render.php, cryptcrawl-actions.php, ajax/cryptcrawl-action.php, db.php:10451-10805. DHC trait drop pays on DEPTH, not the win: floor 12 of the 15 rooms, read from data-depth on .cc-result (rooms_cleared) rather than the .won class, bands 13/15 so a full escape still pays the placement_3 the win used to. Floors are four fifths of each game, not a shared number -- Conquest is shorter, so 12/15 and 10/12 are the matched pair. |
| games-cryptconquest.md *(new)*      | Regicide-style solo    | cryptconquestgame.php (marketing), cryptconquest.php (game), cryptconquest-render.php, cryptconquest-actions.php, cryptconquest-engine.php, db.php:11343-11800ish (CRYPT CONQUEST block). DHC trait drop pays on DEPTH, not the win: floor 10 of the 12 court cards, read from data-depth on .cq-result (enemies_defeated) rather than the absence of .lost, band 12 so a full conquest still pays the placement_10 the win used to. |
| games-skullracer.md *(new)*         | Pseudo-3D racer        | skullracergame.php (public marketing landing, what nav points at), skullracer.php (nav wrapper, inlines racing/index.html's style+body server-side -- no iframe), racing/index.html (game, client-side, also works visited standalone), ajax/skullracer-finalize.php, db.php SKULL RACER block (end of file) |
| games-dhc-fighters.md *(new)*       | DHC Fighters: earn traits, assemble Fighters, compete on rarity | dhcfighters.php (assembler/workshop), dhcgallery.php (the collection browser -- also requires dhcarena-engine.php, PURELY for numbers: dhca_build_fighter() is side-effect free, so health, power, crit and fighting style are derived per row and shown on every card, sortable by Deadliest/Toughest/Hardest hitting and filterable by kit. Rarity score answers what a Fighter is WORTH and these answer what it can DO; the two are near-uncorrelated by design and the gallery used to show only the first -- PUBLIC since it is the shop window, shows owners regardless of profile visibility since Fighters are not holdings), dhc-assembler.php (layering rules, shared with dhcsandbox.php -- both are platform pages now, and $dhca_mode vs $dhca_standalone is what keeps the sandbox's powers separate from its old skin), dhcfighters-config.php (game->category map, tier tables, score floors, DHC2F numbering from 421; DHCF_DAILY_CAP = 3 per source per day, overridable per source via 'cap'). Not every source is a game: maxingo, dailystreak and raids award from db.php directly rather than through the claim endpoint, which is why the cap is enforced inside dhcf_award() rather than only in ajax/dhc-claim-drop.php. DHCF_GAMES['dailystreak'] carries 'unlisted' => true: it stays in the array because everything functional reads it (dhcf_award, the cap, the claim endpoint, the notifier) and removing it would stop the streak paying, but dhcfighters.php skips it when rendering "where traits drop", which is a list of games to go and play. That also leaves exactly 12 rows, which is 2 clean rows of 6 at the repeat(6,...) breakpoint, dhcfighters-lib.php (scoring, consumable inventory, save/disassemble, boards), dhc-dropmodal.php (shared drop modal, DHC_DROP()), ajax/dhc-claim-drop.php (the trust boundary -- floors, daily cap and tier tables live server-side), ajax/dhc-save-fighter.php, ajax/dhc-update-fighter.php (edit in place -- keeps serial/name/created_at, recomputes traits+score+hash; dhcfighters.php?edit=<id> reloads with dhcf_available()'s \$ignore_id so the Fighter's own traits are selectable again), ajax/dhc-rename-fighter.php, ajax/dhc-delete-fighter.php. Tables dhc_trait_drops (append-only ledger; also the source of newest_trait_at, which is why disassembly can hard-delete a Fighter without opening a monthly-board replay) and dhc_fighters (holds LIVE Fighters only -- disassembly deletes the row and frees its serial, and dhcf_next_serial() hands out the lowest unused number from DHCF_SERIAL_START); see dhcfighters-schema.md. Rarity tiers derive from on-chain frequency across the 226 minted DHC2 Fighters (policy b31a34ca2b08bfc905d2b630c9317d148554303fa7f0d605fd651cb5) via dhc/gen-rarity.py -> dhcrarity.php. Layering exceptions and assembly offsets are recorded in dhc/LAYER-MANIFEST.json (gitignored with the art). A TRAIT SET CAN ONLY EXIST ONCE: dhcf_hash_taken()/dhcf_hash_holder() are checked INSIDE the save and update transactions (outside is a race both writers win), and disassembly frees the combination. This REVERSES an earlier documented decision that nobody should ever be blocked -- the old reasoning was that a player holding one background, one torso and one head has exactly one possible character. That risk is real but small: a dressed Fighter is 1 of ~89 billion, the bare minimum build 1 of 36,750, so the refusal message names the holder and says to change any one piece. dhcf_is_first_build() is gone and the FIRST badge with it (gallery, roster, save/edit messages); DHCF_ORIGINALITY_BONUS is deliberately KEPT and now applies to every Fighter, because removing it would make every stored rarity_score stale until dhcf-rescore.php ran over the lot. A SLUG IS ONLY UNIQUE WITHIN A CATEGORY: nine of them are both a head and a torso (dh-alien-punk, golden-cyborg, silver-cyborg, mk100, mk200, c73, dh-cop-2, inferno-wasp-guardian, planet-8tz-specter) and are different traits with different art, different rarity and separate ledger rows. dhcf_committed() and dhcf_shortfall() therefore key on "category|slug", and blockedReason() in dhc-assembler.php compares s.dir (the category) and not the slot key -- weapon/weaponBack and effects1/effects2 share a dir, which is exactly when a second copy SHOULD be counted. Keying by slug alone made the alien punk head spend the alien punk torso. dhcf_category_of() is the same mistake preserved for callers that have nothing but a slug; prefer the category you already have. Tethered weapon halves (scythe/sash, krusher/sash) live in DHCF_TETHERED + dhcf_tethered_partner(); dhcf_award() awards BOTH halves via dhcf_commit_drop(), marking the second with DHCF_PAIRED_MARK in source_detail. THREE queries count the daily cap and all must exclude that mark or they disagree: dhcf_award()'s own check, dhcf_drops_today() (drives the "N of N left today" line), and ajax/dhc-claim-drop.php. The assembler's COUPLE table generates its mutual pairs from DHCF_TETHERED; the axe -> morning star link stays hand-written there because it is one-way UI convenience, not a tether. Head/headgear incompatibilities live in DHCF_HEADGEAR_EXCLUDED_BY_HEAD + dhcf_headgear_blocked() in dhcfighters-config.php and are enforced in FOUR places, all of which must stay in step: dhcf_layers() (every PHP renderer), paint() in dhc-assembler.php (the canvas keeps its own JS copy), blockedReason() (the picker, both directions), and dhcf_save_fighter()/dhcf_update_fighter() (the trust boundary -- a Fighter saved before a rule landed still holds the pairing, so renderers drop the layer rather than assume it cannot occur). FULL-SIZE DOWNLOAD (dhc-download.php, dhc-download-harness.php). dhcf_render_fighter() now takes a $size; the endpoint asks it for 1000, which is the size of every file under dhc/web/1000, and streams the PNG as an attachment. No second composer -- the reason is spelled out at length in dhc-compose.php's header and applies verbatim here. THE 500 KEEPS THE UNSUFFIXED CACHE NAME (f<serial>-<hash>.png; other sizes get '@<size>'): putting the size in every filename orphans every render already in dhcrenders/ and makes Discord, the Arena and the landing thumbnails each recompose a thousand-pixel stack. $size is clamped to 100..1000 -- above 1000 is upscaled blur and the memory to match. MEASURED: 1000px is 673 KB and 124 ms cold, 2 MB peak, then cached forever by traits hash. The only size-dependent arithmetic is DHCF_NUDGE * $size / 1000, and the harness catches a regression there by rendering both sizes and comparing the 1000 scaled down against the native 500 (correct: mean channel difference 0.07-0.68 of 255; nudge hardcoded back to 500: 2.46; threshold 1.5). THE ARM CLIP IS NOT POLICED BY THAT and the harness says so: DHCF_ONE_ARM_SPLIT has a deliberate ~200px tolerance window, so a wrong split still lands on plain torso (0.68 -> 0.75, inside noise). The filename rule lives in dhcf_download_filename() in the LIBRARY, not the endpoint, so it is testable without a web request -- an unnamed Fighter's display name IS its serial, so the naive form gives DHC2F0123-DHC2F0123.png. OWNER ONLY -- and it shipped PUBLIC first, which was wrong. The argument was that the Collection is public and the 1000px layers are already served to anyone who opens a Fighter; a flattened, named, ready-to-post picture of somebody's assembly is still theirs to hand out and not a stranger's to take. Signed-out requests get 403; another player's serial gets 404, NOT 403, because 403 confirms the serial exists. Ownership is the WHERE clause (`AND user_id = %d`), the same idiom as ajax/dhc-rename-fighter.php -- not a read then a compare. TWO FORMS: `?serial=` for a saved Fighter you own, and `?build=<json>` for the arrangement on the assembler canvas, which usually has no saved row at all. The build form is gated on dhcf_owned() -- NOT dhcf_available(), because a trait committed to another saved Fighter is still a trait you own and still something you are entitled to a picture of. THE BUILD FORM ALSO REQUIRES A WHOLE FIGHTER -- dhcf_missing_required(), the same DHCF_REQUIRED trio a save insists on. Without it ?build={"head":"x"} renders that head alone on a transparent 1000px canvas and the endpoint becomes a way to walk Maxingo's art out one clean layer at a time; reported by the user, not caught in review. The ownership check does NOT cover this -- every extraction build uses traits the player genuinely owns. dhc-assembler.php mirrors DHCF_REQUIRED so the button says what is missing instead of navigating into a refusal, exactly as dhcfighters.php's save button does; the server is the authority. The ?serial= form needs no such gate: those traits come from a row dhcf_save_fighter() already validated. An unsaved build is named DHC-Fighter-build-<6 hex of the traits>.png so repeats reuse the filename instead of piling up as '(1)', '(2)'. The endpoint reads $_SESSION with an isset() guard the ordinary pages skip: they can afford a warning, this one is streaming binary and one notice in front of the PNG is a corrupt download -- that exact warning is how the harness caught the missing session_start() in its own stub. PLACEMENT: the assembler bar, next to 'Copy link to this build', because both act on the canvas; $dhca_can_download is a caller-set flag so the SANDBOX does not get one (it unlocks every trait and the endpoint would refuse them). Plus a corner arrow on your own roster cards -- that row (Edit/Rename/Disassemble) already fills a 150px card exactly, measured, and a fourth control pushed it 7px over with Disassemble at min-content, so the arrow is an absolutely-positioned SIBLING of .art (.art is a button and buttons do not nest). NOT in dhcgallery.php; there is a comment there saying why, because it is the obvious place to put one back. THE ENDPOINT IS TESTED OVER REAL HTTP, because what breaks it is not reachable from a function call: the response headers, and whether any stray output got in front of the PNG. db.php runs with display_errors on and prints nothing today -- one echo or one notice turns every download into a corrupt file with no error anywhere -- so dhc-download.php buffers the include and the harness's stub db.php deliberately prints while loading. The DATABASE IS STUBBED BY SHADOWING db.php ON include_path: the endpoint says `include 'db.php'` with no leading dot, so a db.php in the temp dir wins; everything else is the real library, renderer, art and headers. FOUR TRAPS, all hit writing it: (1) shell_exec() BLOCKS on a backgrounded `php -S`, redirects and all -- it reads the pipe until EOF and the server never closes it; use proc_open with the descriptors on /dev/null. (2) $http_response_header is deprecated in 8.5 and prints a notice per request, so the request goes over a raw socket. (3) THE SERVER MUST BE STARTED WITH -d output_buffering=0. With the ini's buffering on, PHP buffers db.php's echo for free and deleting the endpoint's own ob_start() still passed -- measured, a false green. (4) PHP's + on arrays keeps the LEFT value, so `$plain + array('arms' => ...)` silently tested the plain arms; use array_merge. Reverts confirmed to fail it: ob_start() removed (11 checks), Content-Disposition dropped, the 404 guard removed, Content-Length wrong. COMIC COVERS AND DEMOTED COMPANIONS (DHCF_COVER_EFFECTS, DHCF_COMPANION_OVER_COVER; dhc-layerorder-harness.php). DHCF_COMPANION_UNDER moves a companion to just before Arms -- but the slot order is arms, effects1, effects2, head, headgear, so 'below Arms' unavoidably means below Effects too, and the comment only ever claimed the first. A comic cover therefore drew OVER Code Sea Predator while drawing UNDER every ordinary companion: the same frame, two opposite answers depending on which pet the Fighter carried. NO POSITION IN A FLAT STACK IS BELOW ARMS AND ABOVE EFFECTS, so the cover moves down to just before the companion rather than the companion moving up. Only when the cover is currently ABOVE the companion -- an undemoted companion is already last, and moving the cover to meet it would put the frame over the head. Consequence worth knowing: arms now draw over the frame in this pairing (they already did for head, headgear and weapon). DH VISION SHOULDER CAM CAME OFF DHCF_COMPANION_UNDER ENTIRELY, reported the same day: it was demoted because a shoulder mount drawn in front looks stuck to the outside of the character, but below Arms is also below Effects, head and headgear, and headgear drawn over a shoulder cam looks worse than the problem it solved. No stack position is under the arms and over the head, so it went back to the default (last). DHCF_COMPANION_UNDER now holds one entry, which makes two rows of dhc-layerorder-harness.php's matrix coincide -- it says so rather than letting them read as two cases. THEN THE OTHER HALF OF THE SAME REPORT: the cam must be above head/headgear AND under the arms, and no single stack position is both -- so DHCF_ARMS_OVER_COMPANION moves the ARMS to the very front when the cam is on, via dhcf_move_last(). It runs before the behind-torso rule (which would undo it) and skips arms in DHCF_ARMS_BEHIND_TORSO (accents flanking the body; hoisting them to the front is the opposite of the point). MEASURED: 16 of the 17 arm traits render differently with the rule on, so it is doing real work and not a no-op. WHAT IT COSTS: Arms then draws over head and headgear too; checked against the art (the arm traits hang at the sides and do not reach the face), and if a future arm raises a hand above the shoulder the fix is to take the cam off the list, not to stack a per-arm exception on a per-companion one. The harness now also asserts that every ALL-CAPS constant the extracted JS reads is defined in its prelude -- adding this rule made node die with a bare ReferenceError that said nothing about what was missing. RULE ORDER IN dhcf_layer_order() IS LOAD-BEARING: companion demotion, then arms-behind-torso, then this, then effects-behind-torso -- this one must run after the companion has moved (it targets where the companion landed) and before the behind-torso rule (which is more specific and must still win). MIRRORED IN THE CANVAS: layerOrder() in dhc-assembler.php is a second implementation that has drifted from PHP before (armsBehindTorso() once had an armless-variant escape hatch PHP did not), so dhc-layerorder-harness.php now brace-extracts the REAL layerOrder/armsBehindTorso/effectBehindTorso out of the page, feeds them the same json_encode'd constants the browser gets, and diffs them against dhcf_layer_order() over 14 trait sets; it also asserts the two base slot lists ($dhc_slots vs dhcf_slots()) are identical, that every DHCF_COMPANION_OVER_COVER entry is also in DHCF_COMPANION_UNDER, and that DHCF_COVER_EFFECTS matches the *comic-cover*.png files on disk. Needs node. TEST TRAP: PHP's + on arrays KEEPS the left value, so `$plain + array('arms' => ...)` silently tested the plain arms -- use array_merge. |
| games-dhc-fighters.md (rank preview) | Live rank while assembling | dhc-ranks.php (dhcf_rank_values / dhcf_rank_pool / dhcf_rank_of / dhcf_rank_build), dhcf_rank_axes() in dhcfighters-config.php, ajax/dhc-build-stats.php, the #ranks strip in dhc-assembler.php, dhc-ranks-harness.php. WHAT IT ANSWERS: saving SPENDS the traits, so "what did that piece actually do" was a question you could only ask afterwards. The strip shows where the build on the canvas would place on the four axes the Collection sorts by. ONE DEFINITION OF EACH AXIS: dhcgallery.php named Rarest/Deadliest/Toughest/Hardest hitting first and now READS them from dhcf_rank_axes(), and takes hp/pow/might from dhcf_rank_values() -- passing in the dhca_build_fighter() it already has, so it stays one pass per row. Deadliest = health x power, the Collection's own shorthand, not an engine stat. dhcf_rank_axes() lives in dhcfighters-config.php and NOT in dhc-ranks.php: the assembler needs the labels and already requires config, while dhc-ranks.php drags in the library and the Arena engine -- a caller that stubs dhcf_rarity() (the assembler's own harness does) then gets a fatal redeclare, which is how that was found. THE POOL IS EVERY SAVED FIGHTER read in full, because hp and pow are derived from traits at read time and no ORDER BY can answer 'how many are tougher' -- the same O(n) pass dhcgallery.php already makes per page view, but the canvas asks per keystroke, so it is cached as JSON under dhcrenders/ keyed on COUNT(*)+MAX(updated_at) (temp-then-rename; old pools swept after an hour). Ranking is COMPETITION ranking over a DESCENDING list -- ties take the best place -- by binary search, property-tested against a naive count over 6,000 random lookups with a deliberately small value range so ties are common. THE POOL USES STORED rarity_scores and the BUILD a freshly computed one, deliberately: the preview answers 'if I saved this now', and the fresh number is what would be saved. EDITING passes the Fighter's id so its own row is dropped from the pool, or it competes with itself. Client side: debounced 250ms (paint() runs on every pick) and sequence-guarded (the debounce does not stop two being in flight, and the slower must not overwrite the newer). CSS TRAP, hit: `.top` as the top-three modifier collides with flexbox.css's own `.top` (display:flex; gap:16px), which laid the cell's three lines out in a row -- and only on the cells in the top three. Renamed .rk-top. Caught by previewing against the real stylesheet; see the page-previews rule. ajax/dhc-build-stats.php does NO ownership check on purpose: it is arithmetic over a trait list plus counts from a public collection, spends nothing, and saving is where ownership is enforced. |
| realms.md (identity panel)          | Realm name, artwork, theme, faction | realms-identity.php, ajax/realm-identity.php, setRealmIdentity() in realms.php, realms-harness.php. Both dropdowns submitted a form -- the theme through a hidden #filterNFTsForm, the faction with an inline factionsForm.submit() -- so changing one reloaded the heaviest page on the platform to alter one image, and a rejection came back as alert() over the reloaded page. They POST to ajax/realm-identity.php now and report inline; a refusal RESTORES THE SELECT (data-was), or it reads as saved. The realm is taken from getRealmID() and is never a parameter, same rule as the upgrade endpoint, and the 0 (founder) theme is checked against the discord id server-side rather than just being absent from the markup. The POST handlers in realms.php stay as the no-JS path and because createRealm() shares those names. TWO SPECIFICITY FIGHTS WITH flexbox.css, both worth remembering: #filterNFTs is an ID there (190px wide, 20px bold, 30px tall) so a class selector loses and the Theme select rendered at twice the size of the Faction one with its label clipped -- matched as `.ri-controls #filterNFTs` (1-1-0) instead of reaching for !important; and `.dropdown` is `width:300px !important`, which nothing but !important beats, so these selects simply DO NOT carry that class. Also `.main { text-align:center }` leaked into the whole panel, the same trap the missions rebuild hit -- `.ri, .ri * { text-align:left }`. DEACTIVATE MOVED to the foot of the locations panel: in the header it sat under the fixed #burger-icon (50px + 10px padding, z-index 99, position:fixed top-right), so a destructive control was directly beneath a tap target on every phone. It does confirm, so a misclick was never instantly destructive. ITEM ICONS ARE CSS BACKGROUNDS now (.rl-ico-N, generated from realm_con_names()): the same seven files were fetched once in the strip and once per location, 56 requests for 7 images, and on mobile a different one failed to arrive on every load. A miss now leaves an empty slot rather than a broken-image glyph. REGRESSION CAUGHT BY THE HARNESS WHILE DOING THIS: the identity swap swallowed the entire #raids row, which would have killed the script block for the third time -- toggleSections() lists 'raids' and touches it unguarded. |
| realms.md (Manage modal styles)    | Realms: the seven Manage modals' CSS | realms.php's <style> (the .soldier*/.coffin*/.gear-*/.tower-* run), ajax/get-portal-report.php, get-barracks.php, get-armory.php, get-tower.php, get-crypt.php, get-eligible-nfts.php. SIXTEEN RULES WERE DELETED BY ACCIDENT IN 19ed0d81 and shipped: cutting the #quick-menu blocks out of this sheet took the adjacent soldier-card run with them. Nothing threw, no harness moved, and the page itself looked perfect -- every one of those classes renders ONLY inside a Manage modal. It surfaced from a player as "managing locations has blown out nft images, unusable on mobile": .soldier-nft-img had lost width:64px so a 1000px NFT drew at 1000px, and .soldiers-grid had lost display:grid so the cards stacked one per screen. The @media(max-width:500px) rule setting that grid to three columns SURVIVED the cut and was overriding a grid that no longer existed, which is why the source did not look obviously broken. realms-harness.php now scans every soldier/soldiers/coffin/gear/tower class the modal endpoints emit and asserts each has a rule in realms.php OR flexbox.css (.soldier-discharge-btn lives there), with .tower-pick explicitly exempt as a jQuery selector hook. TWO TRAPS IN WRITING THAT CHECK, both hit: rules whose whole body is a border-radius must be stripped first, or the squaring block's selector list makes a gutted class read as styled (.soldiers-stat passed that way); and the audit must read BOTH <style> blocks, since the loader has its own. |
| realms.md (squaring)                | Realms: square corners page-wide | realms.php's <style>. THE RADII WERE NEVER IN realms.php -- its own sheet had two rounded rules (the loader rail, twice). Everything round came from dist/flexbox.css, which every page shares: .content 10px, .button 0.5rem, .small-button 20px (a pill), .dropdown/#filterNFTs/#filterRealms 8px, .rc-* 10px, .rtc-* 6px, .raid-button 20px, modals 12px, .mc-* 10px. Squared by a scoped override block rather than by editing flexbox.css, which would reshape every page on the platform in one commit; missions.php set that precedent with `.main .content { border-radius: 0 }`. THE BLOCK MUST BE LAST IN THE SHEET: equal specificity means source order decides, and .soldier-card, .coffin-card and fifteen .rla-* rules are declared further down the same <style> -- with the block at the top .soldier-card still measured 8px. Two exceptions kept: anything at 50% is a circular avatar, and #rl-loader .rl-l-rail keeps 2px because that is a rounded line cap on a 3px bar, not a panel (realms-panel-harness.php names it explicitly). The .rla-* animation's timing, sequencing and markup are untouched; only its corners. THE SWEEP TOOK THE CARDS AND LEFT THE PILLS, which is most of what is on screen in the Attack list: .rtc-loc-pill (20px, a true pill), .rtc-balance-pill (12px), .rtc-loc-cat-boost (10px), .rtc-garrison-slot (5px) and the 14px trait thumbnails inside the chips (2px), plus .rtc-stat and .rtc-status-msg. All named in the block now. GUIDE AND DEACTIVATE were the same button side by side, which made the help invisible and the destructive action casual -- Guide now leads, marked as help ("? How Realms works"), and Deactivate is muted with a red hover. MANAGE CAME BACK as an explicit per-location button: it was removed when Stock and Manage together needed 357px in a 356px column, but Stock has since moved to the offer row, so the kit row has 136px spare. Label is just "Manage" (the card names the location two lines above) because "Manage Barracks" at 133px re-wrapped the row. The header stays clickable as a convenience; the button is the signpost, since a hyperlinked title is a weak affordance for the activities behind it. |
| realms-raids.md (raid lists)        | Compact raid rows + detail modal | getRaids() in db.php, the modal in realms.php (openRaidDetail/closeRaidDetail), realms-harness.php. Each card was a progress bar, realm name, date, a header with theme art, TWO full-bleed columns carrying their own background images and avatars, a stack of pills and an action row -- 400px+ per raid, which is why both sections shipped collapsible. Now: one ~79px row per raid (opponent, own odds or outcome, countdown, own modifiers, Replay/Retreat), measured 3 raids in 274px against 1,200px+. THE CARD IS NOT RE-RENDERED OR RE-FETCHED. getRaids() still builds it exactly as before and parks it in a hidden .rc-detail beside its row; the modal CLONES that node. No second copy of a 250-line renderer to keep in step -- the drift that had already bitten ajax/get-locations.php and ajax/get-realm.php. hidden plus the card's existing loading=lazy means an unopened card fetches no theme art at all (measured: 3 details, 1 image fetched). THE CLONE HAS ITS IDS STRIPPED, or every getElementById on the page could land in the copy rather than the original; countdowns still tick because the page's ticker walks .countdown[data-deadline] rather than ids. The row shows THIS player's odds and modifiers, not both sides': outgoing you are the attacker (100 - adj_threshold, offense tags), incoming the defender. array_unique on the tags -- Fast Forward can arrive both from the consumables list and from the duration inference that exists because FF is burned at inception and normally never stored. |
| realms.md (the map on a phone)      | Realms: the faction map below 768px | map.js (packFactions, MOBILE_BP, MOBILE_DESIGN_W), dist/map.css, realms.php. map.css used to open with `#map { display: none !important }` below 768px and that was CORRECT at the time: packFactions() sized containerW to vw*.92 with a 320 floor, every faction block is ceil(sqrt(n))*170 wide, so at 390px nothing could sit side by side. Measured on 52 realms across 10 factions: 542 x 4652 -- a twelve-screen ribbon that overflowed sideways too. Now it COMPOSES at a fixed MOBILE_DESIGN_W (1400) below MOBILE_BP, keeping the desktop shape (1132 x 2062), and the CSS scales that to the screen: 330 x 601 at the same phone. SIZE THE BOX, NOT JUST THE PICTURE: renderMap() gives #container an explicit INLINE width/height -- it has always been sized TO the map rather than BY it -- so scaling the SVG in CSS alone left the drawing at 330 x 601 inside a 1132 x 2062 box: 1471px of empty backdrop under the map on a 390px phone, reported as the background running on for multiples of the map's length. One scale factor (fitW/fitH) is now applied to the svg attributes AND to container.style, measured 621px total for the section against 2082 before. A red herring while chasing this: poking #container's style in the console appears to fix it, because that overwrites the inline height -- do not conclude from that that the CSS is at fault. THE POPUP FOLLOWS THE ZOOM: position:fixed measures against the LAYOUT viewport, so a pinch-zoomed player tapping a marker got an overlay spread across the whole unzoomed page, mostly off-screen. anchorPopup() pins #popup-overlay to window.visualViewport on open and on its resize/scroll while open, and hidePopup() hands sizing back to the sheet. NOT done by forcing a zoom-out: iOS exposes no API for that, the maximum-scale meta trick is flaky and global, and it would cost the player their place on the map every time. THE min-width:0 IN THAT MEDIA BLOCK IS LOAD-BEARING -- #map is a .row (display:flex) and #container is a flex item, both default to min-width:auto and refuse to shrink below the SVG's intrinsic width, so without it `width:100%` resolves against a parent that already grew to fit and the block measures 1132px inside a 390px page, i.e. does nothing. Page pinch-zoom is deliberately left alone (header.php sets no maximum-scale) since markers are ~20px at that scale. The packing, the markers, the popup and the raid animations are untouched. Map is LAST in the nav and no longer stapled under the realm panel. |
| realms.md (attack panel)           | Realms: the Attack list and its header | realms-attack.php (SHARED PARTIAL), realms.php, ajax/get-realms.php, db.php (getRealms), ajax/select-realms-filter.php. THE THIRD PANEL TO SHIP INVISIBLE: get-locations.php and get-realm.php each carried their own stale copy, and get-realms.php did too, so a redesign in realms.php was replaced by the old markup on the first refresh -- immediately, on desktop. The header markup is one partial now and realms-harness.php asserts both callers include it and neither writes its own name="filterRealms". LAYOUT: <h2>Realms</h2> sat OUTSIDE .content.realms while #filter-nfts floated right INSIDE it, so on a wide monitor the title and the Sort By control landed on different lines at opposite ends of a 1900px panel. They are one .ra-head row inside the panel now, the same shape .ri-head and .rl-head already use, stacking under 700px. filterRealms() is untouched: it hits ajax/select-realms-filter.php and replaces only #realms-list, so it never re-renders this header. |
| realms.md (page shell)              | Realms page: loader, panels, the script block | realms.php, realms-harness.php. THE BIG <SCRIPT> BLOCK RUNS ~1,078 TO ~2,618 AND HOLDS THE QUICK MENU, so a PHP notice printed anywhere inside it is a JS syntax error that stops the WHOLE thing -- the symptom is every panel rendering at once with no quick menu, which looks like a layout bug and is not. It happened twice in one afternoon pulling the locations panel into a partial: the panel had been assigning $realm_id and $levels at global scope and the rest of the page read them hundreds of lines later ($realm_id in the right column, $levels[1] INSIDE the script). A third: reading getLocationInfo($conn) at the point of use, ~800 lines after $conn->close(). AND IT IS INVISIBLE FROM THE PAGE -- a warning inside a <script> is not in document.body.innerText, so the browser reports zero warnings while the page is broken; check `typeof` a function defined in the block instead. realms-harness.php is static and covers the class: every variable realms.php reads must be assigned in realms.php first (allowlist for what db.php/skulliance.php/header.php/the partials provide), nothing may call anything with $conn after close (matched on shape, not a name list), the stylesheet must stay above the markup, every id in toggleSections()'s list must exist along with its -icon and vice versa, and the loader must be flushed before the work and dismissed after. It also found a bug older than the rebuild: $barracks_slots_open was assigned after the markup that prints it, so the Enlist modal always opened on "0 of 0 slots selected". LOADER: same pattern and same reasoning as missions.php (#rl-loader), printed immediately after header.php and @flush()ed before a single query; dismissed as the FIRST statement of the script block on purpose, so that if the block ever dies again the loader staying up is a visible symptom. RAID ROWS, three things reported together. (1) THE CAP NEVER CAPPED: getRaids() marks rows past the fifth with the `hidden` attribute, and hidden is enforced by a UA rule that ANY author `display` beats -- .rr sets display:flex -- so all ten rendered and "Show all 10" removed a button that did nothing. `.rr[hidden] { display:none }` fixes it; the lesson is that hidden is a silent API exactly like classList. (2) ONLY THE LEFT OF A PENDING ROW WAS CLICKABLE: .rr-main is flex:1 1 240px and a pending row also carries the boost pills, Replay AND Retreat, so the button got squeezed to the left quadrant; completed rows carry less, which is why it read as random. .rr-main::after stretches inset:0 over the row and .rr-tags/.rr-acts get z-index:1 to stay clickable, with .rr:hover signalling it. (3) THE HISTORY LINK IS GONE: built for one player who is no longer active, pointing at raids.php -- the same renderer with the LIMIT 10 lifted, so Show-all already covers everything but rows 11+. raids.php and the $history flag STAY (still listed in dhcfighters-config.php); deleting a page is a separate decision from removing a link to it. STATS PANEL REMOVED: #stats held getTotalFactionRaids() and getTotalRaids(), a month-to-date scoreboard nobody read. The raid LIST (#raids) is operational and stays. Both functions are left in db.php -- removing a reporting function is a separate decision from removing the panel. SECTION NAV REPLACED THE BOTTOM BAR: #quick-menu was five position:fixed icons at the bottom of the screen and it was reported four times in four different words -- it bounced vertically between panels (it reacted to the height of the page: no content, bar high; content loaded, bar dropped), it sat over the iOS home indicator, it left a dead band under itself in standalone, and the fix for the PWA spread the icons out in the browser. It is a sticky .rl-nav at the TOP now, the same control and the same CSS as missions.php's .ms-nav: top:env(safe-area-inset-top) so it clears the status bar, z-index 20 (higher competes with the platform's own overlays -- 90 beat the opened burger), padding-right:56px under 700px to dodge the burger. Nothing below the fold, no indicator to dodge, no fixed positioning to lose. TWO THINGS THE FIRST CUT GOT WRONG, both found from a phone screen recording and both invisible in the nav's own rules. (1) IT UNPINNED ONE SCREENFUL DOWN: flexbox.css sets .container height:100%, the nav is a direct child of it, and a position:sticky box is clamped to its containing block -- so it pinned for exactly one viewport and then scrolled away with the page. Measured against the real stylesheet: container box 1323px vs document 3043px, nav at -330px by 1600px of scroll. realms.php now overrides `.container { height: auto; }`; min-height:100% (which is what actually keeps a short page filling the screen) stays, and .container paints no background, so nothing else changes. missions.php never showed this because .ms-nav sits inside .main, a flex child with auto height -- the reason to check a sticky element's CONTAINING BLOCK and not just its own CSS. (2) CONTENT SCROLLED THROUGH THE STATUS BAR: body carries padding-top:env(safe-area-inset-top) and that padding scrolls away, so pinning at top:env(inset) left a transparent band above the bar that location rows rode up into, colliding with the clock and the battery. It pins at top:0 now and pays the inset as its own padding, so the bar's background owns that band at every scroll position. ORDER IS ACTION FIRST, set by the player: Locations, Attack, Raids, Realm, Map -- the two you act in lead and are ADJACENT, which on a phone makes them one swipe apart; the Map is pure reference and trails. Reads Locations/Attack/Raids/Realm on a phone (Map hidden) and Locations/Attack/Raids/Map on a desktop (Realm hidden). THE ICON IDS WENT WITH IT and that is the hazard: the top of the script block used to be ~20 UNGUARDED getElementById('<sec>-icon') calls, so one left behind throws on the first statement and takes all 1,500 lines with it. Everything goes through rlNavLink() / rlMark() / rlPanel() now, all three of which no-op on a missing element, and realms-harness.php asserts that no '<sec>-icon' dereference exists and that every toggleSections() id has a data-sec link and vice versa. THE BURGER LINES UP WITH THE BAR: #burger-icon was 45px tall pinned at the inset, against a 38-40px bar, so it hung 4-6px past the bar's bottom border on both pages. It is a 50x39 box now (39 of bar + a 10px right gutter) with object-fit:contain and object-position:right center, which puts its centre on the links' centre to the pixel (measured 79 vs 79 on realms, 79 vs 78 on missions) and moves it nowhere sideways. The old 5px padding-top is gone because the centring replaced it; menu.png carries 23% of its own vertical padding (ink bbox 120-392 of 512), so the glyph still clears the status bar by ~9px. Global, in flexbox.css's <=700px branch. NO DEAD BAND AT REST ON REALMS: the bar is the FIRST thing on that page, so body's padding-top:env(inset) put an empty inset-tall band above it until you scrolled and it pinned. .rl-nav carries margin-top:-env(inset) -- IN the margin shorthand, because written as a separate margin-top above `margin: 0 0 14px` it is silently reset -- so its resting position IS its pinned position. Missions does NOT want this: .ms-head sits above its nav, so there the padding is separating a real panel from the status bar. SWIPE (swipe-nav.js, shared with missions.php): on a phone, swipe left/right moves between sections. It CLICKS THE NEXT VISIBLE NAV LINK rather than calling the page's switcher, which is why one file serves both pages -- realms' link swaps a panel and returns false, missions' link is a real #hash jump plus its scroll-spy pin, and neither page grew a second idea of what a section change is. Touch listeners only (a mouse never fires it) plus a width check. What it REFUSES is the substance: >1 touch, <55px travel, vertical drift over 0.7*dx, over 800ms, an open #navbar.show-menu (guarded in the shared file, it is platform-wide), the caller's blocked() (realms: any visible .modal/.popup-overlay/#raid-detail-*/#raid-anim-overlay; missions: #ms-drawer not hidden), and any ancestor that is a field or scrolls sideways -- which is what keeps it off the nav strip itself, the map's pan surface and wide tables. Ends are walls, not a wrap. A link hidden at this width has no offsetParent and is skipped, so the phone swipe order matches the phone nav. swipe-nav-harness.js (node, DOM stub, no jsdom) drives the real file through all fifteen cases and was verified to fail for each of: flipped direction, counting hidden links, wrapping at the ends, and dropping any one of the five refusals. |
| realms-locations.md (panel)         | Locations panel rebuild | realms-lib.php, realms-locations.php (the one partial), realms.php, ajax/get-locations.php, realms-panel-harness.php. SCOPE: locations list + upgrade flow only -- the map and the soldier battle animations were explicitly left alone, as were raids, soldiers, factions and the seven Manage modals. ONE PARTIAL: realms.php and ajax/get-locations.php each carried a near-verbatim copy of the panel and had already drifted (the page had a Guide button, the refresh did not). THE MAINTAIN BUTTON IS GONE at level >= REALM_UPGRADE_CEILING -- see the upgrades row below for why it could only cost the player. LABELS NOW MATCH BEHAVIOUR: consumable 6 is named "Double Rewards" and on a location is a SHIELD (startRaid reads it via hasDoubleRewardsShield and spends it to absorb a hit); consumable 7 does nothing until EVERY location of that side has one (hasRandomReward), so a lone copy is annotated rather than shown as active -- realm_annotate_random_reward() is separate from realm_location_panel() precisely so it can be tested without a database. The panel also prints the OFFENSE/DEFENSE averages getLocationSuccessRateBoost() actually uses; per-location +10% never meant raids were at +10%. THE BOOST TABLE EXISTS THREE TIMES (db.php's raid maths, realms-lib.php, skulliance.js's post-equip redraw) and the harness diffs all three. THE GLOBAL POINTS LOCK IS REMOVED: togglePointsButtons() hid the points button on all seven locations the moment you pressed one, because the dropdowns were priced at page render and nothing re-checked at spend time; realmUpgradeQuote() re-prices and re-checks now, so a stale choice is refused instead. The function is kept as a no-op for callers. MARKUP HOOKS: a large amount of existing JS reaches into this panel by id and fails SILENTLY when one moves -- the harness renders the real partial and checks 24 of them. Two were broken during the rebuild and caught this way: _syncLocConsumableSlots() looked for .loc-con-badge (now an <i>) and _updateLocationStatusLabels() wrote into #loc-status-N (now #loc-sub-N). ui-selectors.php flagged a third, a genuine one: the rewrite of get-locations.php dropped its whole Create Your Realm branch. CSS is namespaced .rl-* because flexbox.css already owns names like .top. MEASURED: seven 28px slots plus Stock and Manage needed 357px in a 356px column, so the slots wrapped and every card grew a row -- Manage became the card header (click the location to manage it, seven buttons removed) and Stock moved onto the offer row. Cards went from ~178px to ~141px. |
| realms-locations.md (upgrades)      | Location upgrade pricing | realmUpgradeQuote() in db.php, ajax/upgrade-realm-location.php, ajax/points-option.php, ajax/get-locations.php, realms.php, realms-upgrade-harness.php. SECURITY FIX, found while surveying Realms for a redesign. ajax/upgrade-realm-location.php took realm_id, duration, cost AND project_id off the QUERY STRING and spent them; its own comment said "need to double check duration and cost in case someone tries to manually override these variables in the JS function" and the check was never written. upgradeRealmLocation() intval'd them and called updateBalance($conn,$user,$project_id,-$cost) -- and updateBalance() has no floor and no sign check, it writes $subtotal + $current_balance. So: cost=0 bought any level free; cost=NEGATIVE credited the account, because -$cost of a negative is a credit; duration jumped straight to the cap; realm_id was never compared to the session; project_id chose any currency. realmUpgradeQuote($conn, $location_id, $project_id) is the single pricer now: the realm comes from getRealmID() and is NEVER a parameter, the location must belong to it, duration is the level ladder (>10 and ==10 both quote 10, else level+1), cost is duration*100 at the location's own currency or x$points_multiplier for any other, the balance must cover it, and an in-progress upgrade still refuses. The endpoint accepts and IGNORES the old parameters so an un-refreshed page keeps working. All three renderers now quote from the same function -- each used to carry its own copy of the three-line ladder, and the endpoint's copy was decorative. realms-upgrade-harness.php runs the real function against a stub connection over the whole level ladder, the partner-points multiplier, a location outside the realm, project 15, a non-existent project, an unaffordable upgrade, an in-progress lock and a signed-out caller; the structural half asserts no file prices it itself again and the endpoint reads none of cost/duration/realm_id. |
| games-dhc-arena.md *(new)*          | DHC Arena: Crew vs Crew puzzle battler | dhcarena.php (the page: shell, Crew picker, rival list, ladder, board markup, and the client that ANIMATES a server timeline and decides nothing; the picker pages at 12 and the roster on dhcfighters.php does the same -- off-page cards are display:none, NOT removed, because a browser skips image fetches inside a display:none subtree and because the Arena's picks have to survive a page change), ajax/dhcarena-action.php (the trust boundary -- start/move/resume), dhcarena-roles.php (WHAT EACH INDIVIDUAL TRAIT DOES, inferred from its own label: optic->crit, armour->resistance, weapon->power, energy->charge rate, beast->companion assist, plain->a little health. The SLOT is too blunt -- 13 of 32 headgear are helmets and 10 are optics, opposite jobs on one hook. Keyword order is the design and is load-bearing: 'blaster' beats 'arms', 'helmet' beats 'cyber', specific before generic. Two traps already hit: 'explorer' matched a helmet, and 'demon' matched love-demonstration, so short risky keywords carry a hyphen. A worn slot cannot hold a BEAST -- an alien skull on your head is armour. DHCA_ROLE_OVERRIDE records human corrections and beats inference. THE ARENA is inferred the same way: dhca_terrain_for() reads the defender's front-rank BACKGROUND label, replacing dhca_hash($slug)%5 which was stable but arbitrary and could caption a screen of fire as cover. dhca_terrain_name() names the arena: backgrounds that already read as a place keep their own name, the rest are WRITTEN in dhca_terrain_names() and every written one keeps a word from the original slug, so a player who knows the art still recognises it. The note strings in dhca_terrains() state real numbers and must be edited whenever the multiplier beside them is -- guard said "50% more" for a while after the code dropped to 1.3. Watch the DISTRIBUTION when editing these rules: guard battles run ~25 turns against 16-18 everywhere else, so putting too many backgrounds in it visibly lengthens the game -- an early draft gave it 12 of 42 and the average battle went from 27.9 to 33.9 turns), dhcarena-engine.php (pure rules, no \$conn and no \$_SESSION, so it can be run headless -- mirrors cryptconquest-engine.php; DHCA_N=7, DHCA_HP_BASE=320, DHCA_POWER_BASE=20, DHCA_VARIANCE=0.6 which is the §4 lever that keeps rarity from deciding a fight, DHCA_SURGE_MAX=10, DHCA_EXTRA_TURN=5, DHCA_MULTI_BONUS=1.30 at 6 gems and DHCA_MULTI_BIG=1.90 at 9, DHCA_BLAST_CAP=4, DHCA_BLAST_SCALE=0.45; dhca_play() is the ONE entry point and returns false on an illegal move), dhcarena-lib.php (persistence and economy: DHCA_CREW_SIZE=3, DHCA_DAILY_BATTLES=6, DHCA_BENCH_BASE_H=4, DHCA_BENCH_LOSS_MIN=4 to DHCA_BENCH_LOSS_MAX=12; the allowance and the one-reward-per-opponent-per-day rule are COUNTED from the ledger, never stored; ONE OPEN BATTLE AT A TIME via dhca_open_battle() -- without it, abandoning a losing battle skips dhca_finish() and therefore the bench entirely, which makes the only real cost in the game optional, and dhca_sweep_stale() forfeits anything untouched for DHCA_STALE_H=6 hours as a DEFEAT so the rule can never lock somebody out permanently), LIVE MATCHES (§8d) are dhcarena-live.php + ajax/dhcarena-live.php + dhc_arena_live, and the ISOLATION IS THE DESIGN: they touch exactly one table and never call dhca_finish(), dhca_bench(), dhca_record() or dhca_pay(), so 'live never pays' is not a condition anyone can add an `unless` to -- there is no code path from a live battle to the economy. A flag on dhc_arena_battles was rejected for exactly that reason, and because dhca_open_battle() and dhca_sweep_stale() key on dhc_arena_state.user_id with outcome=0, so a live battle stored there would block the player's ranked battle AND be force-forfeited as a DEFEAT at 6h, benching Fighters over a match with no stake. No ladder, no traits, no Fighter record, no bench, no allowance in either direction, and RECOVERING FIGHTERS MAY PLAY, and the PICKER has to agree or the rule is unreachable: the card click used to return early on data-ok!=1, so the server accepted a resting Crew that the UI would not let you assemble. Selection is now free and the ACTION is gated -- aStart disables on any resting pick and says how many, aChallenge does not -- which is also what makes the allowance discoverable instead of invisible (dhcal_pick() deliberately does not check availability -- the bench prices ranked battles and this has no price). PERSPECTIVE IS FLIPPED SERVER-SIDE: the engine has one 'mine' and one 'foes' but both players are 'mine' to themselves, so state is stored host-as-mine and dhcal_view() swaps mine/foes, turn, over and every fx event's side for the guest. The BOARD is not transformed -- dhca_fighter_for_gem() resolves a gem to a RANK within the asking side, so the 7x7 is symmetric -- and the client never learns which seat it is in, so the drag guard (S.turn !== 'mine'), the turn flag and the enemy mirroring all work untouched. Flipping twice is the identity and the harness asserts it. A POLLER GETS THE MOVES IT MISSED: each applied move's fx is kept under meta.tl[seq] and a poll asks 'since'; only DHCAL_TL_KEEP=8 are retained, and a client further behind resyncs without animation rather than letting the state JSON grow with the battle. DHCAL_TURN_S=90 hands a stalled turn to the AI (the same answer for a disconnect: a turn is lost, never a match), DHCAL_INVITE_S=600, DHCAL_IDLE_S=1800. THE CHALLENGE IS ANNOUNCED AND PINGS THE INVITEE -- without it a challenge only reaches somebody already sitting on the Arena page, which is nobody; the ping is the invitation. Channel, not DM (other people knowing is half the point), and the mention goes in discordmsg()'s top-level $content because a mention inside an embed notifies no one. The embed features the challenger's BACK Fighter (end($crew) -- formation order is pick order), because the back rank is the one only a match of 5 reaches and so is the threat; the front Fighter is the one you are meant to get through. Avatar stays the thumbnail, Fighter takes the image, same split as dhca_announce(). serial/display are read defensively: a PHP warning here would print ahead of the endpoint's JSON and break the client's parse, and try/catch does not cover a warning. Rate limited by DHCAL_ANNOUNCE_GAP_S=180, read BEFORE the INSERT so the new row is not itself the 'recent' one; only the post is skipped, never the match. dhcal_finish() announces only if affected_rows -- two clients finishing the same battle must not post twice. Both players are pinged, unlike a ranked attack, because both chose this. Tests: dhcarena-live-harness.php (pure: the flip, seats, clocks, the bounded timeline tail, and 25 whole matches played as two humans with no AI). dhcgallery.php IS ALSO PUBLIC (same conditional-gate pattern as dhcarena.php: restore the cookie, $dhcg_guest, include verify.php+skulliance.php only for a member). It needed almost nothing else -- $dhcg_user was already guarded at all three use sites -- beyond hiding the Mine filter and repointing the one link to dhcfighters.php, which is still gated. It carries canonical/OG/Twitter meta whose TITLE AND DESCRIPTION FOLLOW THE TIER FILTER, because a filtered view is the unit people share; canonical stays the bare page so a thousand filter permutations do not each claim to be a page. dhc-assembler.php IS INCLUDED AT GLOBAL SCOPE, so any bare variable it leaves behind is a global by the time header.php runs: it leaked $name (a trait's display name) and dhcsandbox.php -- the one page that includes it BEFORE the header -- printed "U. Vigilance Device #2" where the player's username goes. dhcfighters.php escaped only because it includes the assembler after the header. Prefix every new variable in that file. dhc-assembler-harness.php guards it (and the mode/standalone matrix, and that every slot still has exactly one tab); it runs each case in a SUBPROCESS because dhc_title() is declared unguarded and the file can only be included once per process. dhcsandbox.php IS NOW A PLATFORM PAGE, not a standalone one: it takes db.php + header.php and the Skulliance palette, is public by the same conditional-gate pattern, and is in the strip AND the nav (as 'Sandbox Experiment'). That needed $dhca_mode SPLIT IN TWO, because it was doing three jobs at once: $dhca_mode is now BEHAVIOUR ('sandbox' = every trait + draggable layers, 'fighters' = owned only + fixed) and $dhca_standalone is SKIN AND LAYOUT (true = DHC ash/ochre and fills the viewport with its own <body>, false = navy and sized by its contents). It defaults to ($dhca_mode === 'sandbox') so anything setting only the mode is unchanged. Verified matrix: sandbox+standalone = ash/ownsBody/reorder, sandbox+embedded = navy/no-body/reorder, fighters+embedded = navy/no-body/no-reorder. Recoupling them would silently give the integrated sandbox its old skin back. Note the assembler's whole output -- CSS included -- is suppressed when $dhc_base is '' (no trait art), so a render with no art uploaded cannot be used to check its palette. dhc-nav.php is the strip across DHC Fighters / the Collection / the Arena / the Sandbox -- ONE file included three times ($dhcnav_at names the current page) so it cannot drift into three strips; dhcgallery.php was not in the platform nav at all, which is what made the Collection hunt-for-a-link. The per-item gated flag in $dhcnav_items is the single place that decides, so opening another page to the public is one 1 changed to a 0 (the Collection already was). Pass $dhcnav_guest on any page a guest can reach: it is public while the other two include skulliance.php, so a guest's links must go to index.php, never to a page that redirects them to error.php. TAB ORDER IS NOT DRAW ORDER in dhc-assembler.php: $dhc_slots is the Z-ORDER and layerOrder() starts from it, so reordering it to suit the picker silently changes how every Fighter composites -- $dhc_tab_order is a separate list for the tabs (torso, head, background, effects1, effects2, arms, weapon, weaponBack, headgear, companion) and the JS appends anything it forgets in draw order so a new slot cannot become unreachable. THE PAGE IS PUBLIC (and that is the point): dhcarena.php does NOT include skulliance.php for an anonymous visitor -- that file redirects them to error.php, which is the wall being removed. It restores the SessionCookie itself (merge, never assign) to decide, then includes verify.php + skulliance.php ONLY for a member, because everything ranked depends on them: verify.php defines checkUser() and skulliance.php calls it to resolve user_id, so dropping the pair outright would make a signed-in player look like a guest and lose their Crew. $dhca_guest gates the markup: a how-to-play panel in place of the Crew picker, four stat tiles either way (the phone breakpoint lays .a-stats out as repeat(4,...), so a different count collapses it), no 'nobody else has a Crew yet' line (a guest's empty $foesList means no user_id, NOT an empty Arena -- printing it would be a lie about how busy the game is on the page built to promote it), and GUEST=true in the client, which deals a practice battle on load and skips the resume call because that endpoint is behind the gate. Public wording avoids chain and token language per the funnel note -- 'players', not 'stakers'. The page carries canonical/OG/Twitter meta via $extra_head + $page_title_override (absolute URLs are correct in SEO markup only, never in a clickable link -- host-only cookies), and og:image is the platform's generic card until an Arena screenshot is FTP'd to images/dhcarena.png. The end-card X SHARE came back with it: it was pulled purely because the link was a login prompt that X could not scrape, and both halves are now fixed. dhcarena-practice.php + ajax/dhcarena-practice.php (PRACTICE: random Crews, nothing at stake. Runs the REAL engine, because two implementations of one game drift and the one that drifts is the one nobody is scoring. STATELESS -- no table, no session, no user_id, and NO db.php or skulliance.php in the endpoint, which is what will let a public page use it; the client holds a spec (seed + both Crews' traits + the PLAYER's slides) and the server replays it from scratch each move. ONLY PLAYER MOVES ARE RECORDED: dhca_ai_move() consumes battle randomness when it picks, so replaying a stored AI slide skips that draw and the board diverges from the next move on -- the defence must be re-derived. Nothing may consume randomness outside dhca_play() for the same reason, which is why the practice AI loop does NOT reshuffle the way dhca_move() does), dhcarena-harness.php (CLI; §4 commons-vs-legendaries is not optional -- commons must keep winning about 70%), TWO SKINS: the selection screen wears the Skulliance palette sampled from the platform stylesheet (#07111d / #00c8a0 / #7a9eb0) and the platform typeface, and is written against dhcfighters.php's idioms -- same masthead, stat tiles and bordered panel with a caption bar; #arenaBattle overrides the whole palette with the game's, because a board with five gem colours, damage reds and shield blues cannot be recoloured to a UI palette without losing what the colours are for. Same call dhc-assembler.php makes about the sandbox. dhcarena-schema.md (run once by hand, nothing in the app creates tables: dhc_arena_battles append-only with seed+moves so any battle replays exactly, dhc_arena_state the live board, dhc_arena_fighters the availability window, the ko flag and the record -- ko drives "resurrecting" vs "recovering" and CANNOT be inferred from the clock, since the remaining time also depends on roster depth; it was added after first release, so there is an ALTER at the top of the schema doc), dhcfighters-config.php DHCF_GAMES['arena'] (wildcard, gated, bands 1/3/6 on the rarity gap) and DHCF_FLOORS['arena']=0, db.php checkDHCArenaLeaderboard() + \$SKULLIANCE_BOARDS['dhcarena'] + the refreshLeaderboardSnapshots dispatch, leaderboards.php (two guards and two switches), rewards.php `?dhcarena=1` (MONTHLY cron, 250,000 CARBON; it settles last month BY SEASON NAME and there is no rewarded flag to close, so running it twice for the same month pays twice), header.php Play menu, launchpad.php tile, webhooks.php `dhcarena` channel (getDHCArenaWebhook(), function_exists-guarded because it fires from inside dhca_finish() AFTER the bench, the record and the trait are already written -- a fatal there would take down the request carrying the player their own result). dhcarena-prototype.php is the throwaway it grew out of -- kept, deliberately unlisted, touches no database. Design decisions and their reasons: dhcarena.md. THE HANDOVER IS ANNOUNCED, NOT JUST PAUSED: a defending slide fires banner('THEIR MOVE', 10) -- the highest priority on the board, because it is the CONTEXT for everything after it -- holds FOE_LEAD=1000ms before the board moves (was 640, which players read as their own move or as a glitch damaging them), and only the FIRST slide of an exchange announces it; later ones use FOE_LEAD_AGAIN=700 and lean on 'again', which now carries a SIDE so it can say THEY GO AGAIN vs YOU GO AGAIN instead of a bare ambiguous AGAIN. Costs ~260ms median / ~300ms mean on a turn (2730->2990 median, measured). .boardwrap.foeturn is the standing state for their whole exchange and now pulses instead of being a 55%-opacity 1px ring nobody saw. NOTE the guest-wait client test asserts the flag AGREES WITH THE PAYLOAD rather than always expecting 'your move': dhcal_challenge() seeds with random_int(), so some runs deal the host a match of 5 and an extra turn, and a fixed expectation makes the test pass or fail on the dice. ONE BANNER, MANY ANNOUNCEMENTS: a single wave emits chain, multi, detonation and bomb-armed in the SAME tick (every handler returns a zero delay, because they are one simultaneous event), so banner() ranks them instead of letting the last writer win -- the last writer is `arm`, which was hiding 56% of multi-matches and 61% of MEGA multi-matches behind "BOMB ARMED". Any new banner() call needs a priority or it will silently lose to one. `animation` IS ONE PROPERTY, so a cell-level animation is all-or-nothing: .cell.drop replaced the board bomb's glow pulse outright (same specificity, declared later) and .drop lands on every cell on every repaint, which left the LOUDER bomb as the only one that stopped moving. Any new .cell animation must be composed with the bomb pulse, not layered over it -- see .cell.drop.bomb2. clip-path IS APPLIED AFTER filter ON THE SAME ELEMENT, so a drop-shadow on .g is clipped away by .g's own hexagon or shield -- the shadow must be cast by an UNCLIPPED ANCESTOR, and it cannot be .cell because .cell paints an opaque square and the shadow would trace that. Hence .bglow, a wrapper emitted only for bomb cells. box-shadow FOLLOWS THE BORDER BOX, filter: drop-shadow() FOLLOWS THE ALPHA. The Charge and Shield gems are clip-paths, so any ring or glow given to them as a box-shadow is clipped away and silently absent -- a diamond is a rotate, not a clip, which is why it kept its ring while they lost theirs. Bomb rings and haloes are drop-shadows on .g for that reason, and they hug the gem instead of lighting the square tile behind it. Note the specificity trap that comes with it: .cell.di.bomb .g carries an extra class, so a plain .cell.blast .g rule LOSES to it and a bomb on a violet tile played its idle pulse through its own explosion; the blast rules are written specific enough to win. A DIAMOND IS A TRANSFORM, so any keyframe animating `transform` on .cell.di .g must carry rotate(45deg) itself or the gem flattens into a square mid-animation -- see @keyframes bmbDi. Setting animation:none is the wrong escape: it leaves violet the one shape on the board that does not move. EVERY BOMB ALSO PULSES AT THE CELL, not only at the gem: .cell.di.bomb .g sets animation:none because a diamond's 45deg rotation and a scale cannot share one transform, so any pulse living on .g is silently absent on violet tiles. A BOMB'S GLOW GOES ON .cell, NEVER ON .g: the Charge and Shield gems are clip-path'd into a hexagon and a shield, and a clip-path clips a box-shadow dead -- so a glow on .g silently vanishes on two of the five gem colours. Also never light anything in var(--ochre) on the board: inside #arenaBattle it is #f5a623, byte for byte the amber gem, so an ochre glow on an amber tile is the tile's own colour. BOMBS CHAIN AND THE CHAIN IS WHAT PAYS: a blast reaching a live bomb queues it (this always worked), but the payoff did not exist -- blasts were 7.7% of all damage and DHCA_BLAST_CAP=4 meant a blast resolved as a match of 4, which reaches the MID rank, so no number of bombs could ever touch the back rank. DHCA_BLAST_CHAIN scales the whole detonation by bomb count and the reach cap climbs to DHCA_BLAST_CAP_MAX at 3+, giving 10/23/42/85/116 average damage for 1/2/3/4/5 bombs at DHCA_BLAST_CHAIN=0.30 (tuned down from 0.40 after play, where a chain was carrying whole battles; the REACH change is the part that mattered and is untouched). The single bomb is deliberately unchanged; only the chain escalates. BOMBS: two invariants, both learned the hard way, both audited by a count of armed-minus-detonated-versus-on-board. (1) A not-yet-live (negative) bomb's cell is dropped from EVERY wave's clear, not just the wave that armed it -- dhca_collapse() only carries a bomb along if its gem survived, so a bomb whose cell cleared on a later link of the same cascade vanished with no detonation and nothing in the log. (2) One bomb per cell: lastTo does not change for a whole move and a bomb's cell deliberately survives clearing, so the same cell gets chosen twice in a cascade and the second write erased the first -- two bombs earned, one delivered. Where every cell of a run is taken the existing bomb is UPGRADED, and an upgrade must not increment stats['bombs'] or the audit reports phantom losses. THE COLLAPSE VARIANTS ARE PINNED, NOT COUNTED: disA falls 28% and disB falls 24%, so which variant a layer gets decides whether pieces CONVERGE or SEPARATE. The head must fall at least as far as the torso or a gap opens at the neck. Grouping headgear with head once shifted every index below it by one, silently swapping half the body onto the other variant and dropping the torso further than the head. Each group now states its own `alt` rather than taking whatever the running index lands on. HEADGEAR MOVES WITH THE HEAD, ALWAYS. Every animation that touches a Fighter's layers takes a GROUP, never a slot -- layerAnim() for the jolt, DEATH_ORDER for the collapse -- because headgear sits ON the head and separate delays or opposite spins slide the helmet off the skull. This has been reported twice, once for the hit jolt and once for the collapse; a third animation that forgets it will look like the art is broken rather than like a bug. THE END CARD LIVES INSIDE .boardwrap, not beside .arena. It is position:absolute;inset:0, and as a sibling of .arena it had NO positioned ancestor, so it anchored to the top of the DOCUMENT and covered the first screenful of it -- invisible on desktop, where the battle is about one screen tall, and wrong on a phone, where the stacked layout puts the enemy Crew and the board in exactly that screenful and the card hid both the moment a battle ended. .boardwrap is already position:relative for the combo banner. THE DRAG PREVIEW IS THE BOARD until the server answers: a committed slide is snapped onto its target cell and LEFT there by snapPreview(), never cleared, because the authoritative board is a round trip away and clearing it first made every accepted move spring home, pause, then jump to the match. Only a no-match slide springs back, and every failure path (refusal, timeout, dropped request) must repaint -- otherwise the board keeps showing a move that did not happen. THE GEMS USE THE PLATFORM ICON SET, not emoji: every kit in dhca_kits() carries an `icon` naming a file in icons/ (white line art on alpha, the same set Monstrocity and Crypt Crawl draw from -- fist, tactical-katana, blood, bone, sniper-rifle, machine-gun, melee, first-attack, plus titanium-armor for Shield, special-attack for Charge, grenade for the CROSS bomb and demolition for the BOARD bomb -- two icons, not one, because ring colour alone is too thin a signal for the difference between clearing a row and clearing the board). Emoji stay as the fallback and for log lines and Discord, where an image cannot go. Drawn as a CSS MASK on the span that already exists, never as an <img>: the mask inherits currentColor so a chip greys out and a token desaturates with everything around it, and the board rewrites all 49 cells on every drop, so an element per cell would push 49 new nodes through every cascade. icons/ is empty in the repo -- images ship by FTP -- so a new icon must be curl-verified before it is referenced. THE CLIENT MUST NOT GROW RULES. It sends 'slide A to B' and plays back the fx timeline the engine emits (slide/wave/multi/act/hit/shielded/heal/shield/erupt/ko/boom/arm/drop/again/reshuffle/hurrah); the only rule duplicated in JS is 'does this slide match', purely to refuse a free move without a round trip. Adding a second would put a number on the board that the server never agreed to. PUBLIC LANDING: dhcgame.php covers all three DHC pages (Fighters / Collection / Arena) in the match3rpg.php mould, with one deliberate difference: NO SESSION AT ALL. The other landings call session_start() only to decide whether to show a back-to-Dashboard button; this page has no such button, and session_start() writes a session file for every cookieless visitor -- every crawler -- which is the cost db.php's own comment documents. Nothing here reads $_SESSION, so nothing starts one. NO db.php either (that include is what kicked stakers out of the other landing pages), relative hrefs only, absolute URLs in SEO markup alone. Its Fighter marquee shows REAL saved Fighters via hp_dhc_fighters() in homepage-data.php, which opens its own short-timeout connection and touches no session. THE PICTURES ARE DERIVATIVES OF THE CANONICAL RENDER, never a second composer: dhc_fighter_thumb() in dhc-compose.php calls dhcf_render_fighter() (the Discord one, which knows armless torsos, the single-arm hybrid clip and DHCF_NUDGE) and downscales its 500px PNG to a 200px JPEG -- ~250KB to ~13KB, and a wall of 28 would otherwise be 7MB. dhcf_render_fighter() gained an is_file() short-circuit for this: it recomposed on every call, which was fine for one Discord post per save and not for a page asking for 28 at once. THREE MARQUEE TRAPS, all measured: loading="lazy" is useless in a marquee (a lazy image loads from its LAYOUT position and a card arrives by transform, so 88 of 384 never loaded and empty cards slid into view) -- eager is affordable only because the repeats share URLs and a browser fetches each once; the half-track must be >= the viewport or the -50% loop shows a gap, so the row repeats until a half holds ~16 cards and the count stays EVEN; and every card is labelled, not just the first pass, because the repeats are what is on screen most of the time. Renamed from digihellcorps.php to match cryptcrawlgame/cryptconquestgame/skullracergame, and titled DHC FIGHTERS throughout -- what the nav, Skull Paper page and Launchpad tile already call it. "Digi-Hell Corps" survives in exactly one place, the schema alternateName, alongside "DHC Arena" and "Digital Hell Citizens 2": those are what people SEARCH for even though the product is displayed as DHC Fighters. It is also a card in homepage.php's games grid and entry 7 of that page's ItemList schema -- and NOTE the grid is a fixed 2 columns, so an odd game count leaves the last card alone; 7 does, 8 would not. A LOOPING REEL sits above the hero wording: images/dhcfighters.mov, autoplay+muted+loop+playsinline (muted is what makes autoplay legal; without playsinline iOS goes fullscreen), and paused with controls shown under prefers-reduced-motion. TWO THINGS MEASURED THE HARD WAY. (1) The .mov source carries NO type attribute: a <source> whose type the browser does not claim is skipped WITHOUT BEING FETCHED, and Chrome's canPlayType('video/quicktime') is "", so the honest MIME made Chrome refuse the only file present -- networkState 3, NO_SOURCE. Typeless it sniffs the H.264 and reaches readyState 4. (2) Sources are emitted only for files that EXIST on disk, checked in PHP. Listing a not-yet-uploaded one should be harmless, but a missing file under /staking/ is caught by WordPress, which 301s it and answers with HTML -- the element then sits in NETWORK_LOADING forever instead of advancing. Upload dhcfighters.mp4 (1.3MB) and .webm (1.0MB) and they are preferred automatically; the .mov is 3.0MB for 11s at 480x480 and Firefox does not play the QuickTime container at all. IT CREDITS THE ARTIST mid-page, right after the screenshot rather than buried in the footer: every trait is Maxingo's art and the minted collection is Digital Hell Citizens 2 on Cardano (226 characters, policy b31a34ca...), linked at the SAME Wayup URL games-dhc-fighters.md already uses so the two cannot drift. The X handle there is MMAXI404, which xrp.cafe's own collection record also gives; homepage.php's partner row still links madmaxi__, so one of the two is stale and they should be reconciled. images/dhcgame.png (FTP, 1996x1255) is both its og:image -- clearing the 1200x630 X and Facebook want, so neither crops it -- and a below-the-fold screenshot section; it is 1.2MB, so that one is lazy and carries width/height plus aspect-ratio or the page jumps when it lands. The same file is a card in homepage.php's screenshot grid, which is its own <div> rather than an $hp_shots row because that array's base is images/screenshots/ and this lives at images/ -- same as cryptcrawl, cryptconquest and guardians. WHY A WIN CAN PAY NOTHING, and how that is now said out loud (dhcarena-reward-harness.php). Reported as "I won and didn't get a trait -- same opponent twice, or a bug?" It was the rule: dhca_already_rewarded() allows ONE rewarded battle per opponent per day. The rule was right and completely invisible, which is indistinguishable from a bug. TWO no-trait paths and the end screen handled neither correctly: a repeat win left rewarded=0 and was reported as "the daily cap is spent" (wrong rule), and a genuinely spent cap left rewarded=1 with drop=null, which the single `rewarded === false` test could not see at all, so it printed NOTHING. dhca_settle() now sets $b['nodrop'] to 'opponent' or 'cap', ajax/dhcarena-action.php forwards it, and the end screen names the actual rule. THE RIVAL LIST IS MARKED TOO: dhca_paid_today() answers the same question once for the whole list and paints "Beaten today - no trait"; the rival stays selectable because the rematch still counts for the ladder and the win record. THE INVARIANT THE HARNESS PROTECTS: dhca_already_rewarded() and dhca_paid_today() must agree for every defender, or the player is shown one thing and paid another -- checked over a stubbed battle table including a payout from YESTERDAY (must not block today) and another player's battles (must not leak). They are allowed to differ in exactly one place and it is asserted: on a failed query already_rewarded returns TRUE (never hand out a second trait) while paid_today returns EMPTY (never paint a marker it cannot justify). CSS: the marker is .a-foe.paid, namespaced -- a bare .paid is one more global in a stylesheet that already owns .top. THEN THE MARKER EXPOSED A REAL BUG UNDERNEATH IT, reported the same day: "I lost a bunch, finally beat a player, got no trait, and now the list says I already beat them today." dhca_settle() set $rewarded = 1 and THEN called dhca_pay(), and the UPDATE stored 1 whether or not dhcf_award() handed anything over -- so a win that hit the three-a-day cap paid nothing AND burned the pairing's one payout, costing the player both the trait and the rematch that could still have earned it. The flag is what dhca_already_rewarded() and dhca_paid_today() both read, so it has to mean "a trait came out of this" and nothing looser. dhca_payout_outcome($already_paid, $got_drop) now owns that rule and is testable on its own; dhca_settle() calls dhca_pay() first and reads the flag off the result. NOTE the marker itself was innocent -- it reported the flag faithfully; the flag was wrong, and making it visible is what surfaced a bug that had been silently eating traits. |
| games-obscura.md *(new)*            | Obscura: crop-reveal   | obscura.php (game page -- nav and Launchpad link straight to it; there is deliberately NO obscuragame.php marketing landing, unlike the other games, because this one is internal), obscura-lib.php (gameplay + score recording + the migrations), ajax/obscura-action.php (guess/reroll), ajax/obscura-crop.php (renders the visible region server-side), db.php OBSCURA block at end of file (checkObscuraLeaderboard, resetObscuraRuns, OBSCURA_CARBON=50000), rewards.php `?obscura=1` (weekly cron), webhooks.php `obscura` channel |
| games-drop-ship.md                  | NFT battler, now in-platform | dropship/ (migrated from madballs.net; requires Skulliance login) |
| games-oculus-lounge.md              | Drop Ship reskin, now in-platform | dropship/ project_id 4 (migrated from oculuslounge.vip; SAME engine and database as Drop Ship, which is project_id 1 -- see dropship/oculus-lounge/) |
| homepage.php (layers)               | Every homepage section | THE THREE MIDDLE SECTIONS WERE THE SAME SHAPE: `section { padding: 48px 0 }`, a hairline rule, a centred h2, a centred 760px .hp-intro, then a grid -- three in a row, so nothing signalled that a new idea had started. They are now .hp-layer full-bleed bands with alternating grounds (.alt) and left-aligned headings, each with its own rhythm. Mission was 371 chars, one image and ZERO links; the art is now the band's background and it has a CTA. Games had seven cards of identical weight, which is the same as none -- DHC Fighters is featured at width and the other six are compact .hp-mini tiles. Platform was 698 chars followed by TWENTY equally-weighted screenshots, a contact sheet; it now states three things you DO with the three best pictures, and the rest became a labelled .hp-wall of evidence with no h3s. Page h3 count went 30 -> 7 and total text 7,072 -> 5,256. EVERY OTHER SECTION FOLLOWED: artists ("Six artists started this"), partners (the count IS the argument and was buried in a sentence -- and it is READ FROM THE DATABASE via hp_stat_artists(), never written: a hardcoded 27 was wrong the moment it was typed, because that is the length of $hp_partners, the list of projects with a FLYER IMAGE, not the list that stakes. The marquee has always been a subset. It falls back to wording with no number, since "0 projects stake here" on the page arguing that projects stake here would be the worst sentence on the site), skull-paper and team as .hp-thin bands because a pointer given the same height as Games claims it matters as much, membership (806 chars of tiers with ZERO links, the most conversion-shaped section on the page, now leading with "staking needs nothing" so a newcomer does not read it as a gate), and the close, which asked for three things at once exactly as the old hero did. GROUNDS MUST ACTUALLY ALTERNATE -- a first pass left games+artists both .alt and partners+platform both plain, merging four bands into two and undoing the treatment; the sequence is art/alt/base/alt/base/alt/base/alt/art and is worth re-checking whenever a section is added or reordered. A SCROLLING ARTIST-FLYER STRIP WAS ADDED TO THE HERO AND REMOVED: those nine files are 1.2-2.8MB each at 1500px for 132px tiles, 18MB eager in front of the first screen. The partner strip further down survives on the same images only because it is lazy and below the fold -- and lazy is what a marquee cannot use, since a tile arrives by transform and never loads (measured: 27 of 54, one tile visible). Bring it back only with ~300px sources. |
| homepage.php (first screen)         | Public homepage hero | Rewritten after comparing the page with omenati.com: 1,345 chars of text and ~4 headings there against 7,072 and 40 here, and a hero that asked a stranger to choose between three CTAs. THE DEPTH BELOW IS NOT THE PROBLEM -- it is why the game pages rank -- the first screen was. The old H1 was 128 characters describing a CATEGORY (what Skulliance IS, never what a visitor gets) and is now the SUB-LINE, where a feature list belongs, so nothing was lost for search. Gone: the third CTA (three is zero; a stranger cannot pick) and the five .hp-badges, replaced by four LIVE numbers from homepage-data.php -- a badge is a claim, a number is evidence. The badge CSS was deleted with them. STAKERS, NOT BLOCKCHAINS, for the fourth tile: the chain count duplicated the sub-line and "2" is weak next to thousands, where a count of PEOPLE is social proof and cannot be inflated by one whale. TWO THINGS THE DEGRADED CASE FORCED: a stat falling back to 0 is DROPPED, not shown -- a homepage announcing "0 NFTs staked" argues against itself, and it is the one number a sceptic believes instantly -- and the tile row uses minmax(140px,200px) columns with justify-content:center, because plain 1fr made a single surviving tile stretch the full 860px and look like a bug, which is exactly what a database blip produces. The art strip repeats until a HALF-track exceeds any viewport and keeps an EVEN count, the same -50%-loop trap as the Fighter marquee on dhcgame.php. |
| dist/flexbox.css (PWA bottom strip) | Standalone-mode chrome | `body::after` in the `@media (display-mode: standalone)` block paints a fixed strip across the bottom of the VIEWPORT. It exists only to mask the gap under a lifted #quick-menu -- and #quick-menu is on realms.php, missions.php and dropship ONLY, while body::after applied to every page. On everything else in the PWA it was a dead band covering the bottom ~44px of the screen at every scroll position (safe-area inset + 10px). Reported on DHC Fighters hiding the Arena stats on trait claims; rubber-banding exposed them and releasing put them back under. body's padding-bottom did NOT compensate, and cannot: padding extends the DOCUMENT, a viewport-fixed strip covers the SCREEN. Now scoped `body:has(#quick-menu[style*="block"])`, which tracks the inline-style toggle live and has the right polarity -- before the JS runs there is no inline style, so nothing paints. Where :has() is unsupported the selector drops out and the strip disappears, which is the safe direction. Verified across all four states: no menu / menu before JS / menu hidden / menu shown. |
| header.php Play menu + DHCF_GAMES   | Member links go to GAMES | A signed-in member's links go STRAIGHT TO THE GAME, never to a *game.php marketing landing. Five in header.php's Play menu (guardians, cryptcrawl, cryptconquest, monstrocity via match3rpg, skullracer) and the same five in DHCF_GAMES['url'] pointed at the pitch, which is an extra click on EVERY attempt to play, forever, to re-read something you accepted by signing in. It was also inconsistent: launchpad.php has always linked straight to the games, so the same Play intent behaved differently depending on which menu you used. SAFE BECAUSE THE PLAY MENU IS MEMBERS-ONLY -- the whole navbar sits inside `if(isset($name))` -- and the public pages keep their audience: homepage.php, site-header.php on every public page, and sitemap.xml. Do not re-point these at *game.php; if a member needs to learn a game, the game's own page should teach them. |
| ui-selectors.php + my-nfts + store  | Platform redesign, pages 1-2 | THE SAFETY NET FIRST. `php ui-selectors.php --snapshot <page> > before.json`, edit, then `--check before.json`: it reports any id or class the page stopped emitting that JavaScript anywhere in the repo still reaches for, INCLUDING the ~140 built by concatenation (getElementById('p' + n + '-image')), which a grep cannot see. Diff-based against the page's own before-state, so there are no false positives from the 771 global selectors. It is static -- rendering needs the database -- so it proves a hook still EXISTS, not that the page still works; drive the page for that. Verified in both directions: silent on an unreferenced id, FAIL+exit 1 on a referenced one.

MY-NFTS.PHP IS THE FIRST PAGE MIGRATED. The pattern for the other fourteen: (1) snapshot, (2) add a data-returning sibling in db.php rather than editing the renderer -- getNFTsData() has the same filters, columns and ORDER BY and NO output, while getNFTs() is untouched because showcase.php and diamond-skulls.php still call it, (3) the page owns its markup and a scoped `<style>`, (4) --check, (5) keep every JS hook. Result here: 9 ids and 17 classes kept, 0 removed, 13 added; db.php +49 lines, 0 removed. THE PLATFORM CARD RULES ARE OVERRIDDEN, NOT EDITED: .nft carries `width:25%; float:left` and .nft-data `text-align:right` -- a fixed four-column float grid from before there was a phone audience -- and both are overridden in the PAGE's inline CSS, so showcase.php renders the identical markup exactly as it did. Do not 'fix' them in flexbox.css until every caller has migrated. NEGATIVE-OFFSET HACKS ARE THE FIRST THING TO CHECK ON EVERY PAGE: flexbox.css claws back space with `position:relative; top:-Npx` in at least eight places -- #filter-nfts is -35px and #filtered-content is -40px, both tuned to close the gap under a bare one-line <h2>. Give a page a taller masthead and they stop closing a gap and start landing content ON TOP of something; here the filter covered the stat tiles and the grid covered the context line. Grep `top: -` before changing any page header. AND SAY REPEATED FACTS ONCE: the rate is per COLLECTION, so a filtered view was printing the same rate, project and collection on all 24 cards -- four of five lines identical, which is what made the grid read as a database report. Fields uniform across the page are hoisted to one context line under the title and dropped from the cards, leaving art and a name. SQUARE CORNERS: DHC Fighters and the Arena have none, and they are the pages that read as modern; the 2.5rem on .nft and 10px on .nft-data are the old platform's. New work is hard-edged -- and the radius is not all in one place: .nft is 2.5rem, .nft-data 10px and .content 10px, so squaring a page means overriding the CONTAINER too, not just the card. ALIGNMENT IS INHERITED, NOT SET: .main carries `text-align:center` for the whole column, so a new element that does not state its own alignment floats centred over a left-aligned h2. State it. NOTHING MAY CROP THE ART: .nft-image is `min/max-height:170px; overflow-y:hidden` with `.nft img { max-height:165px }` -- a fixed window that hard-clips any piece taller than it, which was survivable while the image was a thumbnail and is not once the art leads the card. my-nfts.php replaces it with a 1/1 box and object-fit:contain: uniform grid at 1,483 items, and a tall or very wide piece is letterboxed and shown WHOLE rather than cropped. Verified against 1:1, 1.59:1, 2.06:1 and a 1500x1674 tall piece in one grid.

STORE.PHP IS PAGE TWO, same pattern: getItemsData() added (identical filter, query and ORDER BY, plus the payment options, per-option balance, affordability and already-purchased flags -- that arithmetic is business logic and stays in db.php; only markup moved). getItems() is untouched and still has its one caller, so store.php reverts in a line. renderBuyButton() is still called FROM the page: it emits a form that posts a purchase and must not be duplicated into a template. Checked what the page inherits BEFORE building, which caught four of the five traps for free. THE FIFTH WAS SPECIFICITY: `.nft span { display:block }` is 0-1-1 and beats a bare `.st-meta` at 0-1-0, so new spans inside a .nft rendered as blocks and ran their text together ("Sinder Skullz2 left"). Prefix new card classes with #filtered-content. The store card also now says what a viewer can AFFORD -- a greyed button with the balance beside it, and a masthead count -- which the old page made you read every card to work out. |
| site-header.php *(shared)*          | Public site navigation | site-header.php is the ONE public nav, included by homepage.php, by every public game landing page, and by skullpaper.php FOR ANONYMOUS READERS ONLY -- header.php's entire navbar sits inside `if(isset($name))`, so a stranger arriving at the Skull Paper from search got an EMPTY nav and no way back to anything; a member already has the platform navbar and two stacked bars would be worse than the bug. That page also needs `#skullpaper .sp-side { top: 62px }` when the public menu renders, because its contents sidebar is sticky at top:10px and would scroll under a sticky 51px bar -- the same class of collision as match3rpg.php's exit button. Checked: no id, class or function collisions with header.php or the platform CSS, and .container has no overflow, so sticky survives inside it. Included by (dhcgame, match3rpg, cryptcrawlgame, cryptconquestgame, skullracergame, guardiansgame). It used to be markup+CSS inside homepage.php alone, which is why a visitor landing on a game page had no way to reach the rest of the site. SELF-CONTAINED (own CSS under an `sh-` prefix) because those pages share no stylesheet -- each carries its own inline CSS. NO session, NO db.php: it is navigation, and homepage.php is included by WordPress. ROOT-RELATIVE LINKS, NEVER ABSOLUTE -- cookies here are host-only, so an absolute www. link in SHARED nav would log out everyone browsing the bare domain on every page it appears on; the homepage's old nav had absolute URLs and this move fixes them. STICKY, NOT FIXED: fixed is out of the layout, so every including page would need its own top padding, wrong the moment the bar wraps on a phone. That is why homepage.php's .hp-hero padding-top dropped 120->56 and 96->40 (it was reserving height for the old fixed bar) and why match3rpg.php's #m3-exit moved to top 62px -- it is z-index 99990 against the nav's 9990, so at 8px it covered the skull mark. Two optional knobs set BEFORE the include: $sh_on_home (keeps section links as in-page anchors) and $sh_links (the homepage passes its full 9; the default is the short set a game page wants). skullswap.php, cryptcrawl.php and cryptconquest.php are NOT in the list -- they include db.php and already wear the platform header. |
| platform.md (Launchpad section)     | Post-login landing     | launchpad.php ($lp_sections registry = every tile; process-oauth.php:~198 redirects here, NOT profile.php; header.php top-level link). Nav-only + 3 cheap stats (getWallets/getCurrentDailyRewardStreak/getCurrentBalance) -- NO writes, NO game logic, because it is the first page after login and must never be what breaks. Start Here strip is conditional on those 3 stats and self-clears. Emoji icons, not images (FTP deploy). The staked-NFT view is my-nfts.php (was dashboard.php -- renamed because the name promised an overview it never gave). dashboard.php REMAINS as a permanent 301 stub and must not be deleted: 46 references plus bookmarks, Discord links and in-game "back to Skulliance" buttons still point at it. Its page identifier for filterNFTs()/renderVisibility() is now "my-nfts", and skulliance.php accepts BOTH that and the old "dashboard" for the #holdings anchor. dropship/dashboard.php is a DIFFERENT file and is untouched. TWO PATH CHECKS keyed off the old filename and broke silently in the rename -- skulliance.php gating verifyMembershipNFTs() on REQUEST_URI, and skulliance.js gating the NFT upload button on window.location.pathname. Both now accept my-nfts.php (and still the old name). Anything else that keys off a page NAME rather than a link will fail the same quiet way. Game exit buttons (match3rpg, skullswap x2, monstrocity, displayRound() in skulliance.js, and dropship/header.php) go to launchpad.php, not the NFT view -- Drop Ship was the last one still exiting to dashboard.php, from before it moved into this repo, and it is the only in-repo "back to Skulliance" button that ever pointed there; wallet-ajax.php still redirects to the NFT view after connecting a wallet, which is deliberate. |
| marketplace-store.md *(new)*        | Free member claims     | store.php, ajax/item-edit.php, store-edit-harness.php (EDIT AFTER SUBMISSION. Two editors: user 1 on every listing and every field, and a PARTNER CREATOR on the listings credited to a project they own. OWNERSHIP IS BY PROJECT because there is nothing else -- `items` records no submitter, so the only link between a listing and a person is projects.discord_id, the same link renderItemSubmissionForm() uses to decide who may submit at all; a creator can own several projects, so it is a set. db.php storeItemEditRights() answers that ONCE and both the page and the endpoint ask it; a page that decides for itself which buttons to draw drifts from the endpoint that decides which writes to accept, and the endpoint is the one that matters. The endpoint checks BOTH ENDS of a move -- the stored project_id and the posted one -- so owning a listing today does not let anyone push it onto another artist's shelf. `featured` and `secondary_project_id` are admin-only and are OMITTED from a partner's UPDATE rather than rejected: reading `$_POST['featured'] ?? 0` would clear an Exclusive flag every time its owner fixed a typo. store-edit-harness.php pins all of that down by running the real endpoint in a subprocess against a fake connection -- verified to FAIL when the admin-field gate is removed. NOTE `include '../db.php'` resolves against the WORKING DIRECTORY (a path starting with a dot bypasses include_path), which is why it works under mod_php and why the harness runs with cwd=ajax/. The gate is in the endpoint, checked against the session; store.php's $st_admin/$st_mine only decide whether a button is drawn, and a button nobody can see is not a permission. db.php adminUpdateItem() whitelists the columns it will SET and intersects that list with SHOW COLUMNS FROM items -- not information_schema, which the cPanel user gets #1044 on -- because `secondary_project_id` and `featured` are read unprefixed out of an items+projects join and nothing in the repo proves which side owns them; an UPDATE naming a projects column would fail the whole statement. The modal reads its values off the card's own data-* attributes, so opening it costs no round trip, and it posts to ajax/item-edit.php rather than to store.php -- skulliance.php:404 creates an item on any POSTed `name`, so a form falling back to a normal submit would duplicate the listing it was meant to fix. Save reloads rather than repainting the card: price, currency options, affordability and the featured-first ORDER BY are all server-side. db.php's older updateItem() is dead code covering four of eight columns; left alone rather than widened. quantity 0 delists (the store query is `WHERE quantity != 0`), -1 is unlimited. |
| marketplace-auctions.md *(new)*     | Bid-based NFT sales     | auctions.php, db.php:9379-9577 |
| marketplace-raffles.md *(new)*      | Ticketed raffles        | raffles.php, db.php:9602-9828 |
| platform-dashboard.md *(new)*       | Staking portfolio       | dashboard.php |
| platform-gallery.md *(new)*         | NFT discovery           | gallery.php |
| platform-collections.md *(new)*     | Policy registry         | collections.php |
| platform-leaderboards.md *(new)*    | All leaderboards        | leaderboards.php, db.php:4194-5637 |
| platform-analytics.md *(new)*       | Personal stats          | analytics.php, ajax/analytics-*.php |
| platform-profile.md *(new)*         | Profile + streak cal    | profile.php  GAMES PANEL: profile-lib.php profile_game_record(), plus profile-harness.php. The page predates nine of the platform's games and showed none of them. One panel, not nine sections -- each game has its own page and leaderboard already. Columns are taken from the existing check*Leaderboard() queries in db.php rather than guessed, so they match the live schema by construction (dhc_fighters, dhc_arena_battles, guardians_scores, cryptcrawls, cryptconquests, gauntlets+gauntlets_encounters, obscura_scores, skull_racer_runs). EVERY query is guarded: mysqli_report(MYSQLI_REPORT_OFF) at db.php:49 means a missing table returns false, not an exception, and profile.php is PUBLIC and takes a username from the query string -- a game not yet deployed must not be able to blank it. A game with no plays returns null and is dropped, so the grid shows what someone HAS done. Harness covers the empty player, zero-vs-absent, a false-returning table not taking the others down, plural/singular, formatting, and that a guest issues no queries at all. STYLING: 29 border-radius declarations squared; 50% kept on avatars (header.php and leaderboards.php are round, squaring only this page would read as a mistake) and 2px on the loader rail (gallery/leaderboards convention). |
| platform-wallets.md *(new)*         | Multi-wallet            | wallets.php, db.php:575-611 (getWallets), db.php checkAddress (returns 'added'/'exists_mine'/'exists_other'/'claimed'), wallet.js (connect flow), wallet-ajax.php, header.php (the global wallet modal -- `renderWalletConnection()` in skulliance.php is now an empty stub). **Lucid is constructed with NO provider (`Lucid.new(undefined, "Mainnet")`) and that is load-bearing.** Passing a Blockfrost provider makes `Lucid.new()` fetch live protocol parameters and hand the cost models to the CML WASM pinned in lucid-cardano@0.8.7, which no longer accepts as many cost-model entries as Cardano has: `Uncaught (in promise) CostModel operation 166 out of bounds. Max is 166`, for every wallet, on every attempt. Nothing here builds a transaction - `selectWallet()`'s `address()`/`rewardAddress()` only decode what the CIP-30 extension already returned - and every line touching protocol parameters sits behind `if (provider)` in that build, so dropping the provider skips the broken path rather than patching around it. Do not reintroduce a provider to "fix" anything; upgrade Lucid instead. This also removed a Blockfrost API key that was sitting in readable client-side source (the server's key is separate, from config). `dropship/wallet.js` had the identical break and the identical fix. Also: `rewardAddress()` returns **null**, not `""`, when a wallet has no stake key - the old `!= ""` check therefore passed stake-less wallets straight through. **connectWallet() must keep its try/catch and must open the modal BEFORE its first await**: it had neither, so a locked extension, a dismissed approval popup, an unreachable Blockfrost and an undelegated wallet all produced the same symptom -- clicking a wallet icon did nothing whatsoever. Multi-wallet has always worked; a "disconnect" button is NOT needed to add a second wallet, and there is deliberately no remove-wallet feature (removing one would unstake its NFTs). `renderWalletConnection($page)` renders the **Connect a Wallet** button and had been reduced to an empty stub, on the reasoning that the header modal covered it - but the only thing opening that modal was an unlabelled icon in the top nav, so wallets.php listed your wallets, said "connect as many as you want", and offered no control. It now always renders on the wallets page and elsewhere only for accounts with **no** wallet yet (which is what its other call sites - points, crafting, missions, realms - existed to prompt). The button depends on `wallet.js` being loaded unconditionally by header.php:73; if that ever becomes conditional, the button becomes a dead click. |
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
- Share on X (db.php `shareOnXUrl()` / `shareOnXButton()` + per-game `*ShareText()` builders,
  defined just above `checkActivityLeaderboard()`): finish-screen share buttons on all 6 games.
  Uses X's PUBLIC Web Intent endpoint (`x.com/intent/post`) -- NOT the paid API. No developer
  account, no OAuth, no tokens, no per-post charge. If anyone later proposes "upgrading" this to
  the API, note that as of 2026 post creation costs $0.015, or **$0.20 if the post contains a
  link** -- a 13x surcharge this design avoids entirely.
  Imagery is free too: `url=` points at each game's PUBLIC page, which must carry BOTH `og:image`
  AND `twitter:card=summary_large_image` or X degrades to a small thumbnail. skullswap.php had
  og:image but no twitter:card and was fixed here. Targets: guardiansgame.php, cryptcrawlgame.php,
  **monstrocity.php DOES NOT INCLUDE db.php.** It is standalone -- its own `session_start()`, no
  header.php, no db.php -- so NO helper defined in db.php can be called from it. Calling
  `guestSignupPrompt()` there shipped a fatal on an undefined function that truncated the page
  mid-markup: Monstrocity AND boss battles rendered nothing, with an empty console, because no
  script ever reached the browser. Its guest prompt is inlined instead and must be kept in step
  with db.php's copy by hand. Check this include before using any shared helper in a game file;
  skullswap.php, cryptcrawl.php and cryptconquest.php all DO include db.php.
  cryptconquestgame.php, skullracergame.php, match3rpg.php (Monstrocity's public page -- NOT
  monstrocity.php), skullswap.php. **These must stay outside skulliance.php's login gate** -- X
  would follow a redirect to error.php and the card would silently collapse to a bare link.
  Two implementations by necessity: PHP (`shareOnXButton`) for the server-rendered finish modals
  in cryptcrawl-render.php and cryptconquest-render.php, and inline JS for the client-side
  ones (monstrocity.php, skullswap.php, racing/index.html, and guardians.php's `showDefeat()`)
  where the result isn't known server-side. Realm Guardians shares WAVES HELD, not the wave
  reached, because that is what its board ranks on -- the post and the leaderboard must not tell
  different stories about the same run. Its mid-run nuke modal deliberately has NO share button;
  only the defeat modal does. Both append @skulliance and budget 24 chars for the t.co-wrapped URL against
  the 280 limit. Verified by a harness (31 assertions) covering URL shape, every target page
  having the card tag AND no login gate, per-game text, the 280 budget, and button markup.
- Drop Ship / Oculus Lounge boards (db.php `checkDropShipLeaderboard()` + `dropShipDbConnection()`):
  these two live in Drop Ship's SEPARATE database on the same MySQL server. Oculus Lounge is a
  Drop Ship RESKIN, so both are rows in one `results` table split by project_id -- **1 = Drop
  Ship, 4 = Oculus Lounge** (DROPSHIP_PROJECT_* constants). One function, SIX boards: each game
  has $mode 'weekly' / 'ath' / 'xp', mirroring Drop Ship's own checkLeaderboard /
  checkATHLeaderboard / checkXPLeaderboard. 'weekly' is Drop Ship's "current game" -- renamed
  because its rounds pay out on the same cadence as everything else here; it looks up
  `SELECT id FROM games WHERE active=1 AND project_id=N` (Drop Ship reads that from its session,
  which doesn't exist on this side) and lists individual RUNS, not a per-player aggregate, so one
  player can hold several places -- matching Drop Ship's own board.
  They sit in their own **Specialty Games** group, which is what puts them on a row of their own;
  `$fixed_cols` in renderLeaderboardHub forces that section to 2 columns
  (`.lb-hub-grid--cols-2`, collapsing to 1 under 560px).
  Podium backdrop: many of these players predate Skulliance and have no realm theme, so
  `renderPodium()` takes a 4th `$fallback_image` arg and these boards pass the game's own art
  (`$DROPSHIP_BACKDROPS`, both URLs curl-verified 200 -- images deploy by FTP outside this repo).
  Theme still wins when the leader has one.
  `dropShipDbConnection()` opens a second mysqli with Drop Ship's credentials and the include is
  FUNCTION-SCOPED on purpose -- both credential files define $servername/$username/$password/
  $dbname, so a top-level include would clobber Skulliance's and break $conn. Drop Ship does the
  same thing in reverse in `dropshipConnectedStakeAddresses()`. Connection is statically cached
  and a failure is sticky, so a down database isn't retried per board.
  **Read-only, deliberately.** Drop Ship is quarantined: it reaches into Skulliance to award
  MOON/DREAD and nothing came back the other way until this. Keep it to SELECTs -- there's a test
  asserting no INSERT/UPDATE/DELETE/REPLACE/TRUNCATE appears in the function. If that direction
  is ever unwanted, the alternative is Drop Ship pushing its top scores into
  leaderboard_snapshots on its own cron.
  **Many Drop Ship players predate Skulliance and have no account here.** They are still ranked
  -- hiding the actual best players because they never signed up would make the board wrong --
  they just get no profile link. Matching is by discord_id; matched players display their
  SKULLIANCE username, unmatched their Drop Ship one.
- Leaderboard nav split: `filterLeaderboard()` in skulliance.php (called ONLY from
  leaderboards.php:~460) lists **projects only** now -- All Projects, Core and Partner.
  Delegations was dropped from it too since it has a hub card; `?filterby=15` still resolves via
  the numeric project branch, so the card's link is unaffected. The ~31 board options were removed; boards live on the hub. Do NOT add a
  board back to that selector: mixing 31 boards with 36+ projects is what made it useless for
  both jobs. Consequence to preserve: since boards are no longer listed there, the
  selector's first entry is `<option value="hub">` and the "&larr; All Leaderboards" link in
  leaderboards.php (printed under the `<h2>`, suppressed on the hub itself). Those two are the
  ONLY routes from a board back to the others -- removing both strands every board page. `?filterby=15` (Delegations) is reachable via the numeric project branch, not a named
  case -- it has no dispatch `case` and does not need one.
- Leaderboard hub (db.php `$SKULLIANCE_BOARDS` / `renderLeaderboardHub()` /
  `refreshLeaderboardSnapshots()`): card grid shown at leaderboards.php with no filter, or
  `?filterby=hub`. `$SKULLIANCE_BOARDS` is the single registry of subject -> periods; its period
  values are the EXISTING `?filterby=` strings, so old links and game finish-screen buttons are
  unaffected -- this is a navigation layer, not a re-route.
  **REQUIRES A MIGRATION** (page works without it, cards just show "No leader yet"):
  `CREATE TABLE leaderboard_snapshots (board VARCHAR(64) NOT NULL, rank_pos TINYINT NOT NULL,
  username VARCHAR(255) NULL, discord_id VARCHAR(32) NULL, avatar VARCHAR(255) NULL,
  score VARCHAR(64) NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (board, rank_pos));`
  Then a cron on `rewards.php?leaderboardsnapshot=1` (hourly is fine). That endpoint is
  READ-ONLY for players: refreshLeaderboardSnapshots() calls only DISPLAY variants, never
  $rewards=true, so it never pays out, resets a reward flag or posts to Discord.
  **FACTIONS IS THE ONLY BOARD THAT DOESN'T RANK PLAYERS** -- it ranks projects, and pushes
  `['faction'=>true,'project_id','project_name','currency']` with no username/discord_id/avatar
  (renderPodium() special-cases it the same way via `$is_faction`). Verified that every other
  board pushes the standard user shape. The snapshot normalises it into the existing columns
  rather than adding one: name column holds the project name, avatar column holds a ready-made
  `icons/<currency>.png` path, discord_id is left EMPTY -- and empty discord_id is exactly what
  the renderer uses to decide whether the avatar column is a Discord hash or a direct path. Any
  future non-player board needs the same treatment or its card silently reads "No leader yet".
  It captures each board's top 3 by buffering the board's own output and keeping the global
  `$leaderboard_top3` -- so a card can never disagree with the board it links to, since there is
  one implementation of each ranking. Its dispatch map is deliberately NOT shared with
  leaderboards.php's switch (that one also routes reward runs and project ids; coupling them
  would put payout paths one edit away from an unattended cron). NOTE `delegations` is not a
  board -- `?filterby=15` is a PROJECT id routed through checkLeaderboard(); there is no
  checkDelegationsLeaderboard(). Emoji icons, not images, because images deploy by FTP outside
  the repo and 16 missing icons would ship a broken-looking page.
- Skull Racer weekly LB: 50,000 CARBON x2 -- a Races board and a Laps board, each with its own
  50,000 pool, so one driver topping both takes 100,000 (db.php SKULL RACER block, end of file).

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
  TWO leaderboards off one function: `checkSkullRacerLeaderboard($conn, $weekly, $rewards, $mode)`
  where $mode is 'race' (ORDER BY best_time) or 'lap' (ORDER BY best_lap). Filters
  `skullracer` / `weekly-skullracer` / `skullracer-laps` / `weekly-skullracer-laps` in
  leaderboards.php -- note each new filter must ALSO be added to the two `$filterby != ...` guard
  lists (~line 218 and ~362) or it falls through to the "treat it as a project id" branch and the
  page breaks. Selector options live in skulliance.php (~line 1020), not leaderboards.php.
  **Payout ordering is load-bearing**: rewards.php?skullracer=1 calls the function twice ('race'
  then 'lap') against the same reward=0 rows, THEN calls resetSkullRacerRuns() once. The reset
  used to live inside the function; if it goes back there the first board's reset flips every row
  to reward=1 and the second board pays nobody, presenting as "no lap times this week". One cron
  covers both -- do NOT add a second crontab entry. Verified with a harness that extracts the
  real function from db.php, stubs updateBalance/discordmsg/etc, and asserts both orderings,
  6 payouts across 2 boards, 2 distinct Discord posts and exactly one reset.
  Counts toward platform Activity leaderboards (db.php `checkActivityLeaderboard()`, source
  `'racer'`, weight 5, alongside crawl/conquest/mission). No status filter, unlike those two:
  every row in `skull_racer_runs` is already a finished race that passed the SKULLRACER_MIN_*
  floors, since skullRacerFinalizeRun() is the only thing that inserts and it only runs on
  completion. Unlike crawl/conquest this source needs NO migration -- `skull_racer_runs` has had
  `created_at` since its original CREATE and the INSERT writes it explicitly, so monthly/weekly
  filtering works immediately. Note the column is `created_at`, NOT `date_created` like most
  tables here, hence its own `$w_sr` filter var rather than reusing one of the shared ones.
  Time display: skullRacerFormatTime() in db.php is a hand port of formatTime() in
  racing/index.html (~line 2366) -- dot-separated M.SS.T, tenths TRUNCATED not rounded, minutes
  part omitted under 60s. Same manual-sync caveat as common.js's SPRITES object: change one and
  you must change the other. Verified byte-identical against the JS on 9 values including its
  float quirks (104.1 -> "1.44.0", 59.9 -> "59.8" -- the truncation can read a tenth low; the
  game has always done this and both sides now do it identically). Before this, the leaderboard
  and the race-finished Discord post printed raw seconds ("104.1s") while the game printed
  "1.44.0" for the same lap, which got reported as a wrong-data bug when the data was fine.
  Best Lap on the leaderboard is MIN(sr.fastest_lap) GROUP BY user -- already the all-time best
  lap across every race, NOT the best lap of the best race.
  Collision punt (COLLISION PUNT block + puntCar(), racing/index.html): hitting a car throws it
  forward and sideways, scaled by CLOSING speed (impactSpeed - car.speed), not the player's raw
  speed -- PUNT_MIN_DV_FRAC=0.10 of maxSpeed is the threshold below which nothing happens.
  Player recoil is deliberately unchanged: the recoil formula reads the car's speed, so the
  collision handler snapshots carSpeedAtImpact BEFORE calling puntCar() and recoils off the
  snapshot -- swap that order and punting quietly makes your own crashes cheaper. Cars carry
  baseSpeed/puntTimer/puntDx (set in resetCars()); updateCarOffset() returns 0 outright while
  puntTimer > 0, which is what suspends its "steer back on if off road" recovery long enough for
  a car to actually leave the track. Existing leaderboard times stay comparable: the change
  raises the floor (fewer repeat collisions) without lifting the ceiling, since a record run
  avoids traffic rather than punting through it.
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
  not anything sniffed or negotiated here.
  Detection does NOT rely solely on gamepadconnected/gamepaddisconnected -- reported "finicky",
  root-caused to that event being a genuinely unreliable way to learn a controller exists: a
  controller already paired at the OS level before this exact page load, or various browser/OS
  combinations, are real ways for the qualifying first press to fire without the event ever reaching
  this page, leaving gamepadIndex null and every subsequent press going nowhere. gamepadTakeOver(i)/
  gamepadRelease() are the actual state-change functions now (both idempotent -- safe to call
  redundantly); the connect/disconnect LISTENERS still call them as a small optimization (instant
  takeover the moment the browser confirms one, vs. up to one tick/~16ms later), but pollGamepad()
  itself is what's actually authoritative: whenever gamepadIndex is null, it scans every
  navigator.getGamepads() slot for ANY pad with gamepadHasInput() (any button pressed or axis past
  STICK_DEADZONE) EVERY tick, and takes over the instant one matches -- "a player pressing a
  controller button interrupts whatever mode the game is in" literally, not contingent on the event
  having fired first. Releasing is deliberately NOT symmetrical with that -- a quiet poll is never
  treated as a disconnect, only the explicit gamepaddisconnected event is. See the "DO NOT infer a
  gamepad disconnect from a quiet poll" entry below for what happened when it briefly was.
  Genuinely CANNOT be fixed from here: pressing a DualSense/DualShock's PS/Home button while
  connected to an iPhone opens Game Center system-wide, for every app and every website alike --
  Apple intercepts that specific button at the OS level, unconditionally, before any page's JS ever
  sees it. Reported as part of the same "finicky" bug report (losing the game, having to back out of
  Game Center, controls dead on return) but it isn't the same bug -- it's what the OS does with that
  one button on ANY MFi-class controller, full stop, and no web-facing API exists to change or
  suppress it. The robustness work above is what actually helps the RECOVERY side of that sequence
  (pressing any OTHER button once back on the page re-takes-over immediately, same as a fresh
  connect) -- the Game Center detour itself isn't this codebase's to solve.
  NO <audio>/<video> ELEMENTS ON THIS PAGE, EVER -- this is the single most important thing to know
  about audio here, and it is load-bearing, not tidiness. On iOS a PLAYING HTMLMediaElement creates a
  system "Now Playing" session, and while one exists the OS routes a connected game controller's
  buttons to the MEDIA REMOTE (play/pause/skip) rather than delivering them to the page's Gamepad
  API at all. In the installed PWA that presented as the controller working for exactly one press
  and then going dead -- and it was identified only when a player noticed that opening the media
  player that had popped up and PAUSING the music handed control straight back. Several consecutive
  rounds of gamepad-polling "fixes" (stored slot index, release-on-quiet-poll, stateless re-scan)
  were chasing that symptom; none of them could ever have worked, because the input was never
  reaching the page. Toggling mute from the controller broke it the same way for the same reason
  (flipping .muted on the <audio> element re-asserted the session), which is why THAT looked like a
  separate bug too. Everything is Web Audio now -- engine, pass-by, collision, lap, and finally music
  (Game.playMusic() in racing/common.js) -- and an AudioContext creates no Now Playing session, so
  there is nothing for iOS to hand the controller to. Music is fetch()'d + decodeAudioData'd one
  track at a time rather than base64-embedded like the SFX (~3MB each, and a decoded AudioBuffer is
  raw PCM), trading a short silent gap at each track change for the controller working at all;
  mute is a GainNode value, not .muted, so a muted track keeps playing silently and unmuting resumes
  in place. v1-v3/dev.html still have an inert <audio id='music'> element in their markup -- nothing
  reads it any more, but don't take that as license to add one back here.
  KNOWN, ACCEPTED SIDE EFFECT of all of the above: on iPhone the game is now silent when the ringer
  switch is set to silent. That is a real behaviour change, not a bug to hunt -- <audio>/<video>
  playback on iOS ignores the mute switch (which is why video sites play on silent), while Web Audio
  respects it, so moving everything to Web Audio inherited that. It cost a live debugging session
  ("nothing starts audio now... the phone was on silent") before it was spotted, hence this note.
  There IS a WebKit API that would override it -- `navigator.audioSession.type = 'playback'` (Safari
  16.4+) -- but 'playback' is precisely the category meant for media that belongs in Now Playing,
  i.e. the exact thing whose session was hijacking the controller in the first place. Assume trying
  it re-breaks controller input until someone has actually tested it on a device; the ringer switch
  is a far cheaper thing to live with than that bug coming back. The other known workaround (playing
  a silent looping <audio> element to promote the audio session category) is a non-starter here for
  the same reason -- it's a playing HTMLMediaElement, which is the thing that must not exist.
  Audio unlocking must KEEP RETRYING, not fire once (Game.startMusic() in racing/common.js). An
  AudioContext starts 'suspended' and iOS only honours resume() from inside a genuine user-gesture
  call stack -- a gamepad button press very likely isn't one there (same platform family as iOS
  reserving the controller's PS button system-wide). A controller-only player therefore fires the
  first-interaction hook, has resume() quietly refused, and sits in silence forever if nothing
  retries -- reported exactly that way the moment music moved off <audio>. So startMusic() is
  idempotent (starting the playlist at most once) but re-attempts resume() every time it's called,
  and is wired to click/touchstart/keydown listeners that stay attached, plus gamepadTakeOver() in
  racing/index.html. Whichever gesture the platform does accept wins; the rest are no-ops. A source
  started while still suspended simply begins the moment the context resumes, so nothing is lost by
  starting the track before the unlock lands.
  DO NOT store a gamepad slot index (pollGamepad() in racing/index.html). Two separate revisions
  tracked "which slot is the active controller" (gamepadIndex) and both produced the same PWA bug:
  the stored index goes stale -- iOS shuffles or empties slots, re-reports the same pad at a
  different position, or hands back a Gamepad object that stops updating -- and every later poll then
  reads a dead slot forever while the controller is still physically connected and sending input.
  Reported as "it recognizes the first button press, then locks me out", recoverable only by bouncing
  out to Game Center and back (i.e. by forcing iOS to re-hand the page a fresh pad). There is no
  index now: every tick re-scans navigator.getGamepads() from scratch, taking any pad with live input
  first (which also makes "a controller press takes precedence" literally true, and picks the RIGHT
  pad when several are paired) and otherwise the first pad merely present (so releasing every button
  coasts, rather than silently handing the car back to auto-gas mid-straight). `gamepadActive` exists
  only to run the takeover/release side effects once -- it is never used to FIND the pad.
  DO NOT infer a gamepad disconnect from a quiet poll (pollGamepad()'s `if (!gp) return;` in
  racing/index.html). navigator.getGamepads() transiently returns null/empty entries for a frame or
  two entirely on its own -- the ORIGINAL version of that line already documented this ("browsers
  keep the slot but null the entry between the connect event and first poll, sometimes") and simply
  skipped the tick. A later "robustness" pass changed it to call gamepadRelease() there, reasoning
  that a vanished slot meant a vanished controller. It did not: every transient blip became a full
  handover back to auto-gas mid-race (keyFaster = true, hint text flipped, gamepadIndex cleared),
  and re-acquiring then required a FRESH button press with live input, since the takeover scan only
  fires on gamepadHasInput(). Reported as constant, unpredictable dropouts -- "the controller
  doesn't disconnect from the phone, it just disconnects from the game" -- and correctly identified
  by the user as a regression against code that had worked reliably. ONLY the explicit
  gamepaddisconnected EVENT releases now; that's an actual statement from the browser rather than
  something inferred from one quiet frame. The takeover scan (which only ever ADDS detection, never
  removes control) was kept.
  Options/Start -> mute (buttons[9], edge-detected against optionsWasPressed in pollGamepad()). Was
  pulled once and is back: toggling it used to kill controller input instantly ("I can toggle the
  audio with the controller but the second I do, I lose control"), which read as this binding's
  fault, but wasn't -- mute flipped .muted on the old <audio> music element, re-asserting iOS's Now
  Playing session and handing the controller to the media remote (see the no-<audio> entry above).
  With music on Web Audio there is no media element to poke. It calls Game.toggleMute() directly
  rather than dispatching a synthetic .click() on #mute (which is how it reused that element's two
  listeners before) -- same end state, no DOM events fired from inside the input poll loop. That
  function returns the new muted state, which the caller assigns to engineMuted so the Web Audio
  SFX stay in step with the music gain; tapping the icon still goes through the click listener,
  which updates engineMuted its own way.
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
  #mute (racing/index.html markup) was, until now, a sibling of #frame at the very end of <body> --
  NOT nested inside #racer despite #minimap's own comment already assuming it shared that coordinate
  space ("below the HUD row + mute icon"). With no positioned ancestor, its position:absolute resolved
  against the page's real viewport, not the canvas -- harmless everywhere it happened to roughly
  coincide with the canvas's own corner by accident (every layout before landscape touch controls
  existed), until #touch-controls became a genuine fixed, full-viewport overlay with a real button
  living in that exact corner. Reported as the right brake button's label rendering wrong because the
  icon sat on top of it. The 768px portrait override's own comment ("lands directly under the site's
  real hamburger menu... outside the iframe") is stale in the same way -- this page hasn't been
  iframed since skullracer.php switched to inlining it directly (see that file's own top comment) --
  but the underlying concern it's protecting against is still real today, just under different
  terminology; that override's actual VALUES needed no change, since portrait's canvas already spans
  nearly the full page width, making canvas-relative and viewport-relative land in nearly the same
  place there regardless. Moved the `<span id="mute">` element itself into #racer's own markup
  (right before #minimap, matching that comment's original assumption) -- position:absolute now
  correctly resolves against #racer everywhere, no CSS values needed changing to fix the collision,
  just the DOM structure that was wrong underneath them.
  A second, separate bug on the same button: #btn-brake-2's own base rule (`display: none`, an ID
  selector) beats .touch-btn's class-level `display: flex` on specificity alone regardless of source
  order, so the first landscape override re-enabling it as `display: block` didn't actually restore
  flex-centering -- its BRAKE label rendered top-left per normal block text flow instead of centered
  like every other .touch-btn, #btn-brake included (which has no ID-level display rule fighting it).
  Reported as the right brake's text still not matching the left one's even after the #mute fix.
  Needs to say `display: flex` explicitly wherever it's re-enabled, not just "not none".
  Landscape widescreen (updateLandscapeCanvasSize()/LANDSCAPE_QUERY in racing/index.html, called from
  ready:, window resize, and orientationchange): fills the actual space between the side touch/brake
  buttons instead of staying locked to the fixed 4:3 every other layout uses. Not a hack -- Util.
  project() in common.js (its own 3-line body) scales screen.x purely off `width` and screen.y purely
  off `height`, entirely independently; nothing in the actual projection math assumes 4:3, that's
  only ever been a choice every OTHER layout happened to make. Widening the canvas relative to its
  height doesn't stretch anything, it genuinely reveals more world side-to-side (same vertical FOV, a
  wider horizontal one) -- confirmed by `resolution = height/480` in reset() already deriving sprite
  scale from height alone, further proof width was never load-bearing for anything visual.
  CSS/JS split: #racer's own landscape rule now computes HEIGHT directly (`calc(100dvh - 56px)`, the
  exact same dvh-safe reserve math from the earlier landscape-fit fix, just expressed without a
  4:3-derived width anymore) and leaves width:auto for JS to own; #canvas gets height:100%/
  aspect-ratio:auto instead of the unconditional rule's width:100%/aspect-ratio:4:3. JS reads
  #racer's own CSS-computed height back via getBoundingClientRect() (never recomputes it -- one
  source of truth for that number, not two) and sets an explicit inline width from there: measures
  #btn-left's real rendered right edge (its width + the 10px inset already baked into that one
  number) rather than duplicating the .touch-btn clamp() formula in JS too -- same "two copies, one
  forgotten" drift this project got burned by once already with the resprite/SPRITES hand-sync (see
  that feature's own comment in common.js). Adds a flat +44px beyond the measured button zone
  specifically so the widened canvas doesn't just re-create the #mute-vs-brake-button collision one
  edge over (32px icon + 10px inset + a little room, now that #mute sits at the canvas's own corner
  for real). Leaving landscape mode (or #btn-left not existing at all, i.e. every non-landscape
  layout) resets both inline widths to '' and reset()s back to the fixed 1024x768, handing control
  back to the stylesheet's own rule exactly as if this feature didn't exist there.
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
  Collision/lap SFX (playCollisionSound()/playLapSound(), collisionBuffer/lapBuffer, both in
  racing/index.html; sounds/collision-data.js/sounds/lap-data.js): Web Audio API one-shot
  BufferSourceNode -> GainNode chains now, same technique as playPassSound() just above them in the
  file, NOT the `<audio id='collision-sound'>`/`<audio id='lap-sound'>` elements this used to be.
  Reported live: repeatedly play()ing/pausing an HTMLMediaElement (once per hit, once per lap) was
  triggering iOS's system "Now Playing" media overlay in the installed PWA specifically -- WebKit
  auto-populates SOME level of media-session info for ANY HTMLMediaElement playback in standalone
  display mode, without a site needing to touch navigator.mediaSession at all, and each fresh
  paused->playing transition (which a short SFX finishing and re-triggering does constantly) can
  re-surface that overlay, popping up over the game and stealing focus from an active gamepad
  mid-race. Web Audio API buffer playback isn't treated as OS-level "media" the same way, so it
  doesn't build that overlay in the first place -- not a workaround for the popup, just not using
  the API that causes it. Base64-embedded (decodeBase64Audio(), shared with engineBuffer's own
  decode -- same file:// CORS reasoning sounds/engine-data.js already documented) rather than
  fetch()'d. Both gate on the same `engineMuted` flag playPassSound() already checks, not an
  `<audio>.muted` property -- there's no HTMLMediaElement left for either sound to set one on.
  Asset cache busting (skullracer.php's $sr_asset_v, RACER_ASSET_V global, loadImages() in
  racing/common.js): the server sends `Cache-Control: max-age=604800, public` -- SEVEN DAYS, no
  revalidation -- for everything static under racing/ (common.js, sounds/*-data.js, sprites.png,
  background.png). That's server config, NOT anything in this repo (no .htaccess here), so the only
  lever available is the URL. skullracer.php appends `?v=<token>` to the script tags in the body it
  extracts, and sets a RACER_ASSET_V global that loadImages() appends to the sprite/background URLs
  it builds at runtime (optional there on purpose -- standalone racing/index.html and v1-v3/dev.html
  don't define it and load the plain URLs, exactly as before). Token is the root VERSION file, which
  is already bumped every commit for the PWA banner, so this rides on an existing discipline rather
  than adding a second thing to remember.
  Reported live: a player got 404s on sprites.png and background.png in Chrome while both were
  perfectly fine on the server (200 on www AND apex). They were running a common.js cached from
  before asset paths were made root-absolute, so it still requested them folder-relative -- resolving
  to /staking/images/..., which 301s to the apex host and 404s there. Their HTML was current
  (skullracer.php is no-store, racing/index.html is max-age=3600 must-revalidate); only the week-old
  script wasn't. The quieter version of the same failure is worse and worth knowing about: sprites.png
  is REPACKED whenever a sprite is added (see common.js's SPRITES comment), so a stale sheet served
  alongside freshly-loaded coordinates means every sprite silently samples the wrong part of the
  image -- no error, just wrong art. If someone reports impossible-looking asset behaviour on this
  game, check what their browser actually has cached before believing the code is wrong.
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
  more precise than that judgment call). **Migration DONE** - `cryptcrawls.date_created` was
  added and verified on the live DB 2026-09-08 (`datetime NOT NULL DEFAULT current_timestamp()`),
  so monthly/weekly crawl counts are live and correct. This entry previously said the migration
  was still outstanding and that claim outlived the fact by some margin - if you are about to
  repeat "crawls don't count toward monthly Activity", re-check the column first.
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
  matching Crypt Crawl's own `'crawl'` weight) -- **migration DONE**, same as Crypt Crawl's:
  `cryptconquests.date_created` verified present on the live DB 2026-09-08
  (`datetime NOT NULL DEFAULT current_timestamp()`), so monthly/weekly Conquest counts are live
  and correct. This entry previously said it was outstanding; it wasn't. Card art (`cryptconquestGetCardArtPools`): auto-assigned
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

### Obscura (crop-reveal) - LAUNCHED

Launched 2026-09-09 after a prototype period. `games-obscura.md` is written and
in `$skullpaper_nav`. **There is deliberately no public marketing page** - no
`obscuragame.php` - because the user considers this an internal game; the nav and
Launchpad tiles point straight at `obscura.php`. Don't add one.

Two tables: `obscura_runs` (one row per player, the live run) and `obscura_scores`
(one row per run, for the boards). Scores are written **live on every solve**, not
at run end, because an unbeaten streak is exactly who the board should show; a
write-on-death design would leave the current leader off it until they lost.
`reward = 0` marks a score as unpaid - a flag, not a date comparison, so the cron
can run whenever and pay whatever is outstanding (same idiom as
`skull_racer_runs.reward`).

Weekly: `rewards.php?obscura=1` needs its own crontab entry. It pays
`checkObscuraLeaderboard($conn, false, true)` then calls `resetObscuraRuns()` -
**in that order**, since the reset is what flips the rows it just paid. The reset
also zeroes every active streak (the period ending is the one thing that breaks a
streak) and **clears the stored puzzle**, which is not optional: a streak-40
player has 12 options and 1 attempt stored, and dropping their streak to 0 without
clearing leaves that board rendering against `obscuraDifficulty(0)`'s 6 options.

Run-end Discord posts go to the `obscura` channel and show the artwork that beat
the player. Requires `getObscuraWebhook()` in
`credentials/webhooks_credentials.php`; guarded by `function_exists` at both the
channel switch and the caller, so a missing or misnamed webhook skips the post
rather than fataling the guess that triggered it. `ajax/obscura-action.php` had to
start including `webhooks.php` for this - db.php does not include it.
`OBSCURA_ANNOUNCE_MIN_STREAK` (3) keeps routine first-puzzle losses out of the
channel; set it to 1 to post every ended run.

The reveal caption also links the collection to **Wayup**
(`https://www.wayup.io/collection/<policy>`), reusing `getPoliciesListing()`'s
rule: linked only when `collections.policy` is a well-formed 56-hex policy id,
otherwise plain text, because a malformed one produces `wayup.io/collection/` -
a dead end that reads as the platform being broken. This is safe **only on the
reveal**: naming the collection while a puzzle is live would be handing over the
answer, which is why the option buttons are still not linked (see the
Not-done note in the option-grid commit).

**No collection repeats within a run.** `obscura_runs.seen_collections` holds the
answer collections used so far (JSON, capped at 200 - it is read on every
request). It is a *preference*, never a blocker: `obscuraPickPuzzle()` samples
once with the exclusion and, if nothing unseen turns up, samples again with none.
A long enough streak WILL exhaust the catalogue, and ending a run there would
punish a player for being good at the game. The list clears when the run ends and
at the weekly reset - the rule is per run, not per account.

Obscura sits **last in the Games group** in `$SKULLIANCE_BOARDS`, and registry
order is display order. It is the oddball of the set (not a run, race or match-3),
so it reads better as the tail than wedged between two arcade games. Game Master
stays first for the mirror-image reason.

Verified constants, from `obscuraDifficulty()` in `obscura-lib.php`:

| Streak | Attempts | Options | Reveal ladder (% of the shorter edge) | Grid |
|--------|----------|---------|----------------------------------------|------|
| 0-5    | 3        | 6       | 15 -> 35 -> 60                         | 3 across |
| 6-15   | 3        | 8       | 12 -> 25 -> 45                         | 4 across |
| 16-30  | 2        | 10      | 10 -> 25                               | 5 across |
| 31+    | 1        | 12      | 8                                      | 4 across |

Streak counts **consecutive solves, not days**, and persists - navigating away or
taking a break never breaks it. Options always outnumber attempts, so elimination
can never guarantee a win; the run is genuinely losable, which is what makes an
unbounded streak worth anything.

Two rules the code exists to enforce, both easy to undo by accident:

1. **The crop is rendered server-side.** `ajax/obscura-crop.php` outputs only the
   visible region as pixels. It was CSS `background-position`/`background-size`
   first, which shipped the entire artwork and asked the browser to look away -
   View Source was the answer. The crop endpoint reads **nothing** from the query
   string but a cache-buster: which NFT, where the crop sits and how far it has
   widened all come from the player's own run row, so a client cannot request a
   wider crop than its attempts have earned. `obscuraState()` therefore returns
   no art url, no `crop_x`/`crop_y` and no zoom - only a `crop_v` token
   (`nft_id-attempts_used`) whose only job is forcing a re-fetch.
2. **A missing or broken image must never cost the player.** `obscuraLocalArt()`
   resolves local cache files only and returns an explicit `null` on a miss -
   it deliberately does NOT call `getIPFS()`, which hands back an `ipfs.io` url
   that is indistinguishable from a real hit without string-matching. Files under
   1KB are treated as truncated. When the browser still cannot render a crop, the
   client asks for a `reroll`, which swaps the puzzle with **no** attempt spent
   and the streak untouched. This matters most at streak 31+, where one attempt
   is the whole run.

The full artwork **is** sent once a puzzle is judged (`'reveal'` on the correct
and failed replies, never on `'wrong'`) - the zoom-out to the whole piece is the
payoff. It is resolved before the run row is cleared, since that drops `nft_id`,
and by then the row already points at a new NFT, so the reveal url cannot be
turned around on a live puzzle.

The reveal is captioned with the NFT's name and, **only with the holder's
consent**, who holds it. `obscuraRevealDetails()` requires `users.visibility == 2`
before naming anyone - the same gate `profile.php:40` and `gallery.php:33` use
before showing a member's NFTs, because "held by X" publishes part of X's
collection to whoever happens to be playing. Don't loosen it, and don't replace
an unnamed holder with "private" or "anonymous": that would itself disclose that
somebody on the platform holds the piece. **The avatar is gated with the name,
in the same branch, and set in exactly one place** - a face identifies as surely
as a username, so there must be no path that shows one without the other. It uses
the podium's construction and its `icons/skull.png` fallback
(`leaderboards.php:302-304`). Most of `nfts` is `user_id = 0` anyway,
so the join is a LEFT JOIN - an unowned NFT still has a name and still earns a
reveal. The name is arbitrary on-chain metadata, so the caption is built from DOM
nodes and never from an HTML string.

**Nothing in Obscura is timed, and that is a decision, not an omission.** A
per-attempt clock was built and reverted the same day: it is anxiety-inducing and
this game is meant to be relaxing. It also fought the run-persistence promise,
since any clock has to tell absence apart from hesitation to keep that promise.
Don't reintroduce a countdown, a speed bonus, or an auto-advancing reveal - the
reveal parks the next puzzle behind a Next button and waits for the player
indefinitely. Score is streak depth alone.

All of the above shipped at launch. Obscura also feeds `checkActivityLeaderboard()`
as the `obscura` source, counting **completed runs** (`active = 0`), weight 5 -
the same class of single session as a delve or a race. Counting solves instead
would let a ~15-second unit outweigh every other game by volume.

### Realm Guardians (tower defense) - LIVE, DOC PAGE STILL OWED

`guardians.php`, linked from the main menu under Realms as "Guardians" and
tiled on the Launchpad directly after Realms (that page mirrors the nav's
grouping deliberately - don't reorder it independently). It now has a **monthly
leaderboard paying 100,000 CARBON**, saved runs and Discord announcements, so
the "no doc page while it is a prototype" reasoning has expired: **`games-
guardians.md` is now owed** and is the main outstanding item. Prototype labels
were removed from the heading and the Launchpad tile 2026-09-10.

What a doc page will have to get right, because none of it is guessable:

- It **reads the player's real realm and never writes to it.** The snapshot is
  the only reason the file touches the database, and the test suite asserts no
  `INSERT/UPDATE/DELETE` against any `realms*`, `soldiers`, `weapons`, `armor`
  or `raids*` table. That quarantine is the load-bearing property - a bug here
  must not be able to cost someone their raid army.
- **`soldiers.location` is a STATE, not a `locations.id`**: 1 = reserve,
  2 = Tower garrison, 3 = on raid (see `RG_LOC_*` in the file). Misreading it
  as a location id is what once put 20 soldiers on a wall holding 10.
- Every guardian carries their **actual** weapon and armor (level *and* name),
  not a count plus the best item owned. Both feed combat: weapon level sets
  damage, armor level decides how much of a breach is absorbed.
- Forged gear rolls on the **realm's own tables**: the Armory tier odds are the
  ones in `ajax/get-armory.php:151-163`, and Factory items *call*
  `getFactoryOdds()` rather than copying it, so a balance change there reaches
  the game for free.
- **Factory items are a SHELF, not a queue.** Each of the seven has its own
  button showing how many you hold (`#rg-shelf`), and `act('item-<id>')` spends
  that one. It used to be a single "Fortify" that spent `S.items.shift()` -
  whatever the Factory happened to build next - which made it the one action in
  the game the player could not choose ("I'm mashing items hoping for a
  miracle"). Buttons lead with what the item does *here* (`$rg_con_ui`) and keep
  the Realms name on the tooltip; the inventory stays one array so the Factory's
  total cap still binds, which is what makes clearing shelf space a decision.
  **Note the known imbalance:** the `%`-Success boost is inert once a volley
  one-shots an attacker (the Tower is single-target, one shot per 0.6s), and the
  rework cut the heal from `12 + factory*2` (50 at Factory 19) to a flat 8 - an
  ~11x nerf to the panic button that was not intended. Left as-is deliberately:
  the user did not want a stronger Fortify lengthening runs, and preferred the
  agency of choosing over restored numbers.
- **The Crypt costs TIME, not CARBON, and one rite empties it entirely.**
  Raising used to be bought (`raiseCost()`, priced down by Crypt level); it is
  now a production line - `cryptRate()` = `max(50, 320 - level*15)` ticks, a bar
  fills, and Raise returns **every** guardian in the Crypt for free and resets
  it. 30.5s at Crypt 1 down to a 5s floor at Crypt 18. The all-at-once payload
  is what makes the wait a decision: raising the instant it is ready spends the
  cycle on whoever happens to be dead, holding on returns everyone who falls in
  the meantime for the same wait. Measured at Crypt 16 (8s/rite): banking beats
  the old one-at-a-time rate above 4 bodies and is worse below it. Deliberately
  **not** limited by `reserveCap` - that governs Barracks stockpiling, and
  turning your own dead away because the barracks is busy would be a bewildering
  way to lose a run; the Barracks just pauses until the reserve drops back under
  its cap. The Crypt has **unlimited capacity**, so the line can never stall on
  being full - only on having nobody left to raise, which the caption says as
  good news rather than a fault. Consequence worth knowing: CARBON now has
  exactly **one** sink, Upgrade.
- **A progress bar completes only when something ARRIVES.** All production goes
  through one `produce(key, rate, deliver)` helper; the timer resets only if
  `deliver()` returns true, and a capped-out line holds at full (amber) instead
  of cycling. Before this, every line reset regardless, so a full cache still
  swept a bar to 100% for ever - modelled at 8 completions for 4 deliveries,
  half of it theatre. A held bar is also a signal: the caption turns into the
  reason and points at the Upgrade button below it. The Mine (uncapped) and the
  Portal (a cooldown) cannot stall and are not marked blockable.
  **It only says "upgrade for room" when the NEXT level actually adds room**
  (`heldText()` compares against `weaponCap(lvl+1)` etc). A big realm arrives
  holding far more than the in-game caps - 159 weapons and 114 armor against
  caps of 76 and 14 - and clearing those by upgrading would take 28 and 199
  Armory levels. Note also that `armorCap` and `itemCap` only rise every *other*
  level, so an upgrade there often adds nothing; the check catches that too.
  When upgrading cannot help, the caption names what does: issuing gear
  (Deploy/Strike draw from the pool) or spending an item.
- **Issuing gear uses that same roll** (`drawFor()`), not the top of the cache.
  Taking the front of a best-first pool meant a Strike of five drew five
  consecutive entries from a sorted list - i.e. the same item - so every
  guardian rode out identically armed and the Armory level was invisible once
  you owned one good weapon. Measured: average tier issued now rises 1.80 (L1)
  to 4.80 (L9). Levels pair up (1/2, 3/4, 5/6, 7/8) because the real odds table
  does.
- Any bucket read as a **batch** must be de-gradiented first (`$rg_destride`).
  The kit query's `ORDER BY` exists so `LIMIT 400` keeps the best soldiers, but
  it also means reading off the front hands you one gear tier. The raid line
  and the reserve both need it; the Tower bucket deliberately does not, because
  the best soldiers should man the wall.
- **Music: $rg_want's declaration order IS the picker order, and entry one is
  the default track.** Five tracks as of 2026-09-10: Guardians of the Realm,
  Stand Your Ground, Mechanical Pulse, Frontline Assault, War Machine Awakens,
  all committed under `audio/tracks/` alongside Crypt Crawl's six (unlike
  images, audio DOES live in the repo, and a newly dropped file arrives
  untracked -- commit it WITH the code change or the picker gets a 404).
  Files are DISCOVERED by glob + a letters-only substring match, not hardcoded,
  because the filenames vary in spacing and capitalisation; anything unmatched
  and not Crypt Crawl's still shows up labelled by its filename rather than
  leaving an empty control. The order used to come from glob(), i.e. alphabetical
  by FILENAME, so `$rg_want`'s own order was silently ignored and adding a file
  named early in the alphabet would have changed which music a player hears
  first. Matches are now keyed by want-key and emitted by walking `$rg_want`.
  **Frontline Assault is the proof that mattered**: F sorts before G, so under
  the old code it would have become track one and replaced the default music.
  URLs `rawurlencode()` the FILENAME ONLY -- the directory separator must
  survive, the spaces must not.
- **Effects volume: one multiplier, `SFX_GAIN` (0.75), applied inside
  `sfxPlay()`.** Reported 2026-09-10 as effects overpowering the music. The
  per-sound volumes at the call sites (0.09 fire, 0.14/0.16 death, 0.20 wave
  cue, 0.34 nuke) are a balance tuned BY EAR and must stay relative -- trim the
  bank with the multiplier, never by rewriting those numbers one at a time.
  Music is a separate channel (`musicVol`, own mute + slider) and must never be
  scaled by it; the whole point is judging one against the other.
  **The source files are NOT level matched**: measured mean volume runs from
  `machinegun.mp3` at -5.0 dB to `fist.mp3` at -22.0 dB -- a 17 dB spread -- while
  both play at gain 0.09. So perceived loudness depends on what the garrison is
  holding, and a wall of machine guns is the loudest case in the game (which is
  also the default kit on pre-populated raid soldiers). Fixing that properly
  needs per-sample compensation, not a global trim. Measure with
  `ffmpeg -i <f> -af volumedetect -f null -` before changing anything here.
- **`#rg-status` is hidden on phones with `font-size:0`, never `display:none`.**
  Reported 2026-09-11: the yellow status line pushed the board off the side of a
  phone depending on the message. It is the only item in the HUD whose width
  tracks its content (everything else is `flex:0 0 auto`), AND it is the
  `flex:1 1 auto` item that absorbs the bar's slack -- which is what pins the
  mute/pause cluster to the right edge. Measured in a 390px harness built from
  the real rules: `display:none` moved the controls from x=390 to x=293, while
  `font-size:0` keeps them at 390 and makes the bar's width constant no matter
  how long the message is. An auto margin on the first button is NOT a
  substitute: pause and retreat are `hidden` until a run starts, so no button is
  reliably first, and putting it on two splits the gap. Note the harness did NOT
  reproduce the overflow in isolation (ellipsis clipped it correctly there), so
  the trigger on the real device is unconfirmed -- likely larger text settings or
  a narrower screen. The fix removes the message as a width variable regardless.
- **Guardians has TWO clocks and they are not interchangeable.** Reported
  2026-09-11: the defeat modal said "45m 49s" and the Discord post said "264
  minutes" for the same siege. The modal prints `S.tick/10` (PLAYED time, stops
  while paused); the post printed `TIMESTAMPDIFF(SECOND, started_at, NOW())`
  (WALL CLOCK, counts the hours a saved run sat closed). Now that runs persist
  and can be paused, those diverge by hours. `guardians_scores.seconds` stores
  the PLAYED time, clamped server-side with `min($played, $secs)` so an
  untrusted client can only under-report. **The anti-cheat bound must keep using
  the wall-clock `$secs`** -- moving it onto the client figure would let a
  forged play time buy exactly the seconds `GUARDIANS_MIN_WAVE_SECONDS` demands.
  Rows written before this hold wall clock in that column; harmless, because
  `checkGuardiansLeaderboard()` ranks on held/wave/lost and never reads it.
- Guardians defeat posts use the **player's Discord avatar** as the embed
  thumbnail (`cdn.discordapp.com/avatars/<discord_id>/<avatar>.png`), falling
  back to `icons/locations/tower.png` when they have none -- the field must not
  be empty or the embed renders with a hole. Was the Tower on every post, which
  made a channel of sieges a wall of identical icons.
- **Drop Ship's real entry point is `dropship/dashboard.php`, never the bare
  `dropship/` directory.** The directory serves `index.php`, which is a redirect,
  not a page. It used to send everyone to the Skulliance login, so the launchpad
  tile (which linked to `dropship/`) bounced already-signed-in players to a login
  screen while the nav link (straight to dashboard.php) worked -- reported
  2026-09-11. index.php now routes a visitor carrying ANY session cookie to
  dashboard.php and only a cookieless one to the login. It deliberately does not
  decide "logged in" itself: `dropship/db.php` owns the session restore,
  including the SessionCookie fallback for Mobile Safari/PWA, and gates to
  error.php. Anything new linking to Drop Ship should point at dashboard.php.
- **The breach payout**: whoever's avatar breaks the wall receives whatever
  CARBON the realm was still holding, paid immediately at defeat (currency 15,
  the same pot the boards use), and 0 when the player spent it all -- the post
  then says the coffers were bare. **Deliberately uncapped**: a player who
  hoards makes a stranger rich, which is the lottery working, not a bug.
  `GUARDIANS_MAX_CARBON_RATE` (200/sec) is NOT that cap -- it is an integrity
  bound like `GUARDIANS_MIN_WAVE_SECONDS`, because the amount AND the recipient
  both arrive from the browser. Measured: honest leftover accrues at ~1.5/sec
  and the game's theoretical maximum is 50/sec, so the bound refuses only what
  the simulation could not have produced. The breacher is re-validated against
  `users` (non-empty discord_id and avatar, not the player) because a forged
  breacher used to cost a wrong @mention and now costs CARBON. **Known residual
  risk:** a modified client can still name a specific ELIGIBLE member rather
  than the one who actually broke through; closing that needs the horde roster
  persisted at Begin, which it is not. Needs the two-column migration in
  guardians-lib.php's header.
- Naming trap, verified live: icons use dashes and sounds omit separators -
  `icons/machine-gun.png` and `audio/sounds/machinegun.mp3`. Deriving one from
  the other with a single rule silently 404s. The sound whitelist mirrors
  `cryptcrawlWeaponSfxName()` in `cryptcrawl-render.php`.
- Player-facing wording drifts from the code on purpose: the Portal action is
  **Strike** in the UI while the action id, state and CSS ids stay `sortie`
  (the replay action log is written in the id). Factory items are **implicit** -
  one button, always "Fortify"; the log says what it did after it lands.
- **Progress IS saved now** (reversed 2026-09-10; it was deliberately not, on
  the reasoning that every realm eventually falls). What changed is run length:
  a siege is half an hour and the long ones an hour, so a closed tab costing one
  is the same failure the pause button exists to prevent, one step further out.
  `guardians_runs` holds one snapshot row per player; `guardians_scores` holds
  one row per fallen siege. **Both migrations are documented in
  `guardians-lib.php`'s header, not in `guardians.php`.**
- **The trust boundary is real and is bounded, not assumed.** Guardians cannot
  be server-authoritative the way Crypt Conquest is - it is a 10 Hz client
  simulation, not a turn per AJAX call. So: a saved *snapshot* is convenience
  and is **never scored**; a submitted *score* is checked against wall-clock
  time, with `started_at` stamped server-side at Begin and
  `GUARDIANS_MIN_WAVE_SECONDS = 15` as the floor (the modelled minimum is ~28s
  for wave 1 and ~47s at wave 50+, so it rejects nothing real). Full replay
  verification is possible later without touching the client - the sim is
  deterministic and `actionLog` already records `[tick, action]` - and that is a
  port of the combat loop, which is why the cheap bound is what shipped.
- **Monthly board, 100,000 CARBON**, ranked by waves **held past your own
  starting wave** rather than the raw wave reached: the wave you start on is
  handed to you by realm power, so ranking on it would rank realms rather than
  play. Monthly rather than weekly because an hour-long run would otherwise make
  the board about who had a free evening. Needs its own crontab entry hitting
  `rewards.php?guardians=1`; nothing here schedules it.
- **Retreat** (with a confirmation dialog) is how you leave a siege you no
  longer want - persistence created the need for it, since closing the tab used
  to be how you abandoned one. It returns you to the pre-run board, which is
  where the "start from scratch" toggle lives, so restarting from scratch falls
  out of the existing controls. Not scored: paying out for abandoning would make
  quitting a losing siege the correct play.
- **The page is PUBLIC** (2026-09-10). It does not include `skulliance.php` or
  `verify.php` - same pattern as `skullpaper.php` and `profile.php` - and
  restores the session from `SessionCookie` with `array_merge`, never a raw
  assign. A guest plays the conscript baseline (every realm query already sat
  behind `if ($rg_me > 0)`), and `post()` returns early rather than firing
  requests the endpoint would refuse. Nothing is saved and nothing is scored for
  them, said in the blurb BEFORE the run and again on the send-off. The horde is
  no new exposure: `profile.php` is public and already shows username and avatar;
  `visibility == 2` gates NFTs, not identity.
- **`guardiansgame.php` is the public front door**, the same split the other
  three games use (`cryptcrawlgame.php` etc): the `*game.php` file is the
  marketing page, the bare name is the thing you play. It includes NOTHING, not
  even `db.php`, so no visitor's session can affect it and a database outage
  cannot break it. Its CTA is a RELATIVE link, because the login cookie is
  host-only and an absolute one would log a visitor out of the game they just
  clicked into. `homepage.php` carries it in three places: the ItemList schema,
  the games grid and the screenshot strip.
- The screenshot is `images/guardians.png` (741x931, uploaded 2026-09-10). One
  `$rg_og_image` variable on the landing feeds the hero, OpenGraph, Twitter and
  the schema; `homepage.php` references it three times. **The homepage's
  screenshot grid now has 19 cards**, which is six full 3-column rows and one
  left over - so the `:last-child { grid-column: 2 }` straggler rule is back,
  and Realm Guardians is the card it centres. That grid pins `minmax` to 260px
  precisely so a 4th column can never fit (4x260+3x18 = 1094 against 1060px of
  content); at `.hp-grid`'s 250px a 4th *would* fit and the rule would misfire.
- The nav points Guardians at **`guardiansgame.php`**, the marketing page, not
  straight at the game - the same route Crypt Crawl, Crypt Conquest and Skull
  Racer take, so a staker clicking any of the four gets the same thing. Obscura
  is the deliberate exception (no public page; its nav comment says so). The
  **Launchpad** still links straight to `guardians.php`, which is also what it
  does for the others: someone on the Launchpad has already chosen to play.
- Every realm still eventually falls to the horde. There is no win condition.
  That is exactly why the siege can be **paused** - a run reaches an hour, so
  losing it to a phone call is the one failure a player learns nothing from.
  Pause is gated inside `step()` (not by clearing the interval, so resuming
  twice cannot start a second timer), it freezes `S.tick` so the `[tick, action]`
  log replays identically either way, and it refuses every action, because a
  pause you can act inside is an untimed planning window and deciding under
  pressure is most of the game. It **auto-pauses on `visibilitychange`** - that
  is the interruption that actually happens, and a backgrounded tab has its
  timers throttled so hard the game lurches rather than runs. Resuming is always
  manual: returning to a wave already half-way across the field is the ambush
  being avoided.
- **Horde spacing is the breath, not a tuning knob.** Attackers spawn off-screen
  in a column (`pos: 100 + i * (2.2 + rand() * 1.8)`) and walk in. In the user's
  words: *"the spacing is the breath that allows a player to focus on their mine
  upgrades without getting pummeled."* Do not tighten it to shorten runs -
  measured at wave 83 with identical firepower, going to 1.2-2.4 took leaks from
  19 to 58 and peak on-screen foes from 37 to 63.
- Related, and counter-intuitive: **a wave's length is set by the spawn tail, not
  by the fight.** The wave cannot end until the last attacker has crossed, so
  sweeping the garrison's volley damage across a 2x range moves total run time by
  zero. Upgrading makes you safer, never faster. ~1 minute per wave at waves
  50-83 is expected and is not a bug. A ~30-minute run is the intended shape.
- If difficulty is ever reopened, change **count, not per-foe HP**: the Tower is
  single-target (one volley per 0.6s), so HP is hypersensitive - at wave 83,
  +1 hp/wave swung total wall damage to 461%.
- A **"Start from scratch" toggle** lets a realm-holder play the conscript
  baseline (`$rg_scratch`) - every location at level 1, no enlisted guardians,
  a level-1 cache. It is a whole baseline object, not a flag: `REALM` points at
  `SNAPSHOT` or `SCRATCH` and everything downstream reads it identically. The
  toggle is hidden for players who have no realm (it would do nothing) and
  locked while a siege runs. The gear catalogues are read *outside* the realm
  gate for this reason - a conscript with no catalogue can never forge anything.

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
