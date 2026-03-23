<?php
include '../db.php';
include '../webhooks.php';
include '../skulliance.php';

if(!isset($_SESSION['userData']['user_id'])){ exit; }

startAllFreeEligibleMissions($conn);

$conn->close();
?>
