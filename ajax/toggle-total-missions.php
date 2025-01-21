<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['visibility'])){
	$_SESSION['userData']['total_missions'] = $_GET['visibility'];
	if($_GET['visibility'] == "show"){
		getTotalMissions($conn);
	}
}else{
	echo "No Session";
}

// Close DB Connection
$conn->close();
?>