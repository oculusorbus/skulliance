<?php
// auctions_verify.php — Nightly cron: process ended auctions
// Runs after midnight CST when end_date has passed.
// Marks processing=1, sends DMs to winner and creator, marks completed=1.
set_time_limit(0);
include 'db.php';
include 'message.php';
include 'verify.php';
include 'webhooks.php';

$now = date('Y-m-d H:i:s');

// Find ended auctions not yet processed or completed
$result = $conn->query("SELECT a.*, u.name AS creator_name, u.discord_id AS creator_discord
                         FROM auctions a
                         INNER JOIN users u ON u.id = a.user_id
                         WHERE a.end_date <= '$now'
                           AND a.completed = 0
                           AND a.processing = 0
                           AND a.canceled = 0");

if (!$result || $result->num_rows === 0) {
    echo "No auctions to process.\n";
    $conn->close();
    exit;
}

while ($auction = $result->fetch_assoc()) {
    $aid = intval($auction['id']);
    echo "Processing auction #$aid: {$auction['title']}\n";

    // Mark as processing to prevent double-run
    $conn->query("UPDATE auctions SET processing=1 WHERE id='$aid'");

    $winner_id  = intval($auction['current_bidder_id']);
    $won_amount = floatval($auction['current_bid']);
    $won_pid    = intval($auction['current_bid_project_id']);

    if ($winner_id && $won_amount > 0) {
        // Fetch winner info
        $wres = $conn->query("SELECT name, discord_id FROM users WHERE id='$winner_id' LIMIT 1");
        $winner = $wres ? $wres->fetch_assoc() : null;

        // Fetch currency name
        $pr = $conn->query("SELECT name, currency FROM projects WHERE id='$won_pid' LIMIT 1");
        $cur = 'pts'; $proj_name = '';
        if ($pr && $pr->num_rows) { $row = $pr->fetch_assoc(); $cur = strtoupper($row['currency']); $proj_name = $row['name']; }

        $img_url = !empty($auction['image_path']) ? 'https://skulliance.io/staking/' . $auction['image_path'] : '';
        $title   = $auction['title'];

        // DM winner
        if ($winner && $winner['discord_id']) {
            $msg = "🎉 Congratulations! You won the auction for **{$title}**!\n\n"
                 . "Your winning bid: **" . number_format($won_amount) . " $cur**\n"
                 . "Please contact the creator **{$auction['creator_name']}** in the Skulliance Discord to arrange delivery of your prize.\n\n"
                 . "⚠️ If you do not hear from the creator within 48 hours, please open a support ticket.";
            sendDM($winner['discord_id'], $msg);
        }

        // DM creator
        if ($auction['creator_discord']) {
            $winner_name = $winner ? $winner['name'] : 'Unknown';
            $msg = "🔨 Your auction **{$title}** has ended!\n\n"
                 . "Winner: **{$winner_name}**\n"
                 . "Winning bid: **" . number_format($won_amount) . " $cur**\n\n"
                 . "Please arrange delivery of your prize to the winner via Skulliance Discord. "
                 . "Ensure DMs from Skulliance members are enabled so winners can reach you.";
            sendDM($auction['creator_discord'], $msg);
        }

        // Webhook announcement
        $winner_name = $winner ? $winner['name'] : 'Unknown';
        discordmsg(
            '🏆 Auction Ended: ' . $title,
            "**{$winner_name}** won **{$title}** with a bid of **" . number_format($won_amount) . " $cur**!\n" .
            "Creator: **{$auction['creator_name']}** — please arrange prize delivery.",
            $img_url,
            'https://skulliance.io/staking/auctions.php',
            'auctions', '', 'ffc800'
        );

        echo "  Winner: {$winner_name} — " . number_format($won_amount) . " $cur\n";
    } else {
        // No bids — auction ends with no winner
        discordmsg(
            '⏱️ Auction Ended (No Bids): ' . $auction['title'],
            "**{$auction['title']}** by **{$auction['creator_name']}** ended with no bids.",
            !empty($auction['image_path']) ? 'https://skulliance.io/staking/' . $auction['image_path'] : '',
            'https://skulliance.io/staking/auctions.php',
            'auctions', '', '555555'
        );
        echo "  No bids — ending with no winner.\n";
    }

    // Mark completed
    $conn->query("UPDATE auctions SET completed=1, processing=0 WHERE id='$aid'");
    echo "  Auction #$aid marked completed.\n";
}

$conn->close();
echo "Done.\n";
