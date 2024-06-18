<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['action']) && isset($_GET['nft_id']) && isset($_GET['rate'])){
	if($_GET['action'] == "Select"){
		$_SESSION['userData']['mission']['nfts'][$_GET['nft_id']] = $_GET['rate'];
		//print_r($_SESSION['userData']['mission']);
	}else if($_GET['action'] == "Remove"){
		unset($_SESSION['userData']['mission']['nfts'][$_GET['nft_id']]);
		//print_r($_SESSION['userData']['mission']);
	}
}else{
	echo "No Session";
}
//$_SESSION['userData']['mission']['nfts'][$row["id"]] = $row["rate"];

//echo json_encode($project);

// Close DB Connection
$conn->close();
?>