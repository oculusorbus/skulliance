<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['defense_id']) && isset($_GET['duration'])){
	// Need to double check duration in case someone tries to manually override these variables in the JS function
	$realm_id = getRealmID($conn);
	if(checkMaxRaids($conn, $realm_id)){
		startRaid($conn, $_GET['defense_id'], $_GET['duration']);
	}else{
		echo "Maximum Raids Reached.<br>Upgrade Portal to Increase Number of Raids.";
	}
}else{
	echo "No Get Variables";
}

// Close DB Connection
$conn->close();
?>