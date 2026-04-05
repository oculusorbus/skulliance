<?php
/**
 * ajax/merch-submit.php
 * Deduct listing fee, create Printful product with NFT art, publish to stores.
 * POST: nft_id, project_id, product_type_ids[] (array), stores (JSON string)
 */
include '../db.php';
include '../webhooks.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}
$user_id    = intval($_SESSION['userData']['user_id']);
$nft_id     = intval($_POST['nft_id'] ?? 0);
$project_id = intval($_POST['project_id'] ?? 0);

$product_type_ids = array_values(array_unique(array_map('intval', array_filter((array)($_POST['product_type_ids'] ?? [])))));
$stores_raw       = $_POST['stores'] ?? '[]';
$stores_list      = json_decode($stores_raw, true) ?: [];

if ($nft_id <= 0 || $project_id <= 0 || empty($product_type_ids)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
    exit;
}

// ── Verify NFT ownership and licensed collection ─────────────
$nft_res = $conn->query("
    SELECT nfts.id, nfts.name AS nft_name, nfts.ipfs, nfts.collection_id,
           collections.name AS collection_name, collections.project_id
    FROM nfts
    INNER JOIN collections ON collections.id = nfts.collection_id
    WHERE nfts.id = $nft_id AND nfts.user_id = $user_id AND collections.merch_licensed = 1
    LIMIT 1
");
if (!$nft_res || $nft_res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'NFT not eligible or not in your wallet.']);
    exit;
}
$nft = $nft_res->fetch_assoc();

// ── Check for duplicate active listing ───────────────────────
$dup_res = $conn->query("SELECT id FROM merch_products WHERE nft_id = $nft_id AND user_id = $user_id AND status = 'active' LIMIT 1");
if ($dup_res && $dup_res->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'This NFT already has an active listing. Archive it first.']);
    exit;
}

// ── Get Printful API key ─────────────────────────────────────
$acct_res = $conn->query("SELECT printful_api_key_encrypted, connected_stores FROM merch_accounts WHERE user_id = $user_id LIMIT 1");
if (!$acct_res || $acct_res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'No Printful account connected.']);
    exit;
}
$acct    = $acct_res->fetch_assoc();
$api_key = merchDecrypt($acct['printful_api_key_encrypted']);

// ── Get listing fee ──────────────────────────────────────────
$fee_res  = $conn->query("SELECT fee_amount FROM merch_listing_fees WHERE project_id = $project_id LIMIT 1");
$fee_each = ($fee_res && $fee_res->num_rows) ? floatval($fee_res->fetch_assoc()['fee_amount']) : 100;
$total_fee = $fee_each * count($product_type_ids);

// ── Check user balance ───────────────────────────────────────
$balance = getCurrentBalance($conn, $user_id, $project_id);
if ($balance === 'false' || floatval($balance) < $total_fee) {
    $bal_display = ($balance === 'false') ? '0' : floatval($balance);
    echo json_encode(['success' => false, 'error' => 'Insufficient balance. Need ' . $total_fee . ', have ' . $bal_display . '.']);
    exit;
}

// ── Build image URL for Printful ────────────────────────────
$image_url = getIPFS($nft['ipfs'], $nft['collection_id'], $nft['project_id']);
if (str_starts_with($image_url, '/')) {
    $image_url = 'https://skulliance.io' . $image_url;
}

// ── Build product title and description ──────────────────────
$product_title = $nft['nft_name'] . ' — ' . $nft['collection_name'] . ' | Skulliance';
$product_desc  = 'Official merch featuring ' . $nft['nft_name'] . ' from the ' . $nft['collection_name'] . ' collection on the Skulliance platform. Art submitted by the verified NFT holder.';

// ── Get selected product types ───────────────────────────────
$pt_ids_safe   = implode(',', $product_type_ids);
$pt_res        = $conn->query("SELECT * FROM merch_product_types WHERE id IN ($pt_ids_safe) AND active = 1");
$selected_types = [];
if ($pt_res) {
    while ($row = $pt_res->fetch_assoc()) $selected_types[$row['id']] = $row;
}
if (empty($selected_types)) {
    echo json_encode(['success' => false, 'error' => 'No valid product types selected.']);
    exit;
}

// ── Determine stores to publish to ──────────────────────────
// Use user-selected stores from modal, or all connected stores
$connected_stores = json_decode($acct['connected_stores'], true) ?: [];
$publish_stores   = [];
if (!empty($stores_list)) {
    foreach ($stores_list as $s) {
        $publish_stores[] = [
            'store_id'   => intval($s['store_id']),
            'store_type' => $conn->real_escape_string($s['store_type'] ?? 'etsy'),
        ];
    }
} else {
    foreach ($connected_stores as $s) {
        $publish_stores[] = [
            'store_id'   => intval($s['id']),
            'store_type' => strtolower($s['type'] ?? 'store'),
        ];
    }
}

// ── Deduct fee (before Printful API calls to prevent double-spend) ──
updateBalance($conn, $user_id, $project_id, -$total_fee);
logDebit($conn, $user_id, 0, $total_fee, $project_id);

