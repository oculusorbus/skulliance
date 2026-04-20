<?php
/**
 * ajax/save-swap-score.php
 * Validates and saves a completed Skull Swap score.
 * Requires a session token issued by start-swap-game.php.
 *
 * Validations:
 *   1. User must be logged in
 *   2. Token must match the session-issued token (one-time use)
 *   3. Minimum elapsed time since game start (blocks automated submissions)
 *   4. Score must not exceed the mathematical ceiling
 */
include '../db.php';
include '../webhooks.php';
include '../skulliance.php';

// 25 matches × max ~790 pts each (full diamond-bomb board clear) ≈ 19,750
// Cap set generously above all legitimate records with room for extreme cascades.
define('SWAP_MAX_SCORE',   25000);
define('SWAP_MIN_SECONDS', 60);   // minimum real-time seconds for a full 25-match game

if (!isset($_SESSION['userData']['user_id'])) {
    echo "error"; exit;
}

$score = intval($_GET['score'] ?? 0);
$token = trim($_GET['token'] ?? '');

// Validate one-time session token
if (empty($token)
    || empty($_SESSION['swap_token'])
    || !hash_equals($_SESSION['swap_token'], $token)) {
    error_log('SwapScore: invalid token for user ' . $_SESSION['userData']['user_id']);
    echo "error"; exit;
}
unset($_SESSION['swap_token']); // invalidate — single use

// Validate minimum elapsed time
$started = intval($_SESSION['swap_started'] ?? 0);
unset($_SESSION['swap_started']);
if ($started === 0 || (time() - $started) < SWAP_MIN_SECONDS) {
    error_log('SwapScore: submission too fast for user ' . $_SESSION['userData']['user_id']);
    echo "error"; exit;
}

// Validate score ceiling
if ($score <= 0 || $score > SWAP_MAX_SCORE) {
    error_log('SwapScore: score ' . $score . ' out of range for user ' . $_SESSION['userData']['user_id']);
    echo "error"; exit;
}

saveSwapScore($conn, $score);

// Close DB Connection
$conn->close();
?>
