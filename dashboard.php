<?php
include 'db.php';
include 'skulliance.php';
//include 'webhooks.php';
include 'header.php';

// Handle wallet selection
if(isset($_POST['address'])){
	checkAddress($conn, $_POST['address']);
}

$addresses = array();
$addresses = getAddresses($conn);
$policies = array();
$policies = getPolicies($conn);

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
					if(isset($tokenresponse[0])){
						foreach($tokenresponse[0]->minting_tx_metadata AS $metadata){
							$counter++;
							$policy_id = $token->policy_id;
							$asset_name = $tokenresponse[0]->asset_name_ascii;
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
							if(checkNFT($conn, $token->fingerprint)){
								// update NFT
							}else{
								createNFT($conn, $token->fingerprint, $nft_data->AssetName, $nft_data->name, $ipfs, $collection_id);
							}
						} // End foreach
					}// End if
				} // End if
			} // End foreach
			//updateNFTs($conn, implode("', '", $asset_names));
		} // End if
	}
}
?>

<a name="dashboard" id="dashboard"></a>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <div class="main">
    <div class="content">
	
    </div>
  </div>
  <div class="side">
		<h2>Skulliance Staking</h2>
		<div class="content" id="player-stats">
			<ul>
				<div class="wallet-connect">
				<li class="role"><img class="icon" src="icons/wallet.png"/>
					<label for="wallets"><strong>Connect</strong>&nbsp;</label>
					<select onchange="javascript:connectWallet(this.options[this.selectedIndex].value);" name="wallets" id="wallets">
						<option value="none">Wallet</option>
					</select>
					<form id="addressForm" action="dashboard.php" method="post">
					  <input type="hidden" id="wallet" name="wallet" value="">	
					  <input type="hidden" id="address" name="address" value="">
					  <input type="submit" value="Submit" style="display:none;">
					</form>
				</li>
				</div>
			</ul>
		</div>
  </div>
</div>

	<!-- Footer -->
	<div class="footer">
	  <p>Skulliance<br>Copyright © <span id="year"></span>
	</div>
</div>
</div>
</body>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>