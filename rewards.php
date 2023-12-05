<?php
/* Commenting this out to protect from assigning rewards to the session user or the script getting abused by web crawlers.
include_once 'db.php';

if(isset($argv)){
parse_str(implode('&', array_slice($argv, 1)), $_GET);
}
if(isset($_GET['deploy'])){
	set_time_limit(0);
	updateBalances($conn);
	deployDiamondSkullRewards($conn);
}*/
include_once 'db.php';
include 'webhooks.php';

$users = array();
$users = getUsers($conn);
print_r($users);
?>