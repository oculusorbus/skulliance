<?php
include '../db.php';
include '../skulliance.php';

if(isset($_GET['realm_id']) && isset($_GET['location_id']) && isset($_GET['duration']) && isset($_GET['cost'])){
	// Need to double check duration and cost in case someone tries to manually override these variables in the JS function
	$balances = getRealmBalances($conn, $_SESSION['userData']['user_id']);
	echo '<select name="points" id="points-'.$_GET['location_id'].'" onchange="document.getElementById(\'points-button-'.$_GET['location_id'].'\').value=\'Upgrade '.number_format($points_multiplier*$_GET['cost']).' \'+this.val();">';
	 echo '<option value="">Select Points Balance</option>';
	foreach($balances AS $currency => $balance){
		if($currency != "CARBON" && $balance >= ($points_multiplier*$_GET['cost'])){
		  echo '<option  value="'.$currency.'">'.$currency.' ('.number_format($balance).')</option>';
		}
	}
	echo '</select>';
	echo "<input id='points-button-".$_GET['location_id']."' class='small-button' type='button' value='Upgrade ".number_format($points_multiplier*$_GET['cost'])." Points' onclick='upgradeRealmLocationPoints();'>";
}else{
	echo "No Get Variables";
}

// Close DB Connection
$conn->close();
?>