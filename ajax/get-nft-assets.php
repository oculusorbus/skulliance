<?php
// Disable error display
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Ensure JSON output
ob_start();
header('Content-Type: application/json');

// Include dependencies
include '../db.php';
include '../skulliance.php';

// Session check
if (!isset($_SESSION['userData']['user_id'])) {
    error_log('get-nft-assets: User not logged in');
    debug_log("Error: User not logged in", true); // Log even without debug mode
    ob_end_clean();
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// Initialize input data array
$input_data = [];

// Handle different request types
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0) {
    $input_data = json_decode(file_get_contents('php://input'), true) ?? [];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_data = $_POST;
} else {
    $input_data = $_GET;
}

// Enable debug mode
$is_debug = isset($input_data['debug']) && $input_data['debug'] === '1';

// Log debug messages to a file
function debug_log($message, $is_debug) {
    if ($is_debug) {
        file_put_contents('nft_debug.log', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
    }
}

// Process policyIds and theme
if (isset($input_data['policyIds'])) {
    $policy_ids_raw = $input_data['policyIds'];
    if (is_array($policy_ids_raw)) {
        $policy_ids = array_filter(array_map('trim', $policy_ids_raw));
    } else {
        $policy_ids = array_filter(array_map('trim', explode(',', $policy_ids_raw)));
    }
    $theme = $input_data['theme'] ?? 'unknown';
} else {
    error_log('get-nft-assets: Missing policyIds');
    debug_log("Error: Missing or empty policyIds", $is_debug);
    ob_end_clean();
    echo json_encode(['error' => 'Missing policyIds']);
    exit;
}

if (empty($policy_ids)) {
    error_log('get-nft-assets: Empty policyIds');
    debug_log("Error: Empty policyIds", $is_debug);
    ob_end_clean();
    echo json_encode([]);
    exit;
}

function getNFTAssets($conn, $policy_ids) {
    global $is_debug;
    if (!isset($_SESSION['userData']['user_id'])) {
        error_log('get-nft-assets: No user_id in session');
        debug_log("Error: No user_id in session", $is_debug);
        return false;
    }

    $asset_list = ["_asset_list" => []];
    $policy_placeholders = implode(',', array_fill(0, count($policy_ids), '?'));
    $sql = "SELECT collections.policy, nfts.asset_name FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id WHERE user_id = ? AND collections.policy IN ($policy_placeholders)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('get-nft-assets: DB query prepare failed: ' . $conn->error);
        debug_log("Error: DB query prepare failed: " . $conn->error, $is_debug);
        return false;
    }

    $types = 's' . str_repeat('s', count($policy_ids));
    $params = array_merge([$_SESSION['userData']['user_id']], $policy_ids);
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], array_merge([$types], $refs));
    $stmt->execute();

    $policy = '';
    $asset_name = '';
    $stmt->bind_result($policy, $asset_name);

    $index = 0;
    while ($stmt->fetch()) {
        $asset_list["_asset_list"][$index] = [$policy, bin2hex($asset_name)];
        $index++;
    }

    $stmt->close();
    error_log('get-nft-assets: DB returned ' . $index . ' assets');
    debug_log("DB returned $index assets: " . json_encode($asset_list["_asset_list"], JSON_PRETTY_PRINT), $is_debug);
    return $asset_list;
}

// Function to decode hex-encoded strings with validation
function hex2str($hex) {
    if (!is_string($hex) || strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) {
        return false;
    }
    $str = '';
    for ($i = 0; $i < strlen($hex); $i += 2) {
        $str .= chr(hexdec(substr($hex, $i, 2)));
    }
    return $str;
}

