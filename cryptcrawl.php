<?php
// CRYPT CRAWL — now linked in nav (Play > Crypt Crawl, after Gauntlets).
// See db.php ("CRYPT CRAWL — PROTOTYPE" block) for the game logic. Still
// outstanding from the original prototype punch list: currency payout,
// Discord broadcast, and the skullpaper/cryptcrawl.md + MAINTENANCE.md
// entry CLAUDE.md calls for on a significant feature going live.
include_once 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';

$user_id = intval($_SESSION['userData']['user_id']);

if (!isset($_SESSION['cryptcrawl_flash'])) $_SESSION['cryptcrawl_flash'] = [];
function cryptcrawlFlash($msg, $type = 'info') {
	$_SESSION['cryptcrawl_flash'][] = ['msg' => $msg, 'type' => $type];
}

// ── POST action handling — must run before any output ───────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	if ($action === 'start_run') {
		cryptcrawlStartRun($conn, $user_id);

	} elseif ($action === 'play_card') {
		$run = cryptcrawlGetActiveRun($conn, $user_id);
		if ($run) {
			$card_index = intval($_POST['card_index'] ?? -1);
			$use_weapon = isset($_POST['use_weapon']) && $_POST['use_weapon'] === '1';
			$updated = cryptcrawlPlayCard($conn, intval($run['id']), $card_index, $use_weapon);
			if ($updated && $updated['status'] === 'lost') {
				cryptcrawlFlash('You fell in the crypt. Run over — ' . intval($updated['rooms_cleared']) . ' crypts cleared.', 'loss');
			} elseif ($updated && $updated['status'] === 'won') {
				cryptcrawlFlash('Deck cleared! You made it out with ' . intval($updated['hp']) . ' HP left.', 'win');
			}
		}

	} elseif ($action === 'flee') {
		$run = cryptcrawlGetActiveRun($conn, $user_id);
		if ($run) {
			$before = json_decode($run['room'], true) ?: [];
			$updated = cryptcrawlFleeRoom($conn, intval($run['id']));
			if ($updated && intval($updated['fled_last_room']) === 1 && count($before) === 4) {
				cryptcrawlFlash('You slipped past that crypt.', 'info');
			} else {
				cryptcrawlFlash("Can't flee twice in a row — face the crypt.", 'error');
			}
		}

	} elseif ($action === 'abandon') {
		cryptcrawlAbandonRun($conn, $user_id);
		cryptcrawlFlash('Run abandoned.', 'info');
	}

	header('Location: cryptcrawl.php');
	exit;
}

include 'header.php';

$active_run = cryptcrawlGetActiveRun($conn, $user_id);
$recent_run = $active_run ? null : cryptcrawlGetMostRecentRun($conn, $user_id);

if ($active_run)                                              $state = 'active';
elseif ($recent_run && in_array($recent_run['status'], ['won', 'lost'], true)) $state = 'game_over';
else                                                           $state = 'no_run';

$flashes = $_SESSION['cryptcrawl_flash'];
$_SESSION['cryptcrawl_flash'] = [];

$suit_symbol = ['C' => '♣', 'S' => '♠', 'D' => '♦', 'H' => '♥'];
$suit_color  = ['C' => '#c8dce8', 'S' => '#c8dce8', 'D' => '#ff9900', 'H' => '#ff6b6b'];
?>
<style>
/* Card index numerals only — rest of the page stays the site's normal Arial.
   Poppins ExtraBold approximates the bold, slightly-rounded look of a
   classic playing-card corner index (not an exact clone of any specific
   card brand's proprietary face — Google Fonts doesn't host one — but the
   closest properly-licensed match). */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@800&display=swap');

