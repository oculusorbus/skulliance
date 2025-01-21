<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['project_id']){
	getMissions($conn, $quest_id=0, $_GET['project_id']);
}else{
	echo "No GET variables";
}

//echo json_encode($project);

// Close DB Connection
$conn->close();
?>