<?php
/**
 * ajax/merch-relist.php
 * Re-create a previously archived listing on Printful without charging a fee.
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

// ── Load archived listing with all needed context ─────────────
$res = $conn->query("
    SELECT mp.id, mp.nft_id, mp.product_type_id, mp.status,
           nfts.name AS nft_name, nfts.ipfs, nfts.collection_id, nfts.user_id AS nft_owner,
           collections.name AS collection_name, collections.project_id,
           mpt.printful_product_id AS pf_catalog_id, mpt.name AS product_type_name,
           mpt.print_area_config, mpt.base_price,
           mps.store_id, mps.store_type
    FROM merch_products mp
    INNER JOIN nfts               ON nfts.id        = mp.nft_id
    INNER JOIN collections        ON collections.id = nfts.collection_id
    LEFT JOIN  merch_product_types mpt ON mpt.id    = mp.product_type_id
    LEFT JOIN  merch_product_stores mps ON mps.merch_product_id = mp.id
    WHERE mp.id = $listing_id AND mp.user_id = $user_id
    LIMIT 1
");
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Listing not found.']); exit;
}
$listing = $res->fetch_assoc();

if ($listing['status'] !== 'archived') {
    echo json_encode(['success' => false, 'error' => 'Only archived listings can be relisted.']); exit;
}

// ── Verify NFT is still in the user's wallet ──────────────────
if (intval($listing['nft_owner']) !== $user_id) {
    echo json_encode(['success' => false, 'error' => 'This NFT is no longer in your wallet.']); exit;
}

// ── Verify Printful account ───────────────────────────────────
$acct_res = $conn->query("SELECT id FROM merch_accounts WHERE user_id = $user_id LIMIT 1");
if (!$acct_res || $acct_res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'No Printful account connected.']); exit;
}

// ── Build image URL ───────────────────────────────────────────
$image_url = getIPFS($listing['ipfs'], $listing['collection_id'], $listing['project_id']);
if (!str_starts_with($image_url, '/')) {
    ensureNFTImageCached($listing['ipfs'], $listing['collection_id'], $listing['project_id']);
    $image_url = getIPFS($listing['ipfs'], $listing['collection_id'], $listing['project_id']);
}
if (str_starts_with($image_url, '/')) {
    $image_url = 'https://skulliance.io' . $image_url;
}

// ── Pre-upload image to Printful Files API ────────────────────
$pf_file_id = null;
$upload = printfulApiCall($conn, $user_id, 'POST', '/files', [
    'url'      => $image_url,
    'filename' => md5($listing['ipfs']) . '.jpg',
]);
if (!empty($upload['result']['id'])) {
    $pf_file_id = intval($upload['result']['id']);
}

// ── Build print area / file type ─────────────────────────────
$print_area_config = json_decode($listing['print_area_config'] ?? '{}', true);
$file_type  = $print_area_config['file_type'] ?? 'default';
$pos_keys   = ['top', 'left', 'width', 'height'];
$print_area = (count(array_intersect_key($print_area_config, array_flip($pos_keys))) === 4)
    ? array_intersect_key($print_area_config, array_flip($pos_keys))
    : null;

// ── Fetch variants from Printful catalog ─────────────────────
$pf_catalog_id = intval($listing['pf_catalog_id']);
$var_data = printfulApiCall($conn, $user_id, 'GET', '/products/' . $pf_catalog_id);
if (!$var_data || !empty($var_data['_error'])) {
    echo json_encode(['success' => false, 'error' => 'Could not load variants from Printful.']); exit;
}
$variants_to_use = array_slice($var_data['result']['variants'] ?? [], 0, 4);
if (empty($variants_to_use)) {
    echo json_encode(['success' => false, 'error' => 'No variants found for this product type.']); exit;
}

// ── Build sync variants ───────────────────────────────────────
$product_title = $listing['nft_name'] . ' — ' . $listing['collection_name'] . ' | Skulliance';
$product_desc  = 'Official merch featuring ' . $listing['nft_name'] . ' from the ' . $listing['collection_name'] . ' collection on the Skulliance platform. Art submitted by the verified NFT holder.';

$sync_variants = [];
foreach ($variants_to_use as $v) {
    $print_file = $pf_file_id
        ? ['type' => $file_type, 'id' => $pf_file_id]
        : ['type' => $file_type, 'url' => $image_url];
    if ($print_area !== null) $print_file['position'] = $print_area;

    $mockup_file = $pf_file_id
        ? ['type' => 'mockup', 'id' => $pf_file_id]
        : ['type' => 'mockup', 'url' => $image_url];

    $sync_variants[] = [
        'variant_id'   => $v['id'],
        'retail_price' => number_format(floatval($listing['base_price']), 2, '.', ''),
        'files'        => [$print_file, $mockup_file],
    ];
}

// ── Create product on Printful ────────────────────────────────
$ps_store_id = intval($listing['store_id']);
$create_data = printfulApiCall($conn, $user_id, 'POST', '/store/products', [
    'sync_product'  => ['name' => $product_title . ' (' . $listing['product_type_name'] . ')', 'description' => $product_desc],
    'sync_variants' => $sync_variants,
], $ps_store_id ?: null);

$new_pf_id = $create_data['result']['id'] ?? ($create_data['result']['sync_product']['id'] ?? null);
if (!$new_pf_id) {
    $pf_error = isset($create_data['_error'])
        ? 'HTTP ' . $create_data['_http'] . ': ' . $create_data['_body']
        : json_encode($create_data);
    echo json_encode(['success' => false, 'error' => 'Printful product creation failed: ' . $pf_error]); exit;
}

// ── Update DB record ──────────────────────────────────────────
$new_pf_id = intval($new_pf_id);
$conn->query("UPDATE merch_products SET printful_product_id = $new_pf_id, status = 'active', updated_at = NOW() WHERE id = $listing_id LIMIT 1");
$conn->query("UPDATE merch_product_stores SET status = 'active' WHERE merch_product_id = $listing_id");

echo json_encode(['success' => true]);
$conn->close();
?>
