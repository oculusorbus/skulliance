<?php
include '../db.php';
include '../skulliance.php';

$project = array();
$project = getRandomReward($conn);

echo json_encode($project);

// Close DB Connection
$conn->close();
?>