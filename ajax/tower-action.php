<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id   = getRealmID($conn);
$action     = isset($_POST['action'])     ? $_POST['action']         : '';
$soldier_id = isset($_POST['soldier_id']) ? intval($_POST['soldier_id']) : 0;
$value      = isset($_POST['value'])      ? intval($_POST['value'])  : 0;

if ($action === 'remove_bulk') {
    if (!$realm_id) { echo json_encode(['success' => false]); exit; }
    $result = $conn->query("SELECT id FROM soldiers WHERE realm_id = " . intval($realm_id) . " AND location = 2 AND dead IS NULL");
    while ($row = $result->fetch_assoc()) { removeFromTower($conn, intval($row['id']), $realm_id); }
    echo json_encode(['success' => true]);
    $conn->close(); exit;
}

if ($action === 'assign_bulk') {
    if (!$realm_id) { echo json_encode(['success' => false]); exit; }
    $ids = isset($_POST['soldier_ids']) ? (array)$_POST['soldier_ids'] : array();
    foreach ($ids as $sid) { assignToTower($conn, intval($sid), $realm_id); }
    echo json_encode(['success' => true]);
    $conn->close(); exit;
}

if (!$realm_id || !$soldier_id) { echo json_encode(['success' => false]); exit; }

switch ($action) {
    case 'assign':   assignToTower($conn, $soldier_id, $realm_id);   break;
    case 'remove':   removeFromTower($conn, $soldier_id, $realm_id); break;
    default: echo json_encode(['success' => false, 'error' => 'Unknown action']); exit;
}
echo json_encode(['success' => true]);
$conn->close();
?>
