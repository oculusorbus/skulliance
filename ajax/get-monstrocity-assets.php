<?php
include '../db.php';

// This is a JSON API: PHP notices/warnings must NEVER leak into the body.
// db.php sets display_errors=1, so a single notice (e.g. a Monstrocity NFT
// whose Koios metadata is missing a stat field) would print before/inside
// the JSON, making the client's response.json() throw - which lands in the
// catch block and silently shows the 14-character fallback roster despite
// the player owning NFTs. Force clean output; errors still go to the log.
ini_set('display_errors', '0');
header('Content-Type: application/json');
$sp_debug = isset($_GET['debug']) && $_GET['debug'] === '1';
$sp_diag = array('stage' => 'start');

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

// Default roster shown to logged-out visitors: all 14 base Monstrocity
// characters, stat-balanced so no single pick dominates. Each totals ~12
// "power" — stat-sum = 12 minus a powerup cost (Minor Regen 0, Regenerate 1,
// Boost Attack/Heal 2), centred on Craig's 4/4/4 (Craig gets +1 STR as the
// flagship). Size carries its own HP/Tactics tradeoff in-engine, so personality
// is leaned into it. Keep in sync with the JS fallback in monstrocity.php and
// the Monstrocity page in the Skull Paper.
$default_characters = array(
	array('name' => 'Craig',             'strength' => 5, 'speed' => 4, 'tactics' => 4, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Minor Regen'),
	array('name' => 'Merdock',           'strength' => 5, 'speed' => 3, 'tactics' => 4, 'size' => 'Large',  'type' => 'Base', 'powerup' => 'Minor Regen'),
	array('name' => 'Goblin Ganger',     'strength' => 3, 'speed' => 5, 'tactics' => 4, 'size' => 'Small',  'type' => 'Base', 'powerup' => 'Minor Regen'),
	array('name' => 'Texby',             'strength' => 3, 'speed' => 4, 'tactics' => 5, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Minor Regen'),
	array('name' => 'Mandiblus',         'strength' => 5, 'speed' => 3, 'tactics' => 3, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Regenerate'),
	array('name' => 'Koipon',            'strength' => 3, 'speed' => 3, 'tactics' => 5, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Regenerate'),
	array('name' => 'Slime Mind',        'strength' => 3, 'speed' => 4, 'tactics' => 4, 'size' => 'Small',  'type' => 'Base', 'powerup' => 'Regenerate'),
	array('name' => 'Billandar and Ted', 'strength' => 4, 'speed' => 4, 'tactics' => 3, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Regenerate'),
	array('name' => 'Dankle',            'strength' => 5, 'speed' => 3, 'tactics' => 2, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Boost Attack'),
	array('name' => 'Jarhead',           'strength' => 4, 'speed' => 4, 'tactics' => 2, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Boost Attack'),
	array('name' => 'Spydrax',           'strength' => 3, 'speed' => 5, 'tactics' => 2, 'size' => 'Small',  'type' => 'Base', 'powerup' => 'Heal'),
	array('name' => 'Katastrophy',       'strength' => 4, 'speed' => 2, 'tactics' => 4, 'size' => 'Large',  'type' => 'Base', 'powerup' => 'Heal'),
	array('name' => 'Ouchie',            'strength' => 5, 'speed' => 3, 'tactics' => 2, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Heal'),
	array('name' => 'Drake',             'strength' => 3, 'speed' => 4, 'tactics' => 3, 'size' => 'Medium', 'type' => 'Base', 'powerup' => 'Heal'),
);

if(isset($_SESSION['userData']['user_id'])){
	$asset_list = getMonstrocityAssets($conn);
	$sp_diag['logged_in'] = true;
	$sp_diag['user_id'] = (int)$_SESSION['userData']['user_id'];
	$sp_diag['asset_list_is_array'] = is_array($asset_list);
	$sp_diag['nft_count'] = is_array($asset_list) ? count($asset_list['_asset_list']) : 0;
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

		// Fire all batch requests CONCURRENTLY via curl_multi. Sequential
		// batches took ~2-4s EACH, so collectors with 35+ NFTs (2+ batches)
		// blew past the client's fetch timeout and were dropped to the
		// default roster despite owning NFTs. Concurrent total time is
		// roughly one batch's latency regardless of collection size.
		$koios_bearer_token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXhybHB1d2R4MjN4bGRhM3hkOG40NnR3cW0zano5Y3hkNGYyazJoaDhzNGUwMGN3ZmFnNHUiLCJleHAiOjE3OTc5NjAyODEsInRpZXIiOjEsInByb2pJRCI6InNrdWxsaWFuY2UifQ.JWfVIQGU6SH0p7BpyzqV931Em8nz_eKkVbheIGzLShg'; // renewed Dec 2025 (exp Dec 2026) - keep in sync with db.php / verify.php
		$mh = curl_multi_init();
		$handles = array();
		foreach($final_asset_lists AS $final_asset_index => $final_asset_list){
			$tokench = curl_init("https://api.koios.rest/api/v1/asset_info");
			curl_setopt( $tokench, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'authorization: Bearer '.$koios_bearer_token));
			curl_setopt( $tokench, CURLOPT_POST, 1);
			curl_setopt( $tokench, CURLOPT_POSTFIELDS, json_encode($final_asset_list));
			curl_setopt( $tokench, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt( $tokench, CURLOPT_HEADER, 0);
			curl_setopt( $tokench, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt( $tokench, CURLOPT_TIMEOUT, 20);
			curl_multi_add_handle($mh, $tokench);
			$handles[$final_asset_index] = $tokench;
		}
		do {
			$mstatus = curl_multi_exec($mh, $mactive);
			if ($mactive) { curl_multi_select($mh); }
		} while ($mactive && $mstatus == CURLM_OK);

		$sp_diag['koios_http'] = array();
		foreach($handles AS $handle_index => $tokench){
			$tokenresponse = curl_multi_getcontent($tokench);
			$http_code = curl_getinfo($tokench, CURLINFO_HTTP_CODE);
			$sp_diag['koios_http'][] = $http_code;
			curl_multi_remove_handle($mh, $tokench);
			curl_close( $tokench );

			if ($tokenresponse === false || $tokenresponse === '' || $http_code >= 400) {
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
		curl_multi_close($mh);

		$sp_diag['parsed_characters'] = count($final_array);
		$sp_diag['any_batch_failed'] = $batch_failed;
		$sp_diag['returning'] = !empty($final_array) ? 'nfts' : 'defaults';
		if($sp_debug){ echo json_encode($sp_diag, JSON_PRETTY_PRINT); $conn->close(); exit; }

		// One JSON document, always. Partial results beat fallbacks when
		// only some batches failed; the default roster only when nothing
		// came back at all (the old "false" output parsed client-side into
		// a single broken [false] character).
		if(!empty($final_array)){
			echo json_encode($final_array, JSON_PRETTY_PRINT);
		}else{
			echo json_encode($default_characters, JSON_PRETTY_PRINT);
		}
	}else{
		// Logged in but no Monstrocity NFTs in the DB: default roster.
		$sp_diag['returning'] = 'defaults (no monstrocity NFTs in DB)';
		if($sp_debug){ echo json_encode($sp_diag, JSON_PRETTY_PRINT); $conn->close(); exit; }
		echo json_encode($default_characters, JSON_PRETTY_PRINT);
	}
}else{
	// Logged-out visitors get the default roster immediately as JSON — no login
	// gate, no redirect, no blockchain call — so the character-select screen
	// paints instantly instead of waiting on the client's 5s timeout fallback.
	$sp_diag['logged_in'] = false;
	$sp_diag['returning'] = 'defaults (not logged in)';
	if($sp_debug){ echo json_encode($sp_diag, JSON_PRETTY_PRINT); $conn->close(); exit; }
	echo json_encode($default_characters, JSON_PRETTY_PRINT);
}

//echo json_encode($project);

// Close DB Connection
$conn->close();
?>