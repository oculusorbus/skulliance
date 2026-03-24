<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) { echo json_encode(array('success' => false)); exit; }

$equipped = autoEquipReserve($conn, $realm_id);
echo json_encode(array('success' => $equipped > 0, 'equipped' => $equipped));
$conn->close();
?>
