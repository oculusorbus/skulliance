<?php
/**
 * ajax/dhc-save-fighter.php
 * Saves an assembled Fighter, spending the traits it uses.
 *
 * The layout is re-validated server-side against what the player actually
 * holds free -- the browser is only ever a suggestion. A build referencing an
 * unowned or already-committed trait is refused with the names, so the message
 * is actionable rather than "invalid".
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../dhcfighters-lib.php';

header('Content-Type: application/json');

function dhcf_out($ok, $message, $extra = array()) {
	echo json_encode(array_merge(array('ok' => $ok, 'message' => $message), $extra));
	exit;
}

if (empty($_SESSION['userData']['user_id'])) dhcf_out(false, 'Not signed in.');
$user_id = (int)$_SESSION['userData']['user_id'];

$traits = json_decode((string)($_POST['traits'] ?? ''), true);
if (!is_array($traits) || !$traits) dhcf_out(false, 'Nothing to save.');

list($ok, $message, $row) = dhcf_save_fighter($conn, $user_id, $traits, (string)($_POST['name'] ?? ''));
$conn->close();

if (!$ok) dhcf_out(false, $message);
dhcf_out(true, $message, array('display' => $row['display'], 'score' => $row['score'], 'serial' => $row['serial']));
