<?php
/**
 * admin-guild-refresh.php — re-read the Discord roster.
 *
 * Its own endpoint rather than a branch in admin-lapsed.php, because
 * that page is read-only by design and a side effect on GET would fire
 * on every open, including a browser prefetch. POST only, and it
 * redirects straight back so a refresh of the panel cannot replay it.
 *
 * NOTHING MAY PRINT. The whole page is a redirect, and display_errors
 * is on platform-wide, so a notice from the credentials chain would
 * replace the Location header with a wall of text.
 */

ob_start();
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();                       /* exits plain-text 404, no chrome */
/* $bot_token lives in credentials/webhooks_credentials.php, which
   webhooks.php includes. admin-lapsed.php has no reason to load that
   chain, so it is pulled in here and nowhere else. */
require_once __DIR__ . '/webhooks.php';
require_once __DIR__ . '/lib/guild-members.php';
ob_end_clean();

$back = 'admin-lapsed.php';
if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
	header('Location: ' . $back, true, 303);
	exit;
}

$res = guild_members_refresh(isset($bot_token) ? $bot_token : '');

/* Carried in the session rather than the query string: the error text
   is a sentence, and a failure reason does not belong in a URL that
   gets bookmarked or shared. */
if (session_status() === PHP_SESSION_ACTIVE) {
	$_SESSION['lap_guild_msg'] = $res['ok']
		? array('ok', number_format($res['count']) . ' members read from Discord.')
		: array('bad', $res['error']);
}

/* 303: the result of a POST is a page to look at, not the POST again. */
header('Location: ' . $back, true, 303);
exit;
