<?php
/**
 * process-x-oauth.php -- the X callback. Links, or recognises. Never creates.
 *
 * TWO FLOWS THROUGH ONE ENDPOINT, told apart by the mode stored in the
 * session when the flow began rather than by anything in the query
 * string, which the caller does not control:
 *
 *   connect -- a member already signed in with Discord attaching their
 *              X account. This is the ONLY way an x_id is ever written.
 *
 *   login   -- somebody asking to be recognised. Looks up the x_id and
 *              signs them into the account it belongs to. If there is
 *              no such row, it refuses and says what to do instead.
 *
 * NO ACCOUNT IS EVER CREATED HERE, and that is the whole safety
 * property. X and Discord share no identifier, so an unknown X id
 * cannot be matched to an existing member -- creating one would fork a
 * returning player into a second, empty account, and unforking means
 * rewriting them across 46 tables and a second database. The ambiguity
 * is resolved once, by the human, at connect time.
 *
 * NOTHING PRINTS BEFORE THE REDIRECT. Same rule as process-oauth.php,
 * which was found printing the Discord client secret: the page ends in
 * header(), output breaks that AND exposes whatever was written. Own
 * buffer, errors to the log, buffer discarded on the way out.
 */
ob_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

/*
 * THE SESSION IS STARTED HERE, BEFORE db.php, AND THAT IS THE WHOLE
 * REASON THIS FLOW FAILED FIRST TIME.
 *
 * db.php only RESUMES a session, and only when a cookie already
 * exists -- deliberately, so a cookieless bot does not leave an orphan
 * session file per request. A signed-out visitor pressing Sign in with
 * X therefore had no session at all: the state and PKCE verifier went
 * into an array that evaporated at the end of the request, X sent them
 * back, nothing was waiting, and they were told the attempt had
 * expired. Which it had not. It had never been stored.
 *
 * process-oauth.php has always done it this way for Discord, and its
 * own comment says why the order matters: db.php's conditional
 * session_start() must not fire ahead of the cookie params, or it
 * undoes them for this exact login.
 *
 * One month, matching Discord, so signing in with X does not quietly
 * produce a shorter-lived session than the other door.
 */
session_set_cookie_params(2678400);
session_start();

include 'db.php';
require_once __DIR__ . '/lib/x-oauth.php';

/** Leave, with a short reason for the destination page to render. */
function xcb_go($where, $note = '') {
	while (ob_get_level() > 0) ob_end_clean();
	$sep = (strpos($where, '?') === false) ? '?' : '&';
	header('Location: ' . $where . ($note !== '' ? $sep . 'x=' . rawurlencode($note) : ''));
	exit;
}

/*
 * THE STATE IS CHECKED FIRST AND CONSUMED WHATEVER HAPPENS. It is what
 * stops somebody handing us a code they obtained elsewhere, and it is
 * single use so a replayed callback finds nothing waiting.
 */
$flow = x_oauth_take_state(isset($_GET['state']) ? (string)$_GET['state'] : '');
if (!$flow)                 xcb_go('index.php', 'expired');
/* The user declining at X's end arrives here with ?error= and no code.
   That is a normal outcome, not a fault. */
if (!empty($_GET['error'])) xcb_go(($flow['mode'] === 'connect') ? 'wallets.php' : 'index.php', 'cancelled');
$code = isset($_GET['code']) ? (string)$_GET['code'] : '';
if ($code === '')           xcb_go('index.php', 'nocode');

/*
 * THREE OUTCOMES, NOT ONE "failed".
 *
 * This said "X did not complete the sign-in" for every upstream
 * problem, which is useless to a player and worse to whoever is
 * debugging it -- the whole point of reaching this page is to find out
 * which half broke. The credentials being unreadable, X refusing the
 * code, and X refusing to say who you are are three different faults
 * with three different fixes, and the page now distinguishes them
 * without anybody opening a log.
 */
$back = ($flow['mode'] === 'connect') ? 'wallets.php' : 'index.php';
if (!x_oauth_config())      xcb_go($back, 'unavailable');

$token = x_oauth_exchange($code, $flow['verifier'], $flow['uri']);
if ($token === '')          xcb_go($back, 'notoken');

$me = x_oauth_me($token);
if (!$me)                   xcb_go($back, 'noprofile');

$x_id     = $conn->real_escape_string($me['id']);
$x_handle = $conn->real_escape_string($me['username']);

if ($flow['mode'] === 'connect') {
	/* Must still be signed in: the session is what says which account
	   this X id belongs to, and it is the only evidence there is. */
	$uid = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
	if ($uid <= 0) xcb_go('index.php', 'signin');

	/*
	 * ONE X ACCOUNT, ONE MEMBER. x_id carries a UNIQUE index, so this
	 * is belt and braces -- but a plain insert would fail with a
	 * duplicate-key error the player cannot read, where this tells
	 * them what is actually wrong.
	 */
	$r = @$conn->query("SELECT id FROM users WHERE x_id = '$x_id' LIMIT 1");
	if ($r && ($row = $r->fetch_assoc()) && (int)$row['id'] !== $uid) {
		xcb_go('profile.php', 'taken');
	}

	$ok = @$conn->query("UPDATE users
	                        SET x_id = '$x_id', x_username = '$x_handle', x_linked_at = NOW()
	                      WHERE id = $uid");
	if (!$ok) { error_log('x-oauth: link failed for user ' . $uid . ' -- ' . $conn->error); xcb_go('profile.php', 'failed'); }
	$_SESSION['userData']['x_username'] = $me['username'];
	xcb_go('profile.php', 'linked');
}

/*
 * LOGIN. A lookup and nothing else.
 *
 * Matched on the numeric id, never the handle: handles can be changed
 * and released, and a released one can be claimed by somebody else --
 * matching on it would hand an account to whoever registers it next.
 */
$r = @$conn->query("SELECT id, discord_id, username, avatar FROM users WHERE x_id = '$x_id' LIMIT 1");
if (!$r || !($row = $r->fetch_assoc())) {
	/* The expected outcome for a stranger, not an error. index.php
	   explains that X sign-in only works once an account has connected
	   one, and offers Discord. */
	xcb_go('index.php', 'nolink');
}

/*
 * THE SESSION IS BUILT THE SAME WAY DISCORD BUILDS IT, from the users
 * row, so every page downstream cannot tell which door they came
 * through. MERGED, NEVER ASSIGNED: a raw assign here would wipe
 * whatever else the session holds, which is a documented way this
 * platform has broken before.
 */
if (!isset($_SESSION['userData']) || !is_array($_SESSION['userData'])) $_SESSION['userData'] = array();
$_SESSION['userData'] = array_merge($_SESSION['userData'], array(
	'user_id'    => strval($row['id']),
	'discord_id' => (string)$row['discord_id'],
	'name'       => (string)$row['username'],
	'username'   => (string)$row['username'],
	'avatar'     => (string)$row['avatar'],
	'x_username' => $me['username'],
));
/* The handle can change at X's end between sign-ins, so it is refreshed
   rather than trusted from the last time we looked. */
@$conn->query("UPDATE users SET x_username = '$x_handle' WHERE id = " . (int)$row['id']);

xcb_go('launchpad.php');
