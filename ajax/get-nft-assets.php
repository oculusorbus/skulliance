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
	
	// Function to decode hex-encoded strings with validation
	function hex2str($hex) {
	    if (!is_string($hex) || strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) {
	        return false; // Invalid hex string
	    }
	    $str = '';
	    for ($i = 0; $i < strlen($hex); $i += 2) {
	        $str .= chr(hexdec(substr($hex, $i, 2)));
	    }
	    return $str;
	}

    function getNFTAssets($conn, $policy_ids) {
        global $is_debug;
        if (!isset($_SESSION['userData']['user_id'])) {
            error_log('get-nft-assets: No user_id in session');
            if ($is_debug) {
                echo "Error: No user_id in session\n";
            }
            return false;
        }

        $asset_list = ["_asset_list" => []];
        $policy_placeholders = implode(',', array_fill(0, count($policy_ids), '?'));
        $sql = "SELECT collections.policy, nfts.asset_name FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id WHERE user_id = ? AND collections.policy IN ($policy_placeholders)";
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
        $asset_name = '';
        $stmt->bind_result($policy, $asset_name);

        $index = 0;
        while ($stmt->fetch()) {
            $asset_list["_asset_list"][$index] = array($policy, bin2hex($asset_name));
            $index++;
        }

        $stmt->close();
        error_log('get-nft-assets: DB returned ' . $index . ' assets');
        if ($is_debug) {
            echo "DB returned $index assets\n";
        }
        return $asset_list;
    }

    $asset_list = getNFTAssets($conn, $policy_ids);
    if (is_array($asset_list) && !empty($asset_list["_asset_list"])) {
        error_log('get-nft-assets: Processing ' . count($asset_list["_asset_list"]) . ' assets');
        if ($is_debug) {
            echo "Processing " . count($asset_list["_asset_list"]) . " assets\n";
            echo "Asset list: " . json_encode($asset_list["_asset_list"], JSON_PRETTY_PRINT) . "\n";
        }

        // Batch asset list into chunks of 35
        $batch_size = 35;
        $batches = array_chunk($asset_list["_asset_list"], $batch_size);
        $final_array = [];

        foreach ($batches as $batch_index => $batch) {
            // Prepare Koios payload for this batch
            $payload = ["_asset_list" => $batch];
            $payload_json = json_encode($payload);
            if ($is_debug) {
                echo "Batch $batch_index payload: " . $payload_json . "\n";
            }

            $tokench = curl_init("https://api.koios.rest/api/v1/asset_info");
            curl_setopt($tokench, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXlxc3p2dDhjazlmaGVtM3o2M2NqNXpkaGRxem53aGtuczVkeDc1YzNjcDB6Z3MwODR1OGoiLCJleHAiOjE3NjYzNzgxMjEsInRpZXIiOjEsInByb2pJRCI6IlNrdWxsaWFuY2UifQ.qS2b0FAm57dB_kddfrmtFWyHeQC27zz8JJl7qyz2dcI'));
            curl_setopt($tokench, CURLOPT_POST, 1);
            curl_setopt($tokench, CURLOPT_POSTFIELDS, $payload_json);
            curl_setopt($tokench, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($tokench, CURLOPT_HEADER, 0);
            curl_setopt($tokench, CURLOPT_RETURNTRANSFER, 1);
            if ($is_debug) {
                curl_setopt($tokench, CURLOPT_VERBOSE, 1);
                $verbose = fopen('php://temp', 'w+');
                curl_setopt($tokench, CURLOPT_STDERR, $verbose);
            }

            $tokenresponse = curl_exec($tokench);
            if ($tokenresponse === false) {
                error_log("get-nft-assets: Batch $batch_index cURL failed: " . curl_error($tokench));
                if ($is_debug) {
                    echo "Error: Batch $batch_index cURL failed: " . curl_error($tokench) . "\n";
                    rewind($verbose);
                    echo "cURL verbose: " . stream_get_contents($verbose) . "\n";
                    fclose($verbose);
                }
                curl_close($tokench);
                continue; // Skip this batch and continue with the next
            }

            $http_code = curl_getinfo($tokench, CURLINFO_HTTP_CODE);
            if ($http_code >= 400) {
                error_log("get-nft-assets: Batch $batch_index HTTP error: " . $http_code);
                if ($is_debug) {
                    echo "Error: Batch $batch_index HTTP error: $http_code\n";
                    echo "Koios response: " . $tokenresponse . "\n";
                    rewind($verbose);
                    echo "cURL verbose: " . stream_get_contents($verbose) . "\n";
                    fclose($verbose);
                }
                curl_close($tokench);
                continue; // Skip this batch and continue with the next
            }

            $tokenresponse = json_decode($tokenresponse);
            curl_close($tokench);
            if ($is_debug) {
                fclose($verbose);
            }

			// Process Koios response
			if (!is_array($tokenresponse)) {
			    error_log("get-nft-assets: Invalid Koios response for batch $batch_index: " . json_encode($tokenresponse));
			    if ($is_debug) {
			        echo "Error: Invalid Koios response for batch $batch_index: " . json_encode($tokenresponse, JSON_PRETTY_PRINT) . "\n";
			    }
			    continue; // Skip this batch
			}

			if ($is_debug) {
			    echo "Batch $batch_index Koios returned " . count($tokenresponse) . " assets\n";
			}

			foreach ($tokenresponse as $tokenresponsedata) {
			    // Handle object or array access
			    $tokenresponsedata = (array)$tokenresponsedata;
			    $policy_id = $tokenresponsedata['policy_id'] ?? '';
			    $asset_name_ascii = $tokenresponsedata['asset_name_ascii'] ?? '';
			    $asset_name_hex = $tokenresponsedata['asset_name'] ?? '';

			    if ($is_debug) {
			        echo "Asset: policy_id=$policy_id, asset_name_ascii=$asset_name_ascii, asset_name_hex=$asset_name_hex\n";
			    }

			    if (empty($policy_id) || empty($asset_name_ascii)) {
			        error_log("get-nft-assets: Missing policy_id or asset_name_ascii for batch $batch_index");
			        if ($is_debug) {
			            echo "Error: Missing policy_id or asset_name_ascii\n";
			        }
			        continue;
			    }

			    $nft_metadata = null;
			    $is_cip68 = false;

			    // Try CIP-25 metadata
			    try {
			        if (isset($tokenresponsedata['minting_tx_metadata']['721'][$policy_id][$asset_name_ascii])) {
			            $nft_metadata = (object)$tokenresponsedata['minting_tx_metadata']['721'][$policy_id][$asset_name_ascii];
			            if ($is_debug) {
			                echo "Found CIP-25 metadata for $asset_name_ascii: " . json_encode($nft_metadata, JSON_PRETTY_PRINT) . "\n";
			            }
			        }
			    } catch (Exception $e) {
			        error_log("get-nft-assets: Error accessing CIP-25 metadata for $asset_name_ascii, policy_id=$policy_id: " . $e->getMessage());
			        if ($is_debug) {
			            echo "Error accessing CIP-25 metadata: " . $e->getMessage() . "\n";
			        }
			    }

			    // Try CIP-68 metadata if no CIP-25 metadata
			    if ($nft_metadata === null) {
			        try {
			            if (isset($tokenresponsedata['cip68_metadata']['100']['fields'][0]['map'])) {
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
			                            if ($is_debug) {
			                                echo "Error: Invalid hex in CIP-68 metadata for $asset_name_ascii\n";
			                            }
			                            continue 2; // Skip this asset
			                        }
			                        $nft_metadata[$key] = $value;
			                    }
			                }
			                if ($is_debug) {
			                    echo "Decoded CIP-68 metadata for $asset_name_ascii: " . json_encode($nft_metadata, JSON_PRETTY_PRINT) . "\n";
			                }
			            }
			        } catch (Exception $e) {
			            error_log("get-nft-assets: Error decoding CIP-68 metadata for $asset_name_ascii, policy_id=$policy_id: " . $e->getMessage());
			            if ($is_debug) {
			                echo "Error decoding CIP-68 metadata: " . $e->getMessage() . "\n";
			            }
			        }
			    }

			    // Skip if no metadata found
			    if ($nft_metadata === null) {
			        error_log("get-nft-assets: No metadata (CIP-25 or CIP-68) for asset_name_ascii=$asset_name_ascii, policy_id=$policy_id in batch $batch_index");
			        if ($is_debug) {
			            echo "No metadata for asset_name_ascii=$asset_name_ascii, policy_id=$policy_id\n";
			        }
			        continue;
			    }

			    // Ensure $nft_metadata is an object for consistency
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
			            if (isset($file->src) && strpos($file->src, 'ipfs://') === 0) {
			                $ipfs = str_replace('ipfs://', '', $file->src);
			                break;
			            }
			        }
			    }
			    if (empty($ipfs) && isset($nft_metadata->image)) {
			        $ipfs = str_replace('ipfs://', '', $nft_metadata->image);
			    }

			    if (empty($ipfs)) {
			        error_log("get-nft-assets: No IPFS for $name, policy_id=$policy_id in batch $batch_index");
			        if ($is_debug) {
			            echo "No IPFS for name=$name, policy_id=$policy_id\n";
			        }
			        continue;
			    }

			    // Calculate attributes (unchanged)
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
			    if ($is_debug) {
			        echo "Added NFT: name=$name, policy_id=$policy_id, is_cip68=$is_cip68, ipfs=$ipfs\n";
			    }
			}
        }

        if (!empty($final_array)) {
            error_log('get-nft-assets: Returning ' . count($final_array) . ' NFTs');
            if ($is_debug) {
                echo "Returning " . count($final_array) . " NFTs\n";
                echo json_encode($final_array, JSON_PRETTY_PRINT);
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
        error_log('get-nft-assets: No assets found in DB');
        if ($is_debug) {
            echo "No assets found in DB\n";
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