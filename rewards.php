<?php
include_once 'db.php';
include 'webhooks.php';

if(isset($argv)){
parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

if(isset($_GET['rewards'])){
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
if(isset($_GET['factions'])){
	checkFactionsLeaderboard($conn, false, true);
}
if(isset($_GET['swaps'])){
	checkSkullSwapsLeaderboard($conn, false, true);
}
if(isset($_GET['monstrocity'])){
	checkMonstrocityLeaderboard($conn, false, true);
}
?>