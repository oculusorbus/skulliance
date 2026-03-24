<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id   = getRealmID($conn);
if (!$realm_id) { echo json_encode(array('success' => false)); exit; }

$action     = $_POST['action']    ?? '';
$soldier_id = intval($_POST['soldier_id'] ?? 0);
$item_id    = intval($_POST['item_id']    ?? 0);
$is_weapon  = ($_POST['type'] ?? '') === 'weapon';

switch ($action) {
    case 'equip':
        $success = equipGear($conn, $soldier_id, $realm_id, $item_id, $is_weapon);
        break;
    case 'unequip':
        $success = unequipGear($conn, $soldier_id, $realm_id, $is_weapon);
        break;
    default:
        $success = false;
}

echo json_encode(array('success' => $success));
$conn->close();
?>
