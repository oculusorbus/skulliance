<?php
include '../db.php';
include '../skulliance.php';

header('Content-Type: application/json');

if(!isset($_SESSION['userData']['user_id'])){
	echo json_encode(array('error' => 'Not logged in'));
	$conn->close();
	exit;
}

$realm_id          = getRealmID($conn);
$location_id_raw   = isset($_GET['location_id'])   ? $_GET['location_id']   : '0';
$consumable_id_raw = isset($_GET['consumable_id']) ? $_GET['consumable_id'] : '0';

$all_locations  = ($location_id_raw   === 'all');
$all_consumables = ($consumable_id_raw === 'all');

$location_id   = $all_locations   ? 0 : intval($location_id_raw);
$consumable_id = $all_consumables ? 0 : intval($consumable_id_raw);

if(!$realm_id || (!$all_locations && !$location_id) || (!$all_consumables && !$consumable_id)){
	echo json_encode(array('error' => 'Invalid parameters'));
	$conn->close();
	exit;
}

$location_ids = $all_locations ? array(1,2,3,4,5,6,7) : array($location_id);
$upgrades = array();

foreach($location_ids as $lid){
	if($all_consumables){
		$rl_id = getRealmLocationID($conn, $realm_id, $lid);
		if(!$rl_id) continue;
		$eq_res = $conn->query("SELECT consumable_id FROM realms_locations_consumables WHERE realm_location_id='".intval($rl_id)."'");
		if(!$eq_res) continue;
		$equipped_cids = array();
		while($eq_row = $eq_res->fetch_assoc()) $equipped_cids[] = intval($eq_row['consumable_id']);
		foreach($equipped_cids as $cid){
			$result = removeLocationConsumableRefund($conn, $realm_id, $lid, $cid);
			if(isset($result['upgrade'])) $upgrades[$lid] = $result['upgrade'];
		}
	} else {
		$result = removeLocationConsumableRefund($conn, $realm_id, $lid, $consumable_id);
		if(isset($result['upgrade'])) $upgrades[$lid] = $result['upgrade'];
	}
}

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
if(!empty($upgrades)) $out['upgrades'] = $upgrades;
echo json_encode($out);

$conn->close();
?>
