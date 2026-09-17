<?php
/**
 * ajax/start-maxmaxi-missions.php
 * Max Maxi: two missions on every unlocked Maxingo level, each carrying a
 * 100% Success item. See startMaxMaxiMissions() in db.php for the why.
 *
 * The entitlement check lives in that function, not here -- the hidden button
 * is not a boundary, and a direct hit on this endpoint has to meet the same
 * bar as a click.
 */
include '../db.php';
include '../webhooks.php';
include '../skulliance.php';

if(!isset($_SESSION['userData']['user_id'])){ exit; }

startMaxMaxiMissions($conn);

$conn->close();
?>
