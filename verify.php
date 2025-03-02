<?php
include_once 'db.php';
include_once 'message.php';
include 'webhooks.php';

if(isset($argv)){
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
}
// Distinguish between a logged in user and verification cron job
if(isset($_GET['verify'])){
	set_time_limit(0);
	$addresses = array();
	
	// Temporarily altered this function, revert after testing is completed for Havoc Worlds
	$addresses = getAllAddresses($conn);
	
	// Add Havoc Worlds smart contract stake address
	$addresses[] = 'stake1uxg4ucl2m0j4d6ycuychm0dzl2ed4rr33h2q5w8u4yhwtwg3jdp34';
	
	$policies = array();
	$policies = getPolicies($conn);
	// Remove all user ids from NFTs before running cron job verification
	removeUsers($conn);
	// Get all NFT asset IDs to determine whether to update DB records, saves on DB resources instead of individual DB calls to check NFT presence
	$asset_ids = array();
	$asset_ids = getNFTAssetIDs($conn);
	// Verify all NFTs from wallets in the DB
	verifyNFTs($conn, $addresses, $policies, $asset_ids);
	// Get project percentages for Diamond Skull delegations
	$percentages = array();
	$percentages = getProjectDelegationPercentages($conn);
	// Determine whether Diamond Skulls should get a bonus
	$diamond_skull_bonus = getDiamondSkullBonus($percentages);
	// Deploy rewards for all users of the platform
	updateBalances($conn, $diamond_skull_bonus);
	// Deploy rewards for Diamond Skull delegation
	deployDiamondSkullRewards($conn, $percentages);
}

