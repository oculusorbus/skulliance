<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['mission_id']) && isset($_GET['quest_id'])){
	retreatMission($_GET['mission_id'], $_GET['quest_id']);
}else{
	echo "No Variables";
}

//echo json_encode($project);

// Close DB Connection
$conn->close();
?>