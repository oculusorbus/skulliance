<?php
include '../db.php';
include '../skulliance.php';

header('Content-Type: application/json');

if(!isset($_SESSION['userData']['user_id'])){
	echo json_encode(array('error' => 'Not logged in'));
	$conn->close();
	exit;
}

$realm_id    = getRealmID($conn);
$location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;
$consumable_id = isset($_GET['consumable_id']) ? intval($_GET['consumable_id']) : 0;

if(!$realm_id || !$location_id || !$consumable_id){
	echo json_encode(array('error' => 'Invalid parameters'));
	$conn->close();
	exit;
}

$result = removeLocationConsumableRefund($conn, $realm_id, $location_id, $consumable_id);

// Return updated inventory
$inventory = array();
$amounts = getCurrentAmounts($conn);
foreach($amounts as $cid => $data){
	$inventory[$cid] = $data['amount'];
}

$out = array(
	'success'   => true,
	'inventory' => $inventory,
	'equipped'  => getRealmLocationConsumables($conn, $realm_id)
);
if(isset($result['upgrade'])) $out['upgrades'] = array($location_id => $result['upgrade']);
echo json_encode($out);

$conn->close();
?>
