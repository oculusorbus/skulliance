<?php
include '../db.php';
include '../skulliance.php';

print_r($_SESSION);

$project = array();
$project = getRandomReward($conn);

echo $project["currency"];

// Close DB Connection
$conn->close();
?>