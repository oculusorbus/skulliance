<?php
include '../db.php';
include '../skulliance.php';

header('Content-Type: application/json');

if(!isset($_SESSION['userData']['user_id'])){
	echo json_encode(array('error' => 'Not logged in'));
	$conn->close();
	exit;
}

$raid_id = isset($_GET['raid_id']) ? intval($_GET['raid_id']) : 0;
if(!$raid_id){
	echo json_encode(array('error' => 'Invalid raid ID'));
	$conn->close();
	exit;
}

$result = retreatRaid($conn, $raid_id);
echo json_encode($result);

$conn->close();
?>
