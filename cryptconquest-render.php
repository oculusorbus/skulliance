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
// Short quick-reference wording for the always-on .cq-suit-key strip below
// the HUD -- verb+noun, parallel across all four so none reads longer than
// the others at a glance. Keyed/ordered C/H/D/S to match both the actual
// resolution order in cryptconquestPlay() (db.php) and the order
// cryptconquestgame.php's own mechanics section already lists them in --
// one convention, three surfaces.
$CRYPTCONQUEST_SUIT_EFFECT = ['C' => 'Doubles Damage', 'H' => 'Heals Discards', 'D' => 'Draws Cards', 'S' => 'Grants Shield'];

function cryptconquestRankBadge($rank) {
	$labels = [11 => 'J', 12 => 'Q', 13 => 'K'];
	return $labels[intval($rank)] ?? strval(intval($rank));
}

// Corner "rank" display for a card -- Animal Companions have no numeric
// rank (their $card['rank'] is null, so cryptconquestRankBadge() would
// otherwise render intval(null) as a bare "0"). Shows "1" instead of a
// paw glyph -- less thematic, but it's also literally their card value
// (see cryptconquestCardValue()), which reads faster at a glance than a
// decorative icon. Plain text now, same as any other rank -- no special
// styling needed, it inherits the corner's usual shadow like everything else.
function cryptconquestCornerRank($card) {
	return $card['type'] === 'companion' ? '1' : cryptconquestRankBadge($card['rank']);
}

