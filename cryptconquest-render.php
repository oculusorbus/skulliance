<?php
// cryptconquest-render.php — shared game-area renderer for Crypt Conquest.
//
// Same split as cryptcrawl-render.php: both cryptconquest.php's own GET
// (full page load, and the no-JS POST->redirect->GET fallback) and
// ajax/cryptconquest-action.php (fragment response after an action) render
// from exactly one copy of this markup. See cryptconquest.md for the
// design doc and cryptconquest-engine.php for the ruleset this displays.

$CRYPTCONQUEST_SUIT_SYMBOL = ['H' => '♥', 'D' => '♦', 'C' => '♣', 'S' => '♠'];
$CRYPTCONQUEST_SUIT_COLOR  = ['H' => '#ff6b6b', 'D' => '#ff9900', 'C' => '#c8dce8', 'S' => '#c8dce8'];
$CRYPTCONQUEST_SUIT_NAME   = ['H' => 'Hearts', 'D' => 'Diamonds', 'C' => 'Clubs', 'S' => 'Spades'];

function cryptconquestRankBadge($rank) {
	$labels = [11 => 'J', 12 => 'Q', 13 => 'K'];
	return $labels[intval($rank)] ?? strval(intval($rank));
}

// Corner "rank" display for a card -- Animal Companions have no numeric
// rank, so they get a paw instead of cryptconquestRankBadge() (which
// would otherwise render intval(null) as a bare "0"). Keeps its native
// emoji color; a thin white outline (see the cq-companion-icon class in
// cryptconquest.php -- a crisp zero-blur text-shadow ring, not a soft
// glow) is what makes it pop against real S2 art.
function cryptconquestCornerRank($card) {
	return $card['type'] === 'companion' ? '🐾' : cryptconquestRankBadge($card['rank']);
}

// Shared by the no_run intro screen below and the in-game "View
// Instructions" modal -- one copy of the rules text instead of two that
// could quietly drift apart (same convention cryptcrawlRulesHtml() uses).
function cryptconquestRulesHtml() { ?>
	Conquer a 52-card Necropolis alone, one court card at a time: 4
	<strong>Jacks</strong>, then 4 <strong>Queens</strong>, then 4
	<strong>Kings</strong> -- each one immune to its own suit's power (the
	numeric damage still counts). Play a card from your hand (or several of
	the <em>same rank</em> totalling 10 or less) to attack and trigger its
	suit: <strong style="color:#ff6b6b;">♥ Hearts</strong> heals cards back
	from your discard into the deck, <strong style="color:#ff9900;">♦ Diamonds</strong>
	draws you fresh cards, <strong style="color:#c8dce8;">♣ Clubs</strong>
	doubles your damage, <strong style="color:#c8dce8;">♠ Spades</strong>
	shields you from that enemy's counterattack. Deal exactly enough damage
	to kill and the card returns to your hand later as a powerful attack;
	overkill just discards it. Whatever's left of the enemy's attack after
	your shield hits you back -- discard cards from hand totalling enough to
	cover it, or yield outright and skip straight to that. Run dry on cards
	entirely and a one-time <strong style="color:#00c8a0;">Jester flip</strong>
	(twice per run) discards your whole hand and deals you a fresh one. And
	if your hand truly can't cover a hit even after discarding all of it,
	<span class="cq-rally">Last Rally</span> saves you once per run --
	after that, the next uncovered hit ends it. Defeat all 12 court cards to
	conquer the Necropolis.
<?php }

