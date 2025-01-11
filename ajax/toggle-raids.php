<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['visibility']) && isset($_GET['type']) && isset($_GET['status'])){
	$_SESSION['userData']['total_raids'] = $_GET['visibility'];
}else{
	echo "No Session";
}

// Close DB Connection
$conn->close();
?>