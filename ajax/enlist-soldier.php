<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) { echo json_encode(['success' => false, 'error' => 'No realm']); exit; }

$nft_ids = isset($_POST['nft_ids']) ? $_POST['nft_ids'] : array();
if (!is_array($nft_ids)) $nft_ids = array($nft_ids);

$enlisted = 0;
foreach ($nft_ids as $nft_id) {
    $nft_id = intval($nft_id);
    if ($nft_id > 0 && enlistSoldier($conn, $realm_id, $nft_id)) {
        $enlisted++;
    }
}

echo json_encode(['success' => true, 'enlisted' => $enlisted]);
$conn->close();
?>
