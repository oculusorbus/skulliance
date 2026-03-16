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
	1 => array('name'=>'100% Success', 'desc'=>'Guarantees raid success. Burns on use.'),
	2 => array('name'=>'75% Success',  'desc'=>'+75% to your offense success rate. Burns on use.'),
	3 => array('name'=>'50% Success',  'desc'=>'+50% to your offense success rate. Burns on use.'),
	4 => array('name'=>'25% Success',  'desc'=>'+25% to your offense success rate. Burns on use.'),
	5 => array('name'=>'Fast Forward', 'desc'=>'Halves raid duration (or bumps portal +1 level if portal is level 0-1). Burns on use.'),
	6 => array('name'=>'Double Rewards','desc'=>'Doubles loot cap from 500 to 1000 points on a successful raid. Burns on use.'),
	7 => array('name'=>'Random Reward', 'desc'=>'Awards a second random project\'s loot on successful raid. Burns on use.'),
);

$amounts = getCurrentAmounts($conn);

$items = array();
foreach($con_info as $cid => $info){
	$qty = 0;
	if(isset($amounts[$cid])) $qty = intval($amounts[$cid]['amount']);
	$icon = strtolower(str_replace('%','',str_replace(' ','-',$info['name']))).'.png';
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
