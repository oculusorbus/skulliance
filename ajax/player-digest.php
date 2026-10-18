<?php
/**
 * ajax/player-digest.php -- yesterday, for the player asking.
 *
 * Feeds the modal that greets somebody on their first visit of a new day
 * with what they did on the last one. Returns {show:false} whenever there
 * is nothing worth showing: a poster saying "you did nothing yesterday" is
 * worse than silence.
 *
 * ALWAYS THE CALLER'S OWN DAY. There is no user parameter -- the session
 * decides whose digest this is. A ?user= would turn a private summary of
 * somebody's activity into something anyone could enumerate, and nothing on
 * this endpoint needs it.
 *
 * YESTERDAY IS COMPUTED HERE, not sent by the client. A client-supplied date
 * would let anyone walk backwards through a player's whole history one day
 * at a time, and the modal only ever wants the day that just ended.
 */
ob_start();
ini_set('display_errors', 0);
register_shutdown_function(function () {
	$err = error_get_last();
	if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
		while (ob_get_level() > 0) ob_end_clean();
		if (!headers_sent()) header('Content-Type: application/json');
		echo json_encode(array('show' => false, 'message' => 'Fatal: ' . $err['message']));
	}
});

include '../db.php';
require_once __DIR__ . '/../activity-lib.php';

header('Content-Type: application/json');

/* Lightweight session restore, deliberately NOT skulliance.php: that
   redirects anonymous visitors to error.php, and this is fetched from every
   page -- a logged-out visitor should get a clean {show:false}, not an HTML
   error document parsed as JSON. Same fix as ajax/get-nft-assets.php.
   MERGED, never raw-assigned -- see skulliance.php's own note. */
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$cookie = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($cookie)) { $_SESSION = array_merge((array) $_SESSION, $cookie); }
}

$uid = isset($_SESSION['userData']['user_id']) ? (int) $_SESSION['userData']['user_id'] : 0;
if ($uid <= 0) { ob_clean(); echo json_encode(array('show' => false)); exit; }

/*
 * "YESTERDAY" IS THE SERVER'S DAY, and the same one the activity rows were
 * stamped with. Taking it from the browser would mean a player travelling
 * east sees a day that is still running, and one travelling west sees the
 * same digest twice.
 */
$day = date('Y-m-d', strtotime('-1 day'));
$d   = activity_player_digest($conn, $uid, $day);

ob_clean();
if (!$d['total']) { echo json_encode(array('show' => false, 'day' => $day)); exit; }

echo json_encode(array('show' => true, 'digest' => $d), JSON_UNESCAPED_SLASHES);
$conn->close();
