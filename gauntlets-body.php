<?php
/*
 * gauntlets-body.php -- everything on the Gauntlet page that changes.
 *
 * WHY IT IS ITS OWN FILE. Every action on this page was a POST and a
 * redirect, so a round change reloaded the document -- which tore down
 * the <audio> element and restarted the soundtrack twice per round.
 * The element now lives in the shell, outside #g-area, and this is the
 * part that gets swapped in its place. ajax/gauntlet-page.php renders
 * exactly this and nothing else.
 *
 * It is an INCLUDE, not a function: it reads the state computed by
 * gauntlets-state.php out of the enclosing scope, the way it did when
 * it was inline. Both callers include state before body.
 */
if (!defined('GAUNTLET_BODY_OK')) { http_response_code(404); exit; }
?>

	<?php /* Over a live fight on a phone the masthead and the how-to-play
	         line pushed the arena most of a screen down, and neither
	         tells you anything mid-battle. Desktop has the room, so it
	         keeps them. */
	   $gcls = ($state === 'encounter') ? ' hide-in-fight' : ''; ?>
	<div class="gauntlet-title<?php echo $gcls; ?>">Gauntlet</div>
	<div class="gauntlet-subtitle<?php echo $gcls; ?>">Draw a hand of <?php echo GAUNTLET_HAND_SIZE; ?> NFTs. Survive 3 opponents to sweep the run.</div>

	<?php foreach ($flashes as $f): ?>
		<div class="flash <?php echo htmlspecialchars($f['type']); ?>"><?php echo htmlspecialchars($f['msg']); ?></div>
	<?php endforeach; ?>

	<?php if ($last_result): ?>
	<div id="result-reveal" class="<?php echo $last_result['outcome'] === 'win' ? 'win' : 'loss'; ?>" onclick="dismissResult()">
		<div class="rr-accent"></div>
		<?php if ($last_result['outcome'] === 'win'): ?>
		<div class="rr-icon">⚔️</div>
		<div class="rr-title">Victory!</div>
		<div class="rr-reward">
			+<?php echo number_format($last_result['amount']); ?>
			<img src="icons/<?php echo htmlspecialchars(strtolower($last_result['currency'])); ?>.png" onerror="this.src='icons/skull.png'" alt="">
			<?php echo htmlspecialchars($last_result['currency']); ?>
		</div>
		<?php
		/* EVERY item they used, each with the real item's own icon --
		   chosen where the drop was recorded, so this screen and the
		   loadout cannot disagree about what was taken. `drops` is the
		   list; `drop` is the older single field, kept so a fight that
		   closed before the list existed still names its one item. */
		$rr_drops = (isset($last_result['drops']) && is_array($last_result['drops']))
		            ? $last_result['drops'] : array();
		if (!$rr_drops && !empty($last_result['drop']))
			$rr_drops = array(array('label' => $last_result['drop'],
			                        'icon'  => (string)($last_result['drop_icon'] ?? '')));
		if ($rr_drops): ?>
			<div class="rr-drop">
				<?php foreach ($rr_drops as $rd):
					$rr_ico = (string)($rd['icon'] ?? ''); ?>
					<span class="rr-took">
						<?php if ($rr_ico !== ''): ?><img src="icons/<?php echo htmlspecialchars($rr_ico); ?>.png"
						     alt="" onerror="this.style.display='none'"><?php endif; ?>
						<?php echo htmlspecialchars((string)($rd['label'] ?? '')); ?>
					</span>
				<?php endforeach; ?>
				<span class="rr-drop-note">Taken off them - added to your inventory</span></div>
		<?php endif; ?>
		<div class="rr-win-num">Win <?php echo intval($last_result['win_number']); ?> of <?php echo GAUNTLET_MAX_WINS; ?></div>
		<?php else: ?>
		<div class="rr-icon"><img src="/staking/pwa/skulliance-logo-icon.png" alt="" width="58" height="80"></div>
		<div class="rr-title">Defeated</div>
		<?php endif; ?>
		<div class="rr-dismiss">Tap to continue</div>
	</div>
	<?php endif; ?>

	<?php if ($recent_run): ?>
	<div class="run-progress">
		<?php for ($i = 0; $i < GAUNTLET_MAX_WINS; $i++):
			$wins   = intval($run_stats['wins']   ?? 0);
			$losses = intval($run_stats['losses'] ?? 0);
			$cls = '';
			if ($i < $wins)   $cls = 'win';
			elseif ($losses && $i === $wins) $cls = 'loss';
		?>
		<div class="run-pip <?php echo $cls; ?>"></div>
		<?php endfor; ?>
	</div>
	<?php endif; ?>

	<?php
	// ── State: no run ───────────────────────────────────────
	if ($state === 'no_run'):
	?>
	<div style="text-align:center; padding: 40px 0;">
		<div style="font-size:3.5rem; margin-bottom:16px;">&#x2694;</div>
		<div style="color:rgba(255,255,255,.6); margin-bottom:24px; font-size:.9rem;">Enter the Gauntlet and prove your NFTs in battle.</div>
		<form method="POST">
			<input type="hidden" name="action" value="start_run">
			<button type="submit" class="btn-start">Enter the Gauntlet</button>
		</form>
	</div>

	<?php
	// ── State: run over ─────────────────────────────────────
	elseif ($state === 'run_over'):
		$wins   = intval($run_stats['wins']);
		$losses = intval($run_stats['losses']);
		$swept  = ($wins >= GAUNTLET_MAX_WINS);
	?>
	<div class="run-results">
		<div class="run-result-icon"><?php echo $swept ? '&#x1F3C6;' : '<img src="/staking/pwa/skulliance-logo-icon.png" alt="" width="35" height="48">'; ?></div>
		<div class="run-result-title"><?php echo $swept ? 'Gauntlet Swept!' : 'Run Over'; ?></div>
		<div class="run-result-sub"><?php echo $wins; ?> win<?php echo $wins !== 1 ? 's' : ''; ?> &middot; <?php echo $losses; ?> loss</div>
	</div>

	<?php
	/*
	 * THE CHEST, SCALED TO WINS BANKED. Paying only on a sweep is a
	 * closed loop for the under-equipped -- no items, no win; no win, no
	 * chest; no chest, no items -- and the run already ends on a squad
	 * wipe rather than a first loss, so partial success is something the
	 * system understands. One win pays scraps; only a sweep reaches the
	 * full heal.
	 *
	 * It is also the ONE thing that leaves a run: salvage taken off
	 * opponents dies with it, but the fact that you took it swells this.
	 */
	$chest_got = $_SESSION['gauntlet_chest'] ?? null;
	unset($_SESSION['gauntlet_chest']);
	$run_id_over = intval($recent_run['id'] ?? 0);
	if ($chest_got):
		/* Named and iconed once, for both the record below and the
		   reveal that plays over it. */
		$gchest_rows = array();
		foreach ($chest_got as $cid => $qty) {
			$nm = $conn->query("SELECT name FROM consumables WHERE id=" . intval($cid) . " LIMIT 1");
			$nm = ($nm && $nm->num_rows) ? $nm->fetch_assoc()['name'] : ('Item #' . intval($cid));
			$sl = gaunt_consumable_slug($cid);
			$gchest_rows[] = array(
				'icon' => $sl !== '' ? ('icons/' . $sl . '.png') : '',
				'name' => $nm,
				'qty'  => intval($qty),
			);
		}
		?>
		<div class="gchest open">
			<?php /* THE SAME THREE LAYERS THE DAILY REWARD SETTLES ON:
			         the open chest, its own light over it, and a pool on
			         the floor underneath. A lone open.webp with a drop
			         shadow reads as a cut-out floating on the page --
			         reported as "doesn't have the glow". */ ?>
			<div class="gchest-art">
				<img class="gchest-l" src="images/chest/open.webp" alt=""
				     width="480" height="497" decoding="async"
				     onerror="this.src='/staking/pwa/skulliance-logo-icon.png'">
				<img class="gchest-l gchest-glow" src="images/chest/glow.webp" alt=""
				     width="480" height="497" decoding="async" aria-hidden="true"
				     onerror="this.style.display='none'">
			</div>
			<div class="gchest-title">Hauled out of the gauntlet</div>
			<div class="gchest-items">
				<?php foreach ($gchest_rows as $gr): ?>
					<span class="gchest-item"><?php if ($gr['icon'] !== ''): ?><img
						src="<?php echo htmlspecialchars($gr['icon']); ?>" alt=""
						onerror="this.style.display='none'"><?php endif; ?><strong>&times;<?php
						echo $gr['qty']; ?></strong>
						<?php echo htmlspecialchars($gr['name']); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		/*
		 * THE SAME CHEST THE DAILY REWARD OPENS, HANDED OVER AS DATA.
		 *
		 * Not as a <script>, and not by including the reveal partial here:
		 * this file is swapped in with innerHTML between rounds, and a
		 * <script> that arrives that way NEVER RUNS. The partial lives in
		 * the shell; this leaves the payload on the page and the shell
		 * fires it, on a fresh load and after a swap alike.
		 *
		 * The block above stays as the record: it is what is still here
		 * once the reveal is dismissed, and what a no-JS player sees.
		 */
		$g_chest_payload = array(
			'kicker' => 'Gauntlet chest',
			'items'  => array_map(function ($r) {
				/* The real character: the reveal escapes what it is given,
				   so an HTML entity would print as one. */
				return array('icon' => $r['icon'], 'label' => "\xc3\x97" . $r['qty'],
				             'note' => $r['name']);
			}, $gchest_rows),
		); ?>
		<div id="g-chest-payload" hidden data-reveal="<?php
			echo htmlspecialchars(json_encode($g_chest_payload, JSON_UNESCAPED_SLASHES),
			                      ENT_QUOTES, 'UTF-8'); ?>"></div>
	<?php elseif ($run_id_over > 0 && gaunt_chest_pending($conn, $run_id_over)): ?>
		<form method="POST" class="gchest">
			<input type="hidden" name="action" value="claim_chest">
			<input type="hidden" name="run_id" value="<?php echo $run_id_over; ?>">
			<button type="submit" class="gchest-btn" data-sfx="equip">
				<img class="gchest-img" src="images/chest/closed.webp" alt=""
				     onerror="this.src='/staking/pwa/skulliance-logo-icon.png'">
				<span>Open your chest</span>
			</button>
		</form>
	<?php endif; ?>
	<?php
	// Show encounter history for completed run
	$hist_r = $conn->query("
		SELECT ge.outcome, grn.effective_project_id AS player_effective_project_id, ge.opponent_effective_project_id,
		       on2.name AS opponent_nft_name, op.name AS opponent_project_name, op.currency AS opponent_currency,
		       pp.currency AS player_currency,
		       (SELECT GROUP_CONCAT(gec.consumable_id ORDER BY gec.id) FROM gauntlets_encounters_consumables gec WHERE gec.encounter_id = ge.id) AS consumable_ids,
		       (SELECT p2.currency FROM transactions t2 INNER JOIN projects p2 ON p2.id = t2.project_id WHERE t2.gauntlet_encounter_id = ge.id AND t2.type = 'credit' LIMIT 1) AS actual_reward_currency,
		       ge.weapon_id, ge.armor_id,
		       ww.level AS weapon_level, aa.level AS armor_level,
		       ou.username AS opponent_username, ou.discord_id AS opponent_discord_id, ou.avatar AS opponent_avatar
		FROM gauntlets_encounters ge
		INNER JOIN gauntlets_nfts grn ON grn.run_id = ge.run_id AND grn.nft_id = ge.player_nft_id
		INNER JOIN nfts pn       ON pn.id  = ge.player_nft_id
		INNER JOIN collections pc ON pc.id = pn.collection_id
		INNER JOIN projects pp   ON pp.id  = pc.project_id
		INNER JOIN nfts on2      ON on2.id = ge.opponent_nft_id
		INNER JOIN collections oc ON oc.id = on2.collection_id
		INNER JOIN projects op   ON op.id  = oc.project_id
		LEFT JOIN  users ou      ON ou.id  = ge.opponent_user_id
		LEFT JOIN  weapons ww    ON ww.id  = ge.weapon_id
		LEFT JOIN  armor aa      ON aa.id  = ge.armor_id
		WHERE ge.run_id = ".intval($recent_run['id'])." AND ge.outcome != 'pending'
		ORDER BY ge.id ASC
	");
	if ($hist_r && $hist_r->num_rows):
	?>
	<div class="section-heading">Run History</div>
	<div class="history-list">
	<?php $hist_win_count = 0; while ($hr = $hist_r->fetch_assoc()):
		$base_wc      = gauntletCalculateWinChance(intval($hr['player_effective_project_id']), intval($hr['opponent_effective_project_id']));
		$hr_cons      = $hr['consumable_ids'] ? array_map('intval', explode(',', $hr['consumable_ids'])) : [];
		$is_double    = in_array(GAUNTLET_C_DOUBLE, $hr_cons);
		if ($hr['outcome'] === 'win') $hist_win_count++;
		$reward_amt   = ($hist_win_count * 100) * ($is_double ? 2 : 1);
		$cons_bonus   = 0; foreach ($hr_cons as $hc) $cons_bonus += ($consumable_bonuses[$hc] ?? 0);
		$effective_wc = min(100, $base_wc + $cons_bonus + intval($hr['weapon_level'] ?? 0) + intval($hr['armor_level'] ?? 0));
		$actual_currency = $hr['actual_reward_currency'] ?: $hr['opponent_currency'];
		if ($hr['outcome'] === 'win') {
			$reward_icon  = 'icons/' . strtolower($actual_currency) . '.png';
			$reward_label = '+' . $reward_amt;
			$reward_class = 'win';
		} else {
			$reward_icon  = 'icons/' . strtolower($hr['player_currency']) . '.png';
			$reward_label = 'opp +' . GAUNTLET_WIN_REWARD;
			$reward_class = 'loss';
		}
	?>
		<div class="history-row">
			<div class="history-badge <?php echo $hr['outcome']; ?>"><?php echo strtoupper(substr($hr['outcome'], 0, 1)); ?></div>
			<div class="history-text">vs <?php echo htmlspecialchars($hr['opponent_nft_name']); ?> (<?php echo htmlspecialchars($hr['opponent_project_name']); ?>)<?php if (!empty($hr['opponent_username'])): ?> &middot;<?php if (!empty($hr['opponent_discord_id']) && !empty($hr['opponent_avatar'])): ?><img class="history-avatar" src="<?php echo htmlspecialchars(avatar_url(htmlspecialchars($hr['opponent_discord_id']), htmlspecialchars($hr['opponent_avatar']))); ?>" alt="" onerror="this.src='icons/skull.png'"><?php endif; ?><a href="profile.php?username=<?php echo urlencode($hr['opponent_username']); ?>"><?php echo htmlspecialchars($hr['opponent_username']); ?></a><?php endif; ?></div>
			<div class="history-meta">
				<div class="history-reward <?php echo $reward_class; ?>"><?php echo $reward_label; ?><img src="<?php echo htmlspecialchars($reward_icon); ?>" onerror="this.src='icons/skull.png'"></div>
				<div class="history-odds"><?php echo $effective_wc; ?>%</div>
			</div>
		</div>
	<?php endwhile; ?>
	</div>
	<?php endif; ?>
	<div style="text-align:center; margin-top:24px;">
		<form method="POST">
			<input type="hidden" name="action" value="start_run">
			<button type="submit" class="btn-start">New Run</button>
		</form>
	</div>

	<?php
	// ── State: pick card ────────────────────────────────────
	elseif ($state === 'pick_card'):
		$wins = intval($run_stats['wins'] ?? 0);
	?>
	<?php if ($last_result && $last_result['outcome'] === 'win'): ?>
	<div class="win-reward-banner">
		<span class="win-reward-amount">+<?php echo number_format($last_result['amount']); ?></span>
		<img src="icons/<?php echo htmlspecialchars(strtolower($last_result['currency'])); ?>.png" onerror="this.src='icons/skull.png'">
		<span class="win-reward-currency"><?php echo htmlspecialchars($last_result['currency']); ?></span>
	</div>
	<?php endif; ?>
	<div class="section-heading">Your Hand — Pick a Card to Fight</div>
	<?php
	/*
	 * THE HAND CARRIES ITS HEALTH, because picking who to send is the
	 * same judgement Fast Forward asks for and was just as blind here:
	 * health persists across a run, so a card that fought and survived
	 * is not the card it was. A fighter at zero is out for the run and
	 * cannot be sent at all.
	 *
	 * A card can be hurt WITHOUT being "used": Fast Forward swaps the
	 * fighter without writing an encounter row, so damage and the Used
	 * label are genuinely different things.
	 */
	$hand = gaunt_hand_hp($conn, intval($active_run['id']), $hand);
	/* The fight's CSS, <audio> and script are in the SHELL now, not
	   here: this file is what gets swapped between rounds, and an
	   <audio> inside it would be destroyed and rebuilt every time --
	   which is the restart this whole split exists to stop. */
	?>
	<div class="hand-grid">
	<?php foreach ($hand as $card):
		$img  = getIPFS($card['ipfs'], $card['collection_id'], $card['project_id']);
		$out  = !empty($card['played']) || !empty($card['down']);
	?>
		<div class="nft-card <?php echo $out ? 'played' : ''; ?>"
		     <?php if (!$out): ?>onclick="pickCard(<?php echo intval($card['id']); ?>)"<?php endif; ?>>
			<?php if (!empty($card['played'])): ?><span class="played-label">Used</span>
			<?php elseif (!empty($card['down'])): ?><span class="played-label">Down</span><?php endif; ?>
			<img class="nft-img" src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" loading="lazy" onload="this.style.background='none'" onerror="this.src='icons/skull.png';this.style.background='none'">
			<div class="nft-card-info">
				<div class="nft-card-name"><?php echo htmlspecialchars($card['name']); ?></div>
				<div class="nft-card-project"><?php echo htmlspecialchars($card['project_name']); ?></div>
				<?php if (isset($card['hp'])) echo gf_bar((int)$card['hp'], (int)$card['max'], 'hand'); ?>
			</div>
		</div>
	<?php endforeach; ?>
	</div>
	<form method="POST" id="pick-form">
		<input type="hidden" name="action" value="start_encounter">
		<input type="hidden" name="gauntlet_pick_nft_id" id="pick-nft-id" value="0">
	</form>

	<?php
	// ── State: encounter ────────────────────────────────────
	elseif ($state === 'encounter'):
		$enc = $pending_encounter;
		$player_img   = getIPFS($enc['player_ipfs'],   $enc['player_collection_id'],   $enc['player_project_id']);
		$opponent_img = getIPFS($enc['opponent_ipfs'], $enc['opponent_collection_id'], $enc['opponent_project_id_real']);
		$odds_class   = $win_chance_display >= 50 ? '' : 'danger';
	?>
	<?php
		$player_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : ($_SESSION['userData']['name'] ?? '');
		$player_discord_id = $_SESSION['userData']['discord_id'] ?? '';
		$player_avatar     = $_SESSION['userData']['avatar']     ?? '';
	?>
	<?php
	/*
	 * THE FIGHT REPLACES THE ARENA, THE ODDS BAR AND THE REVEAL OVERLAY.
	 *
	 * All three belonged to a coin flip: two portraits, a percentage, and
	 * a "crossing swords" animation to dramatise a single rand(1,100).
	 * There is a real fight here now, so the odds bar has nothing to
	 * describe and the reveal has nothing to reveal -- the exchanges ARE
	 * the drama. gauntlets-fight-render.php draws the portraits, because
	 * it is the thing that has to animate them.
	 */
	$gf_missing = gaunt_schema_missing($conn);
	if ($gf_missing): ?>
	<div class="flash error">
		<strong>The fight needs three columns that are not there yet.</strong>
		Missing: <?php echo htmlspecialchars(implode(', ', $gf_missing)); ?>.
		Run this once, then reload:
		<pre style="white-space:pre-wrap;margin:8px 0 0;font-size:.75rem;"><?php echo htmlspecialchars(gaunt_schema_sql()); ?></pre>
	</div>
	<?php else:
		$gf_enc = $enc;
		$gf_enc['player_img']   = $player_img;
		$gf_enc['opponent_img'] = $opponent_img;
		$gf_fight = gaunt_fight_start($conn, $user_id, $enc);
		/*
		 * FAST FORWARD IS A CHOICE, so it renders with the other
		 * choices rather than after the battle log. Captured and handed
		 * to the renderer because the log has to stay inside the
		 * swapped fragment -- it is what updates every turn -- and
		 * anything appended after the fight would necessarily sit below
		 * it.
		 */
		/* Annotated with the health each replacement is carrying -- the
		   swap is a judgement about condition, so it has to be shown. */
		$gf_extra = gauntletRenderFastForward($enc, $ff_count,
			gaunt_hand_hp($conn, intval($enc['run_id']), $ff_hand));

		gauntletRenderFight($gf_enc, $gf_fight,
			gaunt_loadout($conn, $user_id, intval($enc['run_id']), $gf_fight),
			/* Nothing to animate on a fresh page load -- the turn
			   endpoint supplies this after each exchange. */
			array(),
			gaunt_theme_bg($conn, $enc['opponent_project_name'],
			               intval($enc['opponent_project_id_real']),
			               intval($enc['opponent_collection_id'])),
			$gf_extra);
	endif; ?>


	<?php
	/*
	 * THE LOADOUT PANEL IS GONE. It staked items BEFORE a single roll --
	 * pick a weapon, pick an armour, tick some consumables, watch a
	 * percentage move by four points, then flip a coin. The fight spends
	 * them a turn at a time instead, so the choice is made with the
	 * fight in front of you rather than blind, and the buttons that make
	 * it live in gauntlets-fight-render.php.
	 */
	?>

	<?php
	// Encounter history for current run (resolved only)
	$hist_r = $conn->query("
		SELECT ge.outcome, grn.effective_project_id AS player_effective_project_id, ge.opponent_effective_project_id,
		       on2.name AS opponent_nft_name, op.name AS opponent_project_name, op.currency AS opponent_currency,
		       pp.currency AS player_currency,
		       (SELECT GROUP_CONCAT(gec.consumable_id ORDER BY gec.id) FROM gauntlets_encounters_consumables gec WHERE gec.encounter_id = ge.id) AS consumable_ids,
		       (SELECT p2.currency FROM transactions t2 INNER JOIN projects p2 ON p2.id = t2.project_id WHERE t2.gauntlet_encounter_id = ge.id AND t2.type = 'credit' LIMIT 1) AS actual_reward_currency,
		       ge.weapon_id, ge.armor_id,
		       ww.level AS weapon_level, aa.level AS armor_level,
		       ou.username AS opponent_username, ou.discord_id AS opponent_discord_id, ou.avatar AS opponent_avatar
		FROM gauntlets_encounters ge
		INNER JOIN gauntlets_nfts grn ON grn.run_id = ge.run_id AND grn.nft_id = ge.player_nft_id
		INNER JOIN nfts pn       ON pn.id  = ge.player_nft_id
		INNER JOIN collections pc ON pc.id = pn.collection_id
		INNER JOIN projects pp   ON pp.id  = pc.project_id
		INNER JOIN nfts on2      ON on2.id = ge.opponent_nft_id
		INNER JOIN collections oc ON oc.id = on2.collection_id
		INNER JOIN projects op   ON op.id  = oc.project_id
		LEFT JOIN  users ou      ON ou.id  = ge.opponent_user_id
		LEFT JOIN  weapons ww    ON ww.id  = ge.weapon_id
		LEFT JOIN  armor aa      ON aa.id  = ge.armor_id
		WHERE ge.run_id = ".intval($active_run['id'])." AND ge.outcome != 'pending'
		ORDER BY ge.id ASC
	");
	if ($hist_r && $hist_r->num_rows):
	?>
	<div class="section-heading">This Run</div>
	<div class="history-list">
	<?php $hist_win_count = 0; while ($hr = $hist_r->fetch_assoc()):
		$base_wc      = gauntletCalculateWinChance(intval($hr['player_effective_project_id']), intval($hr['opponent_effective_project_id']));
		$hr_cons      = $hr['consumable_ids'] ? array_map('intval', explode(',', $hr['consumable_ids'])) : [];
		$is_double    = in_array(GAUNTLET_C_DOUBLE, $hr_cons);
		if ($hr['outcome'] === 'win') $hist_win_count++;
		$reward_amt   = ($hist_win_count * 100) * ($is_double ? 2 : 1);
		$cons_bonus   = 0; foreach ($hr_cons as $hc) $cons_bonus += ($consumable_bonuses[$hc] ?? 0);
		$effective_wc = min(100, $base_wc + $cons_bonus + intval($hr['weapon_level'] ?? 0) + intval($hr['armor_level'] ?? 0));
		$actual_currency = $hr['actual_reward_currency'] ?: $hr['opponent_currency'];
		if ($hr['outcome'] === 'win') {
			$reward_icon  = 'icons/' . strtolower($actual_currency) . '.png';
			$reward_label = '+' . $reward_amt;
			$reward_class = 'win';
		} else {
			$reward_icon  = 'icons/' . strtolower($hr['player_currency']) . '.png';
			$reward_label = 'opp +' . GAUNTLET_WIN_REWARD;
			$reward_class = 'loss';
		}
	?>
		<div class="history-row">
			<div class="history-badge <?php echo $hr['outcome']; ?>"><?php echo strtoupper(substr($hr['outcome'], 0, 1)); ?></div>
			<div class="history-text">vs <?php echo htmlspecialchars($hr['opponent_nft_name']); ?> (<?php echo htmlspecialchars($hr['opponent_project_name']); ?>)<?php if (!empty($hr['opponent_username'])): ?> &middot;<?php if (!empty($hr['opponent_discord_id']) && !empty($hr['opponent_avatar'])): ?><img class="history-avatar" src="<?php echo htmlspecialchars(avatar_url(htmlspecialchars($hr['opponent_discord_id']), htmlspecialchars($hr['opponent_avatar']))); ?>" alt="" onerror="this.src='icons/skull.png'"><?php endif; ?><a href="profile.php?username=<?php echo urlencode($hr['opponent_username']); ?>"><?php echo htmlspecialchars($hr['opponent_username']); ?></a><?php endif; ?></div>
			<div class="history-meta">
				<div class="history-reward <?php echo $reward_class; ?>"><?php echo $reward_label; ?><img src="<?php echo htmlspecialchars($reward_icon); ?>" onerror="this.src='icons/skull.png'"></div>
				<div class="history-odds"><?php echo $effective_wc; ?>%</div>
			</div>
		</div>
	<?php endwhile; ?>
	</div>
	<?php endif; ?>

	<?php endif; // end state switch ?>

