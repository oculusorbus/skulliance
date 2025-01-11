<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['visibility'])){
	$_SESSION['userData']['total_raids'] = $_GET['visibility'];
}else{
	echo "No Session";
}

// Close DB Connection
$conn->close();
?>