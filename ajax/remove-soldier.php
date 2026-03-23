<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id   = getRealmID($conn);
$soldier_id = isset($_POST['soldier_id']) ? intval($_POST['soldier_id']) : 0;

if ($realm_id && $soldier_id > 0) {
    removeSoldier($conn, $soldier_id, $realm_id);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
$conn->close();
?>
