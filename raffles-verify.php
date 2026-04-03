<?php
// raffles_verify.php — Nightly cron: draw raffle winners for ended raffles
//
// Phase 1 (winner_id IS NULL): verify creator holds NFT at close, draw winner,
//   save winner_id, reset processing=0. Do NOT pay creator yet.
//   If no asset_id: pay creator from ticket proceeds immediately and mark completed.
//
// Phase 2 (winner_id IS NOT NULL, completed=0): nightly check of winner's wallet.
//   NFT confirmed → credit creator with all ticket proceeds, mark completed.
//   30+ days elapsed → cancel, refund all ticket buyers.
//   Reminders to creator at 7/14/21/28 days.
set_time_limit(0);
include 'db.php';
include 'message.php';
include 'verify.php';
include_once 'webhooks.php';

$now = date('Y-m-d H:i:s');

$result = $conn->query(
    "SELECT r.*, u.name AS creator_name, u.discord_id AS creator_discord
     FROM raffles r
     INNER JOIN users u ON u.id = r.user_id
     WHERE r.end_date <= '$now'
       AND r.completed = 0
       AND r.processing = 0
       AND r.canceled = 0"
);

if (!$result || $result->num_rows === 0) {
    echo "No raffles to process.\n";
    $conn->close();
    exit;
}

