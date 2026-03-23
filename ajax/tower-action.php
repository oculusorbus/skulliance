<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id   = getRealmID($conn);
$action     = isset($_POST['action'])     ? $_POST['action']         : '';
$soldier_id = isset($_POST['soldier_id']) ? intval($_POST['soldier_id']) : 0;
$value      = isset($_POST['value'])      ? intval($_POST['value'])  : 0;

if (!$realm_id || !$soldier_id) { echo json_encode(['success' => false]); exit; }

switch ($action) {
    case 'assign':   assignToTower($conn, $soldier_id, $realm_id);   break;
    case 'remove':   removeFromTower($conn, $soldier_id, $realm_id); break;
    case 'weapon':   equipSoldierWeapon($conn, $soldier_id, $realm_id, $value); break;
    case 'armor':    equipSoldierArmor($conn, $soldier_id, $realm_id, $value);  break;
    default: echo json_encode(['success' => false, 'error' => 'Unknown action']); exit;
}
echo json_encode(['success' => true]);
$conn->close();
?>
