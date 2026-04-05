<?php
/**
 * ajax/merch-connect.php
 * Save and verify a Printful API key for the logged-in user.
 * POST: api_key
 */
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}
$user_id = intval($_SESSION['userData']['user_id']);

$api_key = trim($_POST['api_key'] ?? '');
if (empty($api_key)) {
    echo json_encode(['success' => false, 'error' => 'API key is required.']);
    exit;
}

// ── Verify key by calling Printful /stores ───────────────────
$ch = curl_init('https://api.printful.com/stores');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 12);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http !== 200) {
    echo json_encode(['success' => false, 'error' => 'Invalid API key or Printful error (HTTP ' . $http . ').']);
    exit;
}

$data = json_decode($resp, true);
if (empty($data['result'])) {
    echo json_encode(['success' => false, 'error' => 'No stores found on this Printful account.']);
    exit;
}

// ── Encrypt and upsert ───────────────────────────────────────
$stores      = json_encode($data['result']);
$enc_key     = merchEncrypt($api_key);
$enc_key_esc = $conn->real_escape_string($enc_key);
$stores_esc  = $conn->real_escape_string($stores);

$existing = $conn->query("SELECT id FROM merch_accounts WHERE user_id = $user_id LIMIT 1");
if ($existing && $existing->num_rows > 0) {
    $conn->query("UPDATE merch_accounts SET printful_api_key_encrypted='$enc_key_esc', connected_stores='$stores_esc', updated_at=NOW() WHERE user_id=$user_id LIMIT 1");
} else {
    $conn->query("INSERT INTO merch_accounts (user_id, printful_api_key_encrypted, connected_stores) VALUES ($user_id, '$enc_key_esc', '$stores_esc')");
}

echo json_encode(['success' => true, 'stores' => $data['result']]);
$conn->close();
?>
