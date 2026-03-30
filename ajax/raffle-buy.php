<?php
ob_start();
include '../db.php';
include '../message.php';
include '../webhooks.php';
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        while (ob_get_level() > 0) ob_end_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fatal: ' . $err['message'] . ' (' . basename($err['file']) . ':' . $err['line'] . ')']);
    }
});

header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) { ob_clean(); echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }

$user_id    = intval($_SESSION['userData']['user_id']);
$raffle_id  = intval($_POST['raffle_id'] ?? 0);
$project_id = intval($_POST['project_id'] ?? 0);
$quantity   = intval($_POST['quantity'] ?? 1);

if (!$raffle_id || !$project_id || $quantity < 1) { ob_clean(); echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }

$raffle_check = getRaffle($conn, $raffle_id);
if ($raffle_check && intval($raffle_check['user_id']) === $user_id) { ob_clean(); echo json_encode(['success'=>false,'message'=>'You cannot buy tickets for your own raffle.']); exit; }

$result = buyRaffleTickets($conn, $raffle_id, $user_id, $project_id, $quantity);

if ($result['success']) {
    $raffle = getRaffle($conn, $raffle_id);
    $buyer  = $_SESSION['userData']['name'] ?? 'Unknown';
    $cur    = 'pts';
    $pr     = $conn->query("SELECT currency FROM projects WHERE id='$project_id' LIMIT 1");
    if ($pr && $pr->num_rows) $cur = strtoupper($pr->fetch_assoc()['currency']);
    $sold = intval($raffle['total_tickets_sold'] ?? 0);
    $r_img_url = !empty($raffle['image_path']) ? 'https://skulliance.io/staking/' . $raffle['image_path'] : '';
    discordmsg(
        '🎟️ Ticket Purchase: ' . ($raffle['title'] ?? ''),
        "**$buyer** bought **$quantity** ticket(s) for **" . ($raffle['title'] ?? '') . "** using **$cur**\nTotal tickets sold: **$sold**",
        $r_img_url,
        'https://skulliance.io/staking/raffles.php',
        'raffles', $r_img_url, 'a040ff'
    );
}

$conn->close();
ob_clean();
echo json_encode($result);
