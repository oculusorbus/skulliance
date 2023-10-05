<?php
include_once 'db.php';

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
	// Verify all NFTs from wallets in the DB
	verifyNFTs($conn, $addresses, $policies);
	// Deploy rewards for all users of the platform
	updateBalances($conn);
}

function verifyNFTs($conn, $addresses, $policies){
	global $blockfrost_project_id;
	foreach($addresses AS $index => $address){
		$ch = curl_init("https://api.koios.rest/api/v0/account_assets");
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, '{"_stake_addresses":["'.$address.'"]}');
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
			foreach($response[0]->asset_list AS $index => $token){
				if(in_array($token->policy_id, $policies)){
					$asset_list["_asset_list"][$counter] = array();
					$asset_list["_asset_list"][$counter][0] = $token->policy_id;
					$asset_list["_asset_list"][$counter][1] = $token->asset_name;
					$counter++;
					
				} // End if
			} // End foreach
			
			$tokench = curl_init("https://api.koios.rest/api/v0/asset_info");
			curl_setopt( $tokench, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
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
					if(isset($tokenresponsedata->minting_tx_metadata)){
						foreach($tokenresponsedata->minting_tx_metadata AS $metadata){
							$policy_id = $tokenresponsedata->policy_id;
							if(isset($tokenresponsedata->asset_name_ascii)){
								$asset_name = $tokenresponsedata->asset_name_ascii;
								if(isset($metadata->$policy_id)){
									
									$nft = $metadata->$policy_id;
									$nft_data = $nft->$asset_name;
									// Account for NFT with NaN value for asset name
									if($asset_name == "NaN"){
										$nft_data->AssetName = "DROPSHIP012";
									}else{
										$nft_data->AssetName = $asset_name;
									}
									/*
									if(isset($nft_data->image)){
										$ipfs = substr($nft_data->image, 7, strlen($nft_data->image));
									}else{
										$ipfs = "";
									}
									if(isset($_SESSION['userData']['user_id'])){
										$user_id = $_SESSION['userData']['user_id'];
									}else{
										$user_id = getUserId($conn, $address);
									}
									if(isset($nft_data->name)){
										if(checkNFT($conn, $tokenresponsedata->fingerprint)){
											updateNFT($conn, $tokenresponsedata->fingerprint, $user_id);
										}else{
											$collection_id = getCollectionId($conn, $policy_id);
											createNFT($conn, $tokenresponsedata->fingerprint, $nft_data->AssetName, $nft_data->name, $ipfs, $collection_id, $user_id);
										}
									}*/
									if(isset($nft_data->name)){
										processNFT($conn, $policy_id, $nft_data->AssetName, $nft_data->name, $nft_data->image, $tokenresponsedata->fingerprint, $address);
									}
								}
							}
						} // End foreach
					// Empty Koios metadata, Use Blockfrost for CIP68
					}else{
						$blockfrostch = curl_init("https://cardano-mainnet.blockfrost.io/api/v0/assets/".$tokenresponsedata->policy_id.$tokenresponsedata->asset_name);
						echo $blockfrost_project_id;
						exit;
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
								processNFT($conn, $blockfrostresponse->policy_id, $asset_name , $metadata->name, $metadata->image, $blockfrostresponse->fingerprint, $address);
						}
					}
				} // End foreach
			}// End if
			//updateNFTs($conn, implode("', '", $asset_names));
		} // End if
		}
	}
}

function processNFT($conn, $policy_id, $asset_name, $name, $image, $fingerprint, $address){
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
		if(checkNFT($conn, $fingerprint)){
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
   $string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
   $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
   $string = str_replace('-', ' ', $string); // Replaces all hyphens with space.

   return $string;
}
?>