function verifyNFTs($conn, $addresses, $policies, $asset_ids, $nft_owners=array(), $attempts=0){
	global $blockfrost_project_id;
	
	$collections = getCollectionIDs($conn);
	$failed_addresses = array();
	$attempts++;
	
	$offsets = array();
	$offsets[1] = "";
	$offsets[2] = "offset=1000";
	$offset_flag = false;
	$message = "";
	
	foreach($offsets AS $i => $offset){
		if($i == 2){
			$offset_flag = true;
		}
		foreach($addresses AS $index => $address){
			if(isset($_SESSION['userData']['user_id'])){
				$user_id = $_SESSION['userData']['user_id'];
			}else{
				$user_id = getUserId($conn, $address);
			}
			// Run verification if first pass OR if stake address for dhp157 aka Davi on second pass, accommodates an extra batch for more than 1,000 UTXOs in a single wallet
			if($offset_flag == false || $address == "stake1u9h47jzelq38mk7yvaxklducf9uw7lhmfhwk4fm44wfdszsgqdmmz"){
				$ch = curl_init("https://api.koios.rest/api/v1/account_utxos?select=asset_list&asset_list=not.is.null".$offset);
				curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'accept: application/json', 'authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXlxc3p2dDhjazlmaGVtM3o2M2NqNXpkaGRxem53aGtuczVkeDc1YzNjcDB6Z3MwODR1OGoiLCJleHAiOjE3NjYzNzgxMjEsInRpZXIiOjEsInByb2pJRCI6IlNrdWxsaWFuY2UifQ.qS2b0FAm57dB_kddfrmtFWyHeQC27zz8JJl7qyz2dcI'));
				curl_setopt( $ch, CURLOPT_POST, 1);
				curl_setopt( $ch, CURLOPT_POSTFIELDS, '{"_stake_addresses":["'.$address.'"],"_extended":true}');
				curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
				curl_setopt( $ch, CURLOPT_HEADER, 0);
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
				
				// Verbose Debugging Parameters
				//curl_setopt( $ch, CURLOPT_VERBOSE, true);
				//$streamVerboseHandle = fopen('php://temp', 'w+');
				//curl_setopt( $ch, CURLOPT_STDERR, $streamVerboseHandle);

				$response = curl_exec( $ch );
				
				/* Verbose Debugging Response Handling
				if ($response === FALSE) {
				    printf("cUrl error (#%d): %s<br>\n",
				           curl_errno($ch),
				           htmlspecialchars(curl_error($ch)))
				           ;
				}

				rewind($streamVerboseHandle);
				$verboseLog = stream_get_contents($streamVerboseHandle);

				echo "cUrl verbose information:\n", 
				     "<pre>", htmlspecialchars($verboseLog), "</pre>\n";*/
				
				// If you need to debug, or find out why you can't send message uncomment line below, and execute script.
				$response = json_decode($response);
				//print_r($response[0]->asset_list);
				//exit;
				curl_close( $ch );

				//$_SESSION['userData']['nfts'] = array();
				if(is_array($response)){
			    if(isset($response[0])){
					$asset_names = array();
					$counter = 0;
					$asset_list = array();
					$asset_list["_asset_list"] = array();
					foreach($response AS $index => $list){
						foreach($list->asset_list AS $index => $token){
							if(in_array($token->policy_id, $policies)){
								$asset_list["_asset_list"][$counter] = array();
								$asset_list["_asset_list"][$counter][0] = $token->policy_id;
								$asset_list["_asset_list"][$counter][1] = $token->asset_name;
								$counter++;
					
							} // End if
						} // End foreach
					}
			
					// Batch asset list into arrays of 35 items or less to allow for successful queries, had to reduce from 50 to 35 to remain under the free Koios plan limits.
					$batch_asset_lists = array();
					$final_asset_lists = array();
					$batch_index = 0;
					if(count($asset_list["_asset_list"]) < 35){
						$final_asset_lists[$batch_index] = array();
						$final_asset_lists[$batch_index]["_asset_list"] = $asset_list["_asset_list"];
					}else{
						$batch_asset_lists = array_chunk($asset_list["_asset_list"], 35);
						foreach($batch_asset_lists AS $index => $batch_asset_list){
							$final_asset_lists[$index] = array();
							$final_asset_lists[$index]["_asset_list"] = $batch_asset_list;
						}
					}
					// Temporary counter, remove after testing
					$temp_counter = 0;
					foreach($final_asset_lists AS $final_asset_index => $final_asset_list){
						$temp_counter++;
						$tokench = curl_init("https://api.koios.rest/api/v1/asset_info");
						curl_setopt( $tokench, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXlxc3p2dDhjazlmaGVtM3o2M2NqNXpkaGRxem53aGtuczVkeDc1YzNjcDB6Z3MwODR1OGoiLCJleHAiOjE3NjYzNzgxMjEsInRpZXIiOjEsInByb2pJRCI6IlNrdWxsaWFuY2UifQ.qS2b0FAm57dB_kddfrmtFWyHeQC27zz8JJl7qyz2dcI'));
						curl_setopt( $tokench, CURLOPT_POST, 1);
						curl_setopt( $tokench, CURLOPT_POSTFIELDS, json_encode($final_asset_list));
						curl_setopt( $tokench, CURLOPT_FOLLOWLOCATION, 1);
						curl_setopt( $tokench, CURLOPT_HEADER, 0);
						curl_setopt( $tokench, CURLOPT_RETURNTRANSFER, 1);
			
						//$tokench = curl_init("https://api.koios.rest/api/v0/asset_info?_asset_policy=".$token->policy_id."&_asset_name=".$token->asset_name);
						//curl_setopt( $tokench, CURLOPT_RETURNTRANSFER, 1);
						$tokenresponse = curl_exec( $tokench );
						$tokenresponse = json_decode($tokenresponse);
						curl_close( $tokench );
			
						if(is_array($tokenresponse)){
							print_r($tokenresponse);
							if($temp_counter > 10){
								exit;
							}
							foreach($tokenresponse AS $index => $tokenresponsedata){
								
								// Prevent double creation or update of the same NFT for a specific user
								//if(!checkNFTOwner($conn, $tokenresponsedata->fingerprint, $user_id)){
								if(!in_array($user_id."-".$tokenresponsedata->fingerprint, $nft_owners)){
									// Check whether NFT already exists in the db. If so, just update it and don't fuck with cycling through NFT metadata that tends to randomly fail
									if(in_array($tokenresponsedata->fingerprint, $asset_ids)){
										// Check to see if there is an NFT with no owner in the database
										if(checkAvailableNFT($conn, $tokenresponsedata->fingerprint)){
											// Limit update to 1 record and only for NFTs with no current owner
											updateNFT($conn, $tokenresponsedata->fingerprint, $user_id);
											$nft_owners[] = $user_id."-".$tokenresponsedata->fingerprint;
										// If someone already has ownership, it's an RFT and we need to create a new entry for an additional owner
										}else{
											$payload = processNFTMetadata($conn, $tokenresponsedata, $address, $asset_ids, $nft_owners, $collections);
											$asset_ids = $payload["asset_ids"];
											$nft_owners = $payload["nft_owners"];
										}
									}else{
										$payload = processNFTMetadata($conn, $tokenresponsedata, $address, $asset_ids, $nft_owners, $collections);
										$asset_ids = $payload["asset_ids"];
										$nft_owners = $payload["nft_owners"];
									} // End if
								}
							} // End foreach
						}else{
							$message = "Bulk asset info could not be retrieved for stake address: ".$address." \r\n";
							$failed_addresses[] = $address;
							echo $message;
							print_r($tokenresponse);
							sendDM("772831523899965440", $message);
							exit();
						}
					} // End foreach
					//updateNFTs($conn, implode("', '", $asset_names));
				}else{
					$message = "There was no response data for stake address: ".$address." \r\n";
					$failed_addresses[] = $address;
					echo $message;
					print_r($response);
					sendDM("772831523899965440", $message);
					exit();
				}
				}else{
					$message = "There was no response for stake address: ".$address." \r\n";
					$failed_addresses[] = $address;
					echo $message;
					print_r($response);
					sendDM("772831523899965440", $message);
					exit();
				}
			} // Offset End if
		} // End foreach
	} // End offset foreach
	// This is not working for some reason. It keeps having unverified assets that mess up Diamond Skull delegation.
	/*
	if(!empty($failed_addresses)){
		if($attempts <= 3){
			verifyNFTs($conn, $failed_addresses, $policies, $asset_ids, $nft_owners, $attempts);
			echo "Attempt: ".$attempts." \r\n";
		}else{
			$message = "There were 3 verification attempts yet the following addresses continued to fail: \r\n";
			$message .= print_r($failed_addresses, true);
			echo $message;
			sendDM("772831523899965440", $message);
			exit();
		}
	}*/
}

