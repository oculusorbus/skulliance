<?php
/*
 * gauntlets-actions.php -- every POST the Gauntlet page accepts, with
 * no output and no redirect of its own.
 *
 * TWO CALLERS, ONE BEHAVIOUR. gauntlets.php runs this and then
 * redirects, which is the Post/Redirect/Get the page has always done
 * and still does without JavaScript. ajax/gauntlet-page.php runs the
 * same code and renders the new body instead, so a round change does
 * not reload the document and the soundtrack is not torn down with it.
 * Neither caller may hold a copy of this logic.
 */
if (!defined('GAUNTLET_ACTIONS_OK')) { http_response_code(404); exit; }

if (!isset($_SESSION['gauntlet_flash'])) $_SESSION['gauntlet_flash'] = [];

if (!function_exists('gauntletFlash')) {
	function gauntletFlash($msg, $type = 'info') {
		$_SESSION['gauntlet_flash'][] = ['msg' => $msg, 'type' => $type];
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	if ($action === 'start_run') {
		$run_id = gauntletStartRun($conn, $user_id);
		if (!$run_id) gauntletFlash('No available NFTs to draw a hand. Use your NFTs in staking first.', 'error');

	} elseif ($action === 'start_encounter') {
		$nft_id  = intval($_POST['gauntlet_pick_nft_id'] ?? 0);
		$run     = gauntletGetActiveRun($conn, $user_id);
		if ($run && $nft_id > 0) {
			$result = gauntletStartEncounter($conn, $user_id, intval($run['id']), $nft_id);
			if (!$result) gauntletFlash('Could not start encounter. Please try again.', 'error');
		}

	} elseif ($action === 'fast_forward') {
		$enc_id     = intval($_POST['encounter_id'] ?? 0);
		$new_nft_id = intval($_POST['new_nft_id'] ?? 0);
		if ($enc_id > 0 && $new_nft_id > 0) {
			$ok = gauntletFastForward($conn, $user_id, $enc_id, $new_nft_id);
			if (!$ok) {
				gauntletFlash('Could not swap card. Make sure you have a Fast Forward item.', 'error');
			} else {
				/* The live fight is a blob on the same row and
				   gauntletFastForward() does not touch it, so without
				   this the new fighter inherits the old one's health. */
				$ff_run = gauntletGetActiveRun($conn, $user_id);
				if ($ff_run) gaunt_fight_swap($conn, $enc_id, intval($ff_run['id']), $new_nft_id);
			}
		}

	} elseif ($action === 'fight_turn') {
		/*
		 * THE NO-JS PATH. ajax/gauntlet-fight.php does this without a
		 * navigation so the music keeps playing; this is the same turn
		 * through a plain form post, for a browser that cannot. The game
		 * is complete either way -- only the continuous soundtrack is
		 * lost, which is exactly the trade cryptcrawl.php makes.
		 */
		$enc_id = intval($_POST['encounter_id'] ?? 0);
		$turn   = (string)($_POST['turn'] ?? 'bare');
		$kind = $turn; $item = 0;
		if (strpos($turn, ':') !== false) { list($kind, $item) = explode(':', $turn, 2); $item = intval($item); }
		if ($enc_id > 0) {
			$res = gaunt_fight_turn($conn, $user_id, $enc_id, $kind, $item);
			if (empty($res['ok'])) gauntletFlash($res['why'] ?: 'That move is not available.', 'error');
		}

	} elseif ($action === 'claim_chest') {
		$run_id = intval($_POST['run_id'] ?? 0);
		if ($run_id > 0) {
			$got = gaunt_chest_claim($conn, $user_id, $run_id);
			if ($got) {
				$_SESSION['gauntlet_chest'] = $got;
			} else {
				gauntletFlash('That chest has already been opened.', 'error');
			}
		}

	} elseif ($action === 'close_fight') {
		$enc_id = intval($_POST['encounter_id'] ?? 0);
		if ($enc_id > 0) {
			/* Read the salvage off the fight BEFORE resolving it, so the
			   victory screen can name what was taken. The fight row is
			   the only place that knows. */
			$gf_closing = gaunt_fight_get($conn, $enc_id);
			$gf_drop    = (string)($gf_closing['drop'] ?? '');
			$gf_drop_ico = (string)($gf_closing['drop_icon'] ?? '');
			$gf_drops   = (isset($gf_closing['drops']) && is_array($gf_closing['drops']))
			              ? $gf_closing['drops'] : array();
			/* The fight decided this over a dozen exchanges; this only
			   records it and pays it out. */
			$outcome = gauntletResolveFight($conn, $user_id, $enc_id);
			if ($outcome === 'win') {
				$run  = gauntletGetActiveRun($conn, $user_id);
				$rw_r = $conn->query("
					SELECT ge.run_id, op.currency AS opponent_currency
					FROM gauntlets_encounters ge
					INNER JOIN nfts on2       ON on2.id = ge.opponent_nft_id
					INNER JOIN collections oc ON oc.id  = on2.collection_id
					INNER JOIN projects op    ON op.id  = oc.project_id
					WHERE ge.id = $enc_id LIMIT 1
				");
				if ($rw_r && $rw_r->num_rows) {
					$rw      = $rw_r->fetch_assoc();
					$wnum_r2 = $conn->query("SELECT COUNT(*) AS c FROM gauntlets_encounters WHERE run_id=".intval($rw['run_id'])." AND outcome='win'");
					$wnum2   = ($wnum_r2 && $wnum_r2->num_rows) ? intval($wnum_r2->fetch_assoc()['c']) : 1;
					$enc_cons_r  = $conn->query("SELECT consumable_id FROM gauntlets_encounters_consumables WHERE encounter_id=$enc_id");
					$enc_cons    = [];
					if ($enc_cons_r) while ($ecr = $enc_cons_r->fetch_assoc()) $enc_cons[] = intval($ecr['consumable_id']);
					$is_dbl = in_array(GAUNTLET_C_DOUBLE, $enc_cons);
					$tx_cur_r = $conn->query("SELECT p.currency FROM transactions t INNER JOIN projects p ON p.id = t.project_id WHERE t.gauntlet_encounter_id = $enc_id AND t.user_id = $user_id AND t.type = 'credit' LIMIT 1");
					$actual_currency = ($tx_cur_r && $tx_cur_r->num_rows) ? $tx_cur_r->fetch_assoc()['currency'] : $rw['opponent_currency'];
					$_SESSION['gauntlet_last_result'] = [
						'outcome'    => 'win',
						/* Read off the fight just closed; '' when nothing
						   dropped. */
						'drop'       => $gf_drop,
						'drop_icon'  => $gf_drop_ico,
						'drops'      => $gf_drops,
						'amount'     => ($wnum2 * 100) * ($is_dbl ? 2 : 1),
						'currency'   => $actual_currency,
						'win_number' => $wnum2,
					];
				}
				if (!$run) {
					gauntletFlash('Victory! You swept the gauntlet!', 'win');
					/*
					 * TRAIT DROP -- for SURVIVING THE GAUNTLET, not for winning a
					 * single encounter. A run is three fights; winning the first
					 * one is not the achievement, and hooking the per-encounter
					 * outcome paid out three times a run.
					 *
					 * No active run left is the game's own test for a sweep --
					 * the same condition the victory message uses, so the drop
					 * and the message can never disagree.
					 *
					 * Flagged rather than fired: this page is post-redirect-get,
					 * so a drop claimed here would be lost in the redirect.
					 */
					$_SESSION['dhcf_gauntlet_win'] = 1;
				} else {
					gauntletFlash('Win! Pick your next card.', 'win');
				}
			} elseif ($outcome === 'loss') {
				$_SESSION['gauntlet_last_result'] = ['outcome' => 'loss'];
				/* A loss costs that fighter, not the run -- unless it was
				   the last body. Asked AFTER resolving, so it reads the
				   state the player is about to be shown.
				   FROM THE ENCOUNTER, not $run: that variable is only
				   assigned inside the win branch above. */
				$lr = $conn->query("SELECT run_id FROM gauntlets_encounters WHERE id=$enc_id LIMIT 1");
				$lrid = ($lr && $lr->num_rows) ? intval($lr->fetch_assoc()['run_id']) : 0;
				if ($lrid < 1 || gauntletIsRunOver($conn, $lrid))
					gauntletFlash('Your last fighter is down. The run ends here.', 'loss');
				else
					gauntletFlash('Your fighter is down and out for this run. Send another.', 'loss');
			} else {
				gauntletFlash('Something went wrong. Please try again.', 'error');
			}
		}
	}
}
