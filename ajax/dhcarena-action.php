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
require_once __DIR__ . '/../dhc-json.php';

/*
 * ANSWER A GUEST IN JSON, BEFORE skulliance.php GETS THE CHANCE TO REDIRECT.
 *
 * skulliance.php:35 sends a visitor it cannot identify to error.php with a
 * 302. A browser following that from fetch() lands on HTML, JSON.parse
 * fails, and the Arena reports "Lost contact with the Arena" -- a network
 * error message for a login state. Reported by a player on practice as a
 * guest, where a drawing bug had quietly routed practice moves here.
 *
 * The check below already existed and said exactly the right thing. It just
 * ran one include too late.
 *
 * THE MERGE-RESTORE HAS TO COME FIRST, and that is the whole subtlety: on
 * iOS Safari and in the PWA, PHPSESSID is dropped while the six-month
 * SessionCookie survives, so skulliance.php rebuilding the session from that
 * cookie is the ONLY thing that identifies those players. Checking before it
 * without doing the same restore would log out every PWA user instead of
 * every guest. MERGE, never replace -- see MAINTENANCE.md, and
 * ajax/mission-data.php which does this for the same reason.
 */
if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['logged_in'])
    && isset($_COOKIE['SessionCookie'])) {
	$ck = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($ck)) $_SESSION = array_merge((array)$_SESSION, $ck);
}
if (empty($_SESSION['userData']['user_id']))
	dhc_json(array('ok' => false, 'message' => 'Not signed in.'));

include '../skulliance.php';
require_once __DIR__ . '/../dhcarena-lib.php';

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
		/* '' | 'opponent' | 'cap' -- why a win paid nothing. The end screen
		   cannot work this out from 'rewarded' alone; see dhca_settle(). */
		$out['nodrop']   = isset($b['nodrop']) ? $b['nodrop'] : '';
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
