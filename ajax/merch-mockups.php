<?php
/**
 * ajax/merch-mockups.php
 * Given an nft_id, generate Printful mockups for all active merch_product_types
 * and return mockup URLs (polling until tasks complete or timeout).
 * GET: nft_id
 */
include '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}
$user_id = intval($_SESSION['userData']['user_id']);
$nft_id  = intval($_GET['nft_id'] ?? 0);

if ($nft_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid NFT.']);
    exit;
}

// ── Verify NFT belongs to this user and is from licensed collection ──
$nft_res = $conn->query("
    SELECT nfts.id, nfts.name AS nft_name, nfts.ipfs, nfts.collection_id,
           collections.name AS collection_name, collections.project_id,
           projects.currency
    FROM nfts
    INNER JOIN collections ON collections.id = nfts.collection_id
    INNER JOIN projects    ON projects.id    = collections.project_id
    WHERE nfts.id = $nft_id AND nfts.user_id = $user_id AND collections.merch_licensed = 1
    LIMIT 1
");
if (!$nft_res || $nft_res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'NFT not found or not eligible for merch.']);
    exit;
}
$nft = $nft_res->fetch_assoc();

// ── Get user's Printful account ──────────────────────────────
$acct_res = $conn->query("SELECT connected_stores FROM merch_accounts WHERE user_id = $user_id LIMIT 1");
if (!$acct_res || $acct_res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'No Printful account connected.']);
    exit;
}
$acct   = $acct_res->fetch_assoc();
$stores = json_decode($acct['connected_stores'], true) ?: [];

// ── Build image URL for Printful ────────────────────────────
$image_url = getIPFS($nft['ipfs'], $nft['collection_id'], $nft['project_id']);
if (str_starts_with($image_url, '/')) {
    $image_url = 'https://skulliance.io' . $image_url;
}

// ── Get active product types ─────────────────────────────────
$pt_res = $conn->query("SELECT * FROM merch_product_types WHERE active = 1 ORDER BY name ASC");
$product_types = [];
if ($pt_res) {
    while ($row = $pt_res->fetch_assoc()) $product_types[] = $row;
}
if (empty($product_types)) {
    echo json_encode(['success' => true, 'mockups' => [], 'stores' => $stores, 'fees' => []]);
    exit;
}

// ── Get listing fees per product type ───────────────────────
$project_id = intval($nft['project_id']);
$fee_res    = $conn->query("SELECT fee_amount FROM merch_listing_fees WHERE project_id = $project_id LIMIT 1");
$fee_each   = ($fee_res && $fee_res->num_rows) ? floatval($fee_res->fetch_assoc()['fee_amount']) : 100;
$fees       = [];
foreach ($product_types as $pt) {
    $fees[$pt['id']] = $fee_each;
}

// ── Create mockup tasks on Printful ─────────────────────────
$tasks = [];
foreach ($product_types as $pt) {
    $printful_product_id = intval($pt['printful_product_id']);
    $print_area = json_decode($pt['print_area_config'] ?? '{}', true);
    if (empty($print_area)) {
        $print_area = ['top' => 0, 'left' => 0, 'width' => 1800, 'height' => 2400];
    }

    $payload_arr = [
        'format' => 'jpg',
        'files'  => [[
            'placement' => 'front',
            'image_url' => $image_url,
            'position'  => $print_area,
        ]],
    ];
    if (!empty($pt['default_variant_id'])) {
        $payload_arr['variant_ids'] = [intval($pt['default_variant_id'])];
    }

    $resp_data = printfulApiCall($conn, $user_id, 'POST', '/mockup-generator/create-task/' . $printful_product_id, $payload_arr);
    if ($resp_data && !empty($resp_data['result']['task_key'])) {
        $tasks[] = [
            'task_key'        => $resp_data['result']['task_key'],
            'product_name'    => $pt['name'],
            'product_type_id' => $pt['id'],
        ];
    }
}

// ── Poll tasks for completion (up to ~18 seconds) ────────────
$mockups = [];
if (!empty($tasks)) {
    $max_polls = 6;
    $poll_wait = 3; // seconds between polls
    $pending   = $tasks;

    for ($poll = 0; $poll < $max_polls && !empty($pending); $poll++) {
        if ($poll > 0) sleep($poll_wait);
        $still_pending = [];
        foreach ($pending as $task) {
            $resp_data = printfulApiCall($conn, $user_id, 'GET', '/mockup-generator/task?task_key=' . urlencode($task['task_key']));
            $status = $resp_data['result']['status'] ?? 'waiting';
            if ($status === 'completed' && !empty($resp_data['result']['mockups'])) {
                foreach ($resp_data['result']['mockups'] as $m) {
                    $mockups[] = [
                        'url'             => $m['mockup_url'] ?? $m['url'] ?? '',
                        'product_name'    => $task['product_name'],
                        'product_type_id' => $task['product_type_id'],
                    ];
                    break; // one preview per product type
                }
            } elseif ($status === 'failed') {
                // skip failed tasks
            } else {
                $still_pending[] = $task;
            }
        }
        $pending = $still_pending;
    }
}

echo json_encode([
    'success'  => true,
    'mockups'  => $mockups,
    'stores'   => $stores,
    'fees'     => $fees,
    'currency' => $nft['currency'],
]);
$conn->close();
?>
