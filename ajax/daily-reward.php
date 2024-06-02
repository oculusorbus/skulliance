<?php
include 'db.php';
include 'skulliance.php';

$project = array();
$project = getRandomReward($conn);

echo $project["currency"];

// Close DB Connection
$conn->close();
?>