// Shared by the no_run intro screen below and the in-game "View
// Instructions" modal -- one copy of the rules text instead of two that
// could quietly drift apart (same convention cryptcrawlRulesHtml() uses).
//
// Restructured 2026-08-31 from one dense prose paragraph into labeled
// sections -- direct feedback that the wall of text made it hard to tell
// separate ideas apart. Also the point the exact-kill/recruit mechanic (a
// real new player reported it wasn't landing as an actual instruction even
// though the old paragraph technically mentioned it in one clause) got its
// own section instead of a half-sentence aside, and where the two
// intuitive-but-wrong-play warnings (below, .cq-rules-tips) moved from a
// bolted-on afterthought to a proper "Common Mistakes" section.
function cryptconquestRulesHtml() { ?>
	<div class="cq-rules-section">
		<div class="cq-rules-label">🎯 The Goal</div>
		<p>Defeat all 12 court cards -- 4 <strong>Jacks</strong>, then 4 <strong>Queens</strong>, then 4 <strong>Kings</strong> -- to conquer the Necropolis. Each court card is immune to its own suit's power (numeric damage still counts against it).</p>
	</div>

	<div class="cq-rules-section">
		<div class="cq-rules-label">🃏 Your Turn</div>
		<p>Play one of the following to attack and trigger its suit, or <strong>Yield</strong>: no card played, no suit power, straight to covering whatever hits back.</p>
		<ul class="cq-rules-list">
			<li><strong>One card</strong>, any card.</li>
			<li><strong>2-4 cards of the same rank</strong>, totalling 10 or less.</li>
			<li><strong>An Animal Companion plus one other card</strong> -- any card, at any value. This pairing ignores the 10 limit, so a Companion can ride along with your 10.</li>
			<li><strong>Two Animal Companions</strong> together.</li>
		</ul>
	</div>

	<!-- Added 2026-09-01 after a player reported cards seeming to vanish from
	     hand ("I see 5, play a couple, expect 3, there's just 1"). Nothing was
	     wrong -- verified by a 300-game card-conservation simulation -- but
	     NOTHING in the rules said the hand never auto-refills, or that
	     covering damage also spends hand cards. That's the single most
	     important thing about this game and it was missing entirely. The same
	     player also assumed you could play cards FROM the discard pile, which
	     is why that's now stated explicitly rather than just implied. -->
	<div class="cq-rules-section">
		<div class="cq-rules-label">🔄 Your Hand Only Shrinks</div>
		<p><strong>Your hand is your health, and it does not refill at the end of a turn.</strong> Cards leave your hand two ways:</p>
		<ul class="cq-rules-list">
			<li><strong>Playing them</strong> -- any card you attack with goes to the discard pile, whatever its suit.</li>
			<li><strong>Covering damage</strong> -- cards you discard to survive a hit are gone too, <em>unless</em> you cover the hit exactly (see Perfect Guard below).</li>
		</ul>
		<p>You <em>never</em> play cards out of the discard pile -- every play comes from your hand. The only ways to gain cards back are <strong style="color:#ff9900;">♦ Diamonds</strong> and a <strong style="color:#00c8a0;">Joker flip</strong>.</p>
		<p style="opacity:.85;">Card flow: <strong>Deck → Hand → Discard.</strong> ♥ Hearts is the only route back, and it returns cards to the <em>deck</em>, not your hand.</p>
	</div>

	<div class="cq-rules-section">
		<div class="cq-rules-label">✨ Suit Powers</div>
		<p style="opacity:.85;">A power only fires if its suit differs from the court card's own.</p>
		<ul class="cq-rules-list">
			<li><strong style="color:#ff6b6b;">♥ Hearts</strong> -- moves that many <em>random</em> cards from your discard pile to the <strong>bottom of the deck</strong>. Not into your hand, and you won't draw them again for a while -- it keeps the deck from running dry rather than helping you right now.</li>
			<li><strong style="color:#ff9900;">♦ Diamonds</strong> -- draws that many cards from the deck <strong>into your hand</strong>. The only routine way to refill, so this is usually your lifeline, not a bonus. Draws stop at your <strong>8-card hand limit</strong> and the rest are forfeited.</li>
			<li><strong style="color:#c8dce8;">♣ Clubs</strong> -- doubles your damage. Resolves <em>first</em>, so the other suits in the same play scale off the doubled number too.</li>
			<li><strong style="color:#c8dce8;">♠ Spades</strong> -- shields you against that court card. Shield <strong>stacks and never wears off</strong> -- it lasts the whole fight, so two Spades plays against a King (20 attack) can leave it hitting you for almost nothing.</li>
		</ul>
		<!-- The single most-missed rule in the game: a player asked why an 8
		     played with a 1-value Companion drew far more than one card. Every
		     power reads the COMBINED total (engine: $attackValue is the sum of
		     the whole play), which the old "worth the total value of what you
		     played" line stated too quietly to land. -->
		<p><strong>Every power is worth the total of the whole play, not the value of the card carrying the suit.</strong> This is the most important thing on this page and the easiest to miss: pair an <strong>Animal Companion</strong> (worth 1) with your biggest card and that Companion's suit fires at the <em>combined</em> value. A Companion of Diamonds played with an 8 draws <strong>9 cards</strong>, not 1 -- and you never had to spend a big Diamond to do it.</p>
	</div>

	<div class="cq-rules-section">
		<div class="cq-rules-label">👑 Exact Kills Recruit the Enemy</div>
		<p>Deal <strong>exactly</strong> enough damage to defeat a court card and it doesn't go to the discard -- it goes face-down on <strong>top</strong> of your deck, so it's the very next card you draw. It then fights <em>for</em> you, worth its own attack stat: a <strong>Jack is 10, a Queen 15, a King 20</strong> -- far bigger than any number card in the deck, and it still triggers its suit at that value. A recruited King paired with an Animal Companion is the strongest play in the game.</p>
		<p style="opacity:.85;">Overkill just discards it like normal -- only an exact hit recruits it.</p>
	</div>

	<div class="cq-rules-section">
		<div class="cq-rules-label">🛡️ Covering Damage</div>
		<p>Whatever the court card hits back with (after your shield, if any) has to be covered by discarding cards from hand totalling <strong>at least</strong> that much. Coming up short doesn't hurt you -- the selection is just rejected so you can try again.</p>
		<!-- Undocumented until now: cryptconquestFlipJester() accepts phase
		     'suffer' as well as 'play', so a Jester is a genuine escape from a
		     hit you can't cover. Players had no way to know that from the
		     rules, which described it only as a turn replacement. -->
		<p><strong style="color:#00c8a0;">You can flip a Joker mid-attack.</strong> Staring at a hit your hand can't cover? Flipping a Joker right then discards your hand and deals you a fresh 8 -- and the attack is still waiting to be covered, now with a full hand. It's the best escape in the game, and you get two per run.</p>
		<p>If your hand truly can't cover a hit even after discarding all of it, <span class="cq-rally">Last Stand</span> saves you once per run: the blow is forgiven <em>and</em> you rally <strong>4 fresh cards</strong> from the deck so you can actually fight on. After that, the next uncovered hit ends it.</p>
	</div>

	<div class="cq-rules-section">
		<div class="cq-rules-label">🛡️ Perfect Guard</div>
		<p>Cover a hit <strong>exactly</strong> -- not a point over -- and your <strong>two highest-value</strong> cards held: they come straight back to your hand. Everything else you spent still goes to the discard.</p>
		<p style="opacity:.85;">So a 2-card exact match costs you <em>nothing at all</em>, while hitting the number with four cards still costs you the smaller two. Overpaying by even one point returns nothing. It's the defensive twin of an exact kill -- precision is rewarded on both sides of the turn.</p>
	</div>

	<!-- Added directly in response to a real new player's reported
	     approach: highest off-suit card on attack, exact-match-with-most-
	     cards on defense -- both textbook, intuitive-but-wrong plays the
	     sections above don't rule out on their own. These exist
	     specifically to head those off before they become habits, not to
	     duplicate the fuller tips list on cryptconquestgame.php. -->
	<div class="cq-rules-section">
		<div class="cq-rules-label">⚠️ Common Mistakes</div>
		<ul class="cq-rules-tips">
			<li><strong>Covering damage only needs to reach the total, not match it.</strong> Discard the fewest cards you can spare -- extra cards left in hand matter more than landing on a clean number.</li>
			<li><strong>Attack for the power you need, not just the biggest number.</strong> A small Diamond when your hand is thin beats a big off-suit card that doesn't do anything you actually need right now.</li>
			<li><strong>Don't spend a big Diamond to refill.</strong> You don't need one: a Diamond <em>Companion</em> paired with your biggest card draws just as many, and you keep the damage. Save real Diamonds for damage, and never play one into a full hand -- draws stop at 8 and the rest are forfeited.</li>
			<li><strong>Don't hoard your Jokers to the end.</strong> A Joker is worth most as an escape from a hit you can't cover, not as a tidy hand refresh. Two go unused in a lot of lost runs.</li>
			<li><strong>Cover the number exactly when you can.</strong> Overpaying by a single point loses everything you spent; hitting it on the nose hands your two best cards back.</li>
		</ul>
	</div>
<?php }

