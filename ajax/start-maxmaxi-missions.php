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

/* THESE RUN LONG AND MUST NOT BE CUT OFF HALFWAY.
   Max Maxi launches up to twenty missions in one request and Start All Auto
   enumerates item subsets per project; PHP's default 30s ceiling is a real
   risk on a loaded host. None of them is transactional, so a timeout does
   not roll back -- it leaves half a batch launched and the browser with no
   reply at all, which is exactly what a stuck spinner looks like. The page
   now recovers from that (see bulkLaunch() in missions.php), but not being
   cut off in the first place is better. Same reasoning as ajax/xrpl-link.php. */
@set_time_limit(180);

startMaxMaxiMissions($conn);

$conn->close();
?>
