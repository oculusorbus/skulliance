<?php
// auctions_verify.php — Nightly cron: process ended auctions
//
// Phase 1 (processing=0, newly ended): select winner, save winner_id, set processing=1.
//   Do NOT pay creator yet.
//   If no asset_id: pay immediately and mark completed (processing=0).
//
// Phase 2 (processing=1): nightly check of winner's wallet.
//   NFT confirmed → pay creator, mark completed (processing=0).
//   30+ days elapsed → cancel, refund winner (processing=0).
//   Reminders to creator at 7/14/21/28 days (processing stays 1).
set_time_limit(0);
include 'db.php';
include 'message.php';
include 'verify.php';
include_once 'webhooks.php';

$now = date('Y-m-d H:i:s');

// ════════════════════════════════════════════════════════════════════════════
// PHASE 1 — Newly ended auctions: select winner
// ════════════════════════════════════════════════════════════════════════════
$result = $conn->query(
    "SELECT a.*, u.username AS creator_name, u.discord_id AS creator_discord,
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

if ($result && $result->num_rows > 0) {
    while ($auction = $result->fetch_assoc()) {
        $aid            = intval($auction['id']);
        $title          = $auction['title'];
        $img_url        = !empty($auction['image']) ? 'https://skulliance.io/staking/images/auctions/' . $auction['image'] : '';
        $creator_id     = intval($auction['user_id']);
        $prev_bidder    = intval($auction['current_bidder_id']);
        $prev_bid       = floatval($auction['current_bid']);
        $prev_pid       = intval($auction['current_bid_project_id']);
        $winning_bid_id = intval($auction['winning_bid_id']);
        $asset_id       = trim($auction['asset_id'] ?? '');

        echo "Phase 1 — auction #$aid: $title\n";

        if ($prev_bidder && $prev_bid > 0) {
            $wres        = $conn->query("SELECT username AS name, discord_id FROM users WHERE id='$prev_bidder' LIMIT 1");
            $winner      = $wres ? $wres->fetch_assoc() : null;
            $winner_name = $winner ? $winner['name'] : 'Unknown';

            $pr  = $conn->query("SELECT currency FROM projects WHERE id='$prev_pid' LIMIT 1");
            $cur = ($pr && $pr->num_rows) ? strtoupper($pr->fetch_assoc()['currency']) : 'pts';

            if ($asset_id !== '') {
                // ── NFT auction: save winner, set processing=1 for Phase 2 tracking ─
                $conn->query("UPDATE auctions SET winner_id='$prev_bidder', processing=1 WHERE id='$aid'");

                $winner_address = getWinnerAddress($conn, $prev_bidder);
                $addr_line = $winner_address
                    ? "Their receiving address:\n`{$winner_address}`"
                    : "⚠️ **{$winner_name}** has no linked Cardano wallet address — please contact them directly in Discord to obtain their address.";

                if ($winner && $winner['discord_id']) {
                    sendDM($winner['discord_id'],
                        "🎉 Congratulations! You won the auction for **{$title}**!\n\n" .
                        "Your winning bid: **" . number_format($prev_bid) . " $cur**\n" .
                        "NFT Asset: `$asset_id`\n\n" .
                        "The creator has been notified and has **30 days** to send the NFT to your linked Cardano wallet. " .
                        "Delivery will be confirmed automatically on-chain.\n\n" .
                        "⚠️ If the NFT is not received within 30 days, the auction will be canceled and your bid refunded.",
                        $img_url
                    );
                }
                if ($auction['creator_discord']) {
                    sendDM($auction['creator_discord'],
                        "🔨 Your auction **{$title}** has ended!\n\n" .
                        "Winner: **{$winner_name}**\n" .
                        "Winning bid: **" . number_format($prev_bid) . " $cur**\n" .
                        "NFT Asset: `$asset_id`\n\n" .
                        $addr_line . "\n\n" .
                        "Please send the NFT within **30 days**. Your **" . number_format($prev_bid) . " $cur** will be credited once on-chain delivery is confirmed.\n\n" .
                        "⚠️ If delivery is not confirmed within 30 days, the auction will be canceled and the winner refunded.",
                        $img_url
                    );
                }
                discordmsg(
                    '🏆 Auction Ended: ' . $title,
                    "**{$winner_name}** won **{$title}** with a bid of **" . number_format($prev_bid) . " $cur**!\n" .
                    "Creator: **{$auction['creator_name']}** — NFT delivery pending (30-day window). Creator paid upon confirmed on-chain delivery.",
                    $img_url, 'https://skulliance.io/staking/auctions.php',
                    'auctions', '', 'ffc800'
                );
                echo "  Winner: $winner_name — delivery tracking active (30-day window).\n";

            } else {
                // ── No asset_id: pay creator now, mark completed ──────────────────
                updateBalance($conn, $creator_id, $prev_pid, $prev_bid);
                logCredit($conn, $creator_id, $prev_bid, $prev_pid);
                echo "  Credited creator (user_id=$creator_id) with " . number_format($prev_bid) . " $cur\n";

                if ($winning_bid_id) $conn->query("UPDATE bids SET status=2 WHERE id='$winning_bid_id'");
                $conn->query("UPDATE auctions SET winner_id='$prev_bidder', completed=1, processing=0 WHERE id='$aid'");

                if ($winner && $winner['discord_id']) {
                    sendDM($winner['discord_id'],
                        "🎉 Congratulations! You won the auction for **{$title}**!\n\n" .
                        "Your winning bid: **" . number_format($prev_bid) . " $cur**\n\n" .
                        "Please contact the creator **{$auction['creator_name']}** in the Skulliance Discord to arrange delivery of your prize.\n\n" .
                        "⚠️ If you do not hear from the creator within 48 hours, please open a support ticket.",
                        $img_url
                    );
                }
                if ($auction['creator_discord']) {
                    sendDM($auction['creator_discord'],
                        "🔨 Your auction **{$title}** has ended!\n\n" .
                        "Winner: **{$winner_name}**\n" .
                        "Winning bid: **" . number_format($prev_bid) . " $cur**\n\n" .
                        "**" . number_format($prev_bid) . " $cur** has been credited to your balance.\n\n" .
                        "Please arrange prize delivery with **{$winner_name}** via the Skulliance Discord.",
                        $img_url
                    );
                }
                discordmsg(
                    '🏆 Auction Ended: ' . $title,
                    "**{$winner_name}** won **{$title}** with a bid of **" . number_format($prev_bid) . " $cur**!\n" .
                    "Creator: **{$auction['creator_name']}** — **" . number_format($prev_bid) . " $cur** sent to creator. Prize delivery in progress.",
                    $img_url, 'https://skulliance.io/staking/auctions.php',
                    'auctions', '', 'ffc800'
                );
                echo "  Winner: $winner_name — auction #$aid marked completed.\n";
            }

        } else {
            // ── No bids ───────────────────────────────────────────────────────
            $conn->query("UPDATE auctions SET completed=1, processing=0 WHERE id='$aid'");

            if ($auction['creator_discord']) {
                sendDM($auction['creator_discord'],
                    "⏱️ Your auction **{$title}** ended with no bids. The listing has been closed."
                );
            }
            discordmsg(
                '⏱️ Auction Ended (No Bids): ' . $title,
                "**{$title}** by **{$auction['creator_name']}** ended with no bids.",
                $img_url, 'https://skulliance.io/staking/auctions.php',
                'auctions', '', '555555'
            );
            echo "  No bids — auction #$aid closed.\n";
        }
    }
} else {
    echo "No new auctions to process (Phase 1).\n";
}

// ════════════════════════════════════════════════════════════════════════════
// PHASE 2 — Delivery tracking: check winner's wallet nightly
// ════════════════════════════════════════════════════════════════════════════
$result2 = $conn->query(
    "SELECT a.*, u.username AS creator_name, u.discord_id AS creator_discord,
            b.id AS winning_bid_id, b.user_id AS current_bidder_id,
            b.amount AS current_bid, b.project_id AS current_bid_project_id
     FROM auctions a
     INNER JOIN users u ON u.id = a.user_id
     LEFT JOIN bids b ON b.auction_id = a.id AND b.status = 1
     WHERE a.processing = 1
       AND a.completed = 0
       AND a.canceled = 0"
);

if (!$result2 || $result2->num_rows === 0) {
    echo "No auctions in delivery tracking (Phase 2).\n";
    $conn->close();
    echo "Done.\n";
    exit;
}

while ($auction = $result2->fetch_assoc()) {
    $aid            = intval($auction['id']);
    $title          = $auction['title'];
    $img_url        = !empty($auction['image']) ? 'https://skulliance.io/staking/images/auctions/' . $auction['image'] : '';
    $creator_id     = intval($auction['user_id']);
    $winner_id      = intval($auction['winner_id'] ?? 0);
    $prev_bidder    = intval($auction['current_bidder_id']);
    $prev_bid       = floatval($auction['current_bid']);
    $prev_pid       = intval($auction['current_bid_project_id']);
    $winning_bid_id = intval($auction['winning_bid_id']);
    $asset_id       = trim($auction['asset_id'] ?? '');

    echo "Phase 2 — auction #$aid: $title\n";

    if ($asset_id === '') {
        // No asset_id — shouldn't be stuck here; mark completed
        $conn->query("UPDATE auctions SET completed=1, processing=0 WHERE id='$aid'");
        echo "  Phase 2 (no asset): marked completed.\n";
        continue;
    }

    $days_elapsed   = (time() - strtotime($auction['end_date'])) / 86400;
    $days_remaining = ceil(30 - $days_elapsed);
    echo "  Checking delivery (day " . round($days_elapsed, 1) . " of 30)\n";

    $wres        = $conn->query("SELECT username AS name, discord_id FROM users WHERE id='$winner_id' LIMIT 1");
    $winner      = $wres ? $wres->fetch_assoc() : null;
    $winner_name = $winner ? $winner['name'] : 'Unknown';

    $pr  = $conn->query("SELECT currency FROM projects WHERE id='$prev_pid' LIMIT 1");
    $cur = ($pr && $pr->num_rows) ? strtoupper($pr->fetch_assoc()['currency']) : 'pts';

    // Check winner's wallet for the NFT
    $winner_stake   = getCreatorStakeAddress($conn, $winner_id);
    $winner_address = getWinnerAddress($conn, $winner_id);
    $nft_delivered  = false;
    if ($winner_stake) {
        echo "  Checking winner wallet $winner_stake for asset $asset_id…\n";
        $nft_delivered = verifyAssetInWallet($winner_stake, $asset_id);
    }

    if ($nft_delivered) {
        // ── Delivery confirmed ────────────────────────────────────────────────
        echo "  NFT confirmed in winner's wallet. Processing payout.\n";

        updateBalance($conn, $creator_id, $prev_pid, $prev_bid);
        logCredit($conn, $creator_id, $prev_bid, $prev_pid);
        echo "  Credited creator (user_id=$creator_id) with " . number_format($prev_bid) . " $cur\n";

        if ($winning_bid_id) $conn->query("UPDATE bids SET status=2 WHERE id='$winning_bid_id'");
        $conn->query("UPDATE auctions SET completed=1, processing=0 WHERE id='$aid'");

        if ($winner && $winner['discord_id']) {
            sendDM($winner['discord_id'],
                "✅ Your auction prize for **{$title}** has been confirmed in your wallet!\n\n" .
                "NFT Asset: `$asset_id`\n\n" .
                "Thank you for using Skulliance. Enjoy your prize!",
                $img_url
            );
        }
        if ($auction['creator_discord']) {
            sendDM($auction['creator_discord'],
                "✅ Delivery confirmed for **{$title}**!\n\n" .
                "The NFT (`$asset_id`) has been verified in **{$winner_name}**'s wallet.\n\n" .
                "**" . number_format($prev_bid) . " $cur** has been credited to your balance.",
                $img_url
            );
        }
        discordmsg(
            '✅ Auction Prize Delivered: ' . $title,
            "The NFT for **{$title}** has been confirmed received by **{$winner_name}**!\n" .
            "Creator **{$auction['creator_name']}** has been paid **" . number_format($prev_bid) . " $cur**.",
            $img_url, 'https://skulliance.io/staking/auctions.php',
            'auctions', '', '00c8a0'
        );
        echo "  Auction #$aid delivery confirmed and completed.\n";

    } elseif ($days_elapsed >= 30) {
        // ── 30-day timeout — cancel and refund winner ─────────────────────────
        echo "  30-day deadline passed. Canceling and refunding winner.\n";

        if ($prev_bidder && $prev_bid > 0 && $prev_pid) {
            updateBalance($conn, $prev_bidder, $prev_pid, $prev_bid);
            logCredit($conn, $prev_bidder, $prev_bid, $prev_pid);
            if ($winning_bid_id) $conn->query("UPDATE bids SET status=0 WHERE id='$winning_bid_id'");
        }
        $conn->query("UPDATE auctions SET canceled=1, processing=0 WHERE id='$aid'");

        if ($winner && $winner['discord_id']) {
            sendDM($winner['discord_id'],
                "❌ The auction **{$title}** has been canceled — the creator did not deliver the NFT within the 30-day window.\n\n" .
                "Your bid of **" . number_format($prev_bid) . " $cur** has been fully refunded. Please open a support ticket if you need assistance.",
                $img_url
            );
        }
        if ($auction['creator_discord']) {
            sendDM($auction['creator_discord'],
                "❌ Your auction **{$title}** has been automatically canceled because the NFT (`$asset_id`) was not confirmed in **{$winner_name}**'s wallet within 30 days.\n\n" .
                "The winner has been refunded. Please contact support if you believe this is an error.",
                $img_url
            );
        }
        discordmsg(
            '❌ Auction Canceled (Delivery Timeout): ' . $title,
            "**{$title}** by **{$auction['creator_name']}** was canceled — NFT delivery to **{$winner_name}** was not confirmed within 30 days. The winner has been refunded.",
            $img_url, 'https://skulliance.io/staking/auctions.php',
            'auctions', '', 'ff3333'
        );
        echo "  Auction #$aid canceled (delivery timeout).\n";

    } else {
        // ── Daily creator reminder + milestone winner reminders (processing stays 1)
        $day_num = max(1, round($days_elapsed));

        // Urgency prefix scales with days remaining
        if ($days_remaining <= 3) {
            $urgency_prefix = "🚨 **CRITICAL — {$days_remaining} days left before auto-cancellation!**\n\n";
        } elseif ($days_remaining <= 7) {
            $urgency_prefix = "⚠️ **Urgent — only {$days_remaining} days remaining.**\n\n";
        } else {
            $urgency_prefix = "";
        }

        $addr_block = $winner_address
            ? "**Send to (winner's wallet):**\n`{$winner_address}`"
            : "⚠️ **{$winner_name}** has no linked Cardano wallet on file. Contact them directly in Discord to obtain their address before your deadline.";

        $creator_daily =
            $urgency_prefix .
            "⏰ **Auction Delivery Reminder — Day {$day_num} of 30**\n\n" .
            "Your auction **{$title}** has a winner waiting for their NFT.\n\n" .
            "**Winner:** {$winner_name}\n" .
            "**NFT Asset ID:** `{$asset_id}`\n" .
            "**Winning bid:** " . number_format($prev_bid) . " {$cur} _(paid to you upon confirmed delivery)_\n\n" .
            $addr_block . "\n\n" .
            "Once the NFT is confirmed on-chain in **{$winner_name}**'s wallet, your **" . number_format($prev_bid) . " {$cur}** will be credited automatically. " .
            "If delivery is not confirmed within **{$days_remaining} days**, the auction will be auto-canceled and the winner refunded.";

        if ($auction['creator_discord']) {
            sendDM($auction['creator_discord'], $creator_daily, $img_url);
        }
        echo "  Sent daily creator reminder (day {$day_num} of 30, {$days_remaining} remaining).\n";

        // Milestone reminders to winner at days 7, 14, 21, 28
        $winner_msg = null;
        if ($days_elapsed >= 28 && $days_elapsed < 29) {
            $winner_msg = "⚠️ Final reminder: You won **{$title}**! The creator has only **{$days_remaining} days** left to deliver the NFT (`{$asset_id}`) to your wallet. If not confirmed on-chain within 30 days, the auction will be canceled and your bid refunded.";
            echo "  Sent 28-day winner reminder.\n";
        } elseif ($days_elapsed >= 21 && $days_elapsed < 22) {
            $winner_msg = "⏰ Reminder: You won **{$title}**! The creator has **{$days_remaining} days** remaining to deliver the NFT (`{$asset_id}`) to your wallet.";
            echo "  Sent 21-day winner reminder.\n";
        } elseif ($days_elapsed >= 14 && $days_elapsed < 15) {
            $winner_msg = "⏰ Reminder: You won **{$title}**! The creator has **{$days_remaining} days** remaining to deliver the NFT to your wallet.";
            echo "  Sent 14-day winner reminder.\n";
        } elseif ($days_elapsed >= 7 && $days_elapsed < 8) {
            $winner_msg = "⏰ Reminder: You won **{$title}**! The creator has **{$days_remaining} days** remaining to deliver the NFT to your wallet.";
            echo "  Sent 7-day winner reminder.\n";
        }
        if ($winner_msg && $winner && $winner['discord_id']) sendDM($winner['discord_id'], $winner_msg, $img_url);
    }
}

$conn->close();
echo "Done.\n";