// Guaranteed win/loss confirmation built ENTIRELY from the run already in
// memory (the one cryptconquestHandleAction() just reported as finished) --
// no DB re-read, no art lookups, nothing that can fail or pick a different
// run. Mirrors cryptcrawlMinimalGameOverHtml(); see
// cryptcrawl-loss-screen-bug.md for why this exists.
//
// The specific failure it prevents: cryptconquestRenderGameArea() below picks
// what to show by asking "does this player have any active run?", and answers
// with a live board whenever an orphaned status='active' row exists. Orphans
// were possible until cryptconquestStartRun() got its duplicate guard, so
// accounts that accumulated them beforehand would otherwise keep getting a
// stray board instead of their result on every single finished run.
// Deliberately emits the same class="cq-result " marker the client's swap
// handler looks for, plus its own #cq-mood, so it behaves identically to a
// normal game-over render.
function cryptconquestMinimalGameOverHtml($run, $user_id) {
	$won      = ($run['status'] === 'won');
	$defeated = intval($run['enemies_defeated'] ?? 0);
	$carbon   = intval($run['carbon_earned'] ?? 0);
	$theme    = '/staking/images/themes/' . cryptconquestKingdomThemeFile($defeated);
	$theme_img = "linear-gradient(180deg, rgba(7,17,26,.55), rgba(7,17,26,.88)), url('" . $theme . "')";
	?>
	<div id="cq-mood" data-mood="<?php echo $won ? 'triumph' : 'death'; ?>" data-restarted="0"
		data-theme-active="1" data-theme-img="<?php echo htmlspecialchars($theme_img); ?>"
		data-sfx="<?php echo $won ? 'victory' : 'death'; ?>"
		style="display:none;"></div>
	<div class="cq-inner">
		<div class="cq-result <?php echo $won ? 'won' : 'lost'; ?>">
			<div class="cq-result-icon"><?php echo $won ? '👑' : '💀'; ?></div>
			<div class="cq-result-title"><?php echo $won ? cryptconquestTier($run) : 'The Necropolis Prevails'; ?></div>
			<div class="cq-result-sub"><?php echo $defeated; ?> / 12 court cards defeated</div>
			<?php if (intval($user_id) > 0 && $carbon > 0): ?>
				<div class="cq-result-carbon">
					<img src="icons/carbon.png" alt="" onerror="this.style.display='none';">
					+<?php echo number_format($carbon); ?> CARBON earned
				</div>
			<?php endif; ?>
		</div>
		<form method="post"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cq-btn">⚔️ New Conquest</button>
		</form>
		<!-- Shown on a WIN as well as a loss (fixed 2026-09-01) -- same fix and
		     rationale as cryptcrawl-render.php. -->
		<a href="leaderboards.php?filterby=monthly-cryptconquest" class="cq-btn gold" style="margin-top:8px;">👑 Monthly Leaderboard</a>
	</div>
	<?php
}

