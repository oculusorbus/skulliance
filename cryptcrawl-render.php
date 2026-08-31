<?php
// cryptcrawl-render.php — shared game-area renderer for Crypt Crawl.
//
// Extracted so both cryptcrawl.php's own GET (full page load, and the no-JS
// POST->redirect->GET fallback) and ajax/cryptcrawl-action.php (fragment
// response after an action, so the client can swap #cc-game-area in place
// without a real page navigation) render from exactly one copy of this
// markup instead of two that could quietly drift apart. See MAINTENANCE.md
// for why the AJAX path exists at all: every action used to be a full page
// reload, which tore down and rebuilt the <audio> element every single
// time, audibly stuttering the ambient music player.
	// Shared by the no_run intro screen below and the in-game "View
	// Instructions" modal (see the flee-row further down) -- one copy of the
	// rules text instead of two that could quietly drift apart.
	//
	// Restructured 2026-08-31 into labeled sections instead of one dense
	// prose paragraph -- same fix, same reason, as cryptconquestRulesHtml()
	// in cryptconquest-render.php: a wall of text made separate ideas hard
	// to tell apart. Also fixed a real inaccuracy caught in the rewrite --
	// the old text said a weapon "can only beat weaker enemies after each
	// kill," but the actual rule (see cardcrawl-crypt-crawl-prototype.md
	// memory / weapon-degrade validation harness) is EQUAL or lesser rank,
	// not strictly weaker.
	function cryptcrawlRulesHtml() { ?>
		<div class="cc-rules-section">
			<div class="cc-rules-label">🎯 The Goal</div>
			<p>Clear the entire 44-card deck to win the delve.</p>
		</div>

		<div class="cc-rules-section">
			<div class="cc-rules-label">🃏 A Crypt (Room)</div>
			<p>Four cards face up at a time. Resolve 3 of the 4 and the last one carries into the next crypt alongside 3 fresh cards. Or flee a fresh crypt once (not twice in a row) to reshuffle all 4 back into the deck and draw a new one.</p>
		</div>

		<div class="cc-rules-section">
			<div class="cc-rules-label">⚔️ Card Types</div>
			<ul class="cc-rules-list">
				<li><strong style="color:#ff9900;">♦ Diamonds</strong> -- weapons. Equip one and it stays until you use it, degrading so it can only beat an enemy at or below the rank of the one it just killed.</li>
				<li><strong style="color:#ff6b6b;">♥ Hearts</strong> -- medkits. The first one you use each crypt heals in full; any more after that in the same crypt still heal, just for half.</li>
				<li><strong style="color:#c8dce8;">♣♠ Clubs &amp; Spades</strong> -- enemies. Fight bare-handed and take full damage, or spend your weapon and take only the difference.</li>
			</ul>
		</div>

		<div class="cc-rules-section">
			<div class="cc-rules-label">🛡️ Last Stand</div>
			<p>The first hit that would take you to 0 HP each delve instead leaves you standing at 1 -- <span class="cc-second-wind">Last Stand</span>, once per delve, automatic.</p>
		</div>

		<div class="cc-rules-section">
			<!-- The last two of these four are distilled from the game's own
			     Discord community, not house-written -- players who improved
			     their win rate specifically called out prioritizing tough
			     enemies over avoiding them, and taking a bare-handed hit on
			     purpose to save the weapon. -->
			<div class="cc-rules-label">⚠️ Common Mistakes</div>
			<ul class="cc-rules-tips">
				<li><strong>Save your weapon for the fight that needs it.</strong> It only degrades further once you use it -- a fresh weapon spent on a weak enemy is a wasted edge later.</li>
				<li><strong>Don't burn every medkit in one crypt.</strong> Only the first heals in full; spacing them across crypts is worth more than hoarding them into one.</li>
				<li><strong>Take out tough enemies while you still can.</strong> Avoiding a dangerous Club or Spade doesn't make it go away -- it's still in the deck, waiting for a moment when you're weaker. Clear it while you can actually afford to.</li>
				<li><strong>Fighting bare-handed on purpose is a real move.</strong> Taking the HP hit instead of spending your weapon on an enemy it could still beat isn't a mistake -- it keeps your weapon's shrinking range open for something worse.</li>
			</ul>
		</div>
	<?php } ?>

