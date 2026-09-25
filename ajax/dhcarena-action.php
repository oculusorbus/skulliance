<?php
/**
 * ajax/dhcarena-action.php — one Arena action per request.
 *
 * The trust boundary. The client sends an intent; everything is decided here
 * and in dhcarena-lib.php. A tampered request gets a refusal, never a result.
 *
 * Deliberately does nothing slow: payout and the Discord announce happen inside
 * dhca_finish(), which is reached only on the move that ends a battle. That
 * follows ajax/cryptcrawl-action.php's hard-won rule -- anything slow in the
 * turn request delays or breaks the confirmation reaching the browser.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../dhcarena-lib.php';
require_once __DIR__ . '/../dhc-json.php';

function dhca_out($ok, $msg, $extra = array()) {
	dhc_json(array_merge(array('ok' => $ok, 'message' => $msg), $extra));
}

$user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
if ($user_id <= 0) dhca_out(false, 'Not signed in.');

$do = isset($_POST['do']) ? $_POST['do'] : '';

if ($do === 'start') {
	$ids = isset($_POST['fighters']) && is_array($_POST['fighters']) ? $_POST['fighters'] : array();
	list($ok, $msg, $b) = dhca_start($conn, $user_id, (int)($_POST['defender'] ?? 0), $ids);
	if (!$ok) dhca_out(false, $msg);
	dhca_out(true, '', array('battle_id' => (int)$b['meta']['battle_id'], 'state' => dhca_public($b)));
}

if ($do === 'move') {
	$bid = (int)($_POST['battle_id'] ?? 0);
	list($ok, $msg, $b) = dhca_move($conn, $user_id, $bid, (int)($_POST['a'] ?? -1), (int)($_POST['z'] ?? -1));
	if (!$ok) dhca_out(false, $msg, $b ? array('state' => dhca_public($b)) : array());
	$out = array('state' => dhca_public($b));
	if ($b['over'] !== null) {
		$out['over']     = $b['over'];
		$out['rewarded'] = !empty($b['rewarded']);
		if (!empty($b['drop'])) $out['drop'] = $b['drop'];
	}
	dhca_out(true, '', $out);
}

if ($do === 'resume') {
	$b = dhca_active($conn, $user_id);
	if (!$b) dhca_out(false, 'Nothing in progress.');
	dhca_out(true, '', array('battle_id' => (int)$b['meta']['battle_id'], 'state' => dhca_public($b)));
}

dhca_out(false, 'Unknown action.');
