<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) { echo json_encode(array('success' => false)); exit; }

$result = autoEquipReserve($conn, $realm_id);
echo json_encode(array('success' => $result['equipped'] > 0, 'equipped' => $result['equipped'], 'stripped' => $result['stripped']));
$conn->close();
?>
