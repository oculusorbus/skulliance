<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['score'])){
	saveSwapScore($conn, $_GET['score']);
}else{
	echo "No Get Variables";
}

// Close DB Connection
$conn->close();
?>