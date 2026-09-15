<?php
/**
 * ajax/dhc-delete-fighter.php
 * Disassembles a Fighter.
 *
 * Deleting the row IS the refund: availability is owned minus committed, so
 * with the row gone nothing counts those copies as in use. There is no
 * inventory write to get wrong, and no way for the two to disagree.
 *
 * The serial is RELEASED, not retired: the row keeps its history but drops its
 * number, and dhcf_next_serial() hands out the lowest unused one. Disassembly
 * used to burn a number permanently, which mattered when it was also the only
 * way to change a Fighter -- editing covers that now, so deleting a character
 * no longer costs the collection a number.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../dhcfighters-lib.php';

require_once __DIR__ . '/../dhc-json.php';

if (empty($_SESSION['userData']['user_id'])) { dhc_json(array('ok' => false)); exit; }

$ok = dhcf_delete_fighter($conn, (int)$_SESSION['userData']['user_id'], (int)($_POST['id'] ?? 0));
$conn->close();

dhc_json(array('ok' => $ok));