function processNFTMetadata($conn, $tokenresponsedata, $address, $asset_ids, $nft_owners, $collections){
	global $blockfrost_project_id;
	$payload = array();
	// Handle creation of NFTs by cycling through NFT metadata
	if(isset($tokenresponsedata->minting_tx_metadata)){
		foreach($tokenresponsedata->minting_tx_metadata AS $metadata){
			$policy_id = $tokenresponsedata->policy_id;
			if(isset($tokenresponsedata->asset_name_ascii)){
				$asset_name = $tokenresponsedata->asset_name_ascii;
				if(isset($metadata->$policy_id)){
					$nft = $metadata->$policy_id;
					if(isset($nft)){
						$nft_data = $nft->$asset_name;
						if(isset($nft_data)){
							// Account for NFT with NaN value for asset name
							if($asset_name == "NaN"){
								$nft_data->AssetName = "DROPSHIP012";
							}else{
								$nft_data->AssetName = $asset_name;
							}
							// When name is not present, but Name is present, make name Name
							if(!isset($nft_data->name)){
								if(isset($nft_data->Name)){
									$nft_data->name = $nft_data->Name;
								}
							}
							if(isset($nft_data->AssetName) && isset($nft_data->name) && isset($nft_data->image) && isset($tokenresponsedata->fingerprint)){
								$payload = processNFT($conn, $policy_id, $nft_data->AssetName, $nft_data->name, $nft_data->image, $tokenresponsedata->fingerprint, $address, $asset_ids, $nft_owners, $collections);
								$asset_ids = $payload["asset_ids"];
								$nft_owners = $payload["nft_owners"];
							}else{
								//echo "NFT is missing an asset name, name, image, or fingerprint.";
							}
						}else{
							// Handles cases where the NFT data is empty for whatever reason, but the NFT still exists in the database and ownership needs to be assigned
							/* This is no longer needed because we're checking for updates above before determining whether processing NFT metadata is necessary to add new NFTs
							echo $asset_name." was missing NFT data, but was still updated in the db. \r\n";
							if(isset($_SESSION['userData']['user_id'])){
								$user_id = $_SESSION['userData']['user_id'];
							}else{
								$user_id = getUserId($conn, $address);
							}
							if(in_array($tokenresponsedata->fingerprint, $asset_ids)){
								updateNFT($conn, $tokenresponsedata->fingerprint, $user_id);
							}*/
						}
					}
				}
			}
		} // End foreach
	// Use Koios CIP-68 metadata
	}else if(isset($tokenresponsedata->cip68_metadata)){
		$traits = array();
		$alternate = "key";
		$key = "";
		$value = "";
		foreach($tokenresponsedata->cip68_metadata AS $metadata){
			foreach($metadata AS $fields){
				if(is_array($fields)){
					foreach($fields AS $maps){
						foreach($maps AS $map){
							if(is_array($map)){
								foreach($map AS $pairings){
									foreach($pairings AS $pairing){
										if(isset($pairing->bytes)){
											if($alternate == "key"){
												$key = hex2str($pairing->bytes);
												$alternate = "value";
											}else{
												$value = hex2str($pairing->bytes);
												$alternate = "key";
											}
										}
									}
									if($key != "" && $value != ""){
										$traits[$key] = $value;
									}
								}
							}
						}
					}
				}
			}
			if(isset($traits["name"]) && isset($traits["image"]) && isset($tokenresponsedata->fingerprint)){
				$payload = processNFT($conn, $tokenresponsedata->policy_id, $traits["name"], $traits["name"], $traits["image"], $tokenresponsedata->fingerprint, $address, $asset_ids, $nft_owners, $collections);
				$asset_ids = $payload["asset_ids"];
				$nft_owners = $payload["nft_owners"];
			}
		}
	// Fallback to Blockfrost for CIP68
	}else{
		$blockfrostch = curl_init("https://cardano-mainnet.blockfrost.io/api/v0/assets/".$tokenresponsedata->policy_id.$tokenresponsedata->asset_name);
		curl_setopt( $blockfrostch, CURLOPT_HTTPHEADER, array('Content-type: application/json', "project_id: ".$blockfrost_project_id));
		curl_setopt( $blockfrostch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $blockfrostch, CURLOPT_HEADER, 0);
		curl_setopt( $blockfrostch, CURLOPT_RETURNTRANSFER, 1);
		$blockfrostresponse = curl_exec( $blockfrostch );
		$blockfrostresponse = json_decode($blockfrostresponse);

		curl_close( $blockfrostch );

		if(is_object($blockfrostresponse)){
				$metadata = $blockfrostresponse->onchain_metadata;
				// Convert CIP68 asset name from hex to str and strip out extra b.s.
				$asset_name = clean(hex2str($blockfrostresponse->asset_name));
				$payload = processNFT($conn, $blockfrostresponse->policy_id, $asset_name , $metadata->name, $metadata->image, $blockfrostresponse->fingerprint, $address, $asset_ids, $nft_owners, $collections);
				$asset_ids = $payload["asset_ids"];
				$nft_owners = $payload["nft_owners"];
		}
	} // End if
	$payload["asset_ids"] = $asset_ids;
	$payload["nft_owners"] = $nft_owners;
	return $payload;
}

