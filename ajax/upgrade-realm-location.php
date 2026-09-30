<?php
/**
 * ajax/upgrade-realm-location.php
 * Starts an upgrade on one location in the signed-in player's own realm.
 *
 * NOTHING ABOUT THE TRANSACTION COMES FROM THE CLIENT except which location
 * and which currency. This endpoint used to take realm_id, duration, cost and
 * project_id off the query string and spend them -- its own comment said
 * "need to double check duration and cost in case someone tries to manually
 * override these variables in the JS function", and that check was never
 * written. A crafted request could buy a level 10 upgrade for nothing; a
 * NEGATIVE cost credited the account, because upgradeRealmLocation() spends
 * -$cost and updateBalance() has no floor; and realm_id was not checked
 * against the session at all.
 *
 * realmUpgradeQuote() now derives the level, the duration, the cost and the
 * realm server-side and refuses anything it cannot price. The old parameters
 * are still accepted and ignored, so an un-refreshed page keeps working.
 */
include '../db.php';
include '../webhooks.php';
include '../skulliance.php';

$location_id = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;
/* project_id is the ONE piece of the price the player chooses: their own
   location currency, or another balance at the points multiplier. */
$project_id  = isset($_GET['project_id'])  ? (int)$_GET['project_id']  : 0;

$q = realmUpgradeQuote($conn, $location_id, $project_id);

if (!$q['ok']) {
	/* The page replaces the button with whatever comes back, so a refusal has
	   to read as a sentence rather than an empty response. */
	echo "<div class='loc-need-msg'>" . htmlspecialchars($q['why'], ENT_QUOTES, 'UTF-8') . "</div>";
} else {
	upgradeRealmLocation($conn, $q['realm_id'], $q['location_id'],
	                     $q['duration'], $q['cost'], $q['project_id']);
	$status = getRealmLocationUpgrade($conn, $q['realm_id'], $q['location_id']);
	echo isset($status[$q['location_id']]) ? $status[$q['location_id']] : '';
}

$conn->close();
