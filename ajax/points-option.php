<?php
include '../db.php';
include '../skulliance.php';
/*
if(isset($_GET['realm_id']) && isset($_GET['location_id']) && isset($_GET['duration']) && isset($_GET['cost'])){
	// Need to double check duration and cost in case someone tries to manually override these variables in the JS function
	$balances = getLocationBalances($conn, $_SESSION['userData']['user_id']);
	echo '<select name="points" id="points-'.$_GET['location_id'].'" onchange="document.getElementById(\'points-button-'.$_GET['location_id'].'\').value=\'Upgrade '.number_format($points_multiplier*$_GET['cost']).' \'+this.value;">';
	 echo '<option value="Points">Select Points Balance</option>';
	foreach($balances AS $project_id => $balance){
		if($balance["balance"] >= ($points_multiplier*$_GET['cost'])){
		  echo '<option value="'.$project_id.'">'.$balance["currency"].' ('.number_format($balance["balance"]).')</option>';
		}
	}
	echo '</select>';
	echo "<input id='points-button-".$_GET['location_id']."' class='small-button' type='button' value='Upgrade ".number_format($points_multiplier*$_GET['cost'])." Points' 
		   onclick='upgradeRealmLocationPoints(this, ".$_GET['realm_id'].", ".$_GET['location_id'].", ".$_GET['duration'].", ".($points_multiplier*$_GET['cost']).");'>";
}else{
	echo "No Get Variables";
}
*/
// Close DB Connection
$conn->close();
?>