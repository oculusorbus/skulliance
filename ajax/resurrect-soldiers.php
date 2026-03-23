<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) { echo json_encode(['success' => false]); exit; }

resurrectSoldiers($conn, $realm_id);
echo json_encode(['success' => true]);
$conn->close();
?>
