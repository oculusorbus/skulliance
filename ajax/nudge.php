<?php
/**
 * ajax/nudge.php -- the player answering the suggestion.
 *
 * Two answers: they clicked it, or they want it gone for a week. Both are
 * writes to the nudges log and nothing else.
 *
 * THE SESSION DECIDES WHOSE ROW THIS IS. There is no user parameter, for
 * the same reason ajax/player-digest.php has none: one would let anybody
 * snooze somebody else's nudges, or write rows under their name.
 *
 * THE KEY IS NOT TRUSTED TO BE A REAL NUDGE and does not need to be --
 * it is stored, length-capped and escaped, never concatenated into a path
 * or compared against anything that grants access. A junk key costs one
 * row that no candidate will ever match.
 *
 * POST ONLY. A GET would let an <img src> in a Discord message snooze
 * somebody's nudges for a week without them touching anything.
 */
ob_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	while (ob_get_level() > 0) ob_end_clean();
	http_response_code(405);
	echo json_encode(array('ok' => false));
	exit;
}

include '../db.php';
require_once '../lib/nudge-lib.php';
while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: application/json');

$uid = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
if ($uid <= 0) { http_response_code(403); echo json_encode(array('ok' => false)); exit; }

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$key    = isset($_POST['key'])    ? (string)$_POST['key']    : '';
if (!in_array($action, array('clicked', 'snoozed'), true)) {
	echo json_encode(array('ok' => false));
	exit;
}

/* A snooze carries its own expiry rather than being worked out at read
   time, so changing NUDGE_SNOOZE_DAYS later does not retroactively move
   every snooze somebody has already taken. */
$until = ($action === 'snoozed')
	? date('Y-m-d H:i:s', strtotime('+' . NUDGE_SNOOZE_DAYS . ' days'))
	: null;

echo json_encode(array('ok' => (bool)nudge_log($conn, $uid, $key, $action, $until)));
