<?php
include '../db.php';

// Lightweight session restore — intentionally do NOT include skulliance.php.
// That file is the login gate: for anonymous / edge sessions it issues header()
// redirects (error.php, or the external discord.gg) and runs membership
// bootstrap. Including it in this public data endpoint made the logged-out
// character-select fetch get redirected instead of receiving JSON, so the
// client's 5-second timeout fired and only THEN showed the default characters.
// We only need the session to know whether the visitor is logged in. (Same
// lightweight pattern skullpaper.php uses to stay public without the gate.)
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$cookie = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($cookie)) { $_SESSION = $cookie; }
}

// Default roster shown to logged-out visitors. Mirrors the client-side fallback
// in monstrocity.php so the select screen is identical either way.
$default_characters = array(
	array('name' => 'Craig',  'strength' => 4, 'speed' => 4, 'tactics' => 4, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Regenerate'),
	array('name' => 'Dankle', 'strength' => 3, 'speed' => 5, 'tactics' => 3, 'size' => 'Small',  'type' => 'Base', 'powerup' => 'Heal'),
);

if(isset($_SESSION['userData']['user_id'])){
	$asset_list = getMonstrocityAssets($conn);
	if(is_array($asset_list)){
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
		// Define the powerup mapping
		$powerup_mapping = [
		    'bloody' => 'Heal',
		    'cardano' => 'Boost Attack',
		    'ada' => 'Regenerate',
		    'none' => 'Minor Regen',
		];

		// Accumulate character configurations across ALL batches, then emit
		// exactly one JSON document at the end. The previous version echoed
		// one array per batch (invalid concatenated JSON for users with >35
		// NFTs) and could echo "false" twice on errors, which broke the
		// client's response.json() and dropped players to default characters.
		$final_array = [];
		$batch_failed = false;

		foreach($final_asset_lists AS $final_asset_index => $final_asset_list){
			$tokench = curl_init("https://api.koios.rest/api/v1/asset_info");
			// Koios bearer token renewed Dec 2025 (exp Dec 2026) - keep in
			// sync with the copies in db.php and verify.php. The old token
			// here expired 2025-12-22 and every query 401'd.
			curl_setopt( $tokench, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXhybHB1d2R4MjN4bGRhM3hkOG40NnR3cW0zano5Y3hkNGYyazJoaDhzNGUwMGN3ZmFnNHUiLCJleHAiOjE3OTc5NjAyODEsInRpZXIiOjEsInByb2pJRCI6InNrdWxsaWFuY2UifQ.JWfVIQGU6SH0p7BpyzqV931Em8nz_eKkVbheIGzLShg'));
			curl_setopt( $tokench, CURLOPT_POST, 1);
			curl_setopt( $tokench, CURLOPT_POSTFIELDS, json_encode($final_asset_list));
			curl_setopt( $tokench, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt( $tokench, CURLOPT_HEADER, 0);
			curl_setopt( $tokench, CURLOPT_RETURNTRANSFER, 1);

			$tokenresponse = curl_exec( $tokench );
			$http_code = curl_getinfo($tokench, CURLINFO_HTTP_CODE);
			curl_close( $tokench );

			if ($tokenresponse === false || $http_code >= 400) {
				$batch_failed = true;
				continue;
			}

			$tokenresponse = json_decode($tokenresponse);
			if(!is_array($tokenresponse)){
				$batch_failed = true;
				continue;
			}

			// Loop through each NFT in the token response
			foreach ($tokenresponse as $tokenresponsedata) {
			    // Extract policy ID and asset name
			    $policy_id = $tokenresponsedata->policy_id;
			    $asset_name_ascii = $tokenresponsedata->asset_name_ascii;

			    // Check if metadata exists for this NFT
			    if (isset($tokenresponsedata->minting_tx_metadata->{'721'}->{$policy_id}->{$asset_name_ascii})) {
			        $nft_metadata = $tokenresponsedata->minting_tx_metadata->{'721'}->{$policy_id}->{$asset_name_ascii};

			        // Ensure character alias and attributes are present
			        if (isset($nft_metadata->character->alias) && isset($nft_metadata->attributes)) {
			            $alias = $nft_metadata->character->alias;
			            $attributes = $nft_metadata->attributes;

			            // Capitalize size and type
			            $size = ucfirst($attributes->size);
			            $type = ucfirst($attributes->type);

			            // Map the powerup value
			            $powerup_raw = $attributes->powerup;
			            $powerup = $powerup_mapping[$powerup_raw] ?? $powerup_raw; // Fallback to original if not in mapping

			            // Build the character configuration
			            $final_array[] = [
			                'name' => $alias,
			                'strength' => $attributes->strength,
			                'speed' => $attributes->speed,
			                'tactics' => $attributes->tactics,
			                'size' => $size,
			                'type' => $type,
			                'powerup' => $powerup,
			            ];
			        }
			    }
			}
		} // End foreach

		// One JSON document, always. Partial results beat "false" when only
		// some batches failed; "false" only when nothing came back at all.
		if(!empty($final_array)){
			echo json_encode($final_array, JSON_PRETTY_PRINT);
		}else{
			//echo "Bulk asset info could not be retrieved.";
			echo "false";
		}
	}else{
		//echo "You do not have any Monstrocity NFTs";
		echo "false";
	}
}else{
	// Logged-out visitors get the default roster immediately as JSON — no login
	// gate, no redirect, no blockchain call — so the character-select screen
	// paints instantly instead of waiting on the client's 5s timeout fallback.
	echo json_encode($default_characters, JSON_PRETTY_PRINT);
}

//echo json_encode($project);

// Close DB Connection
$conn->close();
?>