function queryKoios($batch, $batch_index, $is_cip68 = false) {
    global $is_debug;
    $payload = ["_asset_list" => $batch];
    if ($is_cip68) {
        // Add CIP-68 prefix to asset names
        $payload["_asset_list"] = array_map(function ($asset) {
            return [$asset[0], '000643b0' . $asset[1]];
        }, $batch);
    }
    $payload_json = json_encode($payload);
    debug_log("Batch $batch_index payload (CIP-68=$is_cip68): " . $payload_json, $is_debug);

    $tokench = curl_init("https://api.koios.rest/api/v1/asset_info");
    curl_setopt($tokench, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXlxc3p2dDhjazlmaGVtM3o2M2NqNXpkaGRxem53aGtuczVkeDc1YzNjcDB6Z3MwODR1OGoiLCJleHAiOjE3NjYzNzgxMjEsInRpZXIiOjEsInByb2pJRCI6IlNrdWxsaWFuY2UifQ.qS2b0FAm57dB_kddfrmtFWyHeQC27zz8JJl7qyz2dcI'));
    curl_setopt($tokench, CURLOPT_POST, 1);
    curl_setopt($tokench, CURLOPT_POSTFIELDS, $payload_json);
    curl_setopt($tokench, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($tokench, CURLOPT_HEADER, 0);
    curl_setopt($tokench, CURLOPT_RETURNTRANSFER, 1);

    $tokenresponse = curl_exec($tokench);
    if ($tokenresponse === false) {
        error_log("get-nft-assets: Batch $batch_index cURL failed (CIP-68=$is_cip68): " . curl_error($tokench));
        debug_log("Error: Batch $batch_index cURL failed (CIP-68=$is_cip68): " . curl_error($tokench), $is_debug);
        curl_close($tokench);
        return false;
    }

    $http_code = curl_getinfo($tokench, CURLINFO_HTTP_CODE);
    if ($http_code >= 400) {
        error_log("get-nft-assets: Batch $batch_index HTTP error (CIP-68=$is_cip68): " . $http_code);
        debug_log("Error: Batch $batch_index HTTP error (CIP-68=$is_cip68): $http_code, Response: " . $tokenresponse, $is_debug);
        curl_close($tokench);
        return false;
    }

    $tokenresponse = json_decode($tokenresponse, true);
    curl_close($tokench);

    debug_log("Koios response for batch $batch_index (CIP-68=$is_cip68): " . json_encode($tokenresponse, JSON_PRETTY_PRINT), $is_debug);
    return $tokenresponse;
}

$asset_list = getNFTAssets($conn, $policy_ids);
if (!is_array($asset_list) || empty($asset_list["_asset_list"])) {
    error_log('get-nft-assets: No assets found in DB');
    debug_log("No assets found in DB", $is_debug);
    ob_end_clean();
    echo json_encode([]);
    exit;
}

error_log('get-nft-assets: Processing ' . count($asset_list["_asset_list"]) . ' assets');
debug_log("Processing " . count($asset_list["_asset_list"]) . " assets", $is_debug);

// Batch asset list into chunks of 35
$batch_size = 35;
$batches = array_chunk($asset_list["_asset_list"], $batch_size);
$final_array = [];

foreach ($batches as $batch_index => $batch) {
    // Try initial query (CIP-25)
    $tokenresponse = queryKoios($batch, $batch_index, false);

    // If empty or invalid, retry with CIP-68 prefix
    if (!is_array($tokenresponse) || empty($tokenresponse)) {
        debug_log("Batch $batch_index returned empty/invalid response, retrying with CIP-68 prefix", $is_debug);
        $tokenresponse = queryKoios($batch, $batch_index, true);
    }

    if (!is_array($tokenresponse) || empty($tokenresponse)) {
        error_log("get-nft-assets: Invalid or empty Koios response for batch $batch_index after retry");
        debug_log("Error: Invalid or empty Koios response for batch $batch_index after retry", $is_debug);
        continue;
    }

    foreach ($tokenresponse as $tokenresponsedata) {
        $tokenresponsedata = (array)$tokenresponsedata;
        $policy_id = $tokenresponsedata['policy_id'] ?? '';
        $asset_name_ascii = $tokenresponsedata['asset_name_ascii'] ?? '';
        $asset_name_hex = $tokenresponsedata['asset_name'] ?? '';

        debug_log("Asset: policy_id=$policy_id, asset_name_ascii=$asset_name_ascii, asset_name_hex=$asset_name_hex", $is_debug);

        if (empty($policy_id) || empty($asset_name_ascii)) {
            error_log("get-nft-assets: Missing policy_id or asset_name_ascii for batch $batch_index");
            debug_log("Error: Missing policy_id or asset_name_ascii", $is_debug);
            continue;
        }

        $nft_metadata = null;
        $is_cip68 = false;

        // Try CIP-25 metadata
        try {
            if (isset($tokenresponsedata['minting_tx_metadata']['721'][$policy_id][$asset_name_ascii])) {
                $nft_metadata = (object)$tokenresponsedata['minting_tx_metadata']['721'][$policy_id][$asset_name_ascii];
                debug_log("Found CIP-25 metadata for $asset_name_ascii: " . json_encode($nft_metadata, JSON_PRETTY_PRINT), $is_debug);
            }
        } catch (Exception $e) {
            error_log("get-nft-assets: Error accessing CIP-25 metadata for $asset_name_ascii, policy_id=$policy_id: " . $e->getMessage());
            debug_log("Error accessing CIP-25 metadata: " . $e->getMessage(), $is_debug);
        }

        // Try CIP-68 metadata if no CIP-25 metadata
        if ($nft_metadata === null && isset($tokenresponsedata['cip68_metadata']['100']['fields'][0]['map'])) {
            try {
                $is_cip68 = true;
                $cip68_map = $tokenresponsedata['cip68_metadata']['100']['fields'][0]['map'];
                $nft_metadata = [];
                foreach ($cip68_map as $entry) {
                    $entry = (array)$entry;
                    if (isset($entry['k']['bytes']) && isset($entry['v']['bytes'])) {
                        $key = hex2str($entry['k']['bytes']);
                        $value = hex2str($entry['v']['bytes']);
                        if ($key === false || $value === false) {
                            error_log("get-nft-assets: Invalid hex in CIP-68 metadata for $asset_name_ascii, policy_id=$policy_id");
                            debug_log("Error: Invalid hex in CIP-68 metadata for $asset_name_ascii", $is_debug);
                            continue 2;
                        }
                        $nft_metadata[$key] = $value;
                    }
                }
                debug_log("Decoded CIP-68 metadata for $asset_name_ascii: " . json_encode($nft_metadata, JSON_PRETTY_PRINT), $is_debug);
            } catch (Exception $e) {
                error_log("get-nft-assets: Error decoding CIP-68 metadata for $asset_name_ascii, policy_id=$policy_id: " . $e->getMessage());
                debug_log("Error decoding CIP-68 metadata: " . $e->getMessage(), $is_debug);
            }
        }

        if ($nft_metadata === null) {
            error_log("get-nft-assets: No metadata (CIP-25 or CIP-68) for asset_name_ascii=$asset_name_ascii, policy_id=$policy_id in batch $batch_index");
            debug_log("No metadata for asset_name_ascii=$asset_name_ascii, policy_id=$policy_id", $is_debug);
            continue;
        }

        $nft_metadata = (object)$nft_metadata;

        // Extract name
        $name = 'NFT Unknown';
        if (isset($nft_metadata->name)) {
            $name = $nft_metadata->name;
        } elseif (isset($nft_metadata->title)) {
            $name = $nft_metadata->title;
        } elseif ($asset_name_ascii) {
            $name = $asset_name_ascii;
        }

        // Extract IPFS link
        $ipfs = '';
        if (isset($nft_metadata->files) && is_array($nft_metadata->files) && !empty($nft_metadata->files)) {
            foreach ($nft_metadata->files as $file) {
                $file = (array)$file;
                if (isset($file['src']) && strpos($file['src'], 'ipfs://') === 0) {
                    $ipfs = str_replace('ipfs://', '', $file['src']);
                    break;
                }
            }
        }
        if (empty($ipfs) && isset($nft_metadata->image)) {
            $ipfs = str_replace('ipfs://', '', $nft_metadata->image);
        }

        if (empty($ipfs)) {
            error_log("get-nft-assets: No IPFS for $name, policy_id=$policy_id in batch $batch_index");
            debug_log("No IPFS for name=$name, policy_id=$policy_id", $is_debug);
            continue;
        }

        // Calculate attributes
        $char_sum = 0;
        $length = strlen($asset_name_ascii);
        for ($i = 0; $i < $length; $i++) {
            $char_sum += ord($asset_name_ascii[$i]);
        }
        $strength = ($char_sum % 8) + 1;
        $speed_sum = 0;
        for ($i = 0; $i < min(10, $length); $i++) {
            $speed_sum += ord($asset_name_ascii[$i]);
        }
        $speed = ($speed_sum % 7) + 1;
        $tactics_sum = 0;
        for ($i = max(0, $length - 10); $i < $length; $i++) {
            $tactics_sum += ord($asset_name_ascii[$i]);
        }
        $tactics = ($tactics_sum % 7) + 1;
        $size_map = ['Small', 'Medium', 'Large'];
        $size = $size_map[$length % 3];
        $type_map = ['Base', 'Leader', 'Battle Damaged'];
        $hash = crc32($asset_name_ascii);
        $type = $type_map[$hash % 3];
        $powerup_map = ['Minor Regen', 'Regenerate', 'Boost Attack', 'Heal'];
        $powerup = $powerup_map[ord($asset_name_ascii[$length - 1]) % 4];

        // Add to final array
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
        debug_log("Added NFT: name=$name, policy_id=$policy_id, is_cip68=$is_cip68, ipfs=$ipfs", $is_debug);
    }
}

if (!empty($final_array)) {
    error_log('get-nft-assets: Returning ' . count($final_array) . ' NFTs');
    debug_log("Returning " . count($final_array) . " NFTs", $is_debug);
    ob_end_clean();
    echo json_encode($final_array);
} else {
    error_log('get-nft-assets: No valid NFTs');
    debug_log("No valid NFTs", $is_debug);
    ob_end_clean();
    echo json_encode([]);
}

$conn->close();
ob_end_clean();
?>