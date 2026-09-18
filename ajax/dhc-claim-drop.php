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

require_once __DIR__ . '/../dhc-json.php';

function dhcf_drop_out($awarded, $why = '', $extra = array()) {
	dhc_json(array_merge(array('ok' => (bool)$awarded, 'drop' => $awarded, 'why' => $why), $extra));
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
//
// A near miss returns the floor so the game can say "7 of 10 waves held"
// instead of nothing. Silence after a thirty-minute Guardians run reads as a
// bug, and a stated threshold is a goal for the next attempt.
$floor = dhcf_floor($key);
if ($value < $floor) {
	dhcf_drop_out(null, 'below the threshold', array('floor' => $floor, 'value' => $value));
}

// Anti-abuse ceiling. Generous enough that no honest player meets it.
// Tethered second halves excluded, matching dhcf_award()'s own cap. If this
// counted them it would refuse a claim the award function would have allowed,
// and the modal would explain a limit the player had not actually reached.
$sql = sprintf("SELECT COUNT(*) AS c FROM dhc_trait_drops
                WHERE user_id = %d AND source = '%s' AND awarded_at >= CURDATE()
                  AND (source_detail IS NULL OR source_detail NOT LIKE '%s%%')",
	$user_id, $conn->real_escape_string($key), $conn->real_escape_string(DHCF_PAIRED_MARK));
// Parenthesised deliberately: && binds tighter than =, so the obvious
// `if ($res && $row = $res->fetch_assoc() && ...)` assigns the comparison to
// $row and the check never fires.
$res = $conn->query($sql);
if ($res && ($row = $res->fetch_assoc()) && (int)$row['c'] >= dhcf_cap($key)) {
	// Tell the client what the limit was, so the modal can explain rather than
	// leaving a player who just won wondering why nothing happened.
	dhcf_drop_out(null, 'daily limit reached',
		array('cap' => dhcf_cap($key), 'game' => $game['label']));
}

// Quality comes from the best of the game's base table, its performance bands
// and any leaderboard placement -- so going deeper always pays, and there is
// no advantage in dying the moment you clear the floor.
$table = dhcf_table_for($key, $value, $placement);
$band  = dhcf_band_label($key, $value, $placement);

$detail = $key . ' ' . $value . ($placement ? ' (#' . $placement . ')' : '') . ' [' . $band . ']';
$drop = dhcf_award($conn, $user_id, $game['category'], $key, $detail, $table);
$conn->close();

if (!$drop) dhcf_drop_out(null, 'nothing to award');
$drop['band'] = $band;
dhcf_drop_out($drop);
