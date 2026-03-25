<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) { echo json_encode(['success' => false]); exit; }

$location = isset($_POST['location']) ? intval($_POST['location']) : null;
$count = dischargeAllSoldiers($conn, $realm_id, $location);
echo json_encode(['success' => true, 'discharged' => $count]);
$conn->close();
?>
