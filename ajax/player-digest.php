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

/* Their own invite code travels with the digest, so the Share button can
   put it on the link without a second request. Derived from the user id --
   no column, nothing to migrate. */
if (is_file(__DIR__ . '/../lib/referral-lib.php')) {
	require_once __DIR__ . '/../lib/referral-lib.php';
	$d['ref'] = ref_code($uid);
}

/*
 * SOME DAYS A CACHE FALLS OUT.
 *
 * The habit this is buying is opening the digest, which already requires
 * having played the day before -- there is no digest on a quiet day. It is
 * deliberately NOT hung on the Share button: a reward for sharing is a
 * reward for posting on X, and that is the one thing that would put the
 * invite scheme on the wrong side of the rule against paying for
 * engagement.
 *
 * AWARDED HERE, SERVER-SIDE. The once-a-day marker the modal uses is
 * localStorage, which is the right tool for "do not show me this twice"
 * and no kind of guard on a payout.
 *
 * The reveal needs no special handling: this runs in the player's OWN
 * request, so dhcf_award() queues it the way it does for any other drop
 * and the modal fires once the digest is closed.
 */
$d['cache'] = 0;
/*
 * THE LIBRARY IS LOADED BEFORE IT IS ASKED ANYTHING. dhcf_cache_day() lives
 * in dhcfighters-config.php, which nothing in this endpoint's include chain
 * pulls in -- db.php only requires it from inside a function. Calling it
 * first and requiring afterwards is a fatal, and the shutdown handler above
 * turns a fatal into {show:false}, so the symptom would not be a missing
 * cache: it would be NO DIGEST AT ALL, for everybody, silently.
 */
if (is_file(__DIR__ . '/../dhcfighters-lib.php')) {
	require_once __DIR__ . '/../dhcfighters-lib.php';   /* which loads the config */
}
if (function_exists('dhcf_cache_day') && dhcf_cache_day($uid, $day)) {
	/* Belt and braces on top of the day-hash: if a cache is somehow already
	   recorded for today, nothing more is drawn. The hash makes this
	   unreachable; it costs one indexed read to be certain. */
	$had = $conn->query("SELECT 1 FROM dhc_trait_drops
	                      WHERE user_id = $uid AND source = 'digestcache'
	                        AND awarded_at >= CURDATE() LIMIT 1");
	if (!($had && $had->num_rows)) {
		$tbl = dhcf_table_for('digestcache');
		for ($i = 0; $i < DHCF_CACHE_DRAWS; $i++) {
			if (dhcf_award($conn, $uid, 'wildcard', 'digestcache', $day, $tbl)) $d['cache']++;
		}
	}
}

echo json_encode(array('show' => true, 'digest' => $d), JSON_UNESCAPED_SLASHES);
$conn->close();
