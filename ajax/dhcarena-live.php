<?php
/**
 * ajax/dhcarena-live.php — one live-match action per request.
 *
 * The trust boundary for human-vs-human battles. Same shape as
 * ajax/dhcarena-action.php: the client sends an intent, everything is decided
 * here and in dhcarena-live.php, and a tampered request gets a refusal rather
 * than a result.
 *
 * SIGNED IN ONLY, unlike the practice endpoint. A live match is between two
 * named players and the result is announced with their names on it, so there
 * is nobody to be without an account.
 *
 * Nothing here can pay anything out: dhcarena-live.php touches exactly one
 * table and never calls dhca_finish(), dhca_bench(), dhca_record() or
 * dhca_pay(). See dhcarena.md §8d.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../dhcarena-live.php';
require_once __DIR__ . '/../dhc-json.php';

function dhcal_out($ok, $msg, $extra = array()) {
	dhc_json(array_merge(array('ok' => $ok, 'message' => $msg), $extra));
}

$user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
if ($user_id <= 0) dhcal_out(false, 'Not signed in.');

$do = isset($_POST['do']) ? $_POST['do'] : '';

/* What the setup screen asks on a timer: is there a challenge waiting for me,
   or has the person I challenged accepted yet? One row either way. */
if ($do === 'lobby') {
	$row = dhcal_current($conn, $user_id);
	if (!$row) dhcal_out(true, '', array('live' => 0));
	$seat = dhcal_seat($row, $user_id);
	$b    = dhcal_state($row);
	if ($b) dhcal_clock($conn, $row, $b);
	dhcal_out(true, '', dhcal_payload($conn, $row, $b, $seat, -1));
}

if ($do === 'challenge') {
	$ids = isset($_POST['fighters']) && is_array($_POST['fighters']) ? $_POST['fighters'] : array();
	list($ok, $msg, $row) = dhcal_challenge($conn, $user_id, (int)($_POST['guest'] ?? 0), $ids);
	if (!$ok) dhcal_out(false, $msg);
	dhcal_out(true, '', dhcal_payload($conn, $row, null, 'host', -1));
}

if ($do === 'accept') {
	$ids = isset($_POST['fighters']) && is_array($_POST['fighters']) ? $_POST['fighters'] : array();
	list($ok, $msg, $row) = dhcal_accept($conn, $user_id, (int)($_POST['live'] ?? 0), $ids);
	if (!$ok) dhcal_out(false, $msg);
	$b = dhcal_state($row);
	// since = 0: the guest has animated nothing, and an opening board has no
	// timeline to play anyway
	dhcal_out(true, '', dhcal_payload($conn, $row, $b, 'guest', 0));
}

if ($do === 'decline') {
	list($ok, $msg) = dhcal_decline($conn, $user_id, (int)($_POST['live'] ?? 0));
	dhcal_out($ok, $msg, array('live' => 0));
}

if ($do === 'move') {
	list($ok, $msg, $row, $b) = dhcal_move($conn, $user_id,
		(int)($_POST['live'] ?? 0), (int)($_POST['a'] ?? -1), (int)($_POST['z'] ?? -1));
	if (!$ok) {
		/* A refusal still carries the board where there is one, because the
		   commonest refusal by far is "not your turn" on a page whose poll has
		   not landed yet -- and leaving that client showing a stale board is
		   how a live match turns into two people arguing about what happened. */
		$extra = array();
		if ($row && $b) $extra = dhcal_payload($conn, $row, $b, dhcal_seat($row, $user_id), -1);
		dhcal_out(false, $msg, $extra);
	}
	// since = seq-1: the mover animates its own move, the same as ranked
	$seq = count($b['meta']['moves']);
	dhcal_out(true, '', dhcal_payload($conn, $row, $b, dhcal_seat($row, $user_id), $seq - 1));
}

if ($do === 'poll') {
	list($ok, $msg, $payload) = dhcal_poll($conn, $user_id,
		(int)($_POST['live'] ?? 0), (int)($_POST['since'] ?? -1));
	if (!$ok) dhcal_out(false, $msg);
	dhcal_out(true, '', $payload);
}

/* Walking away. With nothing at stake there is no forfeit to apply and no
   record to write -- the match simply stops existing, which is the honest
   result of two people who stopped playing. */
if ($do === 'quit') {
	$row = dhcal_row($conn, (int)($_POST['live'] ?? 0));
	if ($row && dhcal_seat($row, $user_id)) dhcal_close($conn, $row);
	dhcal_out(true, '', array('live' => 0));
}

dhcal_out(false, 'Unknown action.');
