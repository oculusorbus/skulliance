<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include dependencies
include '../db.php';
include '../skulliance.php';

// Set plain text for debug output
header('Content-Type: text/plain');

if (isset($_SESSION['userData']['user_id'])) {
    // Handle GET for manual testing, POST for game
    $policy_ids = [];
    $theme = 'unknown';
    if (isset($_GET['policyIds']) && !empty(trim($_GET['policyIds']))) {
        $policy_ids = array_filter(array_map('trim', explode(',', $_GET['policyIds'])));
        $theme = isset($_GET['theme']) ? trim($_GET['theme']) : 'unknown';
        echo "GET: policyIds=" . implode(',', $policy_ids) . ", theme=$theme\n";
    } elseif (isset($_POST['policyIds']) && is_array($_POST['policyIds'])) {
        $policy_ids = array_filter(array_map('trim', $_POST['policyIds']));
        $theme = isset($_POST['theme']) ? trim($_POST['theme']) : 'unknown';
        echo "POST: policyIds=" . implode(',', $policy_ids) . ", theme=$theme\n";
    } else {
        error_log('get-nft-assets: Missing policyIds');
        echo "Error: Missing or empty policyIds\n";
        echo json_encode(false);
        exit;
    }

    if (empty($policy_ids)) {
        error_log('get-nft-assets: Empty policyIds');
        echo "Error: Empty policyIds\n";
        echo json_encode(false);
        exit;
    }

    function getNFTAssets($conn, $policy_ids) {
        if (!isset($_SESSION['userData']['user_id'])) {
            error_log('get-nft-assets: No user_id in session');
            echo "Error: No user_id in session\n";
            return false;
        }

        $asset_list = ["_asset_list" => []];
        $policy_placeholders = implode(',', array_fill(0, count($policy_ids), '?'));
        $sql = "SELECT nfts.policy, nfts.asset_name FROM nfts WHERE user_id = ? AND policy IN ($policy_placeholders)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('get-nft-assets: DB query prepare failed: ' . $conn->error);
            echo "Error: DB query prepare failed: " . $conn->error . "\n";
            return false;
        }

        $types = 's' . str_repeat('s', count($policy_ids));
        $params = array_merge([$_SESSION['userData']['user_id']], $policy_ids);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $index = 0;
        while ($row = $result->fetch_assoc()) {
            $asset_list["_asset_list"][$index] = [
                "policy_id" => $row["policy"],
                "asset_name" => bin2hex($row["asset_name"])
            ];
            $index++;
        }

        $stmt->close();
        error_log('get-nft-assets: DB returned ' . $index . ' assets');
        echo "DB returned $index assets\n";
        return $asset_list;
    }

    $asset_list = getNFTAssets($conn, $policy_ids);
    if (is_array($asset_list) && !empty($asset_list["_asset_list"])) {
        error_log('get-nft-assets: Processing ' . count($asset_list["_asset_list"]) . ' assets');
        echo "Processing " . count($asset_list["_asset_list"]) . " assets\n";
        $batch_asset_lists = [];
        $final_asset_lists = [];
        $batch_index = 0;
        if (count($asset_list["_asset_list"]) < 35) {
            $final_asset_lists[$batch_index] = ["_asset_list" => $asset_list["_asset_list"]];
        } else {
            $batch_asset_lists = array_chunk($asset_list["_asset_list"], 35);
            foreach ($batch_asset_lists as $index => $batch_asset_list) {
                $final_asset_lists[$index] = ["_asset_list" => $batch_asset_list];
            }
        }

        $final_array = [];
        foreach ($final_asset_lists as $final_asset_index => $final_asset_list) {
            $tokench = curl_init("https://api.koios.rest/api/v1/asset_info");
            curl_setopt($tokench, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXlxc3p2dDhjazlmaGVtM3o2M2NqNXpkaGRxem53aGtuczVkeDc1YzNjcDB6Z3MwODR1OGoiLCJleHAiOjE3NjYzNzgxMjEsInRpZXIiOjEsInByb2pJRCI6IlNrdWxsaWFuY2UifQ.qS2b0FAm57dB_kddfrmtFWyHeQC27zz8JJl7qyz2dcI']);
            curl_setopt($tokench, CURLOPT_POST, 1);
            curl_setopt($tokench, CURLOPT_POSTFIELDS, json_encode($final_asset_list));
            curl_setopt($tokench, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($tokench, CURLOPT_HEADER, 0);
            curl_setopt($tokench, CURLOPT_RETURNTRANSFER, 1);

            $tokenresponse = curl_exec($tokench);
            if ($tokenresponse === false) {
                error_log('get-nft-assets: cURL failed: ' . curl_error($tokench));
                echo "Error: cURL failed: " . curl_error($tokench) . "\n";
                echo json_encode(false);
                exit;
            }

            $http_code = curl_getinfo($tokench, CURLINFO_HTTP_CODE);
            if ($http_code >= 400) {
                error_log('get-nft-assets: HTTP error: ' . $http_code);
                echo "Error: HTTP error: $http_code\n";
                echo json_encode(false);
                exit;
            }

            $tokenresponse = json_decode($tokenresponse);
            curl_close($tokench);

            if (is_array($tokenresponse)) {
                echo "Koios returned " . count($tokenresponse) . " assets\n";
                foreach ($tokenresponse as $tokenresponsedata) {
                    $policy_id = $tokenresponsedata->policy_id;
                    $asset_name_ascii = $tokenresponsedata->asset_name_ascii ?: '';
                    $asset_name_hex = $tokenresponsedata->asset_name ?: '';

                    echo "Asset: policy_id=$policy_id, asset_name_ascii=$asset_name_ascii\n";

                    if (empty($asset_name_ascii)) {
                        error_log('get-nft-assets: Empty asset_name_ascii for policy_id=' . $policy_id);
                        echo "No asset_name_ascii for policy_id=$policy_id\n";
                        continue;
                    }

                    $metadata_key = $asset_name_ascii;
                    if (isset($tokenresponsedata->minting_tx_metadata->{'721'}->{$policy_id}->{$metadata_key})) {
                        $nft_metadata = $tokenresponsedata->minting_tx_metadata->{'721'}->{$policy_id}->{$metadata_key};

                        $name = 'NFT Unknown';
                        if (isset($nft_metadata->name)) {
                            $name = $nft_metadata->name;
                        } elseif (isset($nft_metadata->title)) {
                            $name = $nft_metadata->title;
                        } elseif ($asset_name_ascii) {
                            $name = $asset_name_ascii;
                        }

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
                            error_log('get-nft-assets: No IPFS for ' . $name . ', policy_id=' . $policy_id);
                            echo "No IPFS for name=$name, policy_id=$policy_id\n";
                            continue;
                        }

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
                        $type = $type_map[ord($asset_name_ascii[0]) % 3];
                        $powerup_map = ['Minor Regen', 'Regenerate', 'Boost Attack', 'Heal'];
                        $powerup = $powerup_map[ord($asset_name_ascii[$length - 1]) % 4];

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
                            'theme' => $_POST['theme'] ?? ($_GET['theme'] ?? 'unknown')
                        ];
                        echo "Added NFT: name=$name, policy_id=$policy_id\n";
                    } else {
                        error_log('get-nft-assets: No metadata for asset_name_ascii=' . $asset_name_ascii . ', policy_id=' . $policy_id);
                        echo "No metadata for asset_name_ascii=$asset_name_ascii, policy_id=$policy_id\n";
                    }
                }
            } else {
                error_log('get-nft-assets: Invalid Koios response');
                echo "Error: Invalid Koios response\n";
                echo json_encode(false);
                exit;
            }
        }

        if (!empty($final_array)) {
            error_log('get-nft-assets: Returning ' . count($final_array) . ' NFTs');
            echo "Returning " . count($final_array) . " NFTs\n";
            echo json_encode($final_array, JSON_PRETTY_PRINT);
        } else {
            error_log('get-nft-assets: No valid NFTs');
            echo "No valid NFTs\n";
            echo json_encode(false);
        }
    } else {
        error_log('get-nft-assets: No assets found in DB');
        echo "No assets found in DB\n";
        echo json_encode(false);
    }
} else {
    error_log('get-nft-assets: User not logged in');
    echo "Error: User not logged in\n";
    echo json_encode(false);
}

$conn->close();
?>