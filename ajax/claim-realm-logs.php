<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) { echo json_encode(array('success' => false)); exit; }

$types   = isset($_POST['types']) ? (array)$_POST['types'] : array();
$success = claimRealmLogs($conn, $realm_id, $types);
if ($success && (in_array('weapon', $types) || in_array('armor', $types))) {
    autoEquipReserve($conn, $realm_id);
}
echo json_encode(array('success' => $success));
$conn->close();
?>
