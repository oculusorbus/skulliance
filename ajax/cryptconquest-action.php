<?php
// ajax/cryptconquest-action.php — AJAX endpoint for Crypt Conquest actions.
//
// Same shape as ajax/cryptcrawl-action.php: performs one action and
// returns just the #cq-game-area HTML fragment, so cryptconquest.php's own
// script can swap it in without a real page navigation.
include_once '../db.php';
include '../message.php';
include '../verify.php';
include_once '../cryptconquest-actions.php';
include_once '../cryptconquest-render.php';

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

if (!isset($_SESSION['cryptconquest_flash'])) $_SESSION['cryptconquest_flash'] = [];
cryptconquestHandleAction($conn, $user_id, $_POST);

header('Content-Type: text/html; charset=utf-8');
cryptconquestRenderGameArea($conn, $user_id);

// Send the response now; run CARBON payout + Discord announce (queued by
// cryptconquestPersist() above, if this action ended a run) afterward,
// off the client's critical path -- see cryptconquestFlushPendingSideEffects()
// in db.php for why. fastcgi_finish_request() actually flushes the
// connection to the client and keeps this PHP-FPM worker alive to keep
// running past it; without it (no FPM), this still runs in the same
// order it always did, just after render instead of before -- no worse
// than before, and every game action stays independently try/catch-
// wrapped inside the flush itself either way. session_write_close() first
// -- PHP's default session handler holds a file lock for the whole
// request, and everything here that needed to write to $_SESSION already
// did (above, before render); without releasing it, a player's very next
// request (e.g. reloading right after this one) would stall waiting on
// that lock for however long the background work below takes.
session_write_close();
if (function_exists('fastcgi_finish_request')) {
	fastcgi_finish_request();
}
cryptconquestFlushPendingSideEffects($conn);
