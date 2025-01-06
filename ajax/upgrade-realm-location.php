<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['realm_id']) && isset($_GET['location_id']) && isset($_GET['duration'])){
	upgradeRealmLocation($conn, $_GET['realm_id'], $_GET['location_id'], $_GET['duration']);
	getRealmLocationUpgrade($conn, $_GET['realm_id'], $_GET['location_id']);
	echo $status[$_GET['location_id']];
}else{
	echo "No Get Variables";
}

// Close DB Connection
$conn->close();
?>