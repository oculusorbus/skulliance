<?php
/**
 * ajax/merch-archive.php
 * Archive a merch listing: delete from Printful and mark DB status = 'archived'.
 * POST: listing_id
 */
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}
$user_id    = intval($_SESSION['userData']['user_id']);
$listing_id = intval($_POST['listing_id'] ?? 0);

if ($listing_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid listing.']);
    exit;
}

// ── Verify listing belongs to user ───────────────────────────
$listing_res = $conn->query("
    SELECT mp.id, mp.printful_product_id, mp.status
    FROM merch_products mp
    WHERE mp.id = $listing_id AND mp.user_id = $user_id
    LIMIT 1
");
if (!$listing_res || $listing_res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Listing not found.']);
    exit;
}
$listing = $listing_res->fetch_assoc();

if ($listing['status'] === 'archived') {
    echo json_encode(['success' => false, 'error' => 'Listing is already archived.']);
    exit;
}

// ── Get Printful API key ─────────────────────────────────────
$acct_res = $conn->query("SELECT printful_api_key_encrypted FROM merch_accounts WHERE user_id = $user_id LIMIT 1");
if (!$acct_res || $acct_res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'No Printful account connected.']);
    exit;
}
$acct    = $acct_res->fetch_assoc();
$api_key = merchDecrypt($acct['printful_api_key_encrypted']);

// ── Delete from Printful ─────────────────────────────────────
$printful_product_id = intval($listing['printful_product_id']);
$pf_error = null;

if ($printful_product_id > 0) {
    $ch = curl_init('https://api.printful.com/store/products/' . $printful_product_id);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 200 = deleted, 404 = already gone — both are fine
    if ($http !== 200 && $http !== 404) {
        $pf_error = 'Printful deletion returned HTTP ' . $http . '. DB status still updated.';
    }
}

// ── Update DB regardless of Printful result ──────────────────
$conn->query("UPDATE merch_products SET status = 'archived', updated_at = NOW() WHERE id = $listing_id LIMIT 1");
$conn->query("UPDATE merch_product_stores SET status = 'archived' WHERE merch_product_id = $listing_id");

$response = ['success' => true];
if ($pf_error) $response['warning'] = $pf_error;

echo json_encode($response);
$conn->close();
?>