@keyframes ccCardIn { from { opacity: 0; transform: translateY(18px) scale(.94); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes ccFlashIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
@keyframes ccPulse { 0%, 100% { box-shadow: 0 0 0 rgba(255,68,68,0); } 50% { box-shadow: 0 0 16px 2px rgba(255,68,68,.65); } }
@keyframes ccBtnSheen { from { transform: translateX(-120%) skewX(-20deg); } to { transform: translateX(220%) skewX(-20deg); } }

.cc-wrap { padding: 20px 16px 60px; }
.cc-inner { max-width: 720px; width: 100%; margin: 0 auto; }
.cc-flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 0.85rem; animation: ccFlashIn .4s ease both; }
.cc-flash.win  { background: rgba(0,200,160,.12); border: 1px solid rgba(0,200,160,.35); color: #00c8a0; }
.cc-flash.loss { background: rgba(255,68,68,.12); border: 1px solid rgba(255,68,68,.35); color: #ff7070; }
.cc-flash.error{ background: rgba(255,68,68,.12); border: 1px solid rgba(255,68,68,.35); color: #ff7070; }
.cc-flash.info { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); color: #c8dce8; }
.cc-theme-bg { background-size: cover; background-position: center; border-radius: 14px; padding: 18px; margin: 0 -16px; transition: background-image .6s ease; display: flex; align-items: center; justify-content: center; box-sizing: border-box; min-height: 200px; }
.cc-hud {
	display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; animation: ccFlashIn .5s ease .15s both;
	background: rgba(5,12,20,.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
	border-radius: 12px; padding: 12px 14px; box-sizing: border-box;
}
.cc-hp-wrap { width: 100%; }
.cc-hud-meta { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; justify-content: space-between; }
.cc-hp-bar-bg { background: rgba(255,255,255,.08); border-radius: 6px; height: 14px; overflow: hidden; }
.cc-hp-bar-fill { background: linear-gradient(90deg,#ff4444,#ff9900,#00c8a0); height: 100%; border-radius: 6px; transition: width 1s cubic-bezier(.22,1,.36,1); }
.cc-hp-wrap.low .cc-hp-bar-bg { animation: ccPulse 1.1s ease-in-out infinite; border-radius: 6px; }
.cc-weapon { font-size: 0.8rem; opacity: 0.8; }
.cc-room { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-bottom: 18px; }
.cc-card {
	background: #002f44; border: 2px solid rgba(255,255,255,.08); border-radius: 10px; overflow: hidden; text-align: center;
	opacity: 0; animation: ccCardIn .5s cubic-bezier(.22,1,.36,1) both;
	transition: transform .18s ease-out, box-shadow .25s ease;
}
.cc-room .cc-card:nth-child(1) { animation-delay: .05s; }
.cc-room .cc-card:nth-child(2) { animation-delay: .13s; }
.cc-room .cc-card:nth-child(3) { animation-delay: .21s; }
.cc-room .cc-card:nth-child(4) { animation-delay: .29s; }
@media (hover: hover) and (pointer: fine) {
	.cc-room { perspective: 900px; }
	.cc-card:hover { box-shadow: 0 16px 32px rgba(0,0,0,.55), 0 0 28px var(--cc-glow, rgba(255,153,0,.35)); }
}
@media (hover: none), (pointer: coarse) {
	/* No hover/tilt on touch — a tap-press scale gives the same "this reacted
	   to me" feedback without a stuck hover state after lifting the finger. */
	.cc-card:active { transform: scale(.97); box-shadow: 0 0 22px var(--cc-glow, rgba(255,153,0,.3)); }
}
.cc-card-art { position: relative; aspect-ratio: 5 / 7; }
.cc-card-img { width: 100%; height: 100%; object-fit: contain; display: block; background: #002f44; }
.cc-card-rank, .cc-card-suit { font-family: 'Poppins', Arial, sans-serif; }
.cc-card-corner {
	position: absolute; display: flex; flex-direction: column; align-items: center; line-height: 1;
	text-shadow:
		-1.5px -1.5px 2px rgba(0,0,0,.95), 1.5px -1.5px 2px rgba(0,0,0,.95),
		-1.5px  1.5px 2px rgba(0,0,0,.95), 1.5px  1.5px 2px rgba(0,0,0,.95),
		0 0 8px rgba(0,0,0,.85), 0 0 3px rgba(0,0,0,.9);
}
.cc-card-corner .cc-card-rank { font-size: 1rem; font-weight: 800; }
.cc-card-corner .cc-card-suit { font-size: 1.3rem; margin-top: 1px; }
.cc-card-corner.tl { top: 8%; left: 12%; }
.cc-card-corner.br { bottom: 8%; right: 12%; transform: rotate(180deg); }
.cc-card-badge-standalone { display: inline-flex; flex-direction: column; align-items: center; gap: 2px; padding: 26px 0; }
.cc-card-badge-standalone .cc-card-rank { font-size: 1.8rem; font-weight: 800; }
.cc-card-badge-standalone .cc-card-suit { font-size: 2.1rem; }
.cc-card-label { font-size: 0.68rem; text-transform: uppercase; opacity: 0.55; letter-spacing: .05em; padding: 8px 10px 0; }
.cc-card-actions { padding: 8px 10px 12px; display: flex; flex-direction: column; gap: 6px; }
.cc-btn {
	position: relative; overflow: hidden; background: #00c8a0; color: #012; border: 1px solid transparent; border-radius: 6px;
	padding: 7px 10px; font-size: 0.78rem; font-weight: 600; cursor: pointer; box-sizing: border-box;
	width: 100%; min-height: 48px; display: flex; align-items: center; justify-content: center; text-align: center;
	transition: transform .12s ease, filter .12s ease, box-shadow .2s ease;
}
.cc-btn:hover:not(:disabled) { filter: brightness(1.12); box-shadow: 0 6px 16px rgba(0,200,160,.35); }
.cc-btn:active:not(:disabled) { transform: scale(.95); }
.cc-btn.secondary { background: rgba(255,255,255,.08); color: #e8f2f8; border-color: rgba(255,255,255,.3); }
.cc-btn.secondary:hover:not(:disabled) { background: rgba(255,255,255,.15); box-shadow: 0 6px 16px rgba(255,255,255,.12); }
.cc-btn.warn { background: #ff9900; color: #012; }
.cc-btn.warn:hover:not(:disabled) { box-shadow: 0 6px 16px rgba(255,153,0,.4); }
.cc-btn.heal { background: #00c8a0; color: #012; }
.cc-btn.heal:hover:not(:disabled) { box-shadow: 0 6px 16px rgba(0,200,160,.4); }
.cc-btn:disabled { opacity: 0.35; cursor: default; }
@media (hover: hover) and (pointer: fine) {
	.cc-btn:not(:disabled)::after {
		content: ''; position: absolute; top: 0; left: 0; width: 40%; height: 100%;
		background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);
		transform: translateX(-120%) skewX(-20deg); pointer-events: none;
	}
	.cc-btn:not(:disabled):hover::after { animation: ccBtnSheen .6s ease; }
}
.cc-note { font-size: 0.68rem; opacity: 0.5; margin-top: 4px; }
.cc-rules { font-size: 0.85rem; line-height: 1.6; opacity: 0.75; margin-bottom: 20px; }
.cc-flee-row {
	text-align: center; margin-bottom: 20px;
	background: rgba(5,12,20,.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
	border-radius: 12px; padding: 14px; box-sizing: border-box;
}
@media (prefers-reduced-motion: reduce) {
	.cc-card, .cc-flash, .cc-hud, .cc-hp-wrap.low .cc-hp-bar-bg, .cc-btn::after { animation: none !important; }
	.cc-card, .cc-btn { transition: none !important; }
}
</style>
<div class="cc-wrap">
<div class="cc-inner">
	<?php foreach ($flashes as $f): ?>
		<div class="cc-flash <?php echo htmlspecialchars($f['type']); ?>"><?php echo htmlspecialchars($f['msg']); ?></div>
	<?php endforeach; ?>

	<?php if ($state === 'no_run'): ?>
		<div class="cc-rules">
			Delve a 44-card crypt deck alone. <strong style="color:#ff9900;">♦ Diamonds</strong> are weapons —
			equip one and it stays until you use it, degrading so it can only beat weaker enemies after each kill.
			<strong style="color:#ff6b6b;">♥ Hearts</strong> heal you, but only the first one you drink each crypt counts.
			<strong style="color:#c8dce8;">♣♠ Clubs &amp; Spades</strong> are enemies — fight bare-handed and take full
			damage, or spend your weapon and take the difference. Resolve 3 of the 4 cards in a crypt and the 4th carries
			into the next; or flee a fresh crypt once (not twice in a row) to reshuffle it back into the deck. Clear the
			deck to win, or run out of HP and the delve ends.
		</div>
		<form method="post"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cc-btn">💀 Start Delve</button>
		</form>
	</div><!-- /cc-inner -->

	<?php elseif ($state === 'game_over'):
			$fell = ($recent_run['status'] === 'lost');
		?>
		<?php if ($fell): ?>
	</div><!-- /cc-inner -->
		<div class="cc-theme-bg" style="background-image:linear-gradient(180deg, rgba(7,17,26,.55), rgba(7,17,26,.88)), url('/staking/images/themes/8.jpg');">
		<div class="cc-inner">
		<?php endif; ?>
		<div class="cc-rules">
			<?php if ($recent_run['status'] === 'won'): ?>
				<strong style="color:#00c8a0;">Deck cleared.</strong> You escaped with <?php echo intval($recent_run['hp']); ?> HP left across <?php echo intval($recent_run['rooms_cleared']); ?> crypts.
			<?php else: ?>
				<strong style="color:#ff7070;">You fell.</strong> <?php echo intval($recent_run['rooms_cleared']); ?> crypts cleared before the crypt got you.
			<?php endif; ?>
		</div>
		<form method="post"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cc-btn">💀 Delve Again</button>
		</form>
		<?php if ($fell): ?>
		</div><!-- /cc-inner -->
		</div><!-- /cc-theme-bg -->
		<?php else: ?>
	</div><!-- /cc-inner -->
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

		// One theme image per room, in play order — a full deck-clear is always
		// exactly 15 rooms (verified: fixed by the 44-card deck + refill rule,
		// not variable), so this list has exactly one entry per room reached.
		$room_themes = ['24.jpg','23.jpg','25.jpg','12.jpg','4.jpg','11.jpg','22.jpg','9.jpg','18.jpg','3.jpg','2.jpg','38.jpg','1.jpg','0old.jpg','6.jpg'];
		$room_theme_url = '/staking/images/themes/' . $room_themes[intval($active_run['rooms_cleared']) % count($room_themes)];
	?>
	</div><!-- /cc-inner (theme backdrop below spans the full page-content width) -->
		<div class="cc-theme-bg" style="background-image:linear-gradient(180deg, rgba(7,17,26,.55), rgba(7,17,26,.88)), url('<?php echo htmlspecialchars($room_theme_url); ?>');">
		<div class="cc-inner">
		<div class="cc-hud">
			<div class="cc-hp-wrap<?php echo $hp_pct <= 30 ? ' low' : ''; ?>">
				<div style="font-size:0.72rem;opacity:0.6;margin-bottom:3px;">HP <?php echo $hp; ?> / <?php echo $max_hp; ?></div>
				<div class="cc-hp-bar-bg"><div class="cc-hp-bar-fill" style="width:<?php echo $hp_pct; ?>%;"></div></div>
			</div>
			<div class="cc-hud-meta">
				<div class="cc-weapon">
					<?php if ($weapon_power !== null): ?>
						🗡️ <strong><?php echo htmlspecialchars($weapon_name); ?></strong> (pwr <?php echo $weapon_power; ?>) — <?php echo $weapon_beaten_rank !== null ? 'beats up to ' . cryptcrawlRankLabel($weapon_beaten_rank) : 'no limit yet, fresh'; ?>
					<?php else: ?>
						🗡️ Bare-handed
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
				$type_label = $type === 'monster' ? 'enemy' : $type;
				$weapon_eligible = ($type === 'monster') && $weapon_power !== null && ($weapon_beaten_rank === null || $rank <= $weapon_beaten_rank);
				$dom_rgb = cryptcrawlDominantColor($card['image_url'] ?? '');
				$glow_rgba = $dom_rgb ? sprintf('rgba(%d,%d,%d,.45)', $dom_rgb[0], $dom_rgb[1], $dom_rgb[2]) : 'rgba(255,153,0,.35)';
			?>
				<div class="cc-card" style="--cc-glow:<?php echo htmlspecialchars($glow_rgba); ?>;">
					<?php if (!empty($card['image_url'])): ?>
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
					<?php else: ?>
						<div class="cc-card-badge-standalone">
							<div class="cc-card-rank" style="color:<?php echo $suit_color[$suit]; ?>;"><?php echo cryptcrawlRankLabel($rank); ?></div>
							<div class="cc-card-suit" style="color:<?php echo $suit_color[$suit]; ?>;"><?php echo $suit_symbol[$suit]; ?></div>
						</div>
					<?php endif; ?>
					<div class="cc-card-label"><?php echo htmlspecialchars($type_label); ?></div>
					<div class="cc-card-actions">
						<?php if ($type === 'monster'): ?>
							<form method="post"><input type="hidden" name="action" value="play_card">
								<input type="hidden" name="card_index" value="<?php echo $i; ?>">
								<input type="hidden" name="use_weapon" value="0">
								<button type="submit" class="cc-btn secondary">⚔️ Fight bare-handed (-<?php echo $rank; ?>)</button>
							</form>
							<?php if ($weapon_power !== null): ?>
								<form method="post"><input type="hidden" name="action" value="play_card">
									<input type="hidden" name="card_index" value="<?php echo $i; ?>">
									<input type="hidden" name="use_weapon" value="1">
									<button type="submit" class="cc-btn" <?php echo $weapon_eligible ? '' : 'disabled'; ?>>
										🗡️ Use weapon<?php echo $weapon_eligible ? ' (-' . max(0, $rank - $weapon_power) . ')' : ''; ?>
									</button>
								</form>
								<?php if (!$weapon_eligible): ?><div class="cc-note">weapon is too worn for this one</div><?php endif; ?>
							<?php endif; ?>
						<?php elseif ($type === 'weapon'): ?>
							<form method="post"><input type="hidden" name="action" value="play_card">
								<input type="hidden" name="card_index" value="<?php echo $i; ?>">
								<input type="hidden" name="use_weapon" value="0">
								<button type="submit" class="cc-btn warn">🛡️ Equip</button>
							</form>
						<?php else: ?>
							<form method="post"><input type="hidden" name="action" value="play_card">
								<input type="hidden" name="card_index" value="<?php echo $i; ?>">
								<input type="hidden" name="use_weapon" value="0">
								<button type="submit" class="cc-btn heal">🧪 Drink</button>
							</form>
							<?php if (intval($active_run['potion_used_this_room']) === 1): ?><div class="cc-note">won't heal — already drank this crypt</div><?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="cc-flee-row">
			<form method="post"><input type="hidden" name="action" value="flee">
				<button type="submit" class="cc-btn secondary" <?php echo $can_flee ? '' : 'disabled'; ?>>🏃 Flee this crypt</button>
			</form>
			<?php if (!$can_flee): ?><div class="cc-note">already fled last crypt, or mid-crypt — can't flee now</div><?php endif; ?>
			<form method="post" style="margin-top:8px;" onsubmit="return confirm('Abandon this run? It counts as a loss.');">
				<input type="hidden" name="action" value="abandon">
				<button type="submit" class="cc-btn secondary" style="font-size:0.68rem;opacity:0.6;padding:4px 8px;">🏳️ Abandon run</button>
			</form>
		</div>
		</div><!-- /cc-inner -->
		</div><!-- /cc-theme-bg -->
	<?php endif; ?>
</div>
<script>
(function() {
	// Fill whatever viewport space is left below the backdrop rather than
	// forcing the page to scroll to show the full theme image — cropping
	// via background-size:cover is fine, scrolling isn't. No-ops cleanly
	// when there's no .cc-theme-bg on the page at all (no_run state, or a
	// game_over 'won' screen, which doesn't get a themed backdrop).
	var el = document.querySelector('.cc-theme-bg');
	if (el) {
		function sizeTheme() {
			var top = el.getBoundingClientRect().top;
			var bottomPad = 60; // matches .cc-wrap's bottom padding
			var available = window.innerHeight - top - bottomPad;
			// Never shrink below what the content actually needs — on narrow
			// viewports the room grid wraps to more rows, and with
			// align-items:center a too-short box would center-overflow,
			// clipping the HUD off the top of the page instead of just
			// letting the page scroll a little. Cropping the art via
			// background-size:cover is fine; clipping the UI isn't.
			el.style.height = 'auto';
			var natural = el.scrollHeight;
			el.style.height = Math.max(200, available, natural) + 'px';
		}
		sizeTheme();
		window.addEventListener('resize', sizeTheme);
	}

	// Desktop-only card tilt — skip entirely on touch devices so there's no
	// stuck "hover" state after a tap. No-ops on states with no .cc-card.
	if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
		document.querySelectorAll('.cc-card').forEach(function(card) {
			card.addEventListener('mousemove', function(e) {
				var r = card.getBoundingClientRect();
				var x = (e.clientX - r.left) / r.width - 0.5;
				var y = (e.clientY - r.top) / r.height - 0.5;
				card.style.transform = 'perspective(700px) rotateX(' + (-y * 8) + 'deg) rotateY(' + (x * 8) + 'deg) translateY(-6px) scale(1.03)';
			});
			card.addEventListener('mouseleave', function() {
				card.style.transform = '';
			});
		});
	}
})();
</script>
</html>
