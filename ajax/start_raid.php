<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['destination_id']) && isset($_GET['duration'])){
	// Need to double check duration in case someone tries to manually override these variables in the JS function
	$realm_id = getRealmID($conn);
	startRaid($conn, $realm_id, $_GET['destination_id'], $_GET['duration']);
}else{
	echo "No Get Variables";
}

// Close DB Connection
$conn->close();
?>