// ── Create products on Printful ──────────────────────────────
$created_product_ids = [];
$errors = [];

foreach ($selected_types as $pt) {
    $printful_product_id = intval($pt['printful_product_id']);
    $print_area = json_decode($pt['print_area_config'] ?? '{}', true);
    if (empty($print_area)) {
        $print_area = ['top' => 0, 'left' => 0, 'width' => 1800, 'height' => 2400];
    }

    // Build sync_variants — we need at least one variant from the product
    // First, get available variants from Printful
    $var_ch = curl_init('https://api.printful.com/products/' . $printful_product_id);
    curl_setopt($var_ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
    curl_setopt($var_ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($var_ch, CURLOPT_TIMEOUT, 12);
    $var_resp = curl_exec($var_ch);
    curl_close($var_ch);
    $var_data     = json_decode($var_resp, true);
    $all_variants = $var_data['result']['variants'] ?? [];
    // Take up to first 4 variants to keep it manageable
    $variants_to_use = array_slice($all_variants, 0, 4);

    if (empty($variants_to_use)) {
        $errors[] = 'Could not load variants for ' . $pt['name'];
        continue;
    }

    $sync_variants = [];
    foreach ($variants_to_use as $v) {
        $sync_variants[] = [
            'variant_id'   => $v['id'],
            'retail_price' => number_format(floatval($pt['base_price']), 2, '.', ''),
            'files'        => [[
                'type'      => 'front',
                'url'       => $image_url,
                'position'  => $print_area,
            ]],
        ];
    }

    $payload = json_encode([
        'sync_product' => [
            'name'        => $product_title . ' (' . $pt['name'] . ')',
            'description' => $product_desc,
        ],
        'sync_variants' => $sync_variants,
    ]);

    $create_ch = curl_init('https://api.printful.com/store/products');
    curl_setopt($create_ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
    curl_setopt($create_ch, CURLOPT_POST, 1);
    curl_setopt($create_ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($create_ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($create_ch, CURLOPT_TIMEOUT, 20);
    $create_resp = curl_exec($create_ch);
    $create_http = curl_getinfo($create_ch, CURLINFO_HTTP_CODE);
    curl_close($create_ch);

    $create_data       = json_decode($create_resp, true);
    $printful_prod_id  = $create_data['result']['sync_product']['id'] ?? null;

    if ($create_http === 200 && $printful_prod_id) {
        $created_product_ids[] = [
            'printful_product_id' => $printful_prod_id,
            'product_type_id'     => $pt['id'],
        ];
    } else {
        $errors[] = 'Failed to create ' . $pt['name'] . ' on Printful.';
    }
}

// ── Insert DB records ────────────────────────────────────────
$nft_id_esc = $nft_id;
foreach ($created_product_ids as $cp) {
    $pf_id = intval($cp['printful_product_id']);
    $conn->query("INSERT INTO merch_products (nft_id, user_id, printful_product_id, status) VALUES ($nft_id_esc, $user_id, $pf_id, 'active')");
    $merch_product_db_id = intval($conn->insert_id);

    // Insert store junction rows
    foreach ($publish_stores as $ps) {
        $store_id   = intval($ps['store_id']);
        $store_type = $ps['store_type'];
        $conn->query("INSERT INTO merch_product_stores (merch_product_id, store_type, store_id, status) VALUES ($merch_product_db_id, '$store_type', $store_id, 'active')");
    }
}

// ── Discord webhook notification ─────────────────────────────
if (!empty($created_product_ids)) {
    $discord_id  = $_SESSION['userData']['discord_id'] ?? '';
    $username    = $_SESSION['userData']['username'] ?? ($_SESSION['userData']['name'] ?? 'Unknown');
    $avatar      = $_SESSION['userData']['avatar'] ?? '';
    $avatar_url  = ($discord_id && $avatar) ? "https://cdn.discordapp.com/avatars/{$discord_id}/{$avatar}.png" : '';
    $mention     = $discord_id ? "<@{$discord_id}>" : $username;
    $nft_img_url = getIPFS($nft['ipfs'], $nft['collection_id'], $nft['project_id']);
    if (str_starts_with($nft_img_url, '/')) $nft_img_url = 'https://skulliance.io' . $nft_img_url;

    discordmsg(
        '&#127758; New Merch Listing',
        "{$mention} listed **{$nft['nft_name']}** ({$nft['collection_name']}) — " . count($created_product_ids) . " product(s) submitted.",
        $nft_img_url,
        'https://skulliance.io/staking/merchandise.php',
        'general',
        $avatar_url
    );
}

$all_success = count($created_product_ids) > 0;
$response    = [
    'success'  => $all_success,
    'created'  => count($created_product_ids),
    'errors'   => $errors,
];
if (!$all_success) {
    $response['error'] = 'No products were created. ' . implode(' ', $errors);
    // Refund on total failure
    if (count($created_product_ids) === 0) {
        updateBalance($conn, $user_id, $project_id, $total_fee);
        logCredit($conn, $user_id, $total_fee, $project_id);
    }
}

echo json_encode($response);
$conn->close();
?>