function sendDM($discord_id, $message){
	# Open the DM first
	$newDM = MakeRequest('/users/@me/channels', array("recipient_id" => $discord_id));
	# Check if DM is created, if yes, let's send a message to this channel.
	if(isset($newDM["id"])) {
	    $newMessage = MakeRequest("/channels/".$newDM["id"]."/messages", array("content" => $message));
	}
}

function processNFT($conn, $policy_id, $asset_name, $name, $image, $fingerprint, $address, $asset_ids, $nft_owners, $collections){
	if(isset($image)){
		// Dank Bit Fix
		if(is_array($image)){
			$image = $image[0].$image[1];
		}
		$ipfs = substr($image, 7, strlen($image));
	}else{
		$ipfs = "";
	}
	if(isset($_SESSION['userData']['user_id'])){
		$user_id = $_SESSION['userData']['user_id'];
	}else{
		$user_id = getUserId($conn, $address);
	}
	$last_id = 0;
	if(isset($name)){
		// Check if NFT already exists in the database or has been added during verification
		if(in_array($fingerprint, $asset_ids)){
			// Check to see if there is an NFT with no owner in the database
			if(checkAvailableNFT($conn, $fingerprint)){
				// Limit update to 1 record and only for NFTs with no current owner
				updateNFT($conn, $fingerprint, $user_id);
				$nft_owners[] = $user_id."-".$fingerprint;
			// If someone already has ownership, it's an RFT and we need to create a new entry for an additional owner
			}else{
				//$collection_id = getCollectionId($conn, $policy_id);
				$last_id = createNFT($conn, $fingerprint, $asset_name, $name, $ipfs, $collections[$policy_id], $user_id);
				$asset_ids[$last_id] = $fingerprint;
				$nft_owners[] = $user_id."-".$fingerprint;
			}
		}else{
			//$collection_id = getCollectionId($conn, $policy_id);
			$last_id = createNFT($conn, $fingerprint, $asset_name, $name, $ipfs, $collections[$policy_id], $user_id);
			$asset_ids[$last_id] = $fingerprint;
			$nft_owners[] = $user_id."-".$fingerprint;
		}
	}
	// Return altered asset ids to ensure new NFTs created are included in the array
	$payload = array();
	$payload["asset_ids"] = $asset_ids;
	$payload["nft_owners"] = $nft_owners;
	return $payload;
}

function hex2str($hex) {
    $str = '';
    for($i=0;$i<strlen($hex);$i+=2) $str .= chr(hexdec(substr($hex,$i,2)));
    return $str;
}

function clean($string) {
   $string = preg_replace('/[^A-Za-z0-9. -]/', '', $string); // Removes special chars.

   return $string;
}
?>