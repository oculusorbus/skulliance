<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['realm_id']) && isset($_GET['type'])){
	toggleRealmState($conn, $_GET['realm_id'], $_GET['type']);
}else{
	echo "No Get Variables";
}

// Close DB Connection
$conn->close();
?>