function cryptconquestRenderGameArea($conn, $user_id) {
	global $CRYPTCONQUEST_SUIT_SYMBOL, $CRYPTCONQUEST_SUIT_COLOR, $CRYPTCONQUEST_SUIT_NAME;

	// Same stale-guest-run guard cryptcrawlRenderGameArea() uses -- once a
	// real user_id is confirmed, a leftover session-only guest run must
	// never resurface and mask the account's real DB-backed run.
	if (intval($user_id) > 0 && isset($_SESSION['cryptconquest_guest_run'])) {
		unset($_SESSION['cryptconquest_guest_run']);
	}
	$active_run = cryptconquestGetActiveRun($conn, $user_id);
	$recent_run = $active_run ? null : cryptconquestGetMostRecentRun($conn, $user_id);

	if ($active_run) $state = 'active';
	elseif ($recent_run && in_array($recent_run['status'], ['won', 'lost'], true)) $state = 'game_over';
	else $state = 'no_run';

	$flashes = $_SESSION['cryptconquest_flash'] ?? [];
	$_SESSION['cryptconquest_flash'] = [];

	// Three art sources: court cards + number cards draw from Season 1
	// (two held wallets pooled together, court and numbers split into
	// non-overlapping slices of it); Animal Companion cards draw from
	// Season 2 specifically, since S2's actual subject matter is animals
	// -- see cryptconquestGetCardArtPools()'s own comment in db.php.
	// Guarded on $conn so a null-$conn test harness (no live DB) degrades
	// to plain card faces everywhere instead of fataling -- production
	// always has a real $conn.
	$art_pools = $conn ? cryptconquestGetCardArtPools($conn) : ['enemy' => [], 'player' => [], 'companion' => []];
	$enemy_art_pool = $art_pools['enemy'];
	$player_art_pool = $art_pools['player'];
	$companion_art_pool = $art_pools['companion'];

	// Theme backdrop -- #cq-theme-bg is a PERMANENT element living outside
	// #cq-game-area in cryptconquest.php (never destroyed/recreated by an
	// AJAX swap), so PHP can't just emit/omit the themed-panel markup
	// per state directly -- that would restart its Ken Burns animation on
	// every single action instead of only when the scene actually changes.
	// This just tells the client (via #cq-theme-state below) whether a
	// themed backdrop applies right now and which image to use;
	// applyThemeState() in cryptconquest.php's script block reconciles the
	// permanent element against it. Same pattern as Crypt Crawl's own
	// #cc-mood/#cc-theme-bg (see cryptcrawl-render.php/cryptcrawl.php).
	$cq_theme_active = false;
	$cq_theme_img = '';
	if ($state === 'active') {
		$cq_theme_active = true;
		$theme_url = '/staking/images/themes/' . cryptconquestKingdomThemeFile($active_run['enemies_defeated']);
		$cq_theme_img = "linear-gradient(180deg, rgba(7,17,26,.55), rgba(7,17,26,.88)), url('" . $theme_url . "')";
	} elseif ($state === 'game_over') {
		// No dedicated "you lost"/"you won" image was supplied (unlike Crypt
		// Crawl's fixed 8.jpg death backdrop) -- reusing the theme matching
		// how far the run actually got reads as "this is where it ended"
		// either way, win or loss, rather than picking one of the 11 owner-
		// selected images arbitrarily to mean "defeat".
		$cq_theme_active = true;
		$theme_url = '/staking/images/themes/' . cryptconquestKingdomThemeFile($recent_run['enemies_defeated']);
		$cq_theme_img = "linear-gradient(180deg, rgba(7,17,26,.55), rgba(7,17,26,.88)), url('" . $theme_url . "')";
	}
	?>
	<div id="cq-theme-state" data-theme-active="<?php echo $cq_theme_active ? '1' : '0'; ?>" data-theme-img="<?php echo htmlspecialchars($cq_theme_img); ?>" style="display:none;"></div>
	<div class="cq-inner">
	<?php if ($flashes): ?>
	<div class="cq-flash-backdrop" id="cq-flash-backdrop" onclick="this.remove();">
		<?php foreach ($flashes as $f): ?>
			<div class="cq-flash-modal <?php echo htmlspecialchars($f['type']); ?>">
				<div class="cq-flash-icon"><?php echo $f['type'] === 'win' ? '⚔️' : ($f['type'] === 'error' ? '💀' : 'ℹ️'); ?></div>
				<div class="cq-flash-text"><?php echo htmlspecialchars($f['msg']); ?></div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php if ($state === 'no_run'): ?>
		<div class="cq-rules"><?php cryptconquestRulesHtml(); ?></div>
		<form method="post"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cq-btn">⚔️ Begin the Conquest</button>
		</form>

	<?php elseif ($state === 'game_over'):
			$won = ($recent_run['status'] === 'won');
			$defeated = intval($recent_run['enemies_defeated']);
		?>
		<div class="cq-result <?php echo $won ? 'won' : 'lost'; ?>">
			<div class="cq-result-icon"><?php echo $won ? '👑' : '💀'; ?></div>
			<div class="cq-result-title"><?php echo $won ? cryptconquestTier($recent_run) : 'The Necropolis Prevails'; ?></div>
			<div class="cq-result-sub"><?php echo $defeated; ?> / 12 court cards defeated</div>
			<?php $carbon_earned = intval($recent_run['carbon_earned'] ?? 0); ?>
			<?php if ($user_id > 0 && $carbon_earned > 0): ?>
				<div class="cq-result-carbon">
					<img src="icons/carbon.png" alt="" onerror="this.style.display='none';">
					+<?php echo number_format($carbon_earned); ?> CARBON earned
				</div>
			<?php endif; ?>
		</div>
		<form method="post"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cq-btn">⚔️ New Conquest</button>
		</form>

	<?php else: // active
		$hand = $active_run['hand'];
		$enemy = $active_run['current_enemy'];
		$enemy_stats = $enemy ? cryptconquestEnemyStats($enemy['rank']) : ['attack' => 0, 'health' => 0];
		$enemy_hp_left = $enemy ? max(0, $enemy_stats['health'] - intval($enemy['damage_taken'])) : 0;
		$enemy_hp_pct = $enemy_stats['health'] > 0 ? max(0, min(100, round(($enemy_hp_left / $enemy_stats['health']) * 100))) : 0;
		$enemy_attack_after_shield = $enemy ? max(0, $enemy_stats['attack'] - intval($enemy['shield'])) : 0;
		$suffering = ($active_run['phase'] === 'suffer');
		$jesters_left = 2 - intval($active_run['jesters_used']);
	?>
		<div class="cq-hud">
			<?php if ($enemy):
				$enemy_art = $enemy_art_pool[cryptconquestCardArtKey(['type' => 'court', 'suit' => $enemy['suit'], 'rank' => $enemy['rank']])] ?? null;
			?>
			<div class="cq-enemy" style="--cq-suit-color:<?php echo $CRYPTCONQUEST_SUIT_COLOR[$enemy['suit']]; ?>;">
				<!-- Same top-left + bottom-right corner-index treatment as
				     Crypt Crawl's own cards (.cc-card-corner tl/br) -- always
				     shown, art or not, rather than a big centered badge as a
				     special fallback case. onerror only removes the <img>
				     itself, not the corners, so a 404'd image still leaves
				     the card legible against the black background. -->
				<div class="cq-enemy-badge<?php echo $enemy_art ? ' cq-has-art' : ''; ?>">
					<?php if ($enemy_art): ?>
						<img class="cq-enemy-art-img" src="<?php echo htmlspecialchars($enemy_art); ?>" alt="" loading="lazy" onerror="this.remove();">
					<?php endif; ?>
					<div class="cq-card-corner tl">
						<div class="cq-corner-rank"><?php echo cryptconquestRankBadge($enemy['rank']); ?></div>
						<div class="cq-corner-suit"><?php echo $CRYPTCONQUEST_SUIT_SYMBOL[$enemy['suit']]; ?></div>
					</div>
					<div class="cq-card-corner br">
						<div class="cq-corner-rank"><?php echo cryptconquestRankBadge($enemy['rank']); ?></div>
						<div class="cq-corner-suit"><?php echo $CRYPTCONQUEST_SUIT_SYMBOL[$enemy['suit']]; ?></div>
					</div>
				</div>
				<div class="cq-enemy-info">
					<div class="cq-enemy-name">
						<?php echo cryptconquestCardLabel(['type' => 'court', 'suit' => $enemy['suit'], 'rank' => $enemy['rank']]); ?>
						<?php if (in_array(intval($enemy['rank']), [11, 12, 13], true)): ?>
							<span class="cq-enemy-immune" title="Immune to its own suit's power -- numeric damage still counts.">
								immune to <span class="cq-enemy-immune-suit"><?php echo $CRYPTCONQUEST_SUIT_SYMBOL[$enemy['suit']]; ?></span>
							</span>
						<?php endif; ?>
					</div>
					<div class="cq-hp-bar-bg"><div class="cq-hp-bar-fill" style="width:<?php echo 100 - $enemy_hp_pct; ?>%;"></div></div>
					<div class="cq-enemy-stats">
						<span><?php echo $enemy_hp_left; ?> / <?php echo $enemy_stats['health']; ?> HP</span>
						<?php if (intval($enemy['shield']) > 0): ?>
							<span class="cq-shield">🛡️ -<?php echo intval($enemy['shield']); ?></span>
						<?php endif; ?>
						<span class="cq-attack">⚔️ <?php echo $enemy_attack_after_shield; ?> atk<?php echo $enemy_attack_after_shield < $enemy_stats['attack'] ? ' (shielded)' : ''; ?></span>
					</div>
				</div>
			</div>
			<?php endif; ?>
			<div class="cq-hud-meta">
				<span title="Court cards defeated so far">👑 <?php echo intval($active_run['enemies_defeated']); ?> / 12</span>
				<span class="cq-rally<?php echo intval($active_run['last_rally_used']) ? ' used' : ''; ?>" title="The first time your whole hand can't cover an attack, you're saved instead of dying. Once per run.">
					🛡️ Last Rally <?php echo intval($active_run['last_rally_used']) ? 'used' : 'ready'; ?>
				</span>
				<span title="Discard your whole hand and refill -- twice per run">🃏 Jesters: <?php echo $jesters_left; ?> left</span>
				<!-- Display labels only -- 'Mausoleum'/'Crypt' are the in-game
				     names for what the code/DB still calls the castle/tavern
				     deck internally (castle_deck/tavern_deck), same way
				     'Necropolis' below is just what the rules text/result
				     screen call the kingdom. Renaming the underlying fields
				     would mean a schema migration for zero player-facing gain. -->
				<span>🏛️ Mausoleum: <?php echo count($active_run['castle_deck']); ?> left</span>
				<span>🪦 Crypt: <?php echo count($active_run['tavern_deck']); ?> left</span>
				<?php if ($user_id > 0): ?>
					<span class="cq-hud-carbon" title="CARBON earned so far this run">
						<img src="icons/carbon.png" alt="" onerror="this.style.display='none';">+<?php echo number_format(intval($active_run['carbon_earned'] ?? 0)); ?>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<?php if ($suffering): ?>
			<div class="cq-suffer-banner">
				⚔️ Incoming: <strong><?php echo intval($active_run['pending_attack']); ?> damage</strong> --
				select cards totalling enough to cover it (or your whole hand if you can't).
			</div>
		<?php endif; ?>

		<form method="post" id="cq-hand-form">
			<div class="cq-hand">
				<?php foreach ($hand as $i => $card):
					$suit = $card['suit'];
					$is_companion = $card['type'] === 'companion';
					$is_court = $card['type'] === 'court';
					$value = cryptconquestCardValue($card);
					// Recovered court cards keep reading from the ENEMY pool, not
					// the player pool -- a recovered King of Spades shows the same
					// art it had while you were fighting it, which is the point
					// (it's still that same enemy, just now in your hand). Animal
					// Companions read from their own S2 pool -- see
					// cryptconquestGetCardArtPools()'s comment in db.php for why
					// that's a different source than court/number cards.
					$art_pool = $is_court ? $enemy_art_pool : ($is_companion ? $companion_art_pool : $player_art_pool);
					$hand_art = $art_pool[cryptconquestCardArtKey($card)] ?? null;
					// Just the paw glyph (not the suit symbol) gets a white
					// text-shadow instead of the corner's usual black one, so it
					// pops against real S2 art without losing its native color --
					// see .cq-companion-icon in cryptconquest.php.
					$corner_rank_class = $is_companion ? ' cq-companion-icon' : '';
					$footer = 'value ' . $value;
					if ($is_court) $footer = 'recovered &middot; ' . $footer;
					elseif ($is_companion) $footer = 'Companion &middot; ' . $footer;
				?>
					<label class="cq-card" style="--cq-suit-color:<?php echo $CRYPTCONQUEST_SUIT_COLOR[$suit]; ?>;">
						<input type="checkbox" name="card_indices[]" value="<?php echo $i; ?>" class="cq-card-check">
						<!-- Unified card face: art (or plain black) + top-left/
						     bottom-right corner index, same as Crypt Crawl's own
						     .cc-card-corner tl/br -- no separate "has no art" layout,
						     just an optional <img> underneath the same corners. Value/
						     type text lives in .cq-card-footer BELOW the face instead
						     of overlaid on it, so real art never gets text stamped
						     across it. -->
						<div class="cq-card-face<?php echo $hand_art ? ' cq-has-art' : ''; ?>">
							<?php if ($hand_art): ?>
								<img class="cq-card-art-img" src="<?php echo htmlspecialchars($hand_art); ?>" alt="" loading="lazy" onerror="this.remove();">
							<?php elseif ($is_companion): ?>
								<div class="cq-card-companion-icon">🐾</div>
							<?php endif; ?>
							<div class="cq-card-corner tl">
								<div class="cq-corner-rank<?php echo $corner_rank_class; ?>"><?php echo cryptconquestCornerRank($card); ?></div>
								<div class="cq-corner-suit"><?php echo $CRYPTCONQUEST_SUIT_SYMBOL[$suit]; ?></div>
							</div>
							<div class="cq-card-corner br">
								<div class="cq-corner-rank<?php echo $corner_rank_class; ?>"><?php echo cryptconquestCornerRank($card); ?></div>
								<div class="cq-corner-suit"><?php echo $CRYPTCONQUEST_SUIT_SYMBOL[$suit]; ?></div>
							</div>
						</div>
						<div class="cq-card-footer"><?php echo $footer; ?></div>
					</label>
				<?php endforeach; ?>
			</div>
			<div class="cq-hand-controls">
				<?php if ($suffering): ?>
					<!-- The ONLY control on this form -- deliberately no hidden
					     action field plus a same-named button (that ambiguity is
					     what a duplicate-key bug looks like: PHP would just take
					     whichever one the browser happens to encode last). Every
					     submit button here names its own action instead. -->
					<button type="submit" class="cq-btn attack" name="action" value="suffer">Cover Damage</button>
				<?php else: ?>
					<button type="submit" class="cq-btn" name="action" value="play">Play Selected</button>
					<button type="submit" class="cq-btn secondary" name="action" value="yield">Yield</button>
				<?php endif; ?>
			</div>
		</form>
		<div class="cq-note">Select one card, or 2-4 of the <em>same rank</em> totalling 10 or less, or an Animal Companion paired with one other card.</div>

		<div class="cq-controls-row">
			<form method="post"><input type="hidden" name="action" value="flip_jester">
				<button type="submit" class="cq-btn secondary" <?php echo $jesters_left > 0 ? '' : 'disabled'; ?>>🃏 Flip Jester (<?php echo $jesters_left; ?> left)</button>
			</form>
			<form method="post" onsubmit="return confirm('Abandon this run? It counts as a loss.');">
				<input type="hidden" name="action" value="abandon">
				<button type="submit" class="cq-btn secondary">🏳️ Abandon Run</button>
			</form>
			<button type="button" class="cq-btn secondary" id="cq-instructions-btn">📖 View Instructions</button>
		</div>
	<?php endif; ?>

	<div class="cq-instructions-backdrop" id="cq-instructions-backdrop">
		<div class="cq-instructions-modal">
			<h3>How Crypt Conquest Works</h3>
			<div class="cq-rules"><?php cryptconquestRulesHtml(); ?></div>
			<button type="button" class="cq-btn" id="cq-instructions-close">Got it</button>
		</div>
	</div>
	</div><!-- /cq-inner -->
	<?php
}
