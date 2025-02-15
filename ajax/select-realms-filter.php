<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['criteria']) && isset($_GET['group'])){
	getRealms($conn, $_GET['criteria'], $_GET['group']);
}else{
	echo "No Filter Criteria";
}

// Close DB Connection
$conn->close();
?>