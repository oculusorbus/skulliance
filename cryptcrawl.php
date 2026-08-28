<?php
// CRYPT CRAWL — prototype. Not linked in nav yet; direct-URL only while we
// test whether the loop is fun. See db.php ("CRYPT CRAWL — PROTOTYPE" block)
// for the game logic. Once this earns its keep, promote it: add to nav,
// wire currency payout + Discord broadcast, write skullpaper/cryptcrawl.md
// and add it to skullpaper/MAINTENANCE.md per CLAUDE.md.
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
				cryptcrawlFlash('You fell in the crypt. Run over — ' . intval($updated['rooms_cleared']) . ' rooms cleared.', 'loss');
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
				cryptcrawlFlash('You slipped past that room.', 'info');
			} else {
				cryptcrawlFlash("Can't flee twice in a row — face the room.", 'error');
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
.cc-wrap { padding: 20px 16px 60px; }
.cc-inner { max-width: 720px; width: 100%; margin: 0 auto; }
.cc-flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 0.85rem; }
.cc-flash.win  { background: rgba(0,200,160,.12); border: 1px solid rgba(0,200,160,.35); color: #00c8a0; }
.cc-flash.loss { background: rgba(255,68,68,.12); border: 1px solid rgba(255,68,68,.35); color: #ff7070; }
.cc-flash.error{ background: rgba(255,68,68,.12); border: 1px solid rgba(255,68,68,.35); color: #ff7070; }
.cc-flash.info { background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); color: #c8dce8; }
.cc-theme-bg { background-size: cover; background-position: center; border-radius: 14px; padding: 18px; margin: 0 -16px; transition: background-image .4s ease; display: flex; align-items: center; justify-content: center; box-sizing: border-box; min-height: 200px; }
.cc-hud { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; margin-bottom: 18px; }
.cc-hp-wrap { flex: 1; min-width: 180px; }
.cc-hp-bar-bg { background: rgba(255,255,255,.08); border-radius: 6px; height: 14px; overflow: hidden; }
.cc-hp-bar-fill { background: linear-gradient(90deg,#ff4444,#ff9900,#00c8a0); height: 100%; transition: width .3s; }
.cc-weapon { font-size: 0.8rem; opacity: 0.8; }
.cc-room { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-bottom: 18px; }
.cc-card { background: linear-gradient(135deg, #2e2013, #1a1209); border: 2px solid transparent; border-image: linear-gradient(135deg, #4a3520, #1a1209) 1; border-radius: 10px; overflow: hidden; text-align: center; }
.cc-card-frame { position: relative; background: linear-gradient(135deg, #2e2013, #1a1209); box-sizing: border-box; padding: 16px; }
.cc-card-art { position: relative; aspect-ratio: 5 / 7; }
.cc-card-img { width: 100%; height: 100%; object-fit: contain; display: block; background: #002f44; }
.cc-card-corner { position: absolute; display: flex; flex-direction: column; align-items: center; line-height: 1; }
.cc-card-corner .cc-card-rank { font-size: 1rem; font-weight: 800; }
.cc-card-corner .cc-card-suit { font-size: 0.8rem; margin-top: 1px; }
.cc-card-corner.tl { top: 3px; left: 4px; }
.cc-card-corner.br { bottom: 3px; right: 4px; transform: rotate(180deg); }
.cc-card-badge-standalone { display: inline-flex; flex-direction: column; align-items: center; gap: 2px; padding: 26px 0; }
.cc-card-badge-standalone .cc-card-rank { font-size: 1.8rem; font-weight: 700; }
.cc-card-badge-standalone .cc-card-suit { font-size: 1.4rem; }
.cc-card-label { font-size: 0.68rem; text-transform: uppercase; opacity: 0.55; letter-spacing: .05em; padding: 8px 10px 0; }
.cc-card-actions { padding: 8px 10px 12px; display: flex; flex-direction: column; gap: 6px; }
.cc-btn { background: #00c8a0; color: #012; border: 1px solid transparent; border-radius: 6px; padding: 7px 10px; font-size: 0.78rem; font-weight: 600; cursor: pointer; }
.cc-btn:hover { filter: brightness(1.1); }
.cc-btn.secondary { background: rgba(255,255,255,.08); color: #e8f2f8; border-color: rgba(255,255,255,.3); }
.cc-btn.secondary:hover { background: rgba(255,255,255,.15); }
.cc-btn.warn { background: #ff9900; color: #012; }
.cc-btn:disabled { opacity: 0.35; cursor: default; }
.cc-note { font-size: 0.68rem; opacity: 0.5; margin-top: 4px; }
.cc-rules { font-size: 0.85rem; line-height: 1.6; opacity: 0.75; margin-bottom: 20px; }
.cc-flee-row { text-align: center; margin-bottom: 20px; }
</style>
<div class="cc-wrap">
<div class="cc-inner">
	<?php foreach ($flashes as $f): ?>
		<div class="cc-flash <?php echo htmlspecialchars($f['type']); ?>"><?php echo htmlspecialchars($f['msg']); ?></div>
	<?php endforeach; ?>

	<?php if ($state === 'no_run'): ?>
		<div class="cc-rules">
			Delve a 44-card crypt deck alone. <strong style="color:#ff9900;">♦ Diamonds</strong> are weapons —
			equip one and it stays until you use it, degrading so it can only beat weaker monsters after each kill.
			<strong style="color:#ff6b6b;">♥ Hearts</strong> heal you, but only the first one you drink each room counts.
			<strong style="color:#c8dce8;">♣♠ Clubs &amp; Spades</strong> are monsters — fight bare-handed and take full
			damage, or spend your weapon and take the difference. Resolve 3 of the 4 cards in a room and the 4th carries
			into the next; or flee a fresh room once (not twice in a row) to reshuffle it back into the deck. Clear the
			deck to win, or run out of HP and the delve ends.
		</div>
		<form method="post"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cc-btn">Start Delve</button>
		</form>
	</div><!-- /cc-inner -->

	<?php elseif ($state === 'game_over'): ?>
		<div class="cc-rules">
			<?php if ($recent_run['status'] === 'won'): ?>
				<strong style="color:#00c8a0;">Deck cleared.</strong> You escaped with <?php echo intval($recent_run['hp']); ?> HP left across <?php echo intval($recent_run['rooms_cleared']); ?> rooms.
			<?php else: ?>
				<strong style="color:#ff7070;">You fell.</strong> <?php echo intval($recent_run['rooms_cleared']); ?> rooms cleared before the crypt got you.
			<?php endif; ?>
		</div>
		<form method="post"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cc-btn">Delve Again</button>
		</form>
	</div><!-- /cc-inner -->

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
			<div class="cc-hp-wrap">
				<div style="font-size:0.72rem;opacity:0.6;margin-bottom:3px;">HP <?php echo $hp; ?> / <?php echo $max_hp; ?></div>
				<div class="cc-hp-bar-bg"><div class="cc-hp-bar-fill" style="width:<?php echo $hp_pct; ?>%;"></div></div>
			</div>
			<div class="cc-weapon">
				<?php if ($weapon_power !== null): ?>
					🗡️ <strong><?php echo htmlspecialchars($weapon_name); ?></strong> (pwr <?php echo $weapon_power; ?>)<?php if ($weapon_beaten_rank !== null): ?> — beats up to <?php echo cryptcrawlRankLabel($weapon_beaten_rank); ?><?php endif; ?>
				<?php else: ?>
					🗡️ Bare-handed
				<?php endif; ?>
			</div>
			<div style="font-size:0.72rem;opacity:0.5;">Rooms cleared: <?php echo intval($active_run['rooms_cleared']); ?> · Deck: <?php echo $deck_count; ?> left</div>
		</div>

		<div class="cc-room">
			<?php foreach ($room as $i => $card):
				$suit = $card['suit']; $rank = intval($card['rank']); $type = $card['type'];
				$weapon_eligible = ($type === 'monster') && $weapon_power !== null && ($weapon_beaten_rank === null || $rank <= $weapon_beaten_rank);
			?>
				<div class="cc-card">
					<div class="cc-card-frame">
						<?php if (!empty($card['image_url'])): ?>
							<div class="cc-card-art">
								<img class="cc-card-img" src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="" loading="lazy" onerror="this.remove();">
							</div>
							<div class="cc-card-corner tl" style="color:<?php echo $suit_color[$suit]; ?>;">
								<div class="cc-card-rank"><?php echo cryptcrawlRankLabel($rank); ?></div>
								<div class="cc-card-suit"><?php echo $suit_symbol[$suit]; ?></div>
							</div>
							<div class="cc-card-corner br" style="color:<?php echo $suit_color[$suit]; ?>;">
								<div class="cc-card-rank"><?php echo cryptcrawlRankLabel($rank); ?></div>
								<div class="cc-card-suit"><?php echo $suit_symbol[$suit]; ?></div>
							</div>
						<?php else: ?>
							<div class="cc-card-badge-standalone">
								<div class="cc-card-rank" style="color:<?php echo $suit_color[$suit]; ?>;"><?php echo cryptcrawlRankLabel($rank); ?></div>
								<div class="cc-card-suit" style="color:<?php echo $suit_color[$suit]; ?>;"><?php echo $suit_symbol[$suit]; ?></div>
							</div>
						<?php endif; ?>
					</div>
					<div class="cc-card-label"><?php echo htmlspecialchars($type); ?></div>
					<div class="cc-card-actions">
						<?php if ($type === 'monster'): ?>
							<form method="post"><input type="hidden" name="action" value="play_card">
								<input type="hidden" name="card_index" value="<?php echo $i; ?>">
								<input type="hidden" name="use_weapon" value="0">
								<button type="submit" class="cc-btn secondary">Fight bare-handed (-<?php echo $rank; ?>)</button>
							</form>
							<?php if ($weapon_power !== null): ?>
								<form method="post"><input type="hidden" name="action" value="play_card">
									<input type="hidden" name="card_index" value="<?php echo $i; ?>">
									<input type="hidden" name="use_weapon" value="1">
									<button type="submit" class="cc-btn" <?php echo $weapon_eligible ? '' : 'disabled'; ?>>
										Use weapon<?php echo $weapon_eligible ? ' (-' . max(0, $rank - $weapon_power) . ')' : ''; ?>
									</button>
								</form>
								<?php if (!$weapon_eligible): ?><div class="cc-note">too worn for this one</div><?php endif; ?>
							<?php endif; ?>
						<?php elseif ($type === 'weapon'): ?>
							<form method="post"><input type="hidden" name="action" value="play_card">
								<input type="hidden" name="card_index" value="<?php echo $i; ?>">
								<input type="hidden" name="use_weapon" value="0">
								<button type="submit" class="cc-btn warn">Equip</button>
							</form>
						<?php else: ?>
							<form method="post"><input type="hidden" name="action" value="play_card">
								<input type="hidden" name="card_index" value="<?php echo $i; ?>">
								<input type="hidden" name="use_weapon" value="0">
								<button type="submit" class="cc-btn warn">Drink</button>
							</form>
							<?php if (intval($active_run['potion_used_this_room']) === 1): ?><div class="cc-note">won't heal — already drank this room</div><?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="cc-flee-row">
			<form method="post"><input type="hidden" name="action" value="flee">
				<button type="submit" class="cc-btn secondary" <?php echo $can_flee ? '' : 'disabled'; ?>>Flee this room</button>
			</form>
			<?php if (!$can_flee): ?><div class="cc-note">already fled last room, or mid-room — can't flee now</div><?php endif; ?>
			<form method="post" style="margin-top:8px;" onsubmit="return confirm('Abandon this run? It counts as a loss.');">
				<input type="hidden" name="action" value="abandon">
				<button type="submit" class="cc-btn secondary" style="font-size:0.68rem;opacity:0.6;padding:4px 8px;">Abandon run</button>
			</form>
		</div>
		</div><!-- /cc-inner -->
		</div><!-- /cc-theme-bg -->
		<script>
		(function() {
			// Fill whatever viewport space is left below the backdrop rather than
			// forcing the page to scroll to show the full theme image — cropping
			// via background-size:cover is fine, scrolling isn't.
			var el = document.querySelector('.cc-theme-bg');
			if (!el) return;
			function sizeTheme() {
				var top = el.getBoundingClientRect().top;
				var bottomPad = 60; // matches .cc-wrap's bottom padding
				el.style.height = Math.max(200, window.innerHeight - top - bottomPad) + 'px';
			}
			sizeTheme();
			window.addEventListener('resize', sizeTheme);
		})();
		</script>
	<?php endif; ?>
</div>
</html>