<?php
// Guaranteed-minimal game-over confirmation -- built ENTIRELY from the
// $run already sitting in memory the moment an action ends a delve
// (cryptcrawlPlayCard()/cryptcrawlAbandonRun()'s own return value), with
// zero DB queries and zero dependency on anything that could fail: no
// fresh cryptcrawlGetMostRecentRun() re-fetch, no art, nothing. This is
// the fallback ajax/cryptcrawl-action.php (and cryptcrawl.php's own no-JS
// POST handler) reach for when the real cryptcrawlRenderGameArea() call
// throws for any reason -- added directly in response to a live report:
// the win/loss screen went missing on a PWA mid-recording, again, despite
// the earlier fix that isolated CARBON payout/Discord announce from the
// render step. That fix stopped a slow/failing SIDE EFFECT from blocking
// the response; it did nothing about the render itself failing. This
// closes that gap: whatever else breaks -- CARBON, Discord, art, a fresh
// DB read glitching -- the player still sees "you died" or "you escaped"
// and how far they got, because none of that is needed to know it.
// Same markup shape as the real game_over branch below (.cc-result,
// #cc-mood) so it's visually identical and the client's existing
// dedup-#cc-mood fix (see the "empty gameArea, not just hide it" comment
// in cryptcrawl.php) still applies unchanged.
function cryptcrawlMinimalGameOverHtml($run, $user_id) {
	$fell = ($run['status'] === 'lost');
	$mood = $fell ? 'death' : 'triumph';
	$theme_active = $fell ? '1' : '0';
	$theme_img = $fell ? "linear-gradient(180deg, rgba(7,17,26,.55), rgba(7,17,26,.88)), url('/staking/images/themes/8.jpg')" : '';
	$carbon_earned = intval($run['carbon_earned'] ?? 0);
	?>
	<div id="cc-mood" data-mood="<?php echo $mood; ?>" data-restarted="0" data-theme-active="<?php echo $theme_active; ?>" data-theme-img="<?php echo htmlspecialchars($theme_img); ?>" style="display:none;"></div>
	<div class="cc-inner">
		<div class="cc-result <?php echo $fell ? 'lost' : 'won'; ?>" data-run-id="<?php echo intval($run['id'] ?? 0); ?>">
			<div class="cc-result-icon"><?php echo $fell ? '💀' : '🏆'; ?></div>
			<div class="cc-result-title"><?php echo $fell ? 'You Died' : 'You Escaped'; ?></div>
			<div class="cc-result-sub">
				<?php echo intval($run['rooms_cleared'] ?? 0); ?> crypts cleared
				<?php if (!$fell): ?> &middot; <?php echo intval($run['hp'] ?? 0); ?> HP remaining<?php endif; ?>
			</div>
			<?php if (intval($user_id) > 0 && $carbon_earned > 0): ?>
				<div class="cc-result-carbon">
					<img src="icons/carbon.png" alt="" onerror="this.style.display='none';">
					+<?php echo number_format($carbon_earned); ?> CARBON earned
				</div>
			<?php endif; ?>
		</div>
		<form method="post"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cc-btn">💀 Delve Again</button>
		</form>
		<?php if ($fell): ?>
		<a href="leaderboards.php?filterby=weekly-cryptcrawl" class="cc-btn gold" style="margin-top:8px;">🏆 Weekly Leaderboard</a>
		<?php endif; ?>
	</div>
	<?php
}

