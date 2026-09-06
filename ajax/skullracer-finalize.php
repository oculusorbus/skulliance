<?php
// ajax/skullracer-finalize.php — fires CARBON payout + Discord announce for
// one just-finished Skull Racer race.
//
// Skull Racer is entirely client-side (racing/index.html) -- there's no
// per-move server round-trip the way Crypt Crawl has, so this is the ONLY
// server interaction the game ever makes. Called by a fire-and-forget
// fetch() from racing/index.html's own finishRace(), only AFTER the
// "Race Complete" overlay is already showing on screen -- same "client
// already rendered the result, this request is purely afterward" shape as
// ajax/cryptcrawl-finalize.php, just without needing that one's separate-
// request-for-CDN-buffering reason, since there's no OTHER slow game logic
// sharing this request to begin with.
//
// Guest sessions: skullRacerFinalizeRun() itself no-ops on user_id <= 0, so
// a guest's race simply isn't saved -- same "plays fine, doesn't count"
// convention as every other real-account-only leaderboard here.
//
// No response body needed -- the client doesn't wait on this at all.
include_once '../db.php';
include '../message.php'; // skullRacerAnnounceResult()'s Discord post (discordmsg(), via verify.php below)
include '../verify.php';  // also pulls in webhooks.php/Bech32.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['logged_in'])
    && isset($_COOKIE['SessionCookie'])) {
    $cookieData = json_decode($_COOKIE['SessionCookie'], true);
    if (is_array($cookieData)) {
        // Merge, not replace -- see skulliance.php's own fix for the
        // platform-wide version of this (a raw assign here would wipe
        // every other key this session already has, not just restore login).
        $_SESSION = array_merge((array)$_SESSION, $cookieData);
    }
}
$user_id = isset($_SESSION['userData']['user_id']) ? intval($_SESSION['userData']['user_id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id > 0) {
	skullRacerFinalizeRun(
		$conn,
		$user_id,
		$_POST['total_time']  ?? 0,
		$_POST['fastest_lap'] ?? 0,
		$_POST['laps']        ?? 0,
		$_POST['token']       ?? ''
	);
}
// 204: nothing to render, and there's no client-side handler waiting on a
// body either way -- the fetch() that hits this is genuinely fire-and-forget.
http_response_code(204);
