<?php
// auctions_verify.php — Nightly cron: process ended auctions
// 1. If an asset_id is set, verify via Koios that the creator still holds the NFT.
//    If not found: cancel, refund current bidder, DM creator, skip to next.
// 2. If verified (or no asset_id): mark completed, DM winner and creator, announce to Discord.
set_time_limit(0);
include 'db.php';
include 'message.php';
include 'verify.php';
include 'webhooks.php';

$now = date('Y-m-d H:i:s');

// Join bids (status=1) to get current leader inline
$result = $conn->query(
    "SELECT a.*, u.name AS creator_name, u.discord_id AS creator_discord,
            b.id AS winning_bid_id, b.user_id AS current_bidder_id,
            b.amount AS current_bid, b.project_id AS current_bid_project_id
     FROM auctions a
     INNER JOIN users u ON u.id = a.user_id
     LEFT JOIN bids b ON b.auction_id = a.id AND b.status = 1
     WHERE a.end_date <= '$now'
       AND a.completed = 0
       AND a.processing = 0
       AND a.canceled = 0"
);

if (!$result || $result->num_rows === 0) {
    echo "No auctions to process.\n";
    $conn->close();
    exit;
}

while ($auction = $result->fetch_assoc()) {
    $aid             = intval($auction['id']);
    $title           = $auction['title'];
    $img_url         = !empty($auction['image_path']) ? 'https://skulliance.io/staking/' . $auction['image_path'] : '';
    $creator_id      = intval($auction['user_id']);
    $prev_bidder     = intval($auction['current_bidder_id']);
    $prev_bid        = floatval($auction['current_bid']);
    $prev_pid        = intval($auction['current_bid_project_id']);
    $winning_bid_id  = intval($auction['winning_bid_id']);

    echo "Processing auction #$aid: $title\n";

    // Mark as processing to prevent double-run
    $conn->query("UPDATE auctions SET processing=1 WHERE id='$aid'");

    // ── Step 1: Blockchain verification (only when asset_id is present) ───────
    $asset_id = trim($auction['asset_id'] ?? '');
    if ($asset_id !== '') {
        $stake_address = getCreatorStakeAddress($conn, $creator_id);

        if (!$stake_address) {
            // Creator has no linked wallet — cannot verify ownership; flag and skip
            echo "  WARNING: No wallet found for creator (user_id=$creator_id). Cannot verify NFT ownership.\n";
            if ($auction['creator_discord']) {
                sendDM($auction['creator_discord'],
                    "⚠️ Your auction **{$title}** could not be completed because we were unable to verify NFT ownership — your Cardano wallet is not linked to your Skulliance account.\n\n" .
                    "The auction has been put on hold. Please link your wallet and contact support."
                );
            }
            discordmsg(
                '⚠️ Auction Verification Failed: ' . $title,
                "**{$auction['creator_name']}**'s auction **{$title}** could not be processed — no linked wallet found to verify NFT ownership.",
                $img_url, 'https://skulliance.io/staking/auctions.php',
                'auctions', '', 'ff6b00'
            );
            // Leave processing=1 as a soft hold; admin must manually resolve
            continue;
        }

        echo "  Verifying asset $asset_id in wallet $stake_address via Koios…\n";
        $asset_found = verifyAssetInWallet($stake_address, $asset_id);

        if (!$asset_found) {
            echo "  FAILED: Asset not found in creator's wallet. Canceling and refunding.\n";

            // Refund current bidder
            if ($prev_bidder && $prev_bid > 0 && $prev_pid) {
                updateBalance($conn, $prev_bidder, $prev_pid, $prev_bid);
                logCredit($conn, $prev_bidder, $prev_bid, $prev_pid);
                $conn->query("UPDATE bids SET status=0 WHERE id='$winning_bid_id'");

                $wres  = $conn->query("SELECT username AS name, discord_id FROM users WHERE id='$prev_bidder' LIMIT 1");
                $loser = $wres ? $wres->fetch_assoc() : null;
                if ($loser && $loser['discord_id']) {
                    $pr  = $conn->query("SELECT currency FROM projects WHERE id='$prev_pid' LIMIT 1");
                    $cur = ($pr && $pr->num_rows) ? strtoupper($pr->fetch_assoc()['currency']) : 'pts';
                    sendDM($loser['discord_id'],
                        "❌ The auction **{$title}** was canceled because the creator could not verify ownership of the NFT at close time.\n\n" .
                        "Your bid of **" . number_format($prev_bid) . " $cur** has been fully refunded."
                    );
                }
            }

            // Notify creator
            if ($auction['creator_discord']) {
                sendDM($auction['creator_discord'],
                    "❌ Your auction **{$title}** was automatically canceled because the NFT (asset: $asset_id) could not be found in your linked wallet at close time.\n\n" .
                    "If this is an error, please contact support. Any bids have been refunded."
                );
            }

            discordmsg(
                '❌ Auction Canceled (NFT Not Verified): ' . $title,
                "**{$title}** by **{$auction['creator_name']}** was canceled — the NFT could not be verified in the creator's wallet at close time. All bids have been refunded.",
                $img_url, 'https://skulliance.io/staking/auctions.php',
                'auctions', '', 'ff3333'
            );

            $conn->query("UPDATE auctions SET canceled=1, processing=0 WHERE id='$aid'");
            echo "  Auction #$aid canceled.\n";
            continue;
        }

        echo "  Verified: asset confirmed in creator's wallet.\n";
    }

    // ── Step 2: Process winner ────────────────────────────────────────────────
    if ($prev_bidder && $prev_bid > 0) {
        $wres        = $conn->query("SELECT username AS name, discord_id FROM users WHERE id='$prev_bidder' LIMIT 1");
        $winner      = $wres ? $wres->fetch_assoc() : null;
        $winner_name = $winner ? $winner['name'] : 'Unknown';

        $pr  = $conn->query("SELECT name, currency FROM projects WHERE id='$prev_pid' LIMIT 1");
        $cur = 'pts';
        if ($pr && $pr->num_rows) { $row = $pr->fetch_assoc(); $cur = strtoupper($row['currency']); }

        // Mark winning bid as won; store winner_id on auction
        if ($winning_bid_id) $conn->query("UPDATE bids SET status=2 WHERE id='$winning_bid_id'");
        $conn->query("UPDATE auctions SET winner_id='$prev_bidder', completed=1, processing=0 WHERE id='$aid'");

        // DM winner
        if ($winner && $winner['discord_id']) {
            $dm = "🎉 Congratulations! You won the auction for **{$title}**!\n\n" .
                  "Your winning bid: **" . number_format($prev_bid) . " $cur**\n" .
                  ($asset_id !== ''
                      ? "NFT Asset ID: `$asset_id`\n\n"
                      : "\n") .
                  "Please contact the creator **{$auction['creator_name']}** in the Skulliance Discord to arrange delivery of your prize.\n\n" .
                  "⚠️ If you do not hear from the creator within 48 hours, please open a support ticket.";
            sendDM($winner['discord_id'], $dm);
        }

        // DM creator
        if ($auction['creator_discord']) {
            $dm = "🔨 Your auction **{$title}** has ended!\n\n" .
                  "Winner: **{$winner_name}**\n" .
                  "Winning bid: **" . number_format($prev_bid) . " $cur**\n" .
                  ($asset_id !== ''
                      ? "NFT Asset ID: `$asset_id`\n\n"
                      : "\n") .
                  "Please send the prize to the winner via the Skulliance Discord. " .
                  "Ensure your DMs from Skulliance members are open so the winner can contact you.";
            sendDM($auction['creator_discord'], $dm);
        }

        // Discord announcement
        discordmsg(
            '🏆 Auction Ended: ' . $title,
            "**{$winner_name}** won **{$title}** with a bid of **" . number_format($prev_bid) . " $cur**!\n" .
            "Creator: **{$auction['creator_name']}** — please arrange prize delivery in the Discord.",
            $img_url, 'https://skulliance.io/staking/auctions.php',
            'auctions', '', 'ffc800'
        );

        echo "  Winner: $winner_name — " . number_format($prev_bid) . " $cur\n";
    } else {
        // No bids
        $conn->query("UPDATE auctions SET completed=1, processing=0 WHERE id='$aid'");
        discordmsg(
            '⏱️ Auction Ended (No Bids): ' . $title,
            "**{$title}** by **{$auction['creator_name']}** ended with no bids.",
            $img_url, 'https://skulliance.io/staking/auctions.php',
            'auctions', '', '555555'
        );
        echo "  No bids — ended with no winner.\n";
    }

    echo "  Auction #$aid marked completed.\n";
}

$conn->close();
echo "Done.\n";
