<?php
// ajax/cryptcrawl-finalize.php — fires CARBON payout + Discord announce for
// one just-ended Crypt Crawl run.
//
// Deliberately a SEPARATE endpoint from ajax/cryptcrawl-action.php, called
// by a fire-and-forget fetch() from cryptcrawl.php's own JS -- and only
// AFTER the win/loss screen is already showing on screen, never before.
// This is the actual fix for the recurring "loss screen doesn't show"
// bug: ajax/cryptcrawl-action.php now does nothing except the game logic,
// the save, and the render -- no CARBON, no Discord, nothing slow -- so
// there is nothing left in that request that can delay or break the
// confirmation reaching the browser. See cryptcrawlFinalizeRun()'s own
// comment in db.php for why this needed to become a genuinely separate
// request rather than another same-request deferral trick, and for the
// idempotency guard that makes calling this twice for the same run safe.
//
// No response body needed -- the client doesn't wait on this at all.
include_once '../db.php';
include '../message.php'; // cryptcrawlAnnounceResult()'s Discord post (discordmsg(), via verify.php below)
include '../verify.php';  // also pulls in webhooks.php/Bech32.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['logged_in'])
    && isset($_COOKIE['SessionCookie'])) {
    $cookieData = json_decode($_COOKIE['SessionCookie'], true);
    if (is_array($cookieData)) {
        $_SESSION = array_merge((array)$_SESSION, $cookieData);
    }
}
$user_id = isset($_SESSION['userData']['user_id']) ? intval($_SESSION['userData']['user_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id > 0) {
	cryptcrawlFinalizeRun($conn, $user_id, intval($_POST['run_id'] ?? 0));
}
// 204: nothing to render, and there's no client-side handler waiting on a
// body either way -- the fetch() that hits this is genuinely fire-and-forget.
http_response_code(204);
