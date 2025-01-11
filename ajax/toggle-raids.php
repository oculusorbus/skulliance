<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['visibility']) && isset($_GET['type']) && isset($_GET['status'])){
	$_SESSION['userData'][$_GET['type'].'-'.$_GET['status'].'-raids'] = $_GET['visibility'];
	echo $_GET['type'].'-'.$_GET['status'].'-raids';
}else{
	echo "No Session";
}

// Close DB Connection
$conn->close();
?>