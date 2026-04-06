<?php
/**
 * ajax/merch-debug.php
 * Debug endpoint — checks Printful product status and image URL reachability.
 * GET: listing_id (merch_products.id)
 */
include '../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) {
    echo json_encode(['error' => 'Not authenticated.']); exit;
}

// Restrict to admin only
$_admin_ids = [772831523899965440]; // Oculus Orbus discord ID — extend as needed
if (!in_array(intval($_SESSION['userData']['discord_id'] ?? 0), $_admin_ids)) {
    echo json_encode(['error' => 'Forbidden.']); exit;
}
$user_id    = intval($_SESSION['userData']['user_id']);
$listing_id = intval($_GET['listing_id'] ?? 0);

if ($listing_id <= 0) {
    echo json_encode(['error' => 'listing_id required.']); exit;
}

// Get listing from DB
$res = $conn->query("
    SELECT mp.*, nfts.ipfs, nfts.collection_id, collections.project_id
    FROM merch_products mp
    INNER JOIN nfts        ON nfts.id        = mp.nft_id
    INNER JOIN collections ON collections.id = nfts.collection_id
    WHERE mp.id = $listing_id AND mp.user_id = $user_id
    LIMIT 1
");
if (!$res || $res->num_rows === 0) {
    echo json_encode(['error' => 'Listing not found.']); exit;
}
$listing = $res->fetch_assoc();
$pf_id   = intval($listing['printful_product_id']);

// Build image URL the same way merch-submit.php does
$image_url = getIPFS($listing['ipfs'], $listing['collection_id'], $listing['project_id']);
if (!str_starts_with($image_url, '/')) {
    ensureNFTImageCached($listing['ipfs'], $listing['collection_id'], $listing['project_id']);
    $image_url = getIPFS($listing['ipfs'], $listing['collection_id'], $listing['project_id']);
}
if (str_starts_with($image_url, '/')) {
    $image_url = 'https://skulliance.io' . $image_url;
}

// Test if image URL is reachable
$ch = curl_init($image_url);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
$img_http        = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$img_final_url   = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$img_content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// Fetch Printful product status
$store_res   = $conn->query("SELECT store_id FROM merch_product_stores WHERE merch_product_id = $listing_id LIMIT 1");
$store_row   = $store_res ? $store_res->fetch_assoc() : null;
$store_id    = $store_row ? intval($store_row['store_id']) : null;

$pf_product  = printfulApiCall($conn, $user_id, 'GET', '/store/products/' . $pf_id, null, $store_id);

// Optional: patch thumbnail if ?fix=1 is passed
$fix_result = null;
if (!empty($_GET['fix'])) {
    $fix_result = printfulApiCall($conn, $user_id, 'PUT', '/store/products/' . $pf_id, [
        'sync_product' => ['thumbnail_url' => $image_url],
    ], $store_id);
}

echo json_encode([
    'listing_id'          => $listing_id,
    'printful_product_id' => $pf_id,
    'store_id'            => $store_id,
    'image_url'           => $image_url,
    'image_http_status'   => $img_http,
    'image_content_type'  => $img_content_type,
    'fix_result'          => $fix_result,
    'printful_product'    => $pf_product,
], JSON_PRETTY_PRINT);

$conn->close();
?>
