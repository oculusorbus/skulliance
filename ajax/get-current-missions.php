<?php
include '../db.php';
include '../skulliance.php';

if(!isset($_SESSION['userData']['user_id'])){ exit; }

getCurrentMissions($conn);

$conn->close();
?>
