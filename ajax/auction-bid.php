<?php
include '../db.php';
include '../message.php';
include '../webhooks.php';
header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }

$user_id    = intval($_SESSION['userData']['user_id']);
$auction_id = intval($_POST['auction_id'] ?? 0);
$amount     = floatval($_POST['amount'] ?? 0);
$project_id = intval($_POST['project_id'] ?? 0);

if (!$auction_id || $amount < 1 || !$project_id) {
    echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
}

// Load auction before placing bid to capture the current leader for outbid DM
$auction_before  = getAuction($conn, $auction_id);
$prev_bidder_id  = intval($auction_before['current_bidder_id'] ?? 0);
$prev_bid_amt    = floatval($auction_before['current_bid'] ?? 0);
$prev_bid_pid    = intval($auction_before['current_bid_project_id'] ?? 0);

$result = placeBid($conn, $auction_id, $user_id, $amount, $project_id);

if ($result['success']) {
    $auction     = getAuction($conn, $auction_id);
    $bidder_name = $_SESSION['userData']['name'] ?? 'Unknown';
    $pr          = $conn->query("SELECT currency FROM projects WHERE id='$project_id' LIMIT 1");
    $cur         = ($pr && $pr->num_rows) ? strtoupper($pr->fetch_assoc()['currency']) : 'pts';

    // DM the outbid previous leader
    if ($prev_bidder_id && $prev_bidder_id !== $user_id && $prev_bid_amt > 0) {
        $ures = $conn->query("SELECT discord_id FROM users WHERE id='$prev_bidder_id' LIMIT 1");
        if ($ures && $ures->num_rows) {
            $urow = $ures->fetch_assoc();
            if ($urow['discord_id']) {
                $pres = $conn->query("SELECT currency FROM projects WHERE id='$prev_bid_pid' LIMIT 1");
                $pcur = ($pres && $pres->num_rows) ? strtoupper($pres->fetch_assoc()['currency']) : 'pts';
                sendDM($urow['discord_id'],
                    "⚡ You've been outbid on **" . htmlspecialchars($auction['title'] ?? '') . "**!\n\n" .
                    "New bid: **" . number_format($amount) . " $cur**\n" .
                    "Your bid of **" . number_format($prev_bid_amt) . " $pcur** has been refunded.\n\n" .
                    "Visit Skulliance to place a higher bid!"
                );
            }
        }
    }

    // Discord channel notification
    discordmsg(
        '💰 New Bid: ' . htmlspecialchars($auction['title'] ?? ''),
        "**$bidder_name** bid **" . number_format($amount) . " $cur** on **" . htmlspecialchars($auction['title'] ?? '') . "**",
        !empty($auction['image_path']) ? 'https://skulliance.io/staking/' . $auction['image_path'] : '',
        'https://skulliance.io/staking/auctions.php',
        'auctions', '', '00c8a0'
    );
}

$conn->close();
echo json_encode($result);
