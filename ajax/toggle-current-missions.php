<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['visibility'])){
	$_SESSION['userData']['current_missions'] = $_GET['visibility'];
}else{
	echo "No Session";
}

// Close DB Connection
$conn->close();
?>