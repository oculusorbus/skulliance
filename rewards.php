<?php
include_once 'db.php';
include 'webhooks.php';

if(isset($argv)){
parse_str(implode('&', array_slice($argv, 1)), $_GET);
}
if(isset($_GET['missions'])){
	checkMissionsLeaderboard($conn, false, true);
}
if(isset($_GET['streaks'])){
	checkStreaksLeaderboard($conn, false, true);
}
if(isset($_GET['raids'])){
	checkRaidsLeaderboard($conn, false, true);
}
?>