while ($raffle = $result->fetch_assoc()) {
    $rid              = intval($raffle['id']);
    $title            = $raffle['title'];
    $img_url          = !empty($raffle['image']) ? 'https://skulliance.io/staking/images/raffles/' . $raffle['image'] : '';
    $creator_id       = intval($raffle['user_id']);
    $winner_id_stored = intval($raffle['winner_id'] ?? 0);
    $asset_id         = trim($raffle['asset_id'] ?? '');

    echo "Processing raffle #$rid: $title\n";
    $conn->query("UPDATE raffles SET processing=1 WHERE id='$rid'");

    // ── PHASE 2: Winner already selected — check on-chain delivery ────────────
    if ($winner_id_stored > 0) {
        if ($asset_id === '') {
            // No asset_id — shouldn't be stuck here; mark completed
            $conn->query("UPDATE raffles SET completed=1, processing=0 WHERE id='$rid'");
            echo "  Phase 2 (no asset): marked completed.\n";
            continue;
        }

        $days_elapsed   = (time() - strtotime($raffle['end_date'])) / 86400;
        $days_remaining = ceil(30 - $days_elapsed);
        echo "  Phase 2: checking delivery (day " . round($days_elapsed, 1) . " of 30)\n";

        $wres        = $conn->query("SELECT username AS name, discord_id FROM users WHERE id='$winner_id_stored' LIMIT 1");
        $winner      = $wres ? $wres->fetch_assoc() : null;
        $winner_name = $winner ? $winner['name'] : 'Unknown';

        // Check winner's wallet for the NFT
        $winner_stake  = getCreatorStakeAddress($conn, $winner_id_stored);
        $nft_delivered = false;
        if ($winner_stake) {
            echo "  Checking winner wallet $winner_stake for asset $asset_id…\n";
            $nft_delivered = verifyAssetInWallet($winner_stake, $asset_id);
        }

        if ($nft_delivered) {
            // ── Delivery confirmed — credit creator with all ticket proceeds ───
            echo "  NFT confirmed in winner's wallet. Processing creator payout.\n";

            $cres = $conn->query(
                "SELECT t.project_id, SUM(t.quantity) AS total_qty, rp.cost
                 FROM tickets t
                 INNER JOIN raffles_projects rp ON rp.raffle_id = t.raffle_id AND rp.project_id = t.project_id
                 WHERE t.raffle_id='$rid' AND t.status=1
                 GROUP BY t.project_id"
            );
            $payout_lines = [];
            if ($cres) {
                while ($crow = $cres->fetch_assoc()) {
                    $payout = intval($crow['total_qty']) * intval($crow['cost']);
                    if ($payout > 0) {
                        updateBalance($conn, $creator_id, intval($crow['project_id']), $payout);
                        logCredit($conn, $creator_id, $payout, intval($crow['project_id']));
                        $pres = $conn->query("SELECT currency FROM projects WHERE id='" . intval($crow['project_id']) . "' LIMIT 1");
                        $pcur = ($pres && $pres->num_rows) ? strtoupper($pres->fetch_assoc()['currency']) : 'pts';
                        $payout_lines[] = number_format($payout) . ' ' . $pcur;
                        echo "  Credited creator (user_id=$creator_id) with $payout $pcur\n";
                    }
                }
            }
            $payout_str = !empty($payout_lines) ? implode(', ', $payout_lines) : 'none';

            $conn->query("UPDATE raffles SET completed=1, processing=0 WHERE id='$rid'");

            if ($winner && $winner['discord_id']) {
                sendDM($winner['discord_id'],
                    "✅ Your raffle prize for **{$title}** has been confirmed in your wallet!\n\n" .
                    "NFT Asset: `$asset_id`\n\n" .
                    "Thank you for using Skulliance. Enjoy your prize!"
                );
            }
            if ($raffle['creator_discord']) {
                sendDM($raffle['creator_discord'],
                    "✅ Delivery confirmed for your raffle **{$title}**!\n\n" .
                    "The NFT (`$asset_id`) has been verified in **{$winner_name}**'s wallet.\n\n" .
                    "**$payout_str** has been credited to your balance."
                );
            }
            discordmsg(
                '✅ Raffle Prize Delivered: ' . $title,
                "The NFT for **{$title}** has been confirmed received by **{$winner_name}**!\n" .
                "Creator **{$raffle['creator_name']}** has been paid **$payout_str**.",
                $img_url, 'https://skulliance.io/staking/raffles.php',
                'raffles', '', '00c8a0'
            );
            echo "  Raffle #$rid delivery confirmed and completed.\n";

        } elseif ($days_elapsed >= 30) {
            // ── 30-day timeout — cancel and refund all ticket buyers ──────────
            echo "  30-day deadline passed. Canceling and refunding ticket buyers.\n";

            $costs = [];
            $cres  = $conn->query("SELECT project_id, cost FROM raffles_projects WHERE raffle_id='$rid'");
            if ($cres) { while ($cr = $cres->fetch_assoc()) $costs[intval($cr['project_id'])] = intval($cr['cost']); }

            $tres = $conn->query("SELECT id, user_id, project_id, quantity FROM tickets WHERE raffle_id='$rid' AND status=1");
            $notified = [];
            if ($tres) {
                while ($t = $tres->fetch_assoc()) {
                    $tuid = intval($t['user_id']);
                    $tpid = intval($t['project_id']);
                    $tamt = ($costs[$tpid] ?? 0) * intval($t['quantity']);
                    if ($tamt > 0) {
                        updateBalance($conn, $tuid, $tpid, $tamt);
                        logCredit($conn, $tuid, $tamt, $tpid);
                    }
                    if (!in_array($tuid, $notified)) {
                        $ures = $conn->query("SELECT discord_id FROM users WHERE id='$tuid' LIMIT 1");
                        if ($ures && $ures->num_rows) {
                            $urow = $ures->fetch_assoc();
                            if ($urow['discord_id']) {
                                sendDM($urow['discord_id'],
                                    "❌ The raffle **{$title}** has been canceled — the creator did not deliver the NFT within the 30-day window.\n\n" .
                                    "Your tickets have been fully refunded."
                                );
                            }
                        }
                        $notified[] = $tuid;
                    }
                }
                $conn->query("UPDATE tickets SET status=0 WHERE raffle_id='$rid' AND status=1");
            }

            $conn->query("UPDATE raffles SET canceled=1, processing=0 WHERE id='$rid'");

            if ($raffle['creator_discord']) {
                sendDM($raffle['creator_discord'],
                    "❌ Your raffle **{$title}** has been automatically canceled because the NFT (`$asset_id`) was not confirmed in **{$winner_name}**'s wallet within 30 days.\n\n" .
                    "All ticket buyers have been refunded. Please contact support if you believe this is an error."
                );
            }
            discordmsg(
                '❌ Raffle Canceled (Delivery Timeout): ' . $title,
                "**{$title}** by **{$raffle['creator_name']}** was canceled — NFT delivery to **{$winner_name}** was not confirmed within 30 days. All ticket buyers have been refunded.",
                $img_url, 'https://skulliance.io/staking/raffles.php',
                'raffles', '', 'ff3333'
            );
            echo "  Raffle #$rid canceled (delivery timeout).\n";

        } else {
            // ── Still within grace period — send milestone reminders ──────────
            $conn->query("UPDATE raffles SET processing=0 WHERE id='$rid'");

            $creator_msg = null;
            $winner_msg  = null;

            if ($days_elapsed >= 28 && $days_elapsed < 29) {
                $creator_msg = "⚠️ **Final warning** — only **{$days_remaining} days** left to deliver the NFT for your raffle **{$title}** to **{$winner_name}**.\n\nAsset: `$asset_id`\n\nIf delivery is not confirmed on-chain within 30 days of raffle close, the raffle will be canceled and all ticket buyers refunded.";
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
                $creator_msg = "⏰ Reminder — **{$days_remaining} days** remaining to deliver the NFT for your raffle **{$title}** to **{$winner_name}**.\n\nPlease send `$asset_id` to **{$winner_name}**'s linked Cardano wallet as soon as possible.";
                $winner_msg  = "⏰ Reminder: You won **{$title}**! The creator has **{$days_remaining} days** remaining to deliver the NFT to your wallet.";
                echo "  Sent 7-day reminder.\n";
            } else {
                echo "  Delivery pending (day " . round($days_elapsed) . " of 30).\n";
            }

            if ($creator_msg && $raffle['creator_discord']) sendDM($raffle['creator_discord'], $creator_msg);
            if ($winner_msg && $winner && $winner['discord_id']) sendDM($winner['discord_id'], $winner_msg);
        }

        continue;
    }

    // ── PHASE 1: First close — verify creator holds NFT, draw winner ──────────

    // Load ticket costs for potential refunds
    $costs = [];
    $cres  = $conn->query("SELECT project_id, cost FROM raffles_projects WHERE raffle_id='$rid'");
    if ($cres) { while ($cr = $cres->fetch_assoc()) $costs[intval($cr['project_id'])] = intval($cr['cost']); }

    if ($asset_id !== '') {
        $stake_address = getCreatorStakeAddress($conn, $creator_id);

        if (!$stake_address) {
            echo "  WARNING: No wallet found for creator (user_id=$creator_id). Cannot verify NFT ownership.\n";

            refundAllRaffleTickets($conn, $rid, $costs);

            if ($raffle['creator_discord']) {
                sendDM($raffle['creator_discord'],
                    "⚠️ Your raffle **{$title}** could not be completed because we were unable to verify NFT ownership — your Cardano wallet is not linked to your Skulliance account.\n\n" .
                    "All ticket buyers have been refunded. Please link your wallet and contact support."
                );
            }
            discordmsg(
                '⚠️ Raffle Verification Failed: ' . $title,
                "**{$raffle['creator_name']}**'s raffle **{$title}** could not be processed — no linked wallet found to verify NFT ownership. All tickets have been refunded.",
                $img_url, 'https://skulliance.io/staking/raffles.php',
                'raffles', '', 'ff6b00'
            );
            $conn->query("UPDATE raffles SET canceled=1, processing=0 WHERE id='$rid'");
            echo "  Raffle #$rid canceled (no wallet).\n";
            continue;
        }

        echo "  Verifying asset $asset_id in wallet $stake_address via Koios…\n";
        $asset_found = verifyAssetInWallet($stake_address, $asset_id);

        if (!$asset_found) {
            echo "  FAILED: Asset not found in creator's wallet. Canceling and refunding all tickets.\n";

            $tres = $conn->query("SELECT id, user_id, project_id, quantity FROM tickets WHERE raffle_id='$rid' AND status=1");
            if ($tres) {
                $notified = [];
                while ($t = $tres->fetch_assoc()) {
                    $tuid = intval($t['user_id']);
                    $tpid = intval($t['project_id']);
                    $tamt = ($costs[$tpid] ?? 0) * intval($t['quantity']);
                    if ($tamt > 0) {
                        updateBalance($conn, $tuid, $tpid, $tamt);
                        logCredit($conn, $tuid, $tamt, $tpid);
                    }
                    if (!in_array($tuid, $notified)) {
                        $ures = $conn->query("SELECT discord_id FROM users WHERE id='$tuid' LIMIT 1");
                        if ($ures && $ures->num_rows) {
                            $urow = $ures->fetch_assoc();
                            if ($urow['discord_id']) {
                                sendDM($urow['discord_id'],
                                    "❌ The raffle **{$title}** was canceled because the creator could not verify ownership of the NFT at close time.\n\n" .
                                    "Your tickets have been fully refunded."
                                );
                            }
                        }
                        $notified[] = $tuid;
                    }
                }
                $conn->query("UPDATE tickets SET status=0 WHERE raffle_id='$rid' AND status=1");
            }

            if ($raffle['creator_discord']) {
                sendDM($raffle['creator_discord'],
                    "❌ Your raffle **{$title}** was automatically canceled because the NFT (asset: `$asset_id`) could not be found in your linked wallet at close time.\n\n" .
                    "All ticket buyers have been refunded. If this is an error, please contact support."
                );
            }
            discordmsg(
                '❌ Raffle Canceled (NFT Not Verified): ' . $title,
                "**{$title}** by **{$raffle['creator_name']}** was canceled — the NFT could not be verified in the creator's wallet at close time. All tickets have been refunded.",
                $img_url, 'https://skulliance.io/staking/raffles.php',
                'raffles', '', 'ff3333'
            );
            $conn->query("UPDATE raffles SET canceled=1, processing=0 WHERE id='$rid'");
            echo "  Raffle #$rid canceled (NFT not in creator wallet).\n";
            continue;
        }

        echo "  Verified: asset confirmed in creator's wallet.\n";
    }

    // ── Build weighted ticket pool and draw winner ────────────────────────────
    $tres       = $conn->query("SELECT user_id, quantity FROM tickets WHERE raffle_id='$rid' AND status=1");
    $pool       = [];
    $total_sold = 0;
    if ($tres && $tres->num_rows > 0) {
        while ($t = $tres->fetch_assoc()) {
            $qty = intval($t['quantity']);
            for ($i = 0; $i < $qty; $i++) $pool[] = intval($t['user_id']);
            $total_sold += $qty;
        }
    }

    if (empty($pool)) {
        $conn->query("UPDATE raffles SET completed=1, processing=0 WHERE id='$rid'");

        if ($raffle['creator_discord']) {
            sendDM($raffle['creator_discord'],
                "⏱️ Your raffle **{$title}** ended with no tickets sold. The listing has been closed."
            );
        }
        discordmsg(
            '🎟️ Raffle Ended (No Tickets): ' . $title,
            "**{$title}** by **{$raffle['creator_name']}** ended with no tickets sold.",
            $img_url, 'https://skulliance.io/staking/raffles.php',
            'raffles', '', '555555'
        );
        echo "  No tickets sold — raffle #$rid closed.\n";
        continue;
    }

    // ── Check ticket minimum ──────────────────────────────────────────────────
    $ticket_minimum = max(1, intval($raffle['ticket_minimum'] ?? 1));
    if ($total_sold < $ticket_minimum) {
        // Below minimum — refund all buyers with DMs, cancel raffle
        $notified = [];
        $tres_min = $conn->query("SELECT user_id, project_id, quantity FROM tickets WHERE raffle_id='$rid' AND status=1");
        if ($tres_min) {
            while ($t = $tres_min->fetch_assoc()) {
                $tuid = intval($t['user_id']);
                $tpid = intval($t['project_id']);
                $tamt = ($costs[$tpid] ?? 0) * intval($t['quantity']);
                if ($tamt > 0) {
                    updateBalance($conn, $tuid, $tpid, $tamt);
                    logCredit($conn, $tuid, $tamt, $tpid);
                }
                if (!in_array($tuid, $notified)) {
                    $ures = $conn->query("SELECT discord_id FROM users WHERE id='$tuid' LIMIT 1");
                    if ($ures && $ures->num_rows) {
                        $urow = $ures->fetch_assoc();
                        if ($urow['discord_id']) {
                            sendDM($urow['discord_id'],
                                "⏱️ The raffle **{$title}** ended without reaching the minimum ticket requirement " .
                                "({$total_sold}/{$ticket_minimum} tickets sold).\n\n" .
                                "Your tickets have been fully refunded."
                            );
                        }
                    }
                    $notified[] = $tuid;
                }
            }
            $conn->query("UPDATE tickets SET status=0 WHERE raffle_id='$rid' AND status=1");
        }
        if ($raffle['creator_discord']) {
            sendDM($raffle['creator_discord'],
                "⏱️ Your raffle **{$title}** ended without meeting the minimum ticket requirement " .
                "({$total_sold}/{$ticket_minimum} tickets sold).\n\n" .
                "All ticket buyers have been refunded. The raffle has been closed."
            );
        }
        discordmsg(
            '🎟️ Raffle Canceled (Min. Not Met): ' . $title,
            "**{$title}** by **{$raffle['creator_name']}** ended with only **{$total_sold}** of **{$ticket_minimum}** required tickets sold. All tickets have been refunded.",
            $img_url, 'https://skulliance.io/staking/raffles.php',
            'raffles', '', '555555'
        );
        $conn->query("UPDATE raffles SET canceled=1, processing=0 WHERE id='$rid'");
        echo "  Ticket minimum not met ({$total_sold}/{$ticket_minimum}) — raffle #$rid canceled and refunded.\n";
        continue;
    }

    $winning_uid = $pool[array_rand($pool)];
    $wres        = $conn->query("SELECT username AS name, discord_id FROM users WHERE id='$winning_uid' LIMIT 1");
    $winner      = $wres ? $wres->fetch_assoc() : null;
    $winner_name = $winner ? $winner['name'] : 'Unknown';

    if ($asset_id !== '') {
        // ── NFT raffle: save winner, wait for on-chain delivery ───────────────
        $conn->query("UPDATE raffles SET winner_id='$winning_uid', processing=0 WHERE id='$rid'");

        if ($winner && $winner['discord_id']) {
            sendDM($winner['discord_id'],
                "🎉 You won the raffle for **{$title}**!\n\n" .
                "You were drawn from **$total_sold** total ticket(s).\n" .
                "NFT Asset: `$asset_id`\n\n" .
                "The creator has been notified and has **30 days** to send the NFT to your linked Cardano wallet. " .
                "Delivery will be confirmed automatically on-chain.\n\n" .
                "⚠️ If the NFT is not received within 30 days, the raffle will be canceled and all ticket buyers refunded."
            );
        }
        if ($raffle['creator_discord']) {
            sendDM($raffle['creator_discord'],
                "🎟️ Your raffle **{$title}** has ended!\n\n" .
                "Winner: **{$winner_name}** (drawn from **$total_sold** ticket(s))\n" .
                "NFT Asset: `$asset_id`\n\n" .
                "Please send the NFT to **{$winner_name}**'s linked Cardano wallet within **30 days**.\n" .
                "Your ticket proceeds will be credited once on-chain delivery is confirmed.\n\n" .
                "⚠️ If delivery is not confirmed within 30 days, the raffle will be canceled and ticket buyers refunded."
            );
        }
        discordmsg(
            '🏆 Raffle Winner Drawn: ' . $title,
            "🎉 **{$winner_name}** won **{$title}**!\n" .
            "Drawn from **$total_sold** ticket(s).\n" .
            "Creator: **{$raffle['creator_name']}** — NFT delivery pending (30-day window). Creator paid upon confirmed on-chain delivery.",
            $img_url, 'https://skulliance.io/staking/raffles.php',
            'raffles', '', 'a040ff'
        );
        echo "  Winner: $winner_name — delivery tracking active (30-day window).\n";

    } else {
        // ── No asset_id: pay creator immediately, mark completed ──────────────
        $cres = $conn->query(
            "SELECT t.project_id, SUM(t.quantity) AS total_qty, rp.cost
             FROM tickets t
             INNER JOIN raffles_projects rp ON rp.raffle_id = t.raffle_id AND rp.project_id = t.project_id
             WHERE t.raffle_id='$rid' AND t.status=1
             GROUP BY t.project_id"
        );
        $payout_lines = [];
        if ($cres) {
            while ($crow = $cres->fetch_assoc()) {
                $payout = intval($crow['total_qty']) * intval($crow['cost']);
                if ($payout > 0) {
                    updateBalance($conn, $creator_id, intval($crow['project_id']), $payout);
                    logCredit($conn, $creator_id, $payout, intval($crow['project_id']));
                    $pres = $conn->query("SELECT currency FROM projects WHERE id='" . intval($crow['project_id']) . "' LIMIT 1");
                    $pcur = ($pres && $pres->num_rows) ? strtoupper($pres->fetch_assoc()['currency']) : 'pts';
                    $payout_lines[] = number_format($payout) . ' ' . $pcur;
                    echo "  Credited creator (user_id=$creator_id) with $payout $pcur\n";
                }
            }
        }
        $payout_str = !empty($payout_lines) ? implode(', ', $payout_lines) : 'none';

        $conn->query("UPDATE raffles SET winner_id='$winning_uid', completed=1, processing=0 WHERE id='$rid'");

        if ($winner && $winner['discord_id']) {
            sendDM($winner['discord_id'],
                "🎉 You won the raffle for **{$title}**!\n\n" .
                "You were drawn from **$total_sold** total ticket(s).\n\n" .
                "Please contact the creator **{$raffle['creator_name']}** in the Skulliance Discord to arrange delivery of your prize.\n\n" .
                "⚠️ If you do not hear from the creator within 48 hours, please open a support ticket."
            );
        }
        if ($raffle['creator_discord']) {
            sendDM($raffle['creator_discord'],
                "🎟️ Your raffle **{$title}** has ended!\n\n" .
                "Winner: **{$winner_name}** (drawn from **$total_sold** ticket(s))\n\n" .
                "**$payout_str** has been credited to your balance.\n\n" .
                "Please send the prize to **{$winner_name}** via the Skulliance Discord."
            );
        }
        discordmsg(
            '🏆 Raffle Winner: ' . $title,
            "🎉 **{$winner_name}** won **{$title}**!\n" .
            "Drawn from **$total_sold** ticket(s).\n" .
            "Creator: **{$raffle['creator_name']}** — **$payout_str** sent to creator. Prize delivery in progress.",
            $img_url, 'https://skulliance.io/staking/raffles.php',
            'raffles', '', 'a040ff'
        );
        echo "  Winner: $winner_name — creator credited $payout_str — raffle #$rid completed.\n";
    }
}

$conn->close();
echo "Done.\n";

// ── Helper: refund all active ticket buyers for a raffle (no DMs) ────────────
function refundAllRaffleTickets($conn, $raffle_id, $costs) {
    $rid  = intval($raffle_id);
    $tres = $conn->query("SELECT user_id, project_id, quantity FROM tickets WHERE raffle_id='$rid' AND status=1");
    if ($tres) {
        while ($t = $tres->fetch_assoc()) {
            $tpid = intval($t['project_id']);
            $tamt = ($costs[$tpid] ?? 0) * intval($t['quantity']);
            if ($tamt > 0) {
                updateBalance($conn, intval($t['user_id']), $tpid, $tamt);
                logCredit($conn, intval($t['user_id']), $tamt, $tpid);
            }
        }
        $conn->query("UPDATE tickets SET status=0 WHERE raffle_id='$rid' AND status=1");
    }
}