// Renders the entire swappable game area -- flash modal, and whichever of
// no_run/game_over/active applies -- for the given user. Echoes directly
// (matches the established convention here, same as cryptcrawlRulesHtml()
// above) rather than returning a string.
function cryptcrawlRenderGameArea($conn, $user_id) {
	// A leftover guest run (from playing before logging in, or from any
	// single request whose $user_id misread as 0 -- e.g. mobile Safari
	// dropping PHPSESSID) must never be allowed to resurface once we know
	// for certain this is a real account. $_SESSION['cryptcrawl_guest_run']
	// is otherwise never cleared anywhere (see cryptcrawlGetActiveRun/
	// cryptcrawlGetMostRecentRun/cryptcrawlPlayCard/cryptcrawlFleeRoom/
	// cryptcrawlSaveRun in db.php, all of which read or write it whenever
	// $user_id is 0 for that one call) -- so a stale guest run sitting in
	// session can silently mask the player's real, DB-backed run on some
	// later request whose own $user_id read hiccuped, showing old fake
	// progress and hiding real data (guest runs never display CARBON).
	// Confirmed via a live DB query: a lost run's carbon_earned was correct
	// in the table (640) while the render showed no CARBON line at all.
	if (intval($user_id) > 0 && isset($_SESSION['cryptcrawl_guest_run'])) {
		unset($_SESSION['cryptcrawl_guest_run']);
	}
	$active_run = cryptcrawlGetActiveRun($conn, $user_id);
	$recent_run = $active_run ? null : cryptcrawlGetMostRecentRun($conn, $user_id);

	if ($active_run)                                              $state = 'active';
	elseif ($recent_run && in_array($recent_run['status'], ['won', 'lost'], true)) $state = 'game_over';
	else                                                           $state = 'no_run';

	$flashes = $_SESSION['cryptcrawl_flash'];
	$_SESSION['cryptcrawl_flash'] = [];

	// One-shot, set by cryptcrawlHandleAction() on start_run, read (and
	// cleared) here -- see #cc-mood's data-restarted below.
	$cc_just_started = !empty($_SESSION['cryptcrawl_just_started']);
	unset($_SESSION['cryptcrawl_just_started']);

	$suit_symbol = ['C' => '♣', 'S' => '♠', 'D' => '♦', 'H' => '♥'];
	$suit_color  = ['C' => '#c8dce8', 'S' => '#c8dce8', 'D' => '#ff9900', 'H' => '#ff6b6b'];

	// Ambient-music "mood" -- read by the audio player in cryptcrawl.php's
	// script block (see #cc-mood below) to swap in one of the 4 situational
	// tracks (Frantic/Doom/Death/Triumph), or fall back to the normal
	// Theme/Reprise loop. Computed server-side, from the same state the
	// rest of this function already has, so the client never needs to know
	// any game rules -- it just reacts to a value.
	//   - death/triumph: the delve just ended (loss/win).
	//   - frantic: Last Stand is still available, but every monster still in
	//     this room would drop the player to (or past) 0 HP even with the
	//     best possible weapon reduction -- i.e. even playing perfectly,
	//     using Last Stand this room is unavoidable.
	//   - doom: same lethal-threat check, but Last Stand is already spent --
	//     there's no safety net left, so the next such hit is a real death.
	// Deliberately a simple "any monster the player can't survive with
	// current gear" check, not a full game-tree solver over which 3 of 4
	// cards to resolve and in what order -- good enough to catch a genuinely
	// inescapable spot without trying to out-think every edge case.
	$cc_mood = 'normal';
	if ($state === 'game_over') {
		$cc_mood = ($recent_run['status'] === 'won') ? 'triumph' : 'death';
	} elseif ($state === 'active') {
		$mood_room = json_decode($active_run['room'], true) ?: [];
		$mood_hp = intval($active_run['hp']);
		$mood_weapon_power = $active_run['weapon_power'] !== null ? intval($active_run['weapon_power']) : null;
		$mood_weapon_beaten_rank = $active_run['weapon_beaten_rank'] !== null ? intval($active_run['weapon_beaten_rank']) : null;
		$lethal_threat = false;
		foreach ($mood_room as $mood_card) {
			if ($mood_card['type'] !== 'monster') continue;
			$mood_rank = intval($mood_card['rank']);
			$mood_weapon_helps = $mood_weapon_power !== null && ($mood_weapon_beaten_rank === null || $mood_rank <= $mood_weapon_beaten_rank);
			$mood_best_case_damage = $mood_weapon_helps ? max(0, $mood_rank - $mood_weapon_power) : $mood_rank;
			if ($mood_best_case_damage >= $mood_hp) { $lethal_threat = true; break; }
		}
		if ($lethal_threat) {
			$cc_mood = (intval($active_run['second_wind_used'] ?? 0) === 1) ? 'doom' : 'frantic';
		}
	}

	// Theme backdrop -- #cc-theme-bg is a PERMANENT element living outside
	// #cc-game-area in cryptcrawl.php (never destroyed/recreated by an AJAX
	// swap, unlike everything below), so it can no longer be PHP that
	// literally emits/omits the themed-panel markup per state the way
	// earlier versions of this page did -- that broke its Ken Burns
	// animation, restarting it on every single action instead of only when
	// the actual scene changed. Instead this just tells the client (via
	// #cc-mood below) whether a themed backdrop applies right now and which
	// image to use; applyThemeState() in cryptcrawl.php's script block
	// reconciles the persistent element against it and only re-randomizes
	// the pan/zoom when the image value itself is different from last time.
	$cc_theme_active = false;
	$cc_theme_img = '';
	if ($state === 'game_over' && $recent_run['status'] === 'lost') {
		$cc_theme_active = true;
		$cc_theme_img = "linear-gradient(180deg, rgba(7,17,26,.55), rgba(7,17,26,.88)), url('/staking/images/themes/8.jpg')";
	} elseif ($state === 'active') {
		$cc_theme_active = true;
		// See cryptcrawlRoomThemeFile() in db.php -- shared with the Discord
		// per-round announcement so there's one theme list, not two.
		$room_theme_url = '/staking/images/themes/' . cryptcrawlRoomThemeFile($active_run['rooms_cleared']);
		$cc_theme_img = "linear-gradient(180deg, rgba(7,17,26,.55), rgba(7,17,26,.88)), url('" . $room_theme_url . "')";
	}
?>
<div id="cc-mood" data-mood="<?php echo htmlspecialchars($cc_mood); ?>" data-restarted="<?php echo $cc_just_started ? '1' : '0'; ?>"
	data-theme-active="<?php echo $cc_theme_active ? '1' : '0'; ?>" data-theme-img="<?php echo htmlspecialchars($cc_theme_img); ?>"
	style="display:none;"></div>
<div class="cc-inner">
	<?php if ($flashes): ?>
	<div class="cc-flash-backdrop" id="cc-flash-backdrop">
		<?php foreach ($flashes as $f):
			$flash_icon = $f['type'] === 'win' ? '🎉' : (($f['type'] === 'loss' || $f['type'] === 'error') ? '⚠️' : 'ℹ️');
		?>
			<div class="cc-flash-modal <?php echo htmlspecialchars($f['type']); ?>" data-source="<?php echo htmlspecialchars($f['source'] ?? ''); ?>">
				<div class="cc-flash-icon"><?php echo $flash_icon; ?></div>
				<div class="cc-flash-text"><?php echo htmlspecialchars($f['msg']); ?></div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
	<?php if ($state === 'no_run'): ?>
		<div class="cc-rules"><?php cryptcrawlRulesHtml(); ?></div>
		<form method="post"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cc-btn">💀 Start Delve</button>
		</form>

	<?php elseif ($state === 'game_over'):
			$fell = ($recent_run['status'] === 'lost');
		?>
		<div class="cc-result <?php echo $fell ? 'lost' : 'won'; ?>" data-run-id="<?php echo intval($recent_run['id'] ?? 0); ?>">
			<div class="cc-result-icon"><?php echo $fell ? '💀' : '🏆'; ?></div>
			<div class="cc-result-title"><?php echo $fell ? 'You Died' : 'You Escaped'; ?></div>
			<div class="cc-result-sub">
				<?php echo intval($recent_run['rooms_cleared']); ?> crypts cleared
				<?php if (!$fell): ?> &middot; <?php echo intval($recent_run['hp']); ?> HP remaining<?php endif; ?>
			</div>
			<?php $carbon_earned = intval($recent_run['carbon_earned'] ?? 0); ?>
			<?php if ($user_id > 0 && $carbon_earned > 0): ?>
				<!-- Guests never see this: carbon_earned still accrues for them
				     (cryptcrawlPlayCard), but there's no account to actually
				     credit (cryptcrawlPayoutCarbon no-ops on a guest run), so
				     showing an amount they didn't really get would be misleading. -->
				<div class="cc-result-carbon">
					<img src="icons/carbon.png" alt="" onerror="this.style.display='none';">
					+<?php echo number_format($carbon_earned); ?> CARBON earned
				</div>
			<?php endif; ?>
		</div>
		<form method="post"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cc-btn">💀 Delve Again</button>
		</form>
		<?php if ($fell): ?>
		<a href="leaderboards.php?filterby=weekly-cryptcrawl" class="cc-btn gold" style="margin-top:8px;">🏆 Weekly Leaderboard</a>
		<?php endif; ?>

	<?php else: // active
		$room = json_decode($active_run['room'], true) ?: [];
		$deck_count = count(json_decode($active_run['deck'], true) ?: []);
		$hp = intval($active_run['hp']);
		$max_hp = intval($active_run['max_hp']);
		$hp_pct = $max_hp > 0 ? max(0, min(100, round(($hp / $max_hp) * 100))) : 0;
		$weapon_power = $active_run['weapon_power'] !== null ? intval($active_run['weapon_power']) : null;
		$weapon_name  = $active_run['weapon_name'];
		$weapon_beaten_rank = $active_run['weapon_beaten_rank'] !== null ? intval($active_run['weapon_beaten_rank']) : null;
		$can_flee = (intval($active_run['fled_last_room']) === 0) && (count($room) === 4);
	?>
		<div class="cc-hud">
			<div class="cc-hp-wrap<?php echo $hp_pct <= 30 ? ' low' : ''; ?>">
				<div style="font-size:0.72rem;opacity:0.6;margin-bottom:3px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
					<span>HP <?php echo $hp; ?> / <?php echo $max_hp; ?></span>
					<?php if (intval($active_run['second_wind_used'] ?? 0) === 0): ?>
						<span class="cc-second-wind" title="The first hit that would drop you to 0 HP this delve instead leaves you at 1 -- once per delve.">🛡️ Last Stand ready</span>
					<?php else: ?>
						<!-- Previously just vanished with no state to check -- a player
						     couldn't glance at the HUD mid-run and confirm whether
						     they'd already spent it (only a flash toast said so, and
						     it auto-dismisses in 4s). Shown explicitly now instead. -->
						<span class="cc-second-wind used" title="Already used this delve -- the next lethal hit ends it for real.">🛡️ Last Stand used</span>
					<?php endif; ?>
					<?php if ($user_id > 0): ?>
						<!-- Running total, updates every card (10x its rank -- see
						     cryptcrawlPlayCard in db.php) so it's visible building up
						     over the whole delve, not just revealed at the end. Pushed
						     to the far right of the row (margin-left:auto) instead of
						     sitting in DOM order after HP -- HP/Last Stand cluster on
						     the left, this stands apart on the right. Guests don't see
						     it here either: it exists for them too (accrues in the
						     guest session run same as a real one), but showing a live
						     "earning" counter for something that'll never actually pay
						     out reads as more misleading mid-game than it does as a
						     one-time number on the result screen. -->
						<span class="cc-hud-carbon" title="CARBON earned so far this delve">
							<img src="icons/carbon.png" alt="" onerror="this.style.display='none';">+<?php echo number_format(intval($active_run['carbon_earned'] ?? 0)); ?>
						</span>
					<?php endif; ?>
				</div>
				<div class="cc-hp-bar-bg"><div class="cc-hp-bar-fill" data-target-width="<?php echo 100 - $hp_pct; ?>" style="width:100%;"></div></div>
			</div>
			<div class="cc-hud-meta">
				<div class="cc-weapon">
					<?php if ($weapon_power !== null):
						// Same icon convention gauntlets.php uses for its own gear
						// panel: lowercase the weapon's name, hyphenate, .png — with
						// the same graceful onerror hide for any name with no icon
						// on disk (e.g. the "Rusty Blade" fallback name).
						$weapon_icon = 'icons/' . strtolower(str_replace(['%', ' '], ['', '-'], $weapon_name)) . '.png';
					?>
						<img class="cc-weapon-icon" src="<?php echo htmlspecialchars($weapon_icon); ?>" alt="" onerror="this.style.display='none';">
						<strong><?php echo htmlspecialchars($weapon_name); ?></strong> (pwr <?php echo $weapon_power; ?>) - <?php echo $weapon_beaten_rank !== null ? 'beats up to ' . cryptcrawlRankLabel($weapon_beaten_rank) : 'no limit yet, fresh'; ?>
					<?php else: ?>
						👊 Bare-handed
					<?php endif; ?>
				</div>
				<div style="font-size:0.72rem;opacity:0.5;">Crypts cleared: <?php echo intval($active_run['rooms_cleared']); ?> · Deck: <?php echo $deck_count; ?> left</div>
			</div>
		</div>

		<div class="cc-room">
			<?php foreach ($room as $i => $card):
				$suit = $card['suit']; $rank = intval($card['rank']); $type = $card['type'];
				// Display only — internal type stays 'monster' (game logic, DB
				// data) so this is purely a label swap, not a data rename.
				$type_label = $type === 'monster' ? 'enemy' : ($type === 'potion' ? 'medkit' : $type);
				$weapon_eligible = ($type === 'monster') && $weapon_power !== null && ($weapon_beaten_rank === null || $rank <= $weapon_beaten_rank);
				$dom_rgb = cryptcrawlDominantColor($card['image_url'] ?? '');
				$glow_rgba = $dom_rgb ? sprintf('rgba(%d,%d,%d,.45)', $dom_rgb[0], $dom_rgb[1], $dom_rgb[2]) : 'rgba(255,153,0,.35)';
				// Weapon cards get an icon-on-black face instead of NFT art (see the card
				// face rendering below) -- computed here, once, so the same icon shows on
				// the card face and the Equip button below it. Same lookup
				// cryptcrawlPlayCard() itself uses on equip, so it always matches what
				// actually gets equipped.
				if ($type === 'weapon') {
					$preview_weapon_name = cryptcrawlWeaponName($conn, $rank);
					$preview_weapon_icon = 'icons/' . strtolower(str_replace(['%', ' '], ['', '-'], $preview_weapon_name)) . '.png';
				}
				$medkit_icon = 'https://madballs.net/drop-ship/icons/medkit.png';
			?>
				<div class="cc-card" style="--cc-glow:<?php echo htmlspecialchars($glow_rgba); ?>;">
					<div class="cc-card-flip">
					<div class="cc-card-flip-inner">
						<div class="cc-card-face cc-card-back">
							<img class="cc-card-back-icon" src="/staking/pwa/skulliance-logo-icon.png" alt="">
						</div>
						<div class="cc-card-face cc-card-front">
						<?php if ($type === 'monster' && !empty($card['image_url'])): ?>
							<div class="cc-card-art">
								<img class="cc-card-img" src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="" loading="lazy" onerror="this.remove();">
								<div class="cc-card-corner tl" style="color:<?php echo $suit_color[$suit]; ?>;">
									<div class="cc-card-rank"><?php echo cryptcrawlRankLabel($rank); ?></div>
									<div class="cc-card-suit"><?php echo $suit_symbol[$suit]; ?></div>
								</div>
								<div class="cc-card-corner br" style="color:<?php echo $suit_color[$suit]; ?>;">
									<div class="cc-card-rank"><?php echo cryptcrawlRankLabel($rank); ?></div>
									<div class="cc-card-suit"><?php echo $suit_symbol[$suit]; ?></div>
								</div>
							</div>
						<?php elseif ($type === 'monster'): ?>
							<div class="cc-card-badge-standalone">
								<div class="cc-card-rank" style="color:<?php echo $suit_color[$suit]; ?>;"><?php echo cryptcrawlRankLabel($rank); ?></div>
								<div class="cc-card-suit" style="color:<?php echo $suit_color[$suit]; ?>;"><?php echo $suit_symbol[$suit]; ?></div>
							</div>
						<?php else: // weapon or potion -- icon on black instead of NFT art, keeping
							// the curated Crypties art reserved for enemies specifically. ?>
							<div class="cc-card-art cc-card-icon-face">
								<img class="cc-card-icon" src="<?php echo htmlspecialchars($type === 'weapon' ? $preview_weapon_icon : $medkit_icon); ?>" alt="" onerror="this.style.display='none';">
								<div class="cc-card-corner tl" style="color:<?php echo $suit_color[$suit]; ?>;">
									<div class="cc-card-rank"><?php echo cryptcrawlRankLabel($rank); ?></div>
									<div class="cc-card-suit"><?php echo $suit_symbol[$suit]; ?></div>
								</div>
								<div class="cc-card-corner br" style="color:<?php echo $suit_color[$suit]; ?>;">
									<div class="cc-card-rank"><?php echo cryptcrawlRankLabel($rank); ?></div>
									<div class="cc-card-suit"><?php echo $suit_symbol[$suit]; ?></div>
								</div>
							</div>
						<?php endif; ?>
						</div>
					</div>
					</div>
					<div class="cc-card-controls">
					<div class="cc-card-label"><?php echo htmlspecialchars($type_label); ?></div>
					<div class="cc-card-actions">
						<?php if ($type === 'monster'): ?>
							<form method="post"><input type="hidden" name="action" value="play_card">
								<input type="hidden" name="card_index" value="<?php echo $i; ?>">
								<input type="hidden" name="use_weapon" value="0">
								<button type="submit" class="cc-btn bare punchy">
									<span class="cc-btn-icon-big">👊</span>
									<span class="cc-btn-action">Fist Fight</span>
									<span class="cc-btn-detail">-<?php echo $rank; ?> HP</span>
								</button>
							</form>
							<?php if ($weapon_power !== null): ?>
								<form method="post"><input type="hidden" name="action" value="play_card">
									<input type="hidden" name="card_index" value="<?php echo $i; ?>">
									<input type="hidden" name="use_weapon" value="1">
									<button type="submit" class="cc-btn attack punchy" <?php echo $weapon_eligible ? '' : 'disabled'; ?>>
										<!-- Same $weapon_icon the HUD line above already computed for the
										     currently equipped weapon -- reused as-is so this always matches. -->
										<img class="cc-btn-icon-big-img" src="<?php echo htmlspecialchars($weapon_icon); ?>" alt="" onerror="this.style.display='none';">
										<span class="cc-btn-action">Use Weapon</span>
										<span class="cc-btn-detail"><?php echo $weapon_eligible ? '-' . max(0, $rank - $weapon_power) . ' HP' : 'Too worn'; ?></span>
									</button>
								</form>
							<?php endif; ?>
						<?php elseif ($type === 'weapon'): ?>
							<form method="post"><input type="hidden" name="action" value="play_card">
								<input type="hidden" name="card_index" value="<?php echo $i; ?>">
								<input type="hidden" name="use_weapon" value="0">
								<button type="submit" class="cc-btn warn punchy">
									<img class="cc-btn-icon-big-img" src="<?php echo htmlspecialchars($preview_weapon_icon); ?>" alt="" onerror="this.style.display='none';">
									<span class="cc-btn-action">Equip</span>
									<span class="cc-btn-detail"><?php echo htmlspecialchars($preview_weapon_name); ?></span>
								</button>
							</form>
						<?php else: ?>
							<form method="post"><input type="hidden" name="action" value="play_card">
								<input type="hidden" name="card_index" value="<?php echo $i; ?>">
								<input type="hidden" name="use_weapon" value="0">
								<button type="submit" class="cc-btn heal punchy">
									<img class="cc-btn-icon-big-img" src="<?php echo htmlspecialchars($medkit_icon); ?>" alt="" onerror="this.style.display='none';">
									<span class="cc-btn-action">Heal</span>
									<span class="cc-btn-detail"><?php echo intval($active_run['potion_used_this_room']) === 1 ? '+' . max(1, intval($rank / 2)) . ' HP (half)' : '+' . $rank . ' HP'; ?></span>
								</button>
							</form>
						<?php endif; ?>
					</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="cc-flee-row">
			<div class="cc-flee-cell">
				<form method="post"><input type="hidden" name="action" value="flee">
					<button type="submit" class="cc-btn secondary" <?php echo $can_flee ? '' : 'disabled'; ?>>🏃 Flee This Crypt</button>
				</form>
				<?php if (!$can_flee): ?><div class="cc-note">already fled last crypt, or mid-crypt - can't flee now</div><?php endif; ?>
			</div>
			<form method="post" onsubmit="return confirm('Abandon this run? It counts as a loss.');">
				<input type="hidden" name="action" value="abandon">
				<button type="submit" class="cc-btn secondary">🏳️ Abandon Run</button>
			</form>
			<button type="button" class="cc-btn secondary" id="cc-instructions-btn">📖 View Instructions</button>
			<a href="leaderboards.php?filterby=weekly-cryptcrawl" class="cc-btn secondary">🏆 View Leaderboard</a>
		</div>

		<!-- Pure client-side toggle -- the rules text is static, no server
		     round trip needed to revisit it mid-delve. See the bottom script
		     block for the open/close wiring. -->
		<div class="cc-instructions-backdrop" id="cc-instructions-backdrop">
			<div class="cc-instructions-modal">
				<h3>📖 How to Play</h3>
				<div class="cc-rules"><?php cryptcrawlRulesHtml(); ?></div>
				<button type="button" class="cc-btn secondary cc-instructions-close" id="cc-instructions-close">Close</button>
			</div>
		</div>
	<?php endif; ?>
</div><!-- /cc-inner -->
<?php
}
