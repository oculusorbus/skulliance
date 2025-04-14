<?php
include '../db.php';
include '../skulliance.php';

if (isset($_SESSION['userData']['user_id'])) {
    if (!isset($_GET['policy_ids']) || empty(trim($_GET['policy_ids']))) {
        error_log('get-nft-assets: Missing policy_ids');
        echo "false";
        exit;
    }

    $policy_ids = array_filter(array_map('trim', explode(',', $_GET['policy_ids'])));
    if (empty($policy_ids)) {
        error_log('get-nft-assets: Empty policy_ids');
        echo "false";
        exit;
    }

    function getNFTAssets($conn, $policy_ids) {
        $asset_list = ["_asset_list" => []];
        $policy_placeholders = implode(',', array_fill(0, count($policy_ids), '?'));
        // Adjust query to your DB schema
        $query = "SELECT policy_id, asset_name FROM assets WHERE policy_id IN ($policy_placeholders) AND user_id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log('get-nft-assets: DB query prepare failed: ' . $conn->error);
            return ["_asset_list" => []];
        }
        $types = str_repeat('s', count($policy_ids)) . 's';
        $params = array_merge($policy_ids, [$_SESSION['userData']['user_id']]);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $asset_list["_asset_list"][] = [
                "policy_id" => $row['policy_id'],
                "asset_name" => bin2hex($row['asset_name']) // Hex format
            ];
        }

        $stmt->close();
        error_log('get-nft-assets: DB returned ' . count($asset_list["_asset_list"]) . ' assets');
        return $asset_list;
    }

    $asset_list = getNFTAssets($conn, $policy_ids);
    if (is_array($asset_list) && !empty($asset_list["_asset_list"])) {
        error_log('get-nft-assets: Processing ' . count($asset_list["_asset_list"]) . ' assets');
        $batch_asset_lists = array();
        $final_asset_lists = array();
        $batch_index = 0;
        if (count($asset_list["_asset_list"]) < 35) {
            $final_asset_lists[$batch_index] = array();
            $final_asset_lists[$batch_index]["_asset_list"] = $asset_list["_asset_list"];
        } else {
            $batch_asset_lists = array_chunk($asset_list["_asset_list"], 35);
            foreach ($batch_asset_lists as $index => $batch_asset_list) {
                $final_asset_lists[$index] = array();
                $final_asset_lists[$index]["_asset_list"] = $batch_asset_list;
            }
        }

        $final_array = [];
        foreach ($final_asset_lists as $final_asset_index => $final_asset_list) {
            $tokench = curl_init("https://api.koios.rest/api/v1/asset_info");
            curl_setopt($tokench, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'authorization: Bearer ***insert-your-token***'));
            curl_setopt($tokench, CURLOPT_POST, 1);
            curl_setopt($tokench, CURLOPT_POSTFIELDS, json_encode($final_asset_list));
            curl_setopt($tokench, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($tokench, CURLOPT_HEADER, 0);
            curl_setopt($tokench, CURLOPT_RETURNTRANSFER, 1);

            $tokenresponse = curl_exec($tokench);
            if ($tokenresponse === false) {
                error_log('get-nft-assets: cURL failed: ' . curl_error($tokench));
                echo "false";
                exit;
            }

            $http_code = curl_getinfo($tokench, CURLINFO_HTTP_CODE);
            if ($http_code >= 400) {
                error_log('get-nft-assets: HTTP error: ' . $http_code);
                echo "false";
                exit;
            }

            $tokenresponse = json_decode($tokenresponse);
            curl_close($tokench);

            if (is_array($tokenresponse)) {
                foreach ($tokenresponse as $tokenresponsedata) {
                    $policy_id = $tokenresponsedata->policy_id;
                    $asset_name_ascii = $tokenresponsedata->asset_name_ascii ?: '';
                    $asset_name_hex = $tokenresponsedata->asset_name ?: '';

                    if (empty($asset_name_ascii)) {
                        error_log('get-nft-assets: Empty asset_name_ascii for policy_id=' . $policy_id);
                        continue;
                    }

                    $metadata_key = $asset_name_ascii;
                    if (isset($tokenresponsedata->minting_tx_metadata->{'721'}->{$policy_id}->{$metadata_key})) {
                        $nft_metadata = $tokenresponsedata->minting_tx_metadata->{'721'}->{$policy_id}->{$metadata_key};

                        // Name fallback
                        $name = 'NFT Unknown';
                        if (isset($nft_metadata->name)) {
                            $name = $nft_metadata->name;
                        } elseif (isset($nft_metadata->title)) {
                            $name = $nft_metadata->title;
                        } elseif ($asset_name_ascii) {
                            $name = $asset_name_ascii;
                        }

                        // IPFS
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
                            continue;
                        }

                        // Derive attributes
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
                            'theme' => $_GET['theme'] ?? 'unknown'
                        ];
                    } else {
                        error_log('get-nft-assets: No metadata for asset_name_ascii=' . $asset_name_ascii . ', policy_id=' . $policy_id);
                    }
                }
            } else {
                error_log('get-nft-assets: Invalid Koios response');
                echo "false";
                exit;
            }
        }

        if (!empty($final_array)) {
            error_log('get-nft-assets: Returning ' . count($final_array) . ' NFTs');
            echo json_encode($final_array, JSON_PRETTY_PRINT);
        } else {
            error_log('get-nft-assets: No valid NFTs');
            echo "false";
        }
    } else {
        error_log('get-nft-assets: No assets found in DB');
        echo "false";
    }
} else {
    error_log('get-nft-assets: User not logged in');
    echo "false";
}

$conn->close();
?>