<?php
include '../db.php';
include '../message.php';
include '../verify.php';
include '../webhooks.php';
header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }

$user_id   = intval($_SESSION['userData']['user_id']);
$raffle_id = intval($_POST['raffle_id'] ?? 0);
$quantity  = intval($_POST['quantity'] ?? 1);

if (!$raffle_id || $quantity < 1) { echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }

$result = buyRaffleTickets($conn, $raffle_id, $user_id, $quantity);

if ($result['success']) {
    $raffle = getRaffle($conn, $raffle_id);
    $buyer  = $_SESSION['userData']['name'] ?? 'Unknown';
    $cur    = strtoupper($raffle['ticket_currency'] ?? 'pts');
    discordmsg(
        '🎟️ Ticket Purchase',
        "**$buyer** bought **$quantity** ticket(s) for **" . htmlspecialchars($raffle['title'] ?? '') . "**\n" .
        "Total tickets sold: **" . $raffle['total_tickets_sold'] . "**" . ($raffle['max_tickets'] ? ' / ' . $raffle['max_tickets'] : ''),
        '',
        'https://skulliance.io/staking/raffles.php',
        'raffles', '', 'a040ff'
    );
}

$conn->close();
echo json_encode($result);
