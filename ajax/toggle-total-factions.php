<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['visibility'])){
	$_SESSION['userData']['total_factions'] = $_GET['visibility'];
}else{
	echo "No Session";
}

// Close DB Connection
$conn->close();
?>