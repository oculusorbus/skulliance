<?php
/*
 * Realm Guardians: begin a run, snapshot it, or record its defeat.
 *
 * Three verbs, because the run has exactly three moments the server needs to
 * know about. Everything in between is the client's simulation, which is the
 * whole reason the trust boundary is written out in guardians-lib.php.
 */
include_once __DIR__ . '/../db.php';
// Needed for the defeat post: db.php does NOT include webhooks.php, and this
// endpoint is where a run actually ends. guardiansAnnounceDefeat() guards on
// function_exists, so a missing webhook degrades to no post rather than a fatal.
include_once __DIR__ . '/../webhooks.php';
include_once __DIR__ . '/../guardians-lib.php';

header('Content-Type: application/json');

$uid = intval($_SESSION['userData']['user_id'] ?? 0);
if ($uid <= 0) { echo json_encode(array('error' => 'not_logged_in')); exit; }

$action = $_POST['action'] ?? '';

if ($action === 'begin') {
	/*
	 * started_at is stamped HERE, server-side, and is what the defeat bound is
	 * measured against. Taking it from the client would let a run claim to have
	 * begun an hour ago and defeat the check entirely.
	 */
	guardiansBeginRun($conn, $uid, intval($_POST['start_wave'] ?? 1), !empty($_POST['scratch']));
	echo json_encode(array('ok' => true));
	exit;
}

if ($action === 'save') {
	$ok = guardiansSaveRun($conn, $uid, (string)($_POST['state'] ?? ''), intval($_POST['wave'] ?? 0));
	echo json_encode(array('ok' => $ok));
	exit;
}

if ($action === 'defeat') {
	$result = guardiansRecordDefeat($conn, $uid, intval($_POST['wave'] ?? 0), intval($_POST['lost'] ?? 0));
	// A rejected or unbacked claim still clears the run, so the player is not
	// left with a stale snapshot they can never resume past.
	if ($result === null) { echo json_encode(array('ok' => false, 'scored' => false)); exit; }
	guardiansAnnounceDefeat($conn, $uid, $result);
	echo json_encode(array('ok' => true, 'scored' => true, 'held' => $result['held']));
	exit;
}

if ($action === 'abandon') {
	// Pressing Begin on a board that already has a saved run. Not scored --
	// abandoning is not defeat, and paying out for it would make quitting a
	// losing run the correct play.
	guardiansClearRun($conn, $uid);
	echo json_encode(array('ok' => true));
	exit;
}

echo json_encode(array('error' => 'unknown_action'));
