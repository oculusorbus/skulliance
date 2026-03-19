<?php
include '../db.php';
include '../webhooks.php';
include '../skulliance.php';

startMission($conn);

//echo json_encode($project);

// Close DB Connection
$conn->close();
?>