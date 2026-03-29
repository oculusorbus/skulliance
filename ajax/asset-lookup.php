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
$r  = $conn->query("SELECT ipfs FROM nfts WHERE asset_id='$fp' AND ipfs != '' LIMIT 1");
if ($r && $r->num_rows > 0) {
    $ipfs = $r->fetch_assoc()['ipfs'];
    // Skip on-chain SVG data URIs — not useful as a preview URL
    if (str_starts_with($ipfs, 'data:')) {
        echo json_encode(['success' => false]);
        exit;
    }
    $clean = str_replace('ipfs/', '', $ipfs);
    echo json_encode([
        'success'  => true,
        'ipfs_url' => 'https://ipfs5.jpgstoreapis.com/ipfs/' . $clean,
        'ipfs_raw' => $ipfs,
    ]);
    exit;
}

echo json_encode(['success' => false]);
