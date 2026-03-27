<?php
// raffles_verify.php — Nightly cron: draw raffle winners for ended raffles
// Runs after midnight CST. Picks a random winning ticket, DMs winner and creator, marks completed.
set_time_limit(0);
include 'db.php';
include 'message.php';
include 'verify.php';
include 'webhooks.php';

$now = date('Y-m-d H:i:s');

// Find ended raffles not yet processed
$result = $conn->query("SELECT r.*, u.name AS creator_name, u.discord_id AS creator_discord,
                                p.name AS ticket_project_name, p.currency AS ticket_currency
                         FROM raffles r
                         INNER JOIN users u ON u.id = r.user_id
                         INNER JOIN projects p ON p.id = r.ticket_project_id
                         WHERE r.end_date <= '$now'
                           AND r.completed = 0
                           AND r.processing = 0
                           AND r.canceled = 0");

if (!$result || $result->num_rows === 0) {
    echo "No raffles to process.\n";
    $conn->close();
    exit;
}

while ($raffle = $result->fetch_assoc()) {
    $rid = intval($raffle['id']);
    echo "Processing raffle #$rid: {$raffle['title']}\n";

    // Mark as processing
    $conn->query("UPDATE raffles SET processing=1 WHERE id='$rid'");

    $cur   = strtoupper($raffle['ticket_currency']);
    $title = $raffle['title'];
    $img_url = !empty($raffle['image_path']) ? 'https://skulliance.io/staking/' . $raffle['image_path'] : '';

    // Fetch all ticket rows with expansion by quantity
    $tres = $conn->query("SELECT t.id AS ticket_id, t.user_id, t.quantity FROM tickets t WHERE t.raffle_id='$rid'");
    $pool = []; // array of [ticket_id, user_id] entries, one per ticket purchased
    $total_sold = 0;
    if ($tres && $tres->num_rows > 0) {
        while ($t = $tres->fetch_assoc()) {
            for ($i = 0; $i < intval($t['quantity']); $i++) {
                $pool[] = ['ticket_id' => intval($t['ticket_id']), 'user_id' => intval($t['user_id'])];
            }
            $total_sold += intval($t['quantity']);
        }
    }

    if (empty($pool)) {
        // No tickets sold
        discordmsg(
            '🎟️ Raffle Ended (No Tickets): ' . $title,
            "**$title** by **{$raffle['creator_name']}** ended with no tickets sold.\nAll participants will be notified.",
            $img_url, 'https://skulliance.io/staking/raffles.php',
            'raffles', '', '555555'
        );
        $conn->query("UPDATE raffles SET completed=1, processing=0 WHERE id='$rid'");
        echo "  No tickets sold.\n";
        continue;
    }

    // Draw winner
    $winning_entry   = $pool[array_rand($pool)];
    $winning_uid     = $winning_entry['user_id'];
    $winning_tick_id = $winning_entry['ticket_id'];

    // Fetch winner info
    $wres   = $conn->query("SELECT name, discord_id FROM users WHERE id='$winning_uid' LIMIT 1");
    $winner = $wres ? $wres->fetch_assoc() : null;
    $winner_name = $winner ? $winner['name'] : 'Unknown';

    // Update raffle record
    $conn->query("UPDATE raffles SET winner_id='$winning_uid', winning_ticket_id='$winning_tick_id', completed=1, processing=0 WHERE id='$rid'");

    // DM winner
    if ($winner && $winner['discord_id']) {
        $msg = "🎉 You won the raffle for **{$title}**!\n\n"
             . "You were drawn from **$total_sold** total ticket(s).\n"
             . "Please contact the creator **{$raffle['creator_name']}** in the Skulliance Discord to arrange delivery of your prize.\n\n"
             . "⚠️ If you do not hear from the creator within 48 hours, please open a support ticket.";
        sendDM($winner['discord_id'], $msg);
    }

    // DM creator
    if ($raffle['creator_discord']) {
        $msg = "🎟️ Your raffle **{$title}** has ended!\n\n"
             . "Winner: **{$winner_name}**\n"
             . "Total tickets sold: **$total_sold**\n\n"
             . "Please arrange delivery of your prize to the winner via Skulliance Discord.";
        sendDM($raffle['creator_discord'], $msg);
    }

    // Webhook announcement
    discordmsg(
        '🏆 Raffle Winner: ' . $title,
        "🎉 **{$winner_name}** won **{$title}**!\n" .
        "Drawn from **$total_sold** ticket(s).\n" .
        "Creator: **{$raffle['creator_name']}** — please arrange prize delivery.",
        $img_url, 'https://skulliance.io/staking/raffles.php',
        'raffles', '', 'a040ff'
    );

    echo "  Winner: {$winner_name} (ticket #{$winning_tick_id}, pool of $total_sold)\n";
}

$conn->close();
echo "Done.\n";
