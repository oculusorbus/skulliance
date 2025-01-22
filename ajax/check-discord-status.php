<?php
include '../db.php';
include '../skulliance.php';

$status = array();
$status = getDiscordStatus($conn, getDiscordID($conn));

if($status['message'] == 1 && $status['reaction'] == 1){
	echo "true";
}else{
	echo "false";
}

// Close DB Connection
$conn->close();
?>