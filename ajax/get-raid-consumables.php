<?php
include '../db.php';
include '../skulliance.php';

header('Content-Type: application/json');

if(!isset($_SESSION['userData']['user_id'])){
	echo json_encode(array('error' => 'Not logged in'));
	$conn->close();
	exit;
}

$con_info = array(
	1 => array('name'=>'+4% Success', 'desc'=>'Adds 4% to your raid success chance. Stacks with other boosts up to a 10% total increase. Burns on use.'),
	2 => array('name'=>'+3% Success', 'desc'=>'Adds 3% to your raid success chance. Stacks with other boosts up to a 10% total increase. Burns on use.'),
	3 => array('name'=>'+2% Success', 'desc'=>'Adds 2% to your raid success chance. Stacks with other boosts up to a 10% total increase. Burns on use.'),
	4 => array('name'=>'+1% Success', 'desc'=>'Adds 1% to your raid success chance. Stacks with other boosts up to a 10% total increase. Burns on use.'),
	5 => array('name'=>'Fast Forward', 'desc'=>'Halves raid duration, rounded up. Burns on use.'),
	6 => array('name'=>'Double Rewards','desc'=>'On a successful raid: doubles your loot (roll is capped at 500 then doubled, max 1000) AND deals a second hit to a defense location. If the first location has a shield, the second hit damages that same location after the shield is stripped. Burns on use.'),
	7 => array('name'=>'Random Reward', 'desc'=>'Selects a random project from the defender and awards its loot if greater than the original loot. Burns on use.'),
);

$amounts = getCurrentAmounts($conn);

$items = array();
foreach($con_info as $cid => $info){
	$qty = 0;
	if(isset($amounts[$cid])) $qty = intval($amounts[$cid]['amount']);
	$icon = strtolower(str_replace(array('%','+',' '),array('','+','-'),$info['name'])).'.png';
	// Fall back to original consumable names for icon lookup (DB names are still "100% Success" etc.)
	$icon_names = array(1=>'100-success',2=>'75-success',3=>'50-success',4=>'25-success',5=>'fast-forward',6=>'double-rewards',7=>'random-reward');
	$icon = $icon_names[$cid].'.png';
	$items[] = array(
		'id'   => $cid,
		'name' => $info['name'],
		'desc' => $info['desc'],
		'icon' => $icon,
		'qty'  => $qty,
	);
}

echo json_encode(array('consumables' => $items));

$conn->close();
?>
