<?php
/*
 * ajax/dhcportal-stage.php — putting pieces on the board and taking them off.
 *
 * SEPARATE FROM THE JETTISON ON PURPOSE. Staging is reversible and
 * cheap; the jettison is neither. Keeping them apart means no path
 * through this file can destroy a trait, which is a property worth
 * having at the file level rather than in a branch.
 *
 * Every call re-verifies ownership and freedom in dhcp_stage(), because
 * the page that sent it may be minutes stale and the board is a draft
 * that deliberately does not lock anything.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../skulliance.php';
require_once __DIR__ . '/../lib/dhcportal-lib.php';

header('Content-Type: application/json');

$uid = (int) ($_SESSION['userData']['user_id'] ?? 0);
if ($uid <= 0) { echo json_encode(array('ok' => false, 'why' => 'Sign in first.')); exit; }

$act = isset($_POST['action']) ? (string) $_POST['action'] : '';
$did = isset($_POST['drop_id']) ? (int) $_POST['drop_id'] : 0;
$why = ''; $ok = true; $returned = array();

if ($act === 'add') {
	list($ok, $why, $returned) = dhcp_stage($conn, $uid, $did);
} elseif ($act === 'remove') {
	$ok = dhcp_unstage($conn, $uid, $did);
	if (!$ok) $why = 'That piece is not on the board.';
} elseif ($act === 'clear') {
	$ok = dhcp_clear($conn, $uid);
} else {
	$ok = false; $why = 'Unknown action.';
}

$board = dhcp_board($conn, $uid);
echo json_encode(array(
	'ok'       => $ok,
	'why'      => $why,
	/* Which pieces the eject rule handed back, so the tray can show them
	   arriving rather than the board silently shrinking. */
	'returned' => $returned,
	'board'    => $board['pieces'],
	'slots'    => $board['slots'],
	'rows'     => dhcp_rows($board['slots']),
	'table'    => dhcp_table_name($board['slots']),
	/* Pieces the board lost to a Fighter since it was last looked at. */
	'dropped'  => $board['dropped'],
));
