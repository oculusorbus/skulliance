<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['project_id'])){
	if(checkMissionTotal($conn, $_GET['project_id'])){
		$_SESSION['userData']['project_id'] = $_GET['project_id'];
	}else{
		unset($_SESSION['userData']['project_id']);
	}
}else{
	echo "No Session";
}

// Close DB Connection
$conn->close();
?>