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

// ── Verify Printful account exists ───────────────────────────
$acct_check = $conn->query("SELECT id FROM merch_accounts WHERE user_id = $user_id LIMIT 1");
if (!$acct_check || $acct_check->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'No Printful account connected.']);
    exit;
}

// ── Delete from Printful ─────────────────────────────────────
$printful_product_id = intval($listing['printful_product_id']);
$pf_error = null;

if ($printful_product_id > 0) {
    // printfulApiCall returns false on non-2xx; 404 is also acceptable (already deleted)
    // We use _printfulCurl directly so we can inspect the HTTP code for 404 tolerance.
    $acct_row = $conn->query("SELECT printful_access_token, printful_refresh_token, token_expires_at FROM merch_accounts WHERE user_id=$user_id LIMIT 1")->fetch_assoc();
    $needs_refresh = (!empty($acct_row['token_expires_at']) && strtotime($acct_row['token_expires_at']) <= time());
    if ($needs_refresh) {
        $token = printfulRefreshToken($conn, $user_id, $acct_row['printful_refresh_token']);
    } else {
        $token = merchDecrypt($acct_row['printful_access_token']);
    }
    if ($token) {
        $del_result = _printfulCurl('DELETE', '/store/products/' . $printful_product_id, $token);
        $http = $del_result['http'];
        // 200 = deleted, 404 = already gone — both are fine
        if ($http !== 200 && $http !== 404) {
            // Retry once after refresh on 401
            if ($http === 401 && !empty($acct_row['printful_refresh_token'])) {
                $token = printfulRefreshToken($conn, $user_id, $acct_row['printful_refresh_token']);
                if ($token) {
                    $del_result = _printfulCurl('DELETE', '/store/products/' . $printful_product_id, $token);
                    $http = $del_result['http'];
                }
            }
            if ($http !== 200 && $http !== 404) {
                $pf_error = 'Printful deletion returned HTTP ' . $http . '. DB status still updated.';
            }
        }
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
