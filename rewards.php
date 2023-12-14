<?php
/* Commenting this out to protect from assigning rewards to the session user or the script getting abused by web crawlers.
include_once 'db.php';

if(isset($argv)){
parse_str(implode('&', array_slice($argv, 1)), $_GET);
}
if(isset($_GET['deploy'])){
	set_time_limit(0);
	// Get project percentages for Diamond Skull delegations
	$percentages = array();
	$percentages = getProjectDelegationPercentages($conn);
	// Determine whether Diamond Skulls should get a bonus
	$diamond_skull_bonus = getDiamondSkullBonus($percentages);
	// Deploy rewards for all users of the platform
	updateBalances($conn, $diamond_skull_bonus);
	// Deploy rewards for Diamond Skull delegation
	deployDiamondSkullRewards($conn, $percentages);
}*/
?>