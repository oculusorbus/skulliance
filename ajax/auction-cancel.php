<?php
include '../db.php';
include '../message.php';
include '../verify.php';
include '../webhooks.php';
header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }

$user_id    = intval($_SESSION['userData']['user_id']);
$auction_id = intval($_POST['auction_id'] ?? 0);

if (!$auction_id) { echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }

// Load auction before canceling so we can capture bidder info and image for notifications
$auction = getAuction($conn, $auction_id);

$result = cancelAuction($conn, $auction_id, $user_id);

if ($result['success'] && $auction) {
    $title   = $auction['title'] ?? '';
    $img_url = !empty($auction['image_path']) ? 'https://skulliance.io/staking/' . $auction['image_path'] : '';
    $creator = $_SESSION['userData']['name'] ?? 'Unknown';

    // DM the outbid bidder (cancelAuction already refunded them)
    $prev_bidder = intval($auction['current_bidder_id'] ?? 0);
    $prev_bid    = floatval($auction['current_bid'] ?? 0);
    $prev_pid    = intval($auction['current_bid_project_id'] ?? 0);
    if ($prev_bidder && $prev_bid > 0 && $prev_bidder !== $user_id) {
        $ures = $conn->query("SELECT discord_id FROM users WHERE id='$prev_bidder' LIMIT 1");
        if ($ures && $ures->num_rows) {
            $urow = $ures->fetch_assoc();
            if ($urow['discord_id']) {
                $pres = $conn->query("SELECT currency FROM projects WHERE id='$prev_pid' LIMIT 1");
                $pcur = ($pres && $pres->num_rows) ? strtoupper($pres->fetch_assoc()['currency']) : 'pts';
                sendDM($urow['discord_id'],
                    "🚫 The auction **{$title}** was canceled by the creator.\n\n" .
                    "Your bid of **" . number_format($prev_bid) . " $pcur** has been fully refunded to your balance."
                );
            }
        }
    }

    $conn->close();

    discordmsg(
        '🚫 Auction Canceled: ' . $title,
        "**$creator** canceled their auction **{$title}**." .
        ($prev_bidder && $prev_bid > 0 ? " The leading bid has been refunded." : " No bids had been placed."),
        $img_url,
        'https://skulliance.io/staking/auctions.php',
        'auctions', '', 'ff6b00'
    );
} else {
    $conn->close();
}

echo json_encode($result);
