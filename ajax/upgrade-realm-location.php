<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['realm_id']) && isset($_GET['location_id']) && isset($_GET['duration']) && isset($_GET['cost']) && isset($_GET['project_id'])){
	// Need to double check duration and cost in case someone tries to manually override these variables in the JS function
	if(checkRealmLocationUpgrade($conn, $_GET['realm_id'], $_GET['location_id'])){
		echo "<br><strong>Existing Upgrade</strong><br>";
	}else{
		upgradeRealmLocation($conn, $_GET['realm_id'], $_GET['location_id'], $_GET['duration'], $_GET['cost'], $_GET['project_id']);
	}
	$status = getRealmLocationUpgrade($conn, $_GET['realm_id'], $_GET['location_id']);
	echo $status[$_GET['location_id']];
}else{
	echo "No Get Variables";
}

// Close DB Connection
$conn->close();
?>