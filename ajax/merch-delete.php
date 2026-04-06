<?php
/**
 * ajax/merch-delete.php
 * Permanently delete a merch listing from Skulliance and Printful.
 * Only archived listings can be hard-deleted.
 * POST: listing_id
 */
include '../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']); exit;
}
$user_id    = intval($_SESSION['userData']['user_id']);
$listing_id = intval($_POST['listing_id'] ?? 0);

if ($listing_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid listing.']); exit;
}

// ── Verify listing belongs to user ───────────────────────────
$res = $conn->query("SELECT id, printful_product_id, status FROM merch_products WHERE id = $listing_id AND user_id = $user_id LIMIT 1");
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Listing not found.']); exit;
}
$listing = $res->fetch_assoc();

if ($listing['status'] === 'active') {
    echo json_encode(['success' => false, 'error' => 'Archive the listing before deleting it.']); exit;
}

// ── Delete from Printful if product still exists there ────────
$pf_id = intval($listing['printful_product_id']);
if ($pf_id > 0) {
    $store_r  = $conn->query("SELECT store_id FROM merch_product_stores WHERE merch_product_id = $listing_id LIMIT 1");
    $store_id = ($store_r && $sr = $store_r->fetch_assoc()) ? intval($sr['store_id']) : null;
    // 404 is fine — already gone from Printful
    printfulApiCall($conn, $user_id, 'DELETE', '/store/products/' . $pf_id, null, $store_id ?: null);
}

// ── Hard delete from DB ───────────────────────────────────────
$conn->query("DELETE FROM merch_product_stores WHERE merch_product_id = $listing_id");
$conn->query("DELETE FROM merch_products WHERE id = $listing_id AND user_id = $user_id LIMIT 1");

echo json_encode(['success' => true]);
$conn->close();
?>
