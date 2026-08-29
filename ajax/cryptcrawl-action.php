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
        $_SESSION = $cookieData;
    }
}
$user_id = isset($_SESSION['userData']['user_id']) ? intval($_SESSION['userData']['user_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	exit;
}

if (!isset($_SESSION['cryptcrawl_flash'])) $_SESSION['cryptcrawl_flash'] = [];
cryptcrawlHandleAction($conn, $user_id, $_POST);

header('Content-Type: text/html; charset=utf-8');
cryptcrawlRenderGameArea($conn, $user_id);
