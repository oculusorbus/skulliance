<?php
include_once 'db.php';
include 'webhooks.php';

if(isset($argv)){
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
}
// Distinguish between a logged in user and verification cron job
if(isset($_GET['verify'])){
	set_time_limit(0);
	$addresses = array();
	$addresses = getAllAddresses($conn);
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

function verifyNFTs($conn, $addresses, $policies, $asset_ids){
	global $blockfrost_project_id;
	
	$offsets = array();
	$offsets[1] = "";
	$offsets[2] = "offset=1000";
	$offset_flag = false;
	
	foreach($offsets AS $i => $offset){
		if($i == 2){
			$offset_flag = true;
		}
	foreach($addresses AS $index => $address){
		// Run verification if first pass OR if stake address for dhp157 aka Davi on second pass, accommodates an extra batch for more than 1,000 UTXOs in a single wallet
		if($offset_flag == false || $address == "stake1u9h47jzelq38mk7yvaxklducf9uw7lhmfhwk4fm44wfdszsgqdmmz"){
		$ch = curl_init("https://api.koios.rest/api/v1/account_utxos?select=asset_list&asset_list=not.is.null".$offset);
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'accept: application/json', 'authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXlxc3p2dDhjazlmaGVtM3o2M2NqNXpkaGRxem53aGtuczVkeDc1YzNjcDB6Z3MwODR1OGoiLCJleHAiOjE3MzQ3MDc5OTUsInRpZXIiOjEsInByb2pJRCI6InNrdWxsaWFuY2UifQ.eYZU74nwkN_qD8uK0UIv9VLveZLXMfJHznvzPWmnrq0'));
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, '{"_stake_addresses":["'.$address.'"],"_extended":true}');
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

		$response = curl_exec( $ch );
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
			
			$tokench = curl_init("https://api.koios.rest/api/v1/asset_info");
			curl_setopt( $tokench, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXlxc3p2dDhjazlmaGVtM3o2M2NqNXpkaGRxem53aGtuczVkeDc1YzNjcDB6Z3MwODR1OGoiLCJleHAiOjE3MzQ3MDc5OTUsInRpZXIiOjEsInByb2pJRCI6InNrdWxsaWFuY2UifQ.eYZU74nwkN_qD8uK0UIv9VLveZLXMfJHznvzPWmnrq0'));
			curl_setopt( $tokench, CURLOPT_POST, 1);
			curl_setopt( $tokench, CURLOPT_POSTFIELDS, json_encode($asset_list));
			curl_setopt( $tokench, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt( $tokench, CURLOPT_HEADER, 0);
			curl_setopt( $tokench, CURLOPT_RETURNTRANSFER, 1);
			
			//$tokench = curl_init("https://api.koios.rest/api/v0/asset_info?_asset_policy=".$token->policy_id."&_asset_name=".$token->asset_name);
			//curl_setopt( $tokench, CURLOPT_RETURNTRANSFER, 1);
			$tokenresponse = curl_exec( $tokench );
			$tokenresponse = json_decode($tokenresponse);
			curl_close( $tokench );
			
			if(is_array($tokenresponse)){
				foreach($tokenresponse AS $index => $tokenresponsedata){
					// Check whether NFT already exists in the db. If so, just update it and don't fuck with cycling through NFT metadata that tends to randomly fail
					if(in_array($tokenresponsedata->fingerprint, $asset_ids)){
						if(isset($_SESSION['userData']['user_id'])){
							$user_id = $_SESSION['userData']['user_id'];
						}else{
							$user_id = getUserId($conn, $address);
						}
						updateNFT($conn, $tokenresponsedata->fingerprint, $user_id);
					}else{
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
												if(isset($nft_data->AssetName) && isset($nft_data->name) && isset($nft_data->image) && isset($tokenresponsedata->fingerprint)){
													processNFT($conn, $policy_id, $nft_data->AssetName, $nft_data->name, $nft_data->image, $tokenresponsedata->fingerprint, $address, $asset_ids);
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
							//foreach($tokenresponsedata->cip68_metadata->222->fields[0]->map[0] AS $metadata){
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
												}else{
													echo "Map: ".$map; 
												}
											}
										}
									}
								}
								echo $tokenresponsedata->policy_id."<br>";
								print_r($traits);
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
									processNFT($conn, $blockfrostresponse->policy_id, $asset_name , $metadata->name, $metadata->image, $blockfrostresponse->fingerprint, $address, $asset_ids);
							}
						} // End if
					} // End if
				} // End foreach
			}// End if
			//updateNFTs($conn, implode("', '", $asset_names));
		}else{
			echo "There was no response data for stake address: ".$address." \r\n";
			print_r($response);
		}
		}else{
			echo "There was no response for stake address: ".$address." \r\n";
		}
		} // Offset End if
	} // End foreach
	} // End offset foreach
}

function processNFT($conn, $policy_id, $asset_name, $name, $image, $fingerprint, $address, $asset_ids){
	if(isset($image)){
		$ipfs = substr($image, 7, strlen($image));
	}else{
		$ipfs = "";
	}
	if(isset($_SESSION['userData']['user_id'])){
		$user_id = $_SESSION['userData']['user_id'];
	}else{
		$user_id = getUserId($conn, $address);
	}
	if(isset($name)){
		if(in_array($fingerprint, $asset_ids)){
			updateNFT($conn, $fingerprint, $user_id);
		}else{
			$collection_id = getCollectionId($conn, $policy_id);
			createNFT($conn, $fingerprint, $asset_name, $name, $ipfs, $collection_id, $user_id);
		}
	}
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