function cryptconquestRenderGameArea($conn, $user_id) {
	global $CRYPTCONQUEST_SUIT_SYMBOL, $CRYPTCONQUEST_SUIT_COLOR, $CRYPTCONQUEST_SUIT_NAME, $CRYPTCONQUEST_SUIT_EFFECT;

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
	// Card keys returned to hand by the most recent perfect guard -- read and
	// cleared like a flash, so the glow shows for exactly one render.
	$saved_keys = $_SESSION['cryptconquest_saved'] ?? [];
	$_SESSION['cryptconquest_saved'] = [];
	// Cards Diamonds just drew -- same read-and-clear lifetime, so they file
	// in on exactly one render.
	$drawn_keys = $_SESSION['cryptconquest_drawn'] ?? [];
	$_SESSION['cryptconquest_drawn'] = [];
	// A Jester's fresh hand -- same stagger-in/glow/sound treatment as a
	// Diamonds draw (shared .cq-card-drawn below), but tracked separately so
	// the badge can say something true: this hand didn't come from Diamonds.
	$jester_drawn_keys = $_SESSION['cryptconquest_jester_drawn'] ?? [];
	$_SESSION['cryptconquest_jester_drawn'] = [];
	$drawn_i = 0;

	// Sounds the action queued for the client (see cryptconquestSfx()), same
	// read-and-clear lifetime so each one plays on exactly one render. Emitted
	// on #cq-mood below rather than on a flash modal, because the outcomes that
	// need sound don't all show a modal -- Perfect Guard only shows one once
	// per run, but has to click on every exact defense.
	$sfx_queue = $_SESSION['cryptconquest_sfx'] ?? [];
	$_SESSION['cryptconquest_sfx'] = [];
	// Death is derived from the run's STATE rather than queued as an event,
	// unlike every other sound here. A loss has two render paths -- this one
	// and cryptconquestMinimalGameOverHtml(), the zero-dependency fallback
	// from the loss-screen bug -- and a session queue only survives one of
	// them. Reading it off status='lost' means both paths sound the same,
	// which matters most on exactly the path that exists because things went
	// wrong. Safe to re-derive on every render: the client only plays these
	// on an AJAX swap, so reloading a result screen stays silent.
	if ($state === 'game_over') {
		$ended = $recent_run['status'] ?? '';
		if ($ended === 'lost') $sfx_queue[] = 'death';
		elseif ($ended === 'won') $sfx_queue[] = 'victory';
	}

	// Three art sources: court cards + number cards draw from Season 1
	// (two held wallets pooled together, court and numbers split into
	// non-overlapping slices of it); Animal Companion cards draw from
	// Season 2 specifically, since S2's actual subject matter is animals
	// -- see cryptconquestGetCardArtPools()'s own comment in db.php.
	// Only fetched for the 'active' state -- the only place any of these
	// three pools actually get used (hand cards, the enemy badge); the
	// game_over screen shows none of it. This used to run unconditionally
	// for every render including game_over -- three extra queries plus
	// IPFS URL resolution, all wasted, and each one a chance for the very
	// screen a player needs most reliably (the win/loss result) to fail
	// for a reason that has nothing to do with it. Also try/catch-wrapped
	// now regardless of state: a real query error here (or anywhere else
	// in this function) must never take down the whole render -- see the
	// try/catch wrapper around this function's real body, further down.
	$enemy_art_pool = []; $player_art_pool = []; $companion_art_pool = [];
	$enemy_owner_pool = [];
	if ($state === 'active' && $conn) {
		try {
			// Seed the court lineup off the run's own id so it's fixed for
			// this run but different between runs. Guest runs have id 0, so
			// they fall back to the session id -- otherwise every guest on
			// the platform would face the exact same 12 owners forever.
			$art_seed = intval($active_run['id'] ?? 0);
			if ($art_seed <= 0) $art_seed = crc32(session_id() ?: 'guest');
			$art_pools = cryptconquestGetCardArtPools($conn, $art_seed);
			$enemy_art_pool = $art_pools['enemy'];
			$player_art_pool = $art_pools['player'];
			$companion_art_pool = $art_pools['companion'];
			$enemy_owner_pool = $art_pools['enemy_owners'] ?? [];
		} catch (\Throwable $e) {
			error_log('cryptconquestGetCardArtPools failed: ' . $e->getMessage());
			// Falls through to plain card faces (no art) -- every card face
			// already has an onerror handler for a 404'd <img>, this is the
			// same graceful-degradation path, just triggered earlier.
		}
	}

	// Enemy/hand computed here (not down in the 'active' render branch,
	// even though that's the only place they're displayed) since the mood
	// calc just below needs them too -- one computation, reused by both.
	$enemy = ($state === 'active') ? $active_run['current_enemy'] : null;
	$hand = ($state === 'active') ? $active_run['hand'] : [];
	$enemy_stats = $enemy ? cryptconquestEnemyStats($enemy['rank']) : ['attack' => 0, 'health' => 0];

	// One-shot, set by cryptconquestHandleAction() on start_run, read (and
	// cleared) here -- see #cq-mood's data-restarted below. Same pattern
	// as Crypt Crawl's cryptcrawl_just_started.
	$cq_just_started = !empty($_SESSION['cryptconquest_just_started']);
	unset($_SESSION['cryptconquest_just_started']);

	// Ambient-music "mood" -- read by the audio player in cryptconquest.php's
	// script block (see #cq-mood below) to swap in one of the 4 situational
	// tracks (Frantic/Doom/Death/Triumph, re-leveraging Crypt Crawl's own
	// audio files), or fall back to the normal Theme/Reprise loop.
	//   - death/triumph: the run just ended (loss/win).
	//   - frantic: Last Stand is still available, but the current hand's
	//     total value can't cover the attack that's either already pending
	//     (suffer phase) or would land if nothing changes (play phase,
	//     computed from the enemy's own stats minus shield) -- i.e. this
	//     exact fight is currently unsurvivable without Last Stand.
	//   - doom: same lethal-threat check, but Last Stand is already spent --
	//     there's no safety net left.
	// Deliberately the same "simple, not a full game-tree solver" spirit as
	// Crypt Crawl's own room-threat check (cryptcrawl-render.php).
	$cq_mood = 'normal';
	if ($state === 'game_over') {
		$cq_mood = ($recent_run['status'] === 'won') ? 'triumph' : 'death';
	} elseif ($state === 'active' && $enemy) {
		$mood_hand_total = array_sum(array_map('cryptconquestCardValue', $hand));
		$mood_pending = ($active_run['phase'] === 'suffer')
			? intval($active_run['pending_attack'])
			: max(0, $enemy_stats['attack'] - intval($enemy['shield']));
		if ($mood_hand_total < $mood_pending) {
			$cq_mood = (intval($active_run['last_rally_used']) === 0) ? 'frantic' : 'doom';
		}
	}

	// Theme backdrop -- #cq-theme-bg is a PERMANENT element living outside
	// #cq-game-area in cryptconquest.php (never destroyed/recreated by an
	// AJAX swap), so PHP can't just emit/omit the themed-panel markup
	// per state directly -- that would restart its Ken Burns animation on
	// every single action instead of only when the scene actually changes.
	// This just tells the client (via #cq-mood below) whether a themed
	// backdrop applies right now and which image to use; applyThemeState()
	// in cryptconquest.php's script block reconciles the permanent element
	// against it. Same pattern as Crypt Crawl's own #cc-mood/#cc-theme-bg
	// (see cryptcrawl-render.php/cryptcrawl.php) -- one hidden div carries
	// both the theme and mood signals together now, not two separate ones.
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
	<div id="cq-mood" data-mood="<?php echo htmlspecialchars($cq_mood); ?>" data-restarted="<?php echo $cq_just_started ? '1' : '0'; ?>"
		data-theme-active="<?php echo $cq_theme_active ? '1' : '0'; ?>" data-theme-img="<?php echo htmlspecialchars($cq_theme_img); ?>"
		data-sfx="<?php echo htmlspecialchars(implode(' ', $sfx_queue)); ?>"
		style="display:none;"></div>
	<div class="cq-inner">
	<?php if ($flashes): ?>
	<div class="cq-flash-backdrop" id="cq-flash-backdrop" onclick="this.remove();">
		<?php foreach ($flashes as $f): ?>
			<?php
			// A flash may carry card IDENTITIES -- one for an exact kill, several
			// for a perfect guard or a Last Stand rally. Art is resolved here
			// rather than in the action, since this is where the pools already
			// are, and by card key so it still works on the next render even
			// though the board has moved on.
			//
			// isset() on BOTH keys, not just 'cards': a flash carrying neither
			// (every non-exact kill, every plain info message) otherwise read
			// $f['card'] unguarded, and with display_errors on in db.php PHP
			// printed "Undefined array key" straight into the modal.
			$f_cards = $f['cards'] ?? (isset($f['card']) ? [$f['card']] : []);
			?>
			<div class="cq-flash-modal <?php echo htmlspecialchars($f['type']); ?>">
				<div class="cq-flash-icon"><?php echo $f_cards ? '🎯' : ($f['type'] === 'win' ? '⚔️' : ($f['type'] === 'error' ? '💀' : 'ℹ️')); ?></div>
				<?php if ($f_cards): ?>
				<div class="cq-flash-cards">
					<?php foreach ($f_cards as $fc):
						$fc_art = $enemy_art_pool[cryptconquestCardArtKey($fc)]
							?? $player_art_pool[cryptconquestCardArtKey($fc)]
							?? $companion_art_pool[cryptconquestCardArtKey($fc)] ?? null; ?>
						<div class="cq-flash-card<?php echo $fc_art ? ' cq-has-art' : ''; ?>">
							<?php if ($fc_art): ?>
								<img class="cq-card-art-img" src="<?php echo htmlspecialchars($fc_art); ?>" alt="" loading="lazy" onerror="this.remove();">
							<?php endif; ?>
							<div class="cq-card-corner tl">
								<div class="cq-corner-rank"><?php echo cryptconquestCornerRank($fc); ?></div>
								<div class="cq-corner-suit"><?php echo $CRYPTCONQUEST_SUIT_SYMBOL[$fc['suit']] ?? ''; ?></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<?php if (!empty($f['image'])): ?>
				<div class="cq-flash-image">
					<img src="<?php echo htmlspecialchars($f['image']); ?>" alt="" loading="lazy" onerror="this.parentElement.remove();">
				</div>
				<?php endif; ?>
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
		<!-- Shown on a WIN as well as a loss (fixed 2026-09-01) -- same fix and
		     rationale as cryptcrawl-render.php. -->
		<a href="leaderboards.php?filterby=monthly-cryptconquest" class="cq-btn gold" style="margin-top:8px;">👑 Monthly Leaderboard</a>

	<?php else: // active -- $hand/$enemy/$enemy_stats already computed above, for the mood calc
		$enemy_hp_left = $enemy ? max(0, $enemy_stats['health'] - intval($enemy['damage_taken'])) : 0;
		$enemy_hp_pct = $enemy_stats['health'] > 0 ? max(0, min(100, round(($enemy_hp_left / $enemy_stats['health']) * 100))) : 0;
		$enemy_attack_after_shield = $enemy ? max(0, $enemy_stats['attack'] - intval($enemy['shield'])) : 0;
		$suffering = ($active_run['phase'] === 'suffer');
		$jesters_left = 2 - intval($active_run['jesters_used']);
	?>
		<div class="cq-hud">
			<?php if ($enemy):
				$enemy_art_key = cryptconquestCardArtKey(['type' => 'court', 'suit' => $enemy['suit'], 'rank' => $enemy['rank']]);
				$enemy_art = $enemy_art_pool[$enemy_art_key] ?? null;
				// Who owns the NFT currently being used as this court card.
				// Only ever populated for public (visibility = 2) profiles --
				// see cryptconquestFetchCourtCandidates() in db.php.
				$enemy_owner = $enemy_owner_pool[$enemy_art_key] ?? null;
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
						<span class="cq-attack">⚔️ <?php echo $enemy_attack_after_shield; ?> attack<?php echo $enemy_attack_after_shield < $enemy_stats['attack'] ? ' (shielded)' : ''; ?></span>
					</div>
					<?php if ($enemy_owner && !empty($enemy_owner['username'])): ?>
					<!-- Owner credit for the NFT being used as this court card.
					     Deliberately its own element rather than a badge stamped
					     on the card art: cramming an arbitrary Discord avatar
					     into the corner of a curated card face was judged too
					     visually noisy, and it'd be too small to read anyway.
					     Only one is ever on screen (you face one court card at a
					     time), which is what keeps this a moment rather than
					     clutter. Links to the holder's profile. -->
					<a class="cq-enemy-owner" href="profile.php?username=<?php echo urlencode($enemy_owner['username']); ?>" title="View <?php echo htmlspecialchars($enemy_owner['username']); ?>'s profile">
						<?php if (!empty($enemy_owner['avatar_url'])): ?>
							<img class="cq-enemy-owner-avatar" src="<?php echo htmlspecialchars($enemy_owner['avatar_url']); ?>" alt="" loading="lazy" onerror="this.src='icons/skull.png';">
						<?php else: ?>
							<img class="cq-enemy-owner-avatar" src="icons/skull.png" alt="">
						<?php endif; ?>
						<span class="cq-enemy-owner-text"><span class="cq-enemy-owner-prefix">NFT owned by </span><strong><?php echo htmlspecialchars($enemy_owner['username']); ?></strong></span>
					</a>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>
			<div class="cq-hud-meta">
				<span title="Court cards defeated so far">👑 <?php echo intval($active_run['enemies_defeated']); ?> / 12</span>
				<span class="cq-rally<?php echo intval($active_run['last_rally_used']) ? ' used' : ''; ?>" title="The first time your whole hand can't cover an attack, you're saved instead of dying. Once per run.">
					🛡️ Last Stand <?php echo intval($active_run['last_rally_used']) ? 'used' : 'ready'; ?>
				</span>
				<span title="Discard your whole hand and refill -- twice per run">🃏 Jokers: <?php echo $jesters_left; ?> left</span>
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
					$footer = 'value ' . $value;
					if ($is_court) $footer = 'recovered &middot; ' . $footer;
					elseif ($is_companion) $footer = 'Companion &middot; ' . $footer;
				?>
					<?php
					$card_key = cryptconquestCardArtKey($card);
					$is_saved = in_array($card_key, $saved_keys, true);
					// Stagger index so drawn cards arrive one after another
					// rather than all at once -- that sequencing is what makes
					// it read as "Diamonds pulled THESE" (or, for a Jester
					// flip, "the fresh hand arrived"). One shared counter
					// across both sources -- a card can only be flagged by
					// one of them in practice (Diamonds draws mid-hand,
					// Jester empties the hand first), so there's never a
					// meaningful ordering question between them.
					$is_diamonds_drawn = in_array($card_key, $drawn_keys, true);
					$is_jester_drawn = in_array($card_key, $jester_drawn_keys, true);
					$is_drawn = $is_diamonds_drawn || $is_jester_drawn;
					$draw_i = $is_drawn ? $drawn_i++ : 0;
					?>
					<label class="cq-card<?php echo $is_saved ? ' cq-card-saved' : ''; echo $is_drawn ? ' cq-card-drawn' : ''; ?>" style="--cq-suit-color:<?php echo $CRYPTCONQUEST_SUIT_COLOR[$suit]; ?>;--draw-i:<?php echo $draw_i; ?>;">
						<?php if ($is_saved): ?><span class="cq-saved-badge">SAVED</span><?php endif; ?>
						<?php if ($is_diamonds_drawn): ?><span class="cq-drawn-badge">♦ NEW</span>
						<?php elseif ($is_jester_drawn): ?><span class="cq-drawn-badge">🃏 NEW</span><?php endif; ?>
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
								<div class="cq-card-companion-icon">1</div>
							<?php endif; ?>
							<div class="cq-card-corner tl">
								<div class="cq-corner-rank"><?php echo cryptconquestCornerRank($card); ?></div>
								<div class="cq-corner-suit"><?php echo $CRYPTCONQUEST_SUIT_SYMBOL[$suit]; ?></div>
							</div>
							<div class="cq-card-corner br">
								<div class="cq-corner-rank"><?php echo cryptconquestCornerRank($card); ?></div>
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
					<button type="submit" class="cq-btn purple" name="action" value="yield">Yield</button>
				<?php endif; ?>
			</div>
		</form>
		<div class="cq-note">Select one card, or 2-4 of the <em>same rank</em> totalling 10 or less, or an Animal Companion paired with one other card.</div>

		<!-- One panel (.cq-controls-panel, matching Crypt Crawl's own
		     .cc-flee-row), but still two deliberate ROWS inside it rather
		     than one: the run-affecting actions (Flip Joker, Abandon Run)
		     stay visually separate from the read-only ones (View
		     Instructions, View Leaderboard), so a stray tap in the
		     reference row can never reach something that changes or ends
		     the run. -->
		<div class="cq-controls-panel">
			<div class="cq-controls-row">
				<form method="post"><input type="hidden" name="action" value="flip_jester">
					<button type="submit" class="cq-btn secondary" <?php echo $jesters_left > 0 ? '' : 'disabled'; ?>>🃏 Flip Joker (<?php echo $jesters_left; ?> left)</button>
				</form>
				<form method="post" onsubmit="return confirm('Abandon this run? It counts as a loss.');">
					<input type="hidden" name="action" value="abandon">
					<button type="submit" class="cq-btn secondary">🏳️ Abandon Run</button>
				</form>
			</div>
			<div class="cq-controls-row">
				<button type="button" class="cq-btn secondary" id="cq-instructions-btn">📖 View Instructions</button>
				<!-- Mid-run leaderboard link, matching the one Crypt Crawl's own
				     controls row has had all along (Conquest previously had no
				     equivalent). Ordinary <a>, outside any form -- nothing for
				     the AJAX submit handler to intercept. -->
				<a href="leaderboards.php?filterby=monthly-cryptconquest" class="cq-btn secondary">👑 View Leaderboard</a>
			</div>
		</div>

		<!-- Always-on quick reference, not a click-to-open modal (that's
		     what "View Instructions" above is for) -- one row, icon + a
		     couple words per suit, so a new player never has to leave the
		     board to remember what a suit does. Enemy's own suit being
		     immune is already called out on the enemy card itself
		     (.cq-enemy-immune above), not repeated here. -->
		<div class="cq-suit-key">
			<?php
			// Display order only -- deliberately NOT $CRYPTCONQUEST_SUIT_EFFECT's
			// own key order (which stays C/H/D/S to match the engine's actual
			// resolution order and cryptconquestgame.php's mechanics section; see
			// that array's own comment). This row centers as a block, so the gap
			// between the 2nd and 3rd pill only lands on the board's own center
			// line if the two halves' TEXT WIDTH balances -- by letter count,
			// "Doubles Damage"+"Draws Cards" (left) vs "Grants Shield"+"Heals
			// Discards" (right) is the closest 2-2 split available (25 vs 27).
			foreach (['C', 'D', 'S', 'H'] as $suit): $effect = $CRYPTCONQUEST_SUIT_EFFECT[$suit]; ?>
				<span class="cq-suit-key-item" style="--cq-suit-color:<?php echo $CRYPTCONQUEST_SUIT_COLOR[$suit]; ?>;">
					<span class="cq-suit-key-icon"><?php echo $CRYPTCONQUEST_SUIT_SYMBOL[$suit]; ?></span><?php echo $effect; ?>
				</span>
			<?php endforeach; ?>
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
