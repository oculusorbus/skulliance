<?php
/**
 * ajax/start-swap-game.php
 * Called when a Skull Swap game session begins.
 * Stores a one-time token and start timestamp in the session so the
 * score submission endpoint can validate the game was played legitimately.
 */
include '../db.php';
include '../skulliance.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) {
    echo json_encode(['success' => false]); exit;
}

$token = bin2hex(random_bytes(16));
$_SESSION['swap_token']   = $token;
$_SESSION['swap_started'] = time();

echo json_encode(['success' => true, 'token' => $token]);
$conn->close();
?>
