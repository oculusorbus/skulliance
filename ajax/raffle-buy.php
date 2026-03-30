<?php
include '../db.php';
include '../message.php';
include '../verify.php';
include '../webhooks.php';
header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }

$user_id    = intval($_SESSION['userData']['user_id']);
$raffle_id  = intval($_POST['raffle_id'] ?? 0);
$project_id = intval($_POST['project_id'] ?? 0);
$quantity   = intval($_POST['quantity'] ?? 1);

if (!$raffle_id || !$project_id || $quantity < 1) { echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }

$result = buyRaffleTickets($conn, $raffle_id, $user_id, $project_id, $quantity);

if ($result['success']) {
    $raffle = getRaffle($conn, $raffle_id);
    $buyer  = $_SESSION['userData']['name'] ?? 'Unknown';
    $cur    = 'pts';
    $pr     = $conn->query("SELECT currency FROM projects WHERE id='$project_id' LIMIT 1");
    if ($pr && $pr->num_rows) $cur = strtoupper($pr->fetch_assoc()['currency']);
    $sold = intval($raffle['total_tickets_sold'] ?? 0);
    discordmsg(
        '🎟️ Ticket Purchase: ' . htmlspecialchars($raffle['title'] ?? ''),
        "**$buyer** bought **$quantity** ticket(s) for **" . htmlspecialchars($raffle['title'] ?? '') . "** using **$cur**\n" .
        "Total tickets sold: **$sold**",
        !empty($raffle['image_path']) ? 'https://skulliance.io/staking/' . $raffle['image_path'] : '',
        'https://skulliance.io/staking/raffles.php',
        'raffles', '', 'a040ff'
    );
}

$conn->close();
echo json_encode($result);
