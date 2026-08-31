<?php
// ajax/cryptcrawl-action.php — AJAX endpoint for Crypt Crawl actions.
//
// Performs one action (start_run/play_card/flee/abandon) and returns just
// the #cc-game-area HTML fragment, so cryptcrawl.php's own script can swap
// it in without a real page navigation. That's the entire point of this
// endpoint: a full-page reload on every single action was tearing down and
// rebuilding the ambient <audio> element every time, audibly stuttering the
// music player. cryptcrawl.php itself keeps its old POST-and-redirect
// handling too, as the no-JS fallback — see the comment there.
include_once '../db.php';
include '../message.php'; // pulled in for cryptcrawlAnnounceResult()'s Discord post (discordmsg(), via verify.php below)
include '../verify.php';  // also pulls in webhooks.php/Bech32.php -- same include cryptcrawl.php itself uses, for the same reason
include_once '../cryptcrawl-actions.php';
include_once '../cryptcrawl-render.php';

// Same session bootstrap as cryptcrawl.php itself -- this page needs to work
// for a guest too, so it can't rely on a gate like skulliance.php's.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['logged_in'])
    && isset($_COOKIE['SessionCookie'])) {
    $cookieData = json_decode($_COOKIE['SessionCookie'], true);
    if (is_array($cookieData)) {
        // Merge, not replace -- see skulliance.php's own fix for why.
        $_SESSION = array_merge((array)$_SESSION, $cookieData);
    }
}
$user_id = isset($_SESSION['userData']['user_id']) ? intval($_SESSION['userData']['user_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	exit;
}

if (!isset($_SESSION['cryptcrawl_flash'])) $_SESSION['cryptcrawl_flash'] = [];
// $ended_run is null unless this exact action just won/lost/abandoned a
// delve (see cryptcrawlHandleAction()'s own return-value comment) --
// already-known, zero-further-query data used below as a guaranteed
// fallback if the real render throws. Nothing about the fallback depends
// on anything that happened inside cryptcrawlHandleAction() beyond this
// return value, so it's just as reliable as the action itself succeeding.
$ended_run = cryptcrawlHandleAction($conn, $user_id, $_POST);

header('Content-Type: text/html; charset=utf-8');
// Tell nginx not to buffer this response -- same header missions.php's own
// loading spinner already relies on to flush early in production, proven
// working on this exact server/hosting setup. Needed regardless of
// fastcgi_finish_request() below: if this server isn't actually running
// PHP-FPM (or it's disabled for some other reason), fastcgi_finish_request
// silently does nothing and the whole request -- including the CARBON
// payout queries and the Discord webhook POST, which has its own 8-second
// timeout -- runs to completion BEFORE any response reaches the browser,
// which is indistinguishable from "the loss screen is broken" even though
// the server eventually sends a perfectly correct response. Confirmed this
// was happening live: Discord notifications were firing (proof the whole
// request, deferred side effects included, was running end-to-end) while
// the client still saw nothing -- meaning the earlier fastcgi_finish_request
// deferral wasn't actually taking effect on this server.
header('X-Accel-Buffering: no');
// The real render can fail for reasons that have nothing to do with the
// delve actually ending (a fresh DB re-read glitching, art lookups,
// anything else added here later) -- reported live: the win/loss screen
// went missing on a PWA mid-recording despite the delve genuinely having
// ended. If that happens on a run that DID just end, fall back to the
// guaranteed-minimal confirmation instead of leaving the response broken;
// the player sees "you died"/"you escaped" and how far they got no matter
// what else fails. If the render fails on an action that DIDN'T end the
// delve, there's no safe minimal fallback for mid-game board state, so
// this re-throws -- that's not the failure mode being guarded against here.
try {
	cryptcrawlRenderGameArea($conn, $user_id);
} catch (\Throwable $e) {
	if ($ended_run && in_array($ended_run['status'] ?? '', ['won', 'lost'], true)) {
		error_log('cryptcrawlRenderGameArea failed after a delve ended, falling back to minimal game-over: ' . $e->getMessage());
		cryptcrawlMinimalGameOverHtml($ended_run, $user_id);
	} else {
		throw $e;
	}
}

// Send the response now; run CARBON payout + Discord announce (queued by
// cryptcrawlPlayCard()/cryptcrawlAbandonRun() above, if this action ended
// a delve) afterward, off the client's critical path -- see
// cryptcrawlFlushPendingSideEffects() in db.php for why, and
// ajax/cryptconquest-action.php's own copy of this comment for the full
// rationale (same fix, same shape, both games) including why
// session_write_close() has to come first.
session_write_close();
// Let payout/announce actually finish even if the client navigates away or
// closes the tab right after getting its response (which is the whole
// point -- the player doesn't need to keep the connection open for any of
// this anymore).
ignore_user_abort(true);
if (function_exists('fastcgi_finish_request')) {
	fastcgi_finish_request();
} else {
	// Portable fallback for non-FPM SAPIs -- the exact technique
	// missions.php's own loader already uses successfully in production.
	if (ob_get_level() > 0) { @ob_end_flush(); }
	flush();
}
cryptcrawlFlushPendingSideEffects($conn);
