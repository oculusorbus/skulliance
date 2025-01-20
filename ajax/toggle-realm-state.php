<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['realm_id']) && isset($_GET['type'])){
	toggleRealmState($conn, $_GET['realm_id'], $_GET['type']);
	echo "Your Realm has been deactivated. You will not be able to reactivate it for 30 days.";
}else{
	echo "No Get Variables";
}

// Close DB Connection
$conn->close();
?>