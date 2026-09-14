<?php
/**
 * ajax/dhc-rename-fighter.php
 * Renames a Fighter, or clears the override so it falls back to its number.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../dhcfighters-lib.php';

require_once __DIR__ . '/../dhc-json.php';

if (empty($_SESSION['userData']['user_id'])) { dhc_json(array('ok' => false)); exit; }
$user_id = (int)$_SESSION['userData']['user_id'];
$id      = (int)($_POST['id'] ?? 0);
$name    = (string)($_POST['name'] ?? '');

// Ownership is enforced by the WHERE clause on user_id, not by a prior read.
dhcf_rename_fighter($conn, $user_id, $id, $name);

$display = '';
$res = $conn->query(sprintf("SELECT serial, name FROM dhc_fighters WHERE id = %d AND user_id = %d", $id, $user_id));
if ($res && $row = $res->fetch_assoc()) $display = dhcf_display_name($row);
$conn->close();

dhc_json(array('ok' => $display !== '', 'display' => $display));
