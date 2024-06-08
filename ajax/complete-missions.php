<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['mission_id']) && isset($_GET['quest_id'])){
	$mission_ids = array();
	$quest_ids = array();
	$mission_ids = explode(',', $_GET['mission_id']);
	$quest_ids = explode(',', $_GET['quest_id']);
	completeMission($conn, $_GET['mission_id'], $_GET['quest_id']);
}else{
	echo "No Session";
}

//echo json_encode($project);

// Close DB Connection
$conn->close();
?>