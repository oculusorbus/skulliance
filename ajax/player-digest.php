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
/*
 * THE DIGEST CACHE IS GONE, AND THE LIBRARY STILL LOADS.
 *
 * It paid 3 traits on roughly one open in twelve -- the only source in
 * the collection that paid for SHOWING UP rather than for doing
 * something and succeeding. Every other trait says its holder cleared
 * twelve crypts, took a lead, won a battle or held a streak; this one
 * said they loaded a page on a lucky day, which made the claim behind
 * all the others a little less true.
 *
 * The daily habit it was built to reward already had dailystreak, which
 * asks for seven consecutive days and was there first.
 *
 * TRAITS ALREADY AWARDED STAY. The ledger rows are real and people keep
 * what they got; only the faucet is closed.
 *
 * The require stays because the digest's own reveal path needs the
 * library, and because loading it late here was once a fatal that the
 * shutdown handler turned into {show:false} -- no digest at all, for
 * everybody, silently.
 */
if (is_file(__DIR__ . '/../dhcfighters-lib.php')) {
	require_once __DIR__ . '/../dhcfighters-lib.php';
}

echo json_encode(array('show' => true, 'digest' => $d), JSON_UNESCAPED_SLASHES);
$conn->close();
