<?php
/**
 * ajax/dhc-delete-fighter.php
 * Disassembles a Fighter.
 *
 * Deleting the row IS the refund: availability is owned minus committed, so
 * with the row gone nothing counts those copies as in use. There is no
 * inventory write to get wrong, and no way for the two to disagree.
 *
 * The serial is retired rather than reused -- dhcf_next_serial() takes MAX+1,
 * so a disassembled DHC2F421 does not come back on someone else's Fighter.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../dhcfighters-lib.php';

header('Content-Type: application/json');

if (empty($_SESSION['userData']['user_id'])) { echo json_encode(array('ok' => false)); exit; }

$ok = dhcf_delete_fighter($conn, (int)$_SESSION['userData']['user_id'], (int)($_POST['id'] ?? 0));
$conn->close();

echo json_encode(array('ok' => $ok));
