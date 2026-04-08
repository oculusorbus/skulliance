<?php
include '../db.php';
include '../webhooks.php';
include '../skulliance.php';

if(isset($_GET['defense_id']) && isset($_GET['duration'])){
	// Need to double check duration in case someone tries to manually override these variables in the JS function
	$realm_id = getRealmID($conn);
	if(checkMaxRaids($conn, $realm_id)){
		// Parse optional consumables[] param (array of ints, or 'all')
		$consumables = array();
		if(isset($_GET['consumables'])){
			$raw = $_GET['consumables'];
			if($raw === 'all'){
				$consumables = 'all';
			}else if(is_array($raw)){
				foreach($raw as $c){
					$c = intval($c);
					if($c >= 1 && $c <= 7) $consumables[] = $c;
				}
			}
		}
		// Require at least one soldier; cap at min(portal_level, 10)
		$soldier_ids = (isset($_GET['soldiers']) && is_array($_GET['soldiers'])) ? array_map('intval', $_GET['soldiers']) : array();
		if (empty($soldier_ids)) {
			echo "No soldiers selected. Enlist and train soldiers in your Barracks before raiding.";
			$conn->close(); exit;
		}
		$portal_level   = intval(getRealmLocationLevel($conn, $realm_id, 1));
		$max_soldiers   = min($portal_level, 10);
		if (count($soldier_ids) > $max_soldiers) {
			echo "Too many soldiers selected. Maximum " . $max_soldiers . " per raid.";
			$conn->close(); exit;
		}
		$raid_id = startRaid($conn, $_GET['defense_id'], $_GET['duration'], $consumables);
		if ($raid_id > 0) {
			commitRaidSoldiers($conn, $raid_id, $soldier_ids);
		}
		// Save consumable config to session if requested (only from modal start with save checkbox)
		if(isset($_GET['save_config']) && $_GET['save_config'] == '1'){
			$save_cids = array();
			if(isset($_GET['consumables']) && is_array($_GET['consumables'])){
				foreach($_GET['consumables'] as $c){
					$c = intval($c);
					if($c >= 1 && $c <= 7) $save_cids[] = $c;
				}
			}
			$_SESSION['raid_consumable_config'] = $save_cids;
		}
	}else{
		echo "Maximum Raids Reached.<br>Upgrade Portal to Increase Number of Raids.";
	}
}else{
	echo "No Get Variables";
}

// Close DB Connection
$conn->close();
?>