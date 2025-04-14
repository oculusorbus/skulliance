<?php
// Start session explicitly
session_start();

// Include dependencies
include '../db.php';
include '../skulliance.php';

// Set JSON output
header('Content-Type: application/json');

// Enable debug mode if requested
$is_debug = isset($_GET['debug']) && $_GET['debug'] === '1';

// Custom logging function
function debug_log($message) {
    global $is_debug;
    if ($is_debug) {
        echo $message . "\n";
    }
    error_log($message);
}

// Check session
if (!isset($_SESSION['userData']['user_id'])) {
    debug_log('get-nft-assets: No user_id in session');
    echo json_encode(false);
    exit;
}
$user_id = $_SESSION['userData']['user_id'];
debug_log("get-nft-assets: user_id=$user_id");

// Process input (GET or POST)
$policy_ids = [];
$theme = 'unknown';
if (isset($_GET['policyIds']) && !empty(trim($_GET['policyIds']))) {
    $policy_ids = array_filter(array_map('trim', explode(',', $_GET['policyIds'])));
    $theme = isset($_GET['theme']) ? trim($_GET['theme']) : 'unknown';
    debug_log("GET: policyIds=" . implode(',', $policy_ids) . ", theme=$theme");
} elseif (isset($_POST['policyIds']) && !empty($_POST['policyIds'])) {
    $policy_ids = is_array($_POST['policyIds']) ? array_filter(array_map('trim', $_POST['policyIds'])) : array_filter(array_map('trim', explode(',', $_POST['policyIds'])));
    $theme = isset($_POST['theme']) ? trim($_POST['theme']) : 'unknown';
    debug_log("POST: policyIds=" . implode(',', $policy_ids) . ", theme=$theme");
} else {
    debug_log('get-nft-assets: Missing policyIds');
    echo json_encode(false);
    exit;
}

if (empty($policy_ids)) {
    debug_log('get-nft-assets: Empty policyIds');
    echo json_encode(false);
    exit;
}

// Fetch assets from database
function getNFTAssets($conn, $user_id, $policy_ids) {
    global $is_debug;
    $asset_list = ["_asset_list" => []];
    $policy_placeholders = implode(',', array_fill(0, count($policy_ids), '?'));
    $sql = "SELECT collections.policy, nfts.asset_name 
            FROM nfts 
            INNER JOIN collections ON collections.id = nfts.collection_id 
            WHERE user_id = ? AND collections.policy IN ($policy_placeholders)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        debug_log('get-nft-assets: DB query prepare failed: ' . $conn->error);
        return false;
    }

    $types = 's' . str_repeat('s', count($policy_ids));
    $params = array_merge([$user_id], $policy_ids);
    $stmt->bind_param($types, ...$params);
	$stmt->execute();
	$stmt->bind_result($policy, $asset_name); // Bind variables to the two columns
	$index = 0;
	while ($stmt->fetch()) { // Fetch each row into the bound variables
	    $asset_list["_asset_list"][$index] = [$policy, bin2hex($asset_name)];
	    $index++;
	}
    $stmt->close();
    
    debug_log("get-nft-assets: DB returned $index assets");
    return $asset_list;
}

$asset_list = getNFTAssets($conn, $user_id, $policy_ids);
if (!$asset_list || empty($asset_list['_asset_list'])) {
    debug_log('get-nft-assets: No assets found in DB');
    echo json_encode(false);
    exit;
}

// Prepare Koios API payload
$payload = ["_asset_list" => $asset_list["_asset_list"]];
$payload_json = json_encode($payload);
debug_log("Koios payload: $payload_json");

// Make Koios API request
$ch = curl_init('https://api.koios.rest/api/v1/asset_info');
curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXlxc3p2dDhjazlmaGVtM3o2M2NqNXpkaGRxem53aGtuczVkeDc1YzNjcDB6Z3MwODR1OGoiLCJleHAiOjE3NjYzNzgxMjEsInRpZXIiOjEsInByb2pJRCI6IlNrdWxsaWFuY2UifQ.qS2b0FAm57dB_kddfrmtFWyHeQC27zz8JJl7qyz2dcI'));
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false || $http_code >= 400) {
    debug_log('get-nft-assets: cURL failed: ' . curl_error($ch) . " | HTTP Code: $http_code");
    debug_log('get-nft-assets: Response: ' . $response);
    echo json_encode(false);
    curl_close($ch);
    exit;
}

$tokenresponse = json_decode($response, true);
curl_close($ch);

if (!is_array($tokenresponse)) {
    debug_log('get-nft-assets: Invalid Koios response');
    echo json_encode(false);
    exit;
}

// Process API response
$final_array = [];
foreach ($tokenresponse as $data) {
    $policy_id = $data['policy_id'];
    $asset_name_ascii = $data['asset_name_ascii'] ?? '';
    
    if (empty($asset_name_ascii)) {
        debug_log("get-nft-assets: Empty asset_name_ascii for policy_id=$policy_id");
        continue;
    }

    $metadata_key = $asset_name_ascii;
    $metadata = $data['minting_tx_metadata']['721'][$policy_id][$metadata_key] ?? null;
    if (!$metadata) {
        debug_log("get-nft-assets: No metadata for $asset_name_ascii, policy_id=$policy_id");
        continue;
    }

    $name = $metadata['name'] ?? $asset_name_ascii;
    $ipfs = str_replace('ipfs://', '', $metadata['image'] ?? '');
    if (empty($ipfs)) {
        debug_log("get-nft-assets: No IPFS for $name, policy_id=$policy_id");
        continue;
    }

    // Calculate attributes
    $length = strlen($asset_name_ascii);
    $char_sum = array_sum(array_map('ord', str_split($asset_name_ascii)));
    $strength = ($char_sum % 8) + 1;
    $speed = (array_sum(array_map('ord', str_split(substr($asset_name_ascii, 0, min(10, $length))))) % 7) + 1;
    $tactics = (array_sum(array_map('ord', str_split(substr($asset_name_ascii, max(0, $length - 10))))) % 7) + 1;
    $size = ['Small', 'Medium', 'Large'][$length % 3];
    $type = ['Base', 'Leader', 'Battle Damaged'][ord($asset_name_ascii[0]) % 3];
    $powerup = ['Minor Regen', 'Regenerate', 'Boost Attack', 'Heal'][ord($asset_name_ascii[$length - 1]) % 4];

    $final_array[] = [
        'name' => $name,
        'ipfs' => $ipfs,
        'policyId' => $policy_id,
        'strength' => $strength,
        'speed' => $speed,
        'tactics' => $tactics,
        'size' => $size,
        'type' => $type,
        'powerup' => $powerup,
        'theme' => $theme
    ];
}

if (!empty($final_array)) {
    debug_log('get-nft-assets: Returning ' . count($final_array) . ' NFTs');
    echo json_encode($final_array);
} else {
    debug_log('get-nft-assets: No valid NFTs');
    echo json_encode(false);
}

$conn->close();
?>