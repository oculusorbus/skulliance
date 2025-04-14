<?php
include '../db.php';
include '../skulliance.php';

if (isset($_SESSION['userData']['user_id'])) {
    // Get policy IDs from GET parameter
    if (!isset($_GET['policy_ids']) || empty(trim($_GET['policy_ids']))) {
        echo "false";
        exit;
    }

    $policy_ids = array_filter(array_map('trim', explode(',', $_GET['policy_ids'])));
    if (empty($policy_ids)) {
        echo "false";
        exit;
    }

    $asset_list = getNFTAssets($conn, $policy_ids);
    if (is_array($asset_list) && !empty($asset_list["_asset_list"])) {
        // Batch assets (35 per request)
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
            curl_setopt($tokench, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXlxc3p2dDhjazlmaGVtM3o2M2NqNXpkaGRxem53aGtuczVkeDc1YzNjcDB6Z3MwODR1OGoiLCJleHAiOjE3NjYzNzgxMjEsInRpZXIiOjEsInByb2pJRCI6IlNrdWxsaWFuY2UifQ.qS2b0FAm57dB_kddfrmtFWyHeQC27zz8JJl7qyz2dcI'));
            curl_setopt($tokench, CURLOPT_POST, 1);
            curl_setopt($tokench, CURLOPT_POSTFIELDS, json_encode($final_asset_list));
            curl_setopt($tokench, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($tokench, CURLOPT_HEADER, 0);
            curl_setopt($tokench, CURLOPT_RETURNTRANSFER, 1);

            $tokenresponse = curl_exec($tokench);
            if ($tokenresponse === false) {
                echo "false";
                exit;
            }

            $http_code = curl_getinfo($tokench, CURLINFO_HTTP_CODE);
            if ($http_code >= 400) {
                echo "false";
                exit;
            }

            $tokenresponse = json_decode($tokenresponse);
            curl_close($tokench);

            if (is_array($tokenresponse)) {
                foreach ($tokenresponse as $tokenresponsedata) {
                    $policy_id = $tokenresponsedata->policy_id;
                    $asset_name_ascii = $tokenresponsedata->asset_name_ascii;

                    if (isset($tokenresponsedata->minting_tx_metadata->{'721'}->{$policy_id}->{$asset_name_ascii})) {
                        $nft_metadata = $tokenresponsedata->minting_tx_metadata->{'721'}->{$policy_id}->{$asset_name_ascii};

                        if (isset($nft_metadata->name)) {
                            $name = $nft_metadata->name;

                            // Get IPFS URL
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

                            if (empty($ipfs) || empty($asset_name_ascii)) {
                                continue;
                            }

                            // Derive attributes from asset_name_ascii
                            $char_sum = 0;
                            $length = strlen($asset_name_ascii);
                            for ($i = 0; $i < $length; $i++) {
                                $char_sum += ord($asset_name_ascii[$i]);
                            }

                            // Strength: 1–8
                            $strength = ($char_sum % 8) + 1;

                            // Speed: 1–7
                            $speed_sum = 0;
                            for ($i = 0; $i < min(10, $length); $i++) {
                                $speed_sum += ord($asset_name_ascii[$i]);
                            }
                            $speed = ($speed_sum % 7) + 1;

                            // Tactics: 1–7
                            $tactics_sum = 0;
                            for ($i = max(0, $length - 10); $i < $length; $i++) {
                                $tactics_sum += ord($asset_name_ascii[$i]);
                            }
                            $tactics = ($tactics_sum % 7) + 1;

                            // Size: Small, Medium, Large
                            $size_map = ['Small', 'Medium', 'Large'];
                            $size = $size_map[$length % 3];

                            // Type: Base, Leader, Battle Damaged
                            $type_map = ['Base', 'Leader', 'Battle Damaged'];
                            $type = $type_map[ord($asset_name_ascii[0]) % 3];

                            // Powerup: Minor Regen, Regenerate, Boost Attack, Heal
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
                                'theme' => $_GET['theme'] ?? 'unknown'
                            ];
                        }
                    }
                }
            } else {
                echo "false";
                exit;
            }
        }

        if (!empty($final_array)) {
            echo json_encode($final_array, JSON_PRETTY_PRINT);
        } else {
            echo "false";
        }
    } else {
        echo "false";
    }
} else {
    echo "false";
}

$conn->close();
?>