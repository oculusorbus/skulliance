<?php
/**
 * ajax/points-option.php
 * The "pay with another balance" dropdown for one location upgrade.
 *
 * PRICES ITSELF FROM THE SERVER. It used to build the list -- and the button
 * that spends -- from $_GET['cost'], with a comment saying the cost needed
 * double-checking and no check anywhere. realmUpgradeQuote() decides the
 * figure now, so the dropdown, the button and the charge all agree.
 */
include '../db.php';
include '../skulliance.php';

$location_id = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;
$q = realmUpgradeQuote($conn, $location_id);
if ($location_id <= 0 || !$q['duration']) {
	echo "<div class='loc-need-msg'>" . htmlspecialchars($q['why'] !== '' ? $q['why'] : 'No location.', ENT_QUOTES, 'UTF-8') . "</div>";
	$conn->close();
	exit;
}

/* The quote above is priced in the location's OWN currency. Paying with
   anything else costs the multiplier, which is the figure this widget deals
   in throughout. */
$points_cost = $q['duration'] * 100 * (isset($points_multiplier) ? (int)$points_multiplier : 2);
$balances    = getLocationBalances($conn, $_SESSION['userData']['user_id']);

echo '<span id="points-section-'.$location_id.'">';
echo '<select class="dropdown" name="points" id="points-'.$location_id.'" onchange="document.getElementById(\'points-button-'.$location_id.'\').value=\'Upgrade '.number_format($points_cost).' \'+this.options[this.selectedIndex].id;">';
echo '<option id="Points" name="Points" value="0">Select Points</option>';
foreach ($balances AS $project_id => $balance) {
	if ($balance["balance"] >= $points_cost && $project_id != $location_id) {
		echo '<option id="'.htmlspecialchars($balance["currency"], ENT_QUOTES).'" name="'.htmlspecialchars($balance["currency"], ENT_QUOTES).'" value="'.(int)$project_id.'">'
		   . htmlspecialchars($balance["currency"], ENT_QUOTES).' ('.number_format($balance["balance"]).')</option>';
	}
}
echo '</select>';
/* duration and cost are still passed for the sake of the existing JS
   signature; the endpoint ignores them and re-derives both. */
echo "<input id='points-button-".$location_id."' class='small-button' type='button' value='Upgrade ".number_format($points_cost)." Points'
	   onclick='upgradeRealmLocationPoints(this, ".(int)$q['realm_id'].", ".$location_id.", ".(int)$q['duration'].", ".(int)$points_cost.");'>";
echo '</span>';

$conn->close();
