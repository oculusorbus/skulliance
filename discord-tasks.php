<?php
include '../db.php';

if(isset($_GET['discord_id'])){
	if(isset($_GET['message'])){
		updateDiscordMessageStatus($conn, $_GET['discord_id'], $status)
	}else if(isset($_GET['reaction']){
		updateDiscordReactionStatus($conn, $_GET['discord_id'], $status)
	}
}

// Close DB Connection
$conn->close();
?>