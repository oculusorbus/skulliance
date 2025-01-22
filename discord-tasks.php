<?php
include 'db.php';

if(isset($_GET['discord_id'])){
	$status = getDiscordStatus($conn, $_GET['discord_id']);
	if(isset($_GET['message'])){
		if($status["message"] == 0){
			updateDiscordMessageStatus($conn, $_GET['discord_id'], 1);
		}
	}else if(isset($_GET['reaction'])){
		if($status["reaction"] == 0){
			updateDiscordReactionStatus($conn, $_GET['discord_id'], 1);
		}
	}
}

// Close DB Connection
$conn->close();
?>