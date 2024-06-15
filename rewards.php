<?php
include_once 'db.php';

if(isset($argv)){
parse_str(implode('&', array_slice($argv, 1)), $_GET);
}
if(isset($_GET['missions'])){
	checkMissionsLeaderboard($conn, false, true);
}
?>