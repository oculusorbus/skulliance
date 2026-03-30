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

$ch = curl_init('https://pool.pm/asset/' . $fingerprint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: Mozilla/5.0'],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$body || $code !== 200) {
    echo json_encode(['success' => false]);
    exit;
}

$data = json_decode($body, true);
if (!$data) {
    echo json_encode(['success' => false]);
    exit;
}

// Display name: prefer metadata.name, fall back to hex-decoded asset name
$display_name = '';
if (!empty($data['metadata']['name'])) {
    $n = $data['metadata']['name'];
    $display_name = is_array($n) ? implode('', $n) : (string)$n;
} elseif (!empty($data['name'])) {
    $decoded = @hex2bin($data['name']);
    $display_name = ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) ? $decoded : $data['name'];
}

// Quantity / fungibility
$quantity    = isset($data['quantity']) ? max(1, (int)$data['quantity']) : 1;
$is_fungible = $quantity > 1;

// Image from on-chain metadata — can be string or array of strings
$image = '';
if (isset($data['metadata']['image'])) {
    $img   = $data['metadata']['image'];
    $image = is_array($img) ? implode('', $img) : (string)$img;
}

$ipfs_raw = null;
$ipfs_url = null;

if ($image !== '') {
    if (str_starts_with($image, 'data:')) {
        // on-chain SVG — no remote image
    } elseif (str_starts_with($image, 'ipfs://')) {
        $ipfs_raw = substr($image, 7);
        $clean    = ltrim(str_replace('ipfs/', '', $ipfs_raw), '/');
        $ipfs_url = 'https://ipfs5.jpgstoreapis.com/ipfs/' . $clean;
    } elseif (preg_match('/^https?:\/\//', $image)) {
        $ipfs_url = $image;
    } else {
        // bare CID
        $ipfs_raw = $image;
        $ipfs_url = 'https://ipfs5.jpgstoreapis.com/ipfs/' . $image;
    }
}

echo json_encode([
    'success'     => true,
    'name'        => $display_name,
    'ipfs_url'    => $ipfs_url,
    'ipfs_raw'    => $ipfs_raw,
    'is_fungible' => $is_fungible,
    'quantity'    => $quantity,
]);
