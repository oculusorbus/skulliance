<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['mission_id']) && isset($_GET['quest_id'])){
	completeMission($conn, $mission_id, $quest_id);
}else{
	echo "No Session";
}

//echo json_encode($project);

// Close DB Connection
$conn->close();
?>