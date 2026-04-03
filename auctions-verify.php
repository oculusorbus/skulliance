<?php
// auctions_verify.php — Nightly cron: process ended auctions
//
// Phase 1 (winner_id IS NULL): verify creator holds NFT at close, select winner,
//   save winner_id, reset processing=0. Do NOT pay creator yet.
//   If no asset_id: pay immediately and mark completed (no on-chain delivery to track).
//
// Phase 2 (winner_id IS NOT NULL, completed=0): nightly check of winner's wallet.
//   NFT confirmed → pay creator, mark completed.
//   30+ days elapsed → cancel, refund winner.
//   Reminders to creator at 7/14/21/28 days.
set_time_limit(0);
include 'db.php';
include 'message.php';
include 'verify.php';
include_once 'webhooks.php';

$now = date('Y-m-d H:i:s');

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
    $aid              = intval($auction['id']);
    $title            = $auction['title'];
    $img_url          = !empty($auction['image']) ? 'https://skulliance.io/staking/images/auctions/' . $auction['image'] : '';
    $creator_id       = intval($auction['user_id']);
    $winner_id_stored = intval($auction['winner_id'] ?? 0);
    $prev_bidder      = intval($auction['current_bidder_id']);
    $prev_bid         = floatval($auction['current_bid']);
    $prev_pid         = intval($auction['current_bid_project_id']);
    $winning_bid_id   = intval($auction['winning_bid_id']);
    $asset_id         = trim($auction['asset_id'] ?? '');

    echo "Processing auction #$aid: $title\n";
    $conn->query("UPDATE auctions SET processing=1 WHERE id='$aid'");

    // ── PHASE 2: Winner already selected — check on-chain delivery ────────────
    if ($winner_id_stored > 0) {
        if ($asset_id === '') {
            // No asset_id — shouldn't be stuck here; mark completed
            $conn->query("UPDATE auctions SET completed=1, processing=0 WHERE id='$aid'");
            echo "  Phase 2 (no asset): marked completed.\n";
            continue;
        }

        $days_elapsed   = (time() - strtotime($auction['end_date'])) / 86400;
        $days_remaining = ceil(30 - $days_elapsed);
        echo "  Phase 2: checking delivery (day " . round($days_elapsed, 1) . " of 30)\n";

        $wres        = $conn->query("SELECT username AS name, discord_id FROM users WHERE id='$winner_id_stored' LIMIT 1");
        $winner      = $wres ? $wres->fetch_assoc() : null;
        $winner_name = $winner ? $winner['name'] : 'Unknown';

        $pr  = $conn->query("SELECT currency FROM projects WHERE id='$prev_pid' LIMIT 1");
        $cur = ($pr && $pr->num_rows) ? strtoupper($pr->fetch_assoc()['currency']) : 'pts';

        // Check winner's wallet for the NFT
        $winner_stake  = getCreatorStakeAddress($conn, $winner_id_stored);
        $nft_delivered = false;
        if ($winner_stake) {
            echo "  Checking winner wallet $winner_stake for asset $asset_id…\n";
            $nft_delivered = verifyAssetInWallet($winner_stake, $asset_id);
        }

        if ($nft_delivered) {
            // ── Delivery confirmed ────────────────────────────────────────────
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
                    "Thank you for using Skulliance. Enjoy your prize!"
                );
            }
            if ($auction['creator_discord']) {
                sendDM($auction['creator_discord'],
                    "✅ Delivery confirmed for **{$title}**!\n\n" .
                    "The NFT (`$asset_id`) has been verified in **{$winner_name}**'s wallet.\n\n" .
                    "**" . number_format($prev_bid) . " $cur** has been credited to your balance."
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
            // ── 30-day timeout — cancel and refund winner ─────────────────────
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
                    "Your bid of **" . number_format($prev_bid) . " $cur** has been fully refunded. Please open a support ticket if you need assistance."
                );
            }
            if ($auction['creator_discord']) {
                sendDM($auction['creator_discord'],
                    "❌ Your auction **{$title}** has been automatically canceled because the NFT (`$asset_id`) was not confirmed in **{$winner_name}**'s wallet within 30 days.\n\n" .
                    "The winner has been refunded. Please contact support if you believe this is an error."
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
            // ── Still within grace period — send milestone reminders ───────────
            $conn->query("UPDATE auctions SET processing=0 WHERE id='$aid'");

            $creator_msg = null;
            $winner_msg  = null;

            if ($days_elapsed >= 28 && $days_elapsed < 29) {
                $creator_msg = "⚠️ **Final warning** — only **{$days_remaining} days** left to deliver the NFT for **{$title}** to **{$winner_name}**.\n\nAsset: `$asset_id`\n\nIf delivery is not confirmed on-chain within 30 days of auction close, the auction will be canceled and the winner refunded.";
                $winner_msg  = "⏰ Reminder: You won **{$title}**! The creator has **{$days_remaining} days** remaining to deliver the NFT (`$asset_id`) to your wallet.";
                echo "  Sent 28-day reminder.\n";
            } elseif ($days_elapsed >= 21 && $days_elapsed < 22) {
                $creator_msg = "⚠️ Reminder — **{$days_remaining} days** remaining to deliver the NFT for **{$title}** to **{$winner_name}**.\n\nAsset: `$asset_id`";
                $winner_msg  = "⏰ Reminder: You won **{$title}**! The creator has **{$days_remaining} days** remaining to deliver the NFT.";
                echo "  Sent 21-day reminder.\n";
            } elseif ($days_elapsed >= 14 && $days_elapsed < 15) {
                $creator_msg = "⏰ Reminder — **{$days_remaining} days** remaining to deliver the NFT for **{$title}** to **{$winner_name}**.\n\nAsset: `$asset_id`";
                $winner_msg  = "⏰ Reminder: You won **{$title}**! The creator has **{$days_remaining} days** remaining to deliver the NFT.";
                echo "  Sent 14-day reminder.\n";
            } elseif ($days_elapsed >= 7 && $days_elapsed < 8) {
                $creator_msg = "⏰ Reminder — **{$days_remaining} days** remaining to deliver the NFT for **{$title}** to **{$winner_name}**.\n\nPlease send `$asset_id` to **{$winner_name}**'s linked Cardano wallet as soon as possible.";
                $winner_msg  = "⏰ Reminder: You won **{$title}**! The creator has **{$days_remaining} days** remaining to deliver the NFT to your wallet.";
                echo "  Sent 7-day reminder.\n";
            } else {
                echo "  Delivery pending (day " . round($days_elapsed) . " of 30).\n";
            }

            if ($creator_msg && $auction['creator_discord']) sendDM($auction['creator_discord'], $creator_msg);
            if ($winner_msg && $winner && $winner['discord_id']) sendDM($winner['discord_id'], $winner_msg);
        }

        continue;
    }

    // ── PHASE 1: First close — verify creator holds NFT, select winner ────────
    if ($asset_id !== '') {
        $stake_address = getCreatorStakeAddress($conn, $creator_id);

        if (!$stake_address) {
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

            if ($prev_bidder && $prev_bid > 0 && $prev_pid) {
                updateBalance($conn, $prev_bidder, $prev_pid, $prev_bid);
                logCredit($conn, $prev_bidder, $prev_bid, $prev_pid);
                if ($winning_bid_id) $conn->query("UPDATE bids SET status=0 WHERE id='$winning_bid_id'");

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
            if ($auction['creator_discord']) {
                sendDM($auction['creator_discord'],
                    "❌ Your auction **{$title}** was automatically canceled because the NFT (asset: `$asset_id`) could not be found in your linked wallet at close time.\n\n" .
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
            echo "  Auction #$aid canceled (NFT not in creator wallet).\n";
            continue;
        }

        echo "  Verified: asset confirmed in creator's wallet.\n";
    }

    // ── Select winner ─────────────────────────────────────────────────────────
    if ($prev_bidder && $prev_bid > 0) {
        $wres        = $conn->query("SELECT username AS name, discord_id FROM users WHERE id='$prev_bidder' LIMIT 1");
        $winner      = $wres ? $wres->fetch_assoc() : null;
        $winner_name = $winner ? $winner['name'] : 'Unknown';

        $pr  = $conn->query("SELECT currency FROM projects WHERE id='$prev_pid' LIMIT 1");
        $cur = ($pr && $pr->num_rows) ? strtoupper($pr->fetch_assoc()['currency']) : 'pts';

        if ($asset_id !== '') {
            // ── NFT auction: save winner, wait for on-chain delivery ──────────
            $conn->query("UPDATE auctions SET winner_id='$prev_bidder', processing=0 WHERE id='$aid'");

            if ($winner && $winner['discord_id']) {
                sendDM($winner['discord_id'],
                    "🎉 Congratulations! You won the auction for **{$title}**!\n\n" .
                    "Your winning bid: **" . number_format($prev_bid) . " $cur**\n" .
                    "NFT Asset: `$asset_id`\n\n" .
                    "The creator has been notified and has **30 days** to send the NFT to your linked Cardano wallet. " .
                    "Delivery will be confirmed automatically on-chain.\n\n" .
                    "⚠️ If the NFT is not received within 30 days, the auction will be canceled and your bid refunded."
                );
            }
            if ($auction['creator_discord']) {
                sendDM($auction['creator_discord'],
                    "🔨 Your auction **{$title}** has ended!\n\n" .
                    "Winner: **{$winner_name}**\n" .
                    "Winning bid: **" . number_format($prev_bid) . " $cur**\n" .
                    "NFT Asset: `$asset_id`\n\n" .
                    "Please send the NFT to **{$winner_name}**'s linked Cardano wallet within **30 days**.\n" .
                    "Your **" . number_format($prev_bid) . " $cur** will be credited once on-chain delivery is confirmed.\n\n" .
                    "⚠️ If delivery is not confirmed within 30 days, the auction will be canceled and the winner refunded."
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
            // ── No asset_id: complete immediately, pay creator now ────────────
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
                    "⚠️ If you do not hear from the creator within 48 hours, please open a support ticket."
                );
            }
            if ($auction['creator_discord']) {
                sendDM($auction['creator_discord'],
                    "🔨 Your auction **{$title}** has ended!\n\n" .
                    "Winner: **{$winner_name}**\n" .
                    "Winning bid: **" . number_format($prev_bid) . " $cur**\n\n" .
                    "**" . number_format($prev_bid) . " $cur** has been credited to your balance.\n\n" .
                    "Please arrange prize delivery with **{$winner_name}** via the Skulliance Discord."
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
        // ── No bids ───────────────────────────────────────────────────────────
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

$conn->close();
echo "Done.\n";
