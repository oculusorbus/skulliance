<?php
/**
 * ajax/dhc-update-fighter.php
 * Re-saves an EXISTING Fighter with a different set of traits.
 *
 * The point of this endpoint is what it does NOT touch: id, serial, name and
 * created_at all survive. Before it existed the only way to change a saved
 * Fighter was to disassemble and rebuild, which retired its number and lost
 * its name -- so tweaking one trait cost you the character's identity.
 * Disassembly is for dumping a Fighter; this is for editing one.
 *
 * Score and traits_hash ARE recomputed, so an edit can gain or lose the
 * originality bonus on its own merits. That is correct: the bonus belongs to a
 * configuration, not to a serial.
 *
 * Like the save endpoint, the browser is only a suggestion -- dhcf_update_fighter()
 * re-validates the whole layout against what the player holds free, counting
 * this Fighter's own traits as available to it.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../dhcfighters-lib.php';

require_once __DIR__ . '/../dhc-json.php';

function dhcf_out($ok, $message, $extra = array()) {
	dhc_json(array_merge(array('ok' => $ok, 'message' => $message), $extra));
}

if (empty($_SESSION['userData']['user_id'])) dhcf_out(false, 'Not signed in.');
$user_id = (int)$_SESSION['userData']['user_id'];

$fighter_id = (int)($_POST['id'] ?? 0);
if ($fighter_id <= 0) dhcf_out(false, 'Which Fighter?');

$traits = json_decode((string)($_POST['traits'] ?? ''), true);
if (!is_array($traits) || !$traits) dhcf_out(false, 'Nothing to save.');

/*
 * Ownership is checked here as well as in the UPDATE's WHERE clause. The query
 * would already refuse another player's row, but it would report "could not
 * save" -- indistinguishable from a database problem. Worth knowing the
 * difference from the logs.
 */
$res = $conn->query(sprintf(
	"SELECT id, serial, name FROM dhc_fighters
	 WHERE id = %d AND user_id = %d AND disassembled_at IS NULL LIMIT 1",
	$fighter_id, $user_id));
if (!$res || !$res->num_rows) {
	$conn->close();
	dhcf_out(false, 'That Fighter is not yours, or has been disassembled.');
}
$existing = $res->fetch_assoc();

list($ok, $message, $row) = dhcf_update_fighter($conn, $user_id, $fighter_id, $traits);

// Recomputed after the write so the reply carries the name the Fighter keeps,
// which is the reassurance the player wants from an edit.
$display = dhcf_display_name($existing);
$conn->close();

if (!$ok) dhcf_out(false, $message);
dhcf_out(true, $message, array(
	'display' => $display,
	'serial'  => (int)$existing['serial'],
	'score'   => (int)$row['score'],
));
