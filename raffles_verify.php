<?php
// raffles_verify.php — Nightly cron: draw raffle winners for ended raffles
// 1. If an asset_id is set, verify via Koios that the creator still holds the NFT.
//    If not found: cancel, refund all ticket buyers, DM creator, skip to next.
// 2. If verified (or no asset_id): draw a random winner weighted by ticket count,
//    DM winner and creator, announce to Discord.
set_time_limit(0);
include 'db.php';
include 'message.php';
include 'verify.php';
include 'webhooks.php';

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
    $rid        = intval($raffle['id']);
    $title      = $raffle['title'];
    $img_url    = !empty($raffle['image_path']) ? 'https://skulliance.io/staking/' . $raffle['image_path'] : '';
    $creator_id = intval($raffle['user_id']);

    echo "Processing raffle #$rid: $title\n";

    // Mark as processing
    $conn->query("UPDATE raffles SET processing=1 WHERE id='$rid'");

    // Load ticket costs from raffles_projects for refund calculations
    $costs = [];
    $cres  = $conn->query("SELECT project_id, cost FROM raffles_projects WHERE raffle_id='$rid'");
    if ($cres) { while ($cr = $cres->fetch_assoc()) $costs[intval($cr['project_id'])] = intval($cr['cost']); }

    // ── Step 1: Blockchain verification (only when asset_id is present) ───────
    $asset_id = trim($raffle['asset_id'] ?? '');
    if ($asset_id !== '') {
        $stake_address = getCreatorStakeAddress($conn, $creator_id);

        if (!$stake_address) {
            echo "  WARNING: No wallet found for creator (user_id=$creator_id). Cannot verify NFT ownership.\n";

            // Refund all ticket buyers
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

            // Refund all ticket buyers and notify each one
            $tres = $conn->query("SELECT id, user_id, project_id, quantity FROM tickets WHERE raffle_id='$rid' AND status=1");
            if ($tres) {
                $notified = [];
                while ($t = $tres->fetch_assoc()) {
                    $tuid  = intval($t['user_id']);
                    $tpid  = intval($t['project_id']);
                    $tqty  = intval($t['quantity']);
                    $tamt  = ($costs[$tpid] ?? 0) * $tqty;
                    if ($tamt > 0) {
                        updateBalance($conn, $tuid, $tpid, $tamt);
                        logCredit($conn, $tuid, $tamt, $tpid);
                    }
                    // DM each buyer once
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
                    "❌ Your raffle **{$title}** was automatically canceled because the NFT (asset: $asset_id) could not be found in your linked wallet at close time.\n\n" .
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
            echo "  Raffle #$rid canceled.\n";
            continue;
        }

        echo "  Verified: asset confirmed in creator's wallet.\n";
    }

    // ── Step 2: Build weighted ticket pool and draw winner ────────────────────
    $tres = $conn->query("SELECT id AS ticket_id, user_id, quantity FROM tickets WHERE raffle_id='$rid' AND status=1");
    $pool       = [];
    $total_sold = 0;
    if ($tres && $tres->num_rows > 0) {
        while ($t = $tres->fetch_assoc()) {
            $qty = intval($t['quantity']);
            for ($i = 0; $i < $qty; $i++) {
                $pool[] = ['ticket_id' => intval($t['ticket_id']), 'user_id' => intval($t['user_id'])];
            }
            $total_sold += $qty;
        }
    }

    if (empty($pool)) {
        discordmsg(
            '🎟️ Raffle Ended (No Tickets): ' . $title,
            "**{$title}** by **{$raffle['creator_name']}** ended with no tickets sold.",
            $img_url, 'https://skulliance.io/staking/raffles.php',
            'raffles', '', '555555'
        );
        $conn->query("UPDATE raffles SET completed=1, processing=0 WHERE id='$rid'");
        echo "  No tickets sold.\n";
        continue;
    }

    $winning_entry   = $pool[array_rand($pool)];
    $winning_uid     = $winning_entry['user_id'];
    $winning_tick_id = $winning_entry['ticket_id'];

    $wres        = $conn->query("SELECT name, discord_id FROM users WHERE id='$winning_uid' LIMIT 1");
    $winner      = $wres ? $wres->fetch_assoc() : null;
    $winner_name = $winner ? $winner['name'] : 'Unknown';

    // Update raffle record
    $conn->query("UPDATE raffles SET winner_id='$winning_uid', winning_ticket_id='$winning_tick_id', completed=1, processing=0 WHERE id='$rid'");

    // DM winner
    if ($winner && $winner['discord_id']) {
        $dm = "🎉 You won the raffle for **{$title}**!\n\n" .
              "You were drawn from **$total_sold** total ticket(s).\n" .
              ($asset_id !== ''
                  ? "NFT Asset ID: `$asset_id`\n\n"
                  : "\n") .
              "Please contact the creator **{$raffle['creator_name']}** in the Skulliance Discord to arrange delivery of your prize.\n\n" .
              "⚠️ If you do not hear from the creator within 48 hours, please open a support ticket.";
        sendDM($winner['discord_id'], $dm);
    }

    // DM creator
    if ($raffle['creator_discord']) {
        $dm = "🎟️ Your raffle **{$title}** has ended!\n\n" .
              "Winner: **{$winner_name}**\n" .
              "Total tickets sold: **$total_sold**\n" .
              ($asset_id !== ''
                  ? "NFT Asset ID: `$asset_id`\n\n"
                  : "\n") .
              "Please send the prize to the winner via the Skulliance Discord. " .
              "Ensure your DMs from Skulliance members are open so the winner can contact you.";
        sendDM($raffle['creator_discord'], $dm);
    }

    // Discord announcement
    discordmsg(
        '🏆 Raffle Winner: ' . $title,
        "🎉 **{$winner_name}** won **{$title}**!\n" .
        "Drawn from **$total_sold** ticket(s).\n" .
        "Creator: **{$raffle['creator_name']}** — please arrange prize delivery.",
        $img_url, 'https://skulliance.io/staking/raffles.php',
        'raffles', '', 'a040ff'
    );

    echo "  Winner: $winner_name (ticket #$winning_tick_id, pool of $total_sold)\n";
    echo "  Raffle #$rid marked completed.\n";
}

$conn->close();
echo "Done.\n";

// ── Helper: refund all active ticket buyers for a raffle (no DMs) ─────────────
function refundAllRaffleTickets($conn, $raffle_id, $costs) {
    $rid  = intval($raffle_id);
    $tres = $conn->query("SELECT user_id, project_id, quantity FROM tickets WHERE raffle_id='$rid' AND status=1");
    if ($tres) {
        while ($t = $tres->fetch_assoc()) {
            $tpid  = intval($t['project_id']);
            $tamt  = ($costs[$tpid] ?? 0) * intval($t['quantity']);
            if ($tamt > 0) {
                updateBalance($conn, intval($t['user_id']), $tpid, $tamt);
                logCredit($conn, intval($t['user_id']), $tamt, $tpid);
            }
        }
        $conn->query("UPDATE tickets SET status=0 WHERE raffle_id='$rid' AND status=1");
    }
}
