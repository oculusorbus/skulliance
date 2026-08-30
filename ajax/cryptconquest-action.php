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
