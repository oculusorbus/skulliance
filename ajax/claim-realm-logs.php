<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) { echo json_encode(array('success' => false)); exit; }

// Optional type filter: comma-separated string e.g. "carbon" or "weapon,armor"
$types = null;
if (!empty($_GET['types'])) {
    $allowed = array('carbon', 'consumable', 'weapon', 'armor');
    $raw     = explode(',', $_GET['types']);
    $types   = array_values(array_intersect($allowed, array_map('trim', $raw)));
    if (empty($types)) $types = null;
}

$success = claimRealmLogs($conn, $realm_id, $types);
echo json_encode(array('success' => $success));
$conn->close();
?>
