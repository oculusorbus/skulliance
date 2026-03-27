<?php
include '../db.php';
include '../message.php';
include '../verify.php';
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

$result = placeBid($conn, $auction_id, $user_id, $amount, $project_id);

if ($result['success']) {
    // Notify previous leader by DM if outbid
    $auction = getAuction($conn, $auction_id);
    // Notify new leader via DM
    $discord_id = $_SESSION['userData']['discord_id'] ?? null;
    if ($discord_id) {
        $pr = $conn->query("SELECT name, currency FROM projects WHERE id='$project_id' LIMIT 1");
        $cur = 'pts';
        if ($pr && $pr->num_rows) { $row = $pr->fetch_assoc(); $cur = strtoupper($row['currency']); }
        // sendDM is available from verify.php
        // No DM to self needed — just Discord channel notification
    }
    // Webhook notification
    $bidder_name = $_SESSION['userData']['name'] ?? 'Unknown';
    $pr2 = $conn->query("SELECT name, currency FROM projects WHERE id='$project_id' LIMIT 1");
    $cur2 = 'pts'; $proj_name = '';
    if ($pr2 && $pr2->num_rows) { $row2 = $pr2->fetch_assoc(); $cur2 = strtoupper($row2['currency']); $proj_name = $row2['name']; }
    discordmsg(
        '💰 New Bid: ' . htmlspecialchars($auction['title'] ?? ''),
        "**" . $bidder_name . "** bid **" . number_format($amount) . " $cur2** on **" . htmlspecialchars($auction['title'] ?? '') . "**",
        !empty($auction['image_path']) ? 'https://skulliance.io/staking/' . $auction['image_path'] : '',
        'https://skulliance.io/staking/auctions.php',
        'auctions', '', '00c8a0'
    );
}

$conn->close();
echo json_encode($result);
