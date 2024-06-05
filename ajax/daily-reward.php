<?php
include '../db.php';
include '../skulliance.php';

$project = array();
$project = getRandomReward($conn);
if(isset($project)){
	$project["remaining"] = getRewardTimeRemaining($conn);
}

echo json_encode($project);

// Close DB Connection
$conn->close();
?>