<?php
/**
 * x-auth.php -- starts an X flow and gets out of the way.
 *
 * Two modes, and the difference is who is asking:
 *
 *   ?mode=connect  a signed-in member attaching their X account. The
 *                  ONLY way an x_id is ever written.
 *   ?mode=login    somebody asking to be recognised by an X account
 *                  already attached to an account here.
 *
 * THE MODE IS STORED IN THE SESSION, NOT CARRIED IN THE CALLBACK URL.
 * x_oauth_begin() puts it beside the state, so the callback reads it
 * from somewhere the caller cannot reach. A mode in the query string
 * would let anybody turn their own login into a connect.
 *
 * connect REQUIRES A SESSION and is refused without one, because the
 * session is the only evidence of which account the X id belongs to.
 *
 * Nothing prints: this ends in a redirect, same rule as the callback.
 */
ob_start();
ini_set('display_errors', 0);
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

function xa_go($where) {
	while (ob_get_level() > 0) ob_end_clean();
	header('Location: ' . $where);
	exit;
}

$mode = (isset($_GET['mode']) && $_GET['mode'] === 'connect') ? 'connect' : 'login';

if ($mode === 'connect' && empty($_SESSION['userData']['user_id'])) {
	xa_go('index.php?x=signin');
}

$url = x_oauth_begin($mode);
if ($url === '') {
	/* Credentials missing or unreadable. Silence rather than a broken
	   page: the log says why, and the player sees the page they came
	   from with a note rather than a stack trace. */
	xa_go($mode === 'connect' ? 'wallets.php?x=unavailable' : 'index.php?x=unavailable');
}
xa_go($url);
