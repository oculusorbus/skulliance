<?php
/**
 * ajax/dhc-claim-drop.php
 * Awards a trait for a qualifying result in one of the platform's games.
 *
 * CALLED BY THE GAME, NOT TRUSTED BY IT. The browser says which game and what
 * it achieved; this file decides whether that clears the floor, which tier
 * table applies, and what drops. A client that lies about its score still only
 * gets what the floor and the tier tables allow, and every award is written to
 * the ledger with the odds it actually had.
 *
 * POST: game, value (score/waves/level/damage), placement (optional, 1-based)
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../dhcfighters-lib.php';

header('Content-Type: application/json');

function dhcf_drop_out($awarded, $why = '') {
	echo json_encode(array('ok' => (bool)$awarded, 'drop' => $awarded, 'why' => $why));
	exit;
}

if (empty($_SESSION['userData']['user_id'])) dhcf_drop_out(null, 'not signed in');
$user_id = (int)$_SESSION['userData']['user_id'];

$key  = preg_replace('/[^a-z0-9]/', '', strtolower((string)($_POST['game'] ?? '')));
$game = dhcf_game($key);
if (!$game) dhcf_drop_out(null, 'unknown game');

$value     = (int)($_POST['value'] ?? 0);
$placement = isset($_POST['placement']) ? (int)$_POST['placement'] : null;

// Did the run earn a roll? Floors are progress-based, never win/loss --
// losing is the normal outcome in most of these games.
if ($value < dhcf_floor($key)) dhcf_drop_out(null, 'below the threshold');

// Anti-abuse ceiling. Generous enough that no honest player meets it.
$sql = sprintf("SELECT COUNT(*) AS c FROM dhc_trait_drops
                WHERE user_id = %d AND source = '%s' AND awarded_at >= CURDATE()",
	$user_id, $conn->real_escape_string($key));
// Parenthesised deliberately: && binds tighter than =, so the obvious
// `if ($res && $row = $res->fetch_assoc() && ...)` assigns the comparison to
// $row and the check never fires.
$res = $conn->query($sql);
if ($res && ($row = $res->fetch_assoc()) && (int)$row['c'] >= DHCF_DAILY_CAP) {
	dhcf_drop_out(null, 'daily limit reached');
}

$detail = $key . ' ' . $value . ($placement ? ' (#' . $placement . ')' : '');
$drop = dhcf_award($conn, $user_id, $game['category'], $key, $detail, dhcf_tier_table($placement));
$conn->close();

if (!$drop) dhcf_drop_out(null, 'nothing to award');
dhcf_drop_out($drop);
