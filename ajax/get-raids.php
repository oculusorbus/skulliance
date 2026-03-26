<?php
include '../db.php';
include '../webhooks.php';
include '../skulliance.php';

if(!isset($_SESSION['userData']['user_id'])){ exit; }
if(!checkRealm($conn)){ exit; }

$outgoing_raids = getRaids($conn, "outgoing", "pending");
if(isset($outgoing_raids)){
	echo '<div class="content raids">';
	echo $outgoing_raids;
	echo '</div>';
}
$outgoing_completed = getRaids($conn, "outgoing", "completed");
if(isset($outgoing_completed)){
	echo '<div class="content raids">';
	echo $outgoing_completed;
	echo '</div>';
}
$incoming_raids = getRaids($conn, "incoming", "pending");
if(isset($incoming_raids)){
	echo '<div class="content raids">';
	echo $incoming_raids;
	echo '</div>';
}
$incoming_completed = getRaids($conn, "incoming", "completed");
if(isset($incoming_completed)){
	echo '<div class="content raids">';
	echo $incoming_completed;
	echo '</div>';
}

$conn->close();
?>
