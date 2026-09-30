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

/*
 * THE CEILING, RE-DERIVED after bomb-on-bomb matches started chaining.
 *
 * It used to be: 25 matches x max ~790 (one full Diamond board clear)
 * ~= 19,750, capped generously at 25,000. That bound held because a
 * single match could only ever set off ONE bomb.
 *
 * It can now set off several. A five-Diamond match detonates all five
 * plus the Diamond the 5-match forges: 50 tile points + 600 combo +
 * 6 x 780 = ~5,330 in one move. But each of those Diamonds costs its own
 * 5-match to forge, so an "ultra" consumes about six of the twenty-five
 * matches -- four of them is the practical maximum, giving
 *   4 x 5,330 + 1 x 780 ~= 22,100
 * for a game where nothing is wasted and every forge lands adjacent,
 * which no real board will give you.
 *
 * 30,000 keeps roughly the same headroom over that figure as 25,000 had
 * over the old one, leaving room for cascades. Raised deliberately and
 * by that much: a cap set too low does not catch a cheat, it silently
 * throws away a legitimate record -- save-swap-score.php answers "error"
 * and the run is gone.
 */
define('SWAP_MAX_SCORE',   30000);
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
