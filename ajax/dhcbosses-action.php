<?php
/**
 * ajax/dhcbosses-action.php — one DHC Bosses action per request.
 *
 * The same trust boundary as ajax/dhcarena-action.php, and deliberately the
 * same shape: the client sends an intent, everything is decided in
 * dhcbosses-lib.php, and a tampered request gets a refusal rather than a
 * result. The guest-answering preamble below is copied in POSTURE but not in
 * substance -- it is three lines of session restore that every AJAX endpoint
 * on this platform repeats, for the reason documented at length in the Arena
 * endpoint: iOS and the PWA drop PHPSESSID and keep SessionCookie, so a check
 * made before the restore logs out every PWA player instead of every guest.
 *
 * Nothing slow lives in the turn request. The Discord post and the share text
 * happen inside dhcb_finish(), which is reached only on the move that ends a
 * run. The DAMAGE COMMIT is on every move -- dhcb_commit() -- and is three
 * locked reads and at most three updates on a three-row table, which is
 * cheaper than the dhcb_bars() read this endpoint already does per move.
 */
include '../db.php';
require_once __DIR__ . '/../dhc-json.php';

if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['logged_in'])
    && isset($_COOKIE['SessionCookie'])) {
	$ck = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($ck)) $_SESSION = array_merge((array)$_SESSION, $ck);   // merge, never replace
}
if (empty($_SESSION['userData']['user_id']))
	dhc_json(array('ok' => false, 'message' => 'Not signed in.'));

include '../skulliance.php';
require_once __DIR__ . '/../dhcbosses-lib.php';

function dhcb_out($ok, $msg, $extra = array()) {
	dhc_json(array_merge(array('ok' => $ok, 'message' => $msg), $extra));
}

$user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
if ($user_id <= 0) dhcb_out(false, 'Not signed in.');

/* An abandoned run must not lock the player out of the feature. Cheap: one
   indexed read unless something is actually stale. */
dhcb_sweep_stale($conn, $user_id);

$do = isset($_POST['do']) ? $_POST['do'] : '';

/** The shared bars, sent with every reply so the page can show them moving. */
function dhcb_bars($conn) {
	$st = dhcb_state($conn);
	$p  = dhcb_progress($st);
	return array('bosses' => array_values($st), 'progress' => $p,
	             'table' => dhcb_tier_table($p));
}

if ($do === 'state') {
	dhcb_out(true, '', array_merge(dhcb_bars($conn), array(
		'block' => dhcb_entry_block($conn, $user_id),
		'runs'  => dhcb_runs_today($conn, $user_id),
		'max'   => DHCB_DAILY_RUNS,
	)));
}

if ($do === 'start') {
	$ids = isset($_POST['fighters']) && is_array($_POST['fighters']) ? $_POST['fighters'] : array();
	list($ok, $msg, $b) = dhcb_start($conn, $user_id, $ids);
	if (!$ok) dhcb_out(false, $msg);
	dhcb_out(true, '', array_merge(dhcb_bars($conn), array(
		'run_id' => (int)$b['meta']['run_id'],
		'state'  => dhca_public($b))));        // the Arena's view, unchanged
}

if ($do === 'move') {
	$rid = (int)($_POST['run_id'] ?? 0);
	list($ok, $msg, $b) = dhcb_move($conn, $user_id, $rid, (int)($_POST['a'] ?? -1), (int)($_POST['z'] ?? -1));
	if (!$ok) dhcb_out(false, $msg, $b ? array('state' => dhca_public($b)) : array());
	$out = array_merge(dhcb_bars($conn), array('state' => dhca_public($b)));
	/* THE WAVE THIS RUN WAS FOUGHT ON, which the bars above no longer carry:
	   if this run dropped the crew, dhcb_bars() has already read the NEXT
	   wave standing. The end card compares the two to know whether it was
	   this Crew that finished them. */
	$out['wave'] = isset($b['meta']['wave']) ? (int)$b['meta']['wave'] : 1;
	if ($b['over'] !== null) {
		$out['over']  = $b['over'];
		$out['dealt'] = isset($b['dealtTotal']) ? (int)$b['dealtTotal'] : 0;
		$killed = array();
		foreach ((isset($b['killedKeys']) ? $b['killedKeys'] : array()) as $k) {
			$kb = dhcb_boss($k); if ($kb) $killed[] = $kb['name'];
		}
		$out['killed'] = $killed;
		/* WAS THIS CREW STILL OUT THERE WHEN THE BOSSES FELL. With damage
		   committed move by move it cannot be "beaten to it" any more --
		   everything it landed is banked -- but it did not land the last
		   blow either, and the end card must not congratulate it for one. */
		$out['assist'] = !$killed && !empty($b['waveProg']['cleared']);
		/* THE CREW THIS RUN FOUGHT, not the one standing after it. A clear
		   stands the next wave up in this same request, so the live bars
		   describe an untouched crew at nought per cent. */
		$prog = isset($b['waveProg']) ? $b['waveProg'] : $out['progress'];
		/* Built server-side so the post cannot claim something the run did
		   not do, and so the 280-character budget is enforced in one place. */
		$out['share']  = dhcb_share_text($out['dealt'], $killed, $prog);
	}
	dhcb_out(true, '', $out);
}

if ($do === 'resume') {
	$b = dhcb_active($conn, $user_id);
	if (!$b) dhcb_out(false, 'Nothing in progress.');
	dhcb_out(true, '', array_merge(dhcb_bars($conn), array(
		'run_id' => (int)$b['meta']['run_id'],
		'state'  => dhca_public($b))));
}

dhcb_out(false, 'Unknown action.');
