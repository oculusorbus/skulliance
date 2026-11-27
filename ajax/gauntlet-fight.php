<?php
/**
 * ajax/gauntlet-fight.php -- one turn of a Gauntlet fight.
 *
 * Plays one action and returns just the #gf-area fragment, so
 * gauntlets.php can swap it in without a real page navigation. That is
 * the entire point of this endpoint, and it is not speed: a full reload
 * per turn tears down and rebuilds the ambient <audio> element every
 * time, audibly stuttering the music. Crypt Crawl hit this first and
 * ajax/cryptcrawl-action.php says so at length.
 *
 * gauntlets.php keeps its own POST-and-redirect handling as the no-JS
 * fallback, rendering from the SAME gauntlets-fight-render.php, so a
 * player without JavaScript still gets a complete game and only loses
 * the continuous soundtrack.
 *
 * NOTHING SLOW LIVES HERE. No payout, no Discord, no leaderboard --
 * those belong to the encounter resolving, not to a swing.
 */
include_once '../db.php';
include '../message.php';
include '../verify.php';
include_once '../gauntlets-fight-lib.php';
include_once '../gauntlets-fight-render.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$user_id = intval($_SESSION['userData']['user_id'] ?? 0);
if ($user_id <= 0) { http_response_code(403); echo '<div class="gf-err">Signed out.</div>'; exit; }

$enc_id = intval($_POST['encounter_id'] ?? 0);
$turn   = (string)($_POST['turn'] ?? 'bare');

/* "weapon:7" -- the kind and the item in one field, so the markup can use
   plain submit buttons and the no-JS path posts exactly the same shape. */
$kind = $turn; $item = 0;
if (strpos($turn, ':') !== false) {
	list($kind, $item) = explode(':', $turn, 2);
	$item = (int)$item;
}

$res = gaunt_fight_turn($conn, $user_id, $enc_id, $kind, $item);
if (empty($res['ok'])) {
	http_response_code(409);
	echo '<div class="gf-err">' . htmlspecialchars($res['why'] ?: 'That move is not available.') . '</div>';
	exit;
}

/* Everything the renderer needs about who is fighting whom. Read after
   the turn so the fragment reflects the state just written. */
$q = $conn->query("
	SELECT ge.id, ge.run_id, ge.player_nft_id, ge.opponent_nft_id,
	       pn.name AS player_nft_name,   pn.ipfs AS player_ipfs,   pn.collection_id AS player_cid,
	       on2.name AS opponent_nft_name, on2.ipfs AS opponent_ipfs, on2.collection_id AS opponent_cid,
	       pc.project_id AS player_pid,  oc.project_id AS opponent_pid,
	       op.name AS opponent_project_name
	  FROM gauntlets_encounters ge
	  INNER JOIN nfts pn        ON pn.id  = ge.player_nft_id
	  INNER JOIN nfts on2       ON on2.id = ge.opponent_nft_id
	  INNER JOIN collections pc ON pc.id  = pn.collection_id
	  INNER JOIN collections oc ON oc.id  = on2.collection_id
	  INNER JOIN projects op    ON op.id  = oc.project_id
	 WHERE ge.id = " . (int)$enc_id . " LIMIT 1");
if (!$q || !$q->num_rows) { http_response_code(404); echo '<div class="gf-err">Fight not found.</div>'; exit; }
$enc = $q->fetch_assoc();
$enc['player_img']   = getIPFS($enc['player_ipfs'],   $enc['player_cid'],   $enc['player_pid']);
$enc['opponent_img'] = getIPFS($enc['opponent_ipfs'], $enc['opponent_cid'], $enc['opponent_pid']);

/* The same Fast Forward the page renders, or it would vanish from the
   fragment after the first swing. */
$ff_count = getCurrentAmount($conn, $user_id, GAUNTLET_C_FF);
$ff_hand  = array();
if ($ff_count > 0) {
	$hq = $conn->query("SELECT n.id, n.name, n.ipfs, n.collection_id, c.project_id,
	                           p.name AS project_name
	                      FROM gauntlets_nfts grn
	                      INNER JOIN nfts n        ON n.id = grn.nft_id
	                      INNER JOIN collections c ON c.id = n.collection_id
	                      INNER JOIN projects p    ON p.id = c.project_id
	                     WHERE grn.run_id = " . (int)$enc['run_id'] . "
	                       AND n.id <> " . (int)$enc['player_nft_id']);
	if ($hq) while ($hr = $hq->fetch_assoc()) {
		$played = $conn->query("SELECT id FROM gauntlets_encounters
		                         WHERE run_id=" . (int)$enc['run_id'] . "
		                           AND player_nft_id=" . (int)$hr['id'] . " LIMIT 1");
		if ($played && $played->num_rows) continue;
		$ff_hand[] = $hr;
	}
}

gauntletRenderFight($enc, $res['fight'],
	gaunt_loadout($conn, $user_id, (int)$enc['run_id'], $res['fight']),
	$res['timeline'],
	gaunt_theme_bg($conn, $enc['opponent_project_name'],
	                      (int)$enc['opponent_pid'], (int)$enc['opponent_cid']),
	gauntletRenderFastForward($enc, $ff_count, $ff_hand));
