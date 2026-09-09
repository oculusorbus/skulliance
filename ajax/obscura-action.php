<?php
/*
 * Obscura's only endpoint: guess, or ask for a replacement puzzle.
 *
 * The answer never leaves the server until the puzzle is over, so the page
 * source cannot be read for it. Everything is judged in obscura-lib.php.
 */
include_once __DIR__ . '/../db.php';
include_once __DIR__ . '/../obscura-lib.php';

header('Content-Type: application/json');

$uid = intval($_SESSION['userData']['user_id'] ?? 0);
if ($uid <= 0) { echo json_encode(array('error' => 'not_logged_in')); exit; }

$action = $_POST['action'] ?? '';

/*
 * obscuraPickPuzzle already verifies the artwork is on disk, so 'art_gone' here
 * means the file went away between the pick and the render. Reroll once rather
 * than dead-ending the player on a message they can do nothing about.
 */
function obscura_next_state($conn, $uid) {
	$s = obscuraState($conn, $uid);
	if (isset($s['error']) && $s['error'] === 'art_gone') {
		obscuraReroll($conn, $uid);
		$s = obscuraState($conn, $uid);
	}
	return $s;
}

if ($action === 'guess') {
	$out = obscuraGuess($conn, $uid, $_POST['collection_id'] ?? 0);
	// Hand back the next puzzle in the same response so the board never sits
	// blank between rounds.
	if (isset($out['result']) && $out['result'] !== 'wrong') {
		$out['next'] = obscura_next_state($conn, $uid);
	}
	echo json_encode($out);
	exit;
}

if ($action === 'reroll') {
	// The browser could not render the artwork. Swap the puzzle at NO cost --
	// no attempt spent, streak untouched. A broken image is our problem, not
	// the player's, and at streak 31+ they only get one attempt to lose.
	obscuraReroll($conn, $uid);
	echo json_encode(array('next' => obscura_next_state($conn, $uid)));
	exit;
}

echo json_encode(array('error' => 'unknown_action'));
