<?php
// Include dependencies
include '../db.php';
include '../skulliance.php';

// Set JSON output
header('Content-Type: application/json');

if (isset($_SESSION['userData']['user_id'])) {
    // Initialize input data array
    $input_data = [];

    // Handle different request types
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0) {
        // For JSON POST requests
        $input_data = json_decode(file_get_contents('php://input'), true);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // For form-data POST requests
        $input_data = $_POST;
    } else {
        // For GET requests
        $input_data = $_GET;
    }

    // Enable debug mode if requested
    $is_debug = isset($input_data['debug']) && $input_data['debug'] === '1';

    // Process policyIds and theme
    if (isset($input_data['policyIds'])) {
        $policy_ids_raw = $input_data['policyIds'];
        if (is_array($policy_ids_raw)) {
            // If policyIds is already an array (e.g., from JSON)
            $policy_ids = array_filter(array_map('trim', $policy_ids_raw));
        } else {
            // If policyIds is a string (e.g., from GET parameters)
            $policy_ids = array_filter(array_map('trim', explode(',', $policy_ids_raw)));
        }
        $theme = $input_data['theme'] ?? 'unknown';
    } else {
        error_log('get-nft-assets: Missing policyIds');
        if ($is_debug) {
            echo "Error: Missing or empty policyIds\n";
        }
        echo json_encode(false);
        exit;
    }

    if (empty($policy_ids)) {
        error_log('get-nft-assets: Empty policyIds');
        if ($is_debug) {
            echo "Error: Empty policyIds\n";
        }
        echo json_encode(false);
        exit;
    }

    function getNFTAssets($conn, $policy_ids, $theme) {
        global $is_debug;
        if (!isset($_SESSION['userData']['user_id'])) {
            error_log('get-nft-assets: No user_id in session');
            if ($is_debug) {
                echo "Error: No user_id in session\n";
            }
            return false;
        }

        $final_array = [];
        $policy_placeholders = implode(',', array_fill(0, count($policy_ids), '?'));
        $sql = "SELECT collections.policy, nfts.name, nfts.ipfs, nfts.asset_id 
                FROM nfts 
                INNER JOIN collections ON collections.id = nfts.collection_id 
                WHERE user_id = ? AND collections.policy IN ($policy_placeholders)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('get-nft-assets: DB query prepare failed: ' . $conn->error);
            if ($is_debug) {
                echo "Error: DB query prepare failed: " . $conn->error . "\n";
            }
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

        // Bind result variables
        $policy = '';
        $name = '';
        $ipfs = '';
        $asset_id = '';
        $stmt->bind_result($policy, $name, $ipfs, $asset_id);

        $index = 0;
        while ($stmt->fetch()) {
            // Use name field directly, default to 'NFT Unknown' if empty
            $name = $name ?: 'NFT Unknown';

            // Check if IPFS is valid
            if (empty($ipfs)) {
                error_log("get-nft-assets: No IPFS for name=$name, policy_id=$policy");
                if ($is_debug) {
                    echo "No IPFS for name=$name, policy_id=$policy\n";
                }
                continue;
            }

            // Extract unique string from asset_id by removing "asset" prefix
            if (strpos($asset_id, 'asset') !== 0) {
                error_log("get-nft-assets: Invalid asset_id format: $asset_id for name=$name, policy_id=$policy");
                if ($is_debug) {
                    echo "Error: Invalid asset_id format: $asset_id\n";
                }
                continue;
            }
            $unique_id = substr($asset_id, 5); // Remove "asset" prefix
            if (empty($unique_id)) {
                error_log("get-nft-assets: Empty unique_id after removing prefix for name=$name, policy_id=$policy");
                if ($is_debug) {
                    echo "Error: Empty unique_id for name=$name\n";
                }
                continue;
            }

            // Calculate attributes based on unique_id
            $length = strlen($unique_id);
            $char_sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $char_sum += ord($unique_id[$i]);
            }
            $strength = ($char_sum % 8) + 1;

            $speed_sum = 0;
            for ($i = 0; $i < min(10, $length); $i++) {
                $speed_sum += ord($unique_id[$i]);
            }
            $speed = ($speed_sum % 7) + 1;

            $tactics_sum = 0;
            for ($i = max(0, $length - 10); $i < $length; $i++) {
                $tactics_sum += ord($unique_id[$i]);
            }
            $tactics = ($tactics_sum % 7) + 1;

			$size_map = ['Small', 'Medium', 'Large'];
			$hash = crc32($unique_id); // Compute hash
			$size = $size_map[$hash % 3]; // Use hash to pick size

            $type_map = ['Base', 'Leader', 'Battle Damaged'];
            $hash = crc32($unique_id);
            $type = $type_map[$hash % 3];

            $powerup_map = ['Minor Regen', 'Regenerate', 'Boost Attack', 'Heal'];
            $powerup = $powerup_map[ord($unique_id[$length - 1]) % 4];

            $final_array[] = [
                'name' => $name,
                'ipfs' => $ipfs,
                'policyId' => $policy,
                'strength' => $strength,
                'speed' => $speed,
                'tactics' => $tactics,
                'size' => $size,
                'type' => $type,
                'powerup' => $powerup,
                'theme' => $theme
            ];
            $index++;
            if ($is_debug) {
                echo "Added NFT: name=$name, policy_id=$policy, asset_id=$asset_id\n";
            }
        }

        $stmt->close();
        error_log('get-nft-assets: DB returned ' . $index . ' assets');
        if ($is_debug) {
            echo "DB returned $index assets\n";
        }
        return $final_array;
    }

    $final_array = getNFTAssets($conn, $policy_ids, $theme);
    if (is_array($final_array) && !empty($final_array)) {
        error_log('get-nft-assets: Returning ' . count($final_array) . ' NFTs');
        if ($is_debug) {
            echo "Returning " . count($final_array) . " NFTs\n";
            echo json_encode($final_array, JSON_PRETTY_PRINT) . "\n";
        }
        echo json_encode($final_array);
    } else {
        error_log('get-nft-assets: No valid NFTs');
        if ($is_debug) {
            echo "No valid NFTs\n";
        }
        echo json_encode(false);
    }
} else {
    error_log('get-nft-assets: User not logged in');
    if ($is_debug) {
        echo "Error: User not logged in\n";
    }
    echo json_encode(false);
}

$conn->close();
?>