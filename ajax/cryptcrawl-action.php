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
//
// Deliberately does NOTHING beyond the game logic, the save, and the
// render -- no CARBON payout, no Discord announce, nothing slow. Three
// different attempts at keeping that work in THIS request (queued and
// deferred past fastcgi_finish_request(); X-Accel-Buffering + an explicit
// flush() as a portable fallback) all failed to get the response to the
// browser before the slow work finished, in production, on a PWA -- most
// likely a CDN/proxy in front of this server buffers the full origin
// response regardless of anything the origin does. The only fix that
// doesn't depend on guessing right about server/CDN internals: this
// request no longer has any slow work in it AT ALL. cryptcrawl.php's own
// JS fires a completely separate, fire-and-forget request to
// ajax/cryptcrawl-finalize.php for the CARBON/Discord side of things, and
// only AFTER the win/loss screen is already on screen.
include_once '../db.php';
include '../message.php';
include '../verify.php';
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
// fallback if the real render throws.
$ended_run = cryptcrawlHandleAction($conn, $user_id, $_POST);

header('Content-Type: text/html; charset=utf-8');
// The real render can fail for reasons that have nothing to do with the
// delve actually ending (a fresh DB re-read glitching, art lookups,
// anything else added here later). If that happens on a run that DID just
// end, fall back to the guaranteed-minimal confirmation instead of leaving
// the response broken -- the player sees "you died"/"you escaped" and how
// far they got no matter what else fails. If the render fails on an
// action that DIDN'T end the delve, there's no safe minimal fallback for
// mid-game board state, so this re-throws.
if ($ended_run && in_array($ended_run['status'] ?? '', ['won', 'lost'], true)) {
	// THIS REQUEST ENDED A DELVE -> the player sees THAT delve's result,
	// full stop. Deliberately does NOT go through cryptcrawlRenderGameArea(),
	// because that function decides what to show by asking "does this player
	// have any active run?" -- and answers with a live board if one exists.
	// That's the wrong question at this exact moment: the run the player was
	// just playing has ended, and its result is the only correct thing to
	// show, whatever else happens to be sitting in the table.
	//
	// The duplicate-active-run bug that made this matter is fixed at the
	// source in cryptcrawlStartRun() (see its comment). This is the second
	// half of that fix: accounts that ALREADY accumulated orphaned active
	// rows before the guard existed would otherwise keep hitting the old
	// symptom on every death until each orphan was played out. Rendering the
	// just-ended run's own result heals that immediately, without rewriting
	// anyone's run history or inventing losses they didn't earn.
	$_SESSION['cryptcrawl_flash'] = []; // drained, same as the normal render does
	cryptcrawlMinimalGameOverHtml($ended_run, $user_id);
} else {
	// Ordinary mid-delve action. No safe minimal fallback exists for live
	// board state, so a genuine render failure still propagates.
	cryptcrawlRenderGameArea($conn, $user_id);
}
