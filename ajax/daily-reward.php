<?php
include '../db.php';
include '../skulliance.php';

$project = array();
$project = getRandomReward($conn);
if(isset($project)){
	$project["remaining"] = getRewardTimeRemaining($conn);
	$project["progress_bar"] = getRewardProgressBar($conn);
	// Since the Daily Reward has been claimed, reset the Discord Message and Reation Status
	resetDiscordStatus($conn);
}

echo json_encode($project);

// Close DB Connection
$conn->close();
?>