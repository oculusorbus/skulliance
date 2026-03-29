<?php
header('Content-Type: application/json');
include '../db.php';

if (!isset($_SESSION['userData']['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$fingerprint = trim($_GET['asset_id'] ?? '');
if (!preg_match('/^asset1[a-z0-9]{38}$/', $fingerprint)) {
    echo json_encode(['success' => false]);
    exit;
}

$fp = $conn->real_escape_string($fingerprint);
$r  = $conn->query("SELECT name, asset_name, ipfs FROM nfts WHERE asset_id='$fp' LIMIT 1");
if ($r && $r->num_rows > 0) {
    $row  = $r->fetch_assoc();
    $ipfs = $row['ipfs'] ?? '';
    $display_name = !empty($row['name']) ? $row['name'] : ($row['asset_name'] ?? '');

    // No IPFS or on-chain SVG — return name only
    if (empty($ipfs) || str_starts_with($ipfs, 'data:')) {
        echo json_encode(['success' => true, 'name' => $display_name, 'ipfs_url' => null, 'ipfs_raw' => null]);
        exit;
    }
    $clean = str_replace('ipfs/', '', $ipfs);
    echo json_encode([
        'success'  => true,
        'name'     => $display_name,
        'ipfs_url' => 'https://ipfs5.jpgstoreapis.com/ipfs/' . $clean,
        'ipfs_raw' => $ipfs,
    ]);
    exit;
}

echo json_encode(['success' => false]);
