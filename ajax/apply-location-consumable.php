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
$location_id = isset($_GET['location_id']) ? $_GET['location_id'] : null;
$consumable_id = isset($_GET['consumable_id']) ? $_GET['consumable_id'] : null;

if(!$realm_id){
	echo json_encode(array('error' => 'No realm found'));
	$conn->close();
	exit;
}

$applied  = array();
$skipped  = array();

// stock-realm: all locations, all consumable types
if($location_id === 'all' && $consumable_id === 'all'){
	// Type-first: for each consumable ID 1-7, apply to all 7 locations in order until inventory exhausted
	for($cid = 1; $cid <= 7; $cid++){
		for($lid = 1; $lid <= 7; $lid++){
			$result = applyLocationConsumable($conn, $realm_id, $lid, $cid);
			if(isset($result['success'])){
				$applied[] = array('location_id' => $lid, 'consumable_id' => $cid);
			}else{
				$skipped[] = array('location_id' => $lid, 'consumable_id' => $cid, 'reason' => $result['error']);
				// If out of stock, stop trying this consumable type
				if($result['error'] === 'Out of stock') break;
			}
		}
	}
// stock-location: one location, all consumable types
}else if($consumable_id === 'all' && $location_id !== null){
	$lid = intval($location_id);
	for($cid = 1; $cid <= 7; $cid++){
		$result = applyLocationConsumable($conn, $realm_id, $lid, $cid);
		if(isset($result['success'])){
			$applied[] = array('location_id' => $lid, 'consumable_id' => $cid);
		}else{
			$skipped[] = array('location_id' => $lid, 'consumable_id' => $cid, 'reason' => $result['error']);
		}
	}
// individual: one location, one consumable
}else if($location_id !== null && $consumable_id !== null){
	$lid = intval($location_id);
	$cid = intval($consumable_id);
	$result = applyLocationConsumable($conn, $realm_id, $lid, $cid);
	if(isset($result['success'])){
		$applied[] = array('location_id' => $lid, 'consumable_id' => $cid);
	}else{
		$skipped[] = array('location_id' => $lid, 'consumable_id' => $cid, 'reason' => $result['error']);
	}
}else{
	echo json_encode(array('error' => 'Invalid parameters'));
	$conn->close();
	exit;
}

// Return updated inventory alongside results
$inventory = array();
$amounts = getCurrentAmounts($conn);
foreach($amounts as $cid => $data){
	$inventory[$cid] = $data['amount'];
}

echo json_encode(array(
	'applied'   => $applied,
	'skipped'   => $skipped,
	'inventory' => $inventory,
	'equipped'  => getRealmLocationConsumables($conn, $realm_id)
));

$conn->close();
?>
