<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) { echo json_encode(['open' => 0]); exit; }

$cap  = getDeploymentCap($conn, $realm_id);
echo json_encode(['open' => max(0, $cap - getTotalSoldierSlotCost($conn, $realm_id))]);
$conn->close();
?>
