<?php
include 'db.php';

$addresses = array();
$addresses = getAllAddresses($conn);
$policies = array();
$policies = getPolicies($conn);
verifyNFTs($conn, $addresses, $policies);

function verifyNFTs($conn, $addresses, $policies, $user_id=0){
	foreach($addresses AS $index => $address){
		foreach($policies AS $index => $policy){
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
				foreach($response[0]->asset_list AS $index => $token){
					if($token->policy_id == $policy){
						$tokench = curl_init("https://api.koios.rest/api/v0/asset_info?_asset_policy=".$token->policy_id."&_asset_name=".$token->asset_name);
						curl_setopt( $tokench, CURLOPT_RETURNTRANSFER, 1);
						$tokenresponse = curl_exec( $tokench );
						$tokenresponse = json_decode($tokenresponse);
						curl_close( $tokench );
						if(is_array($tokenresponse)){
							foreach($tokenresponse[0]->minting_tx_metadata AS $metadata){
								$counter++;
								$policy_id = $token->policy_id;
								if(isset($tokenresponse[0]->asset_name_ascii)){
									$asset_name = $tokenresponse[0]->asset_name_ascii;
									if(isset($metadata->$policy_id)){
										$nft = $metadata->$policy_id;
										$nft_data = $nft->$asset_name;
										$ipfs = substr($nft_data->image, 7, strlen($nft_data->image));
										// Account for NFT with NaN value for asset name
										if($asset_name == "NaN"){
											$nft_data->AssetName = "DROPSHIP012";
										}else{
											$nft_data->AssetName = $asset_name;
										}
										//renderNFT($nft_data, $ipfs);
										/* Removing to rely on database now
										if($_SESSION['userData']['project_id'] != 1){
											$_SESSION['userData']['nfts'][] = $nft_data;
										}*/
										$asset_names[] = $nft_data->AssetName;
										$collection_id = getCollectionId($conn, $policy);
										if($user_id == 0){
											$user_id = getUserId($conn, $address);
										}
										if(isset($nft_data->name)){
											if(checkNFT($conn, $token->fingerprint)){
												updateNFT($conn, $token->fingerprint, $user_id);
											}else{
												createNFT($conn, $token->fingerprint, $nft_data->AssetName, $nft_data->name, $ipfs, $collection_id, $user_id);
											}
										}
									}
								}
							} // End foreach
						}// End if
					} // End if
				} // End foreach
				//updateNFTs($conn, implode("', '", $asset_names));
			} // End if
			}
		}
	}
}
?>