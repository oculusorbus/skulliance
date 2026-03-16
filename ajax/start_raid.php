<?php
include '../db.php';
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
		startRaid($conn, $_GET['defense_id'], $_GET['duration'], $consumables);
	}else{
		echo "Maximum Raids Reached.<br>Upgrade Portal to Increase Number of Raids.";
	}
}else{
	echo "No Get Variables";
}

// Close DB Connection
$conn->close();
?>