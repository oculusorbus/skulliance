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
$auction_id = intval($_POST['auction_id'] ?? 0);
$amount     = intval($_POST['amount'] ?? 0);
$project_id = intval($_POST['project_id'] ?? 0);

if (!$auction_id || $amount < 1 || !$project_id) {
    ob_clean(); echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
}

$auction_before = getAuction($conn, $auction_id);
if ($auction_before && intval($auction_before['user_id']) === $user_id) { ob_clean(); echo json_encode(['success'=>false,'message'=>'You cannot bid on your own auction.']); exit; }

$prev_bidder_id = $auction_before ? intval($auction_before['current_bidder_id']) : 0;
$prev_bid_amt   = $auction_before ? floatval($auction_before['current_bid']) : 0;
$prev_bid_pid   = $auction_before ? intval($auction_before['current_bid_project_id']) : 0;

$result = placeBid($conn, $auction_id, $user_id, $amount, $project_id);

if ($result['success']) {
    $auction     = getAuction($conn, $auction_id);
    $bidder_name = $_SESSION['userData']['name'] ?? 'Unknown';
    $pr          = $conn->query("SELECT currency FROM projects WHERE id='$project_id' LIMIT 1");
    $cur         = ($pr && $pr->num_rows) ? strtoupper($pr->fetch_assoc()['currency']) : 'pts';

    if ($prev_bidder_id && $prev_bidder_id !== $user_id && $prev_bid_amt > 0) {
        $ures = $conn->query("SELECT discord_id FROM users WHERE id='$prev_bidder_id' LIMIT 1");
        if ($ures && $ures->num_rows) {
            $urow = $ures->fetch_assoc();
            if (!empty($urow['discord_id'])) {
                $pres = $conn->query("SELECT currency FROM projects WHERE id='$prev_bid_pid' LIMIT 1");
                $pcur = ($pres && $pres->num_rows) ? strtoupper($pres->fetch_assoc()['currency']) : 'pts';
                sendDM($urow['discord_id'],
                    "⚡ You've been outbid on **" . ($auction['title'] ?? '') . "**!\n\n" .
                    "New bid: **" . number_format($amount) . " $cur**\n" .
                    "Your bid of **" . number_format($prev_bid_amt) . " $pcur** has been refunded.\n\n" .
                    "Visit Skulliance to place a higher bid!"
                );
            }
        }
    }

    discordmsg(
        '💰 New Bid: ' . ($auction['title'] ?? ''),
        "**$bidder_name** bid **" . number_format($amount) . " $cur** on **" . ($auction['title'] ?? '') . "**",
        !empty($auction['image_path']) ? 'https://skulliance.io/staking/' . $auction['image_path'] : '',
        'https://skulliance.io/staking/auctions.php',
        'auctions', '', '00c8a0'
    );
}

$conn->close();
ob_clean();
echo json_encode($result);
