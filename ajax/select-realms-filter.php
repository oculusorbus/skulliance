<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['criteria'])){
	getRealms($conn, $_GET['criteria']);
}else{
	echo "No Filter Criteria";
}

// Close DB Connection
$conn->close();
?>