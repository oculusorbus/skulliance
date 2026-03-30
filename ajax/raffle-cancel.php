<?php
include '../db.php';
include '../message.php';
include '../verify.php';
include '../webhooks.php';
header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }

$user_id   = intval($_SESSION['userData']['user_id']);
$raffle_id = intval($_POST['raffle_id'] ?? 0);

if (!$raffle_id) { echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }

// Load raffle and ticket buyers before canceling so we can send refund DMs
$raffle = getRaffle($conn, $raffle_id);

// Capture unique buyers with their discord_ids and refund amounts before cancelRaffle wipes status
$buyers = [];
if ($raffle) {
    $costs = [];
    $cres  = $conn->query("SELECT project_id, cost FROM raffles_projects WHERE raffle_id='$raffle_id'");
    if ($cres) { while ($cr = $cres->fetch_assoc()) $costs[intval($cr['project_id'])] = intval($cr['cost']); }

    $tres = $conn->query("SELECT user_id, project_id, quantity FROM tickets WHERE raffle_id='$raffle_id' AND status=1");
    if ($tres) {
        while ($t = $tres->fetch_assoc()) {
            $tuid = intval($t['user_id']);
            $tpid = intval($t['project_id']);
            $tqty = intval($t['quantity']);
            $tamt = ($costs[$tpid] ?? 0) * $tqty;
            if (!isset($buyers[$tuid])) $buyers[$tuid] = [];
            $buyers[$tuid][] = ['amount' => $tamt, 'project_id' => $tpid];
        }
    }
}

$result = cancelRaffle($conn, $raffle_id, $user_id);

if ($result['success'] && $raffle) {
    $title   = $raffle['title'] ?? '';
    $img_url = !empty($raffle['image_path']) ? 'https://skulliance.io/staking/' . $raffle['image_path'] : '';
    $creator = $_SESSION['userData']['name'] ?? 'Unknown';
    $total   = count($buyers);

    // DM each unique ticket buyer about their refund
    foreach ($buyers as $buyer_uid => $purchases) {
        $ures = $conn->query("SELECT discord_id FROM users WHERE id='$buyer_uid' LIMIT 1");
        if (!$ures || !$ures->num_rows) continue;
        $urow = $ures->fetch_assoc();
        if (!$urow['discord_id']) continue;

        $refund_lines = [];
        foreach ($purchases as $p) {
            if ($p['amount'] <= 0) continue;
            $pres = $conn->query("SELECT currency FROM projects WHERE id='" . intval($p['project_id']) . "' LIMIT 1");
            $pcur = ($pres && $pres->num_rows) ? strtoupper($pres->fetch_assoc()['currency']) : 'pts';
            $refund_lines[] = number_format($p['amount']) . ' ' . $pcur;
        }
        if (empty($refund_lines)) continue;

        sendDM($urow['discord_id'],
            "🚫 The raffle **{$title}** was canceled by the creator.\n\n" .
            "Your tickets have been refunded: **" . implode(', ', $refund_lines) . "**."
        );
    }

    $conn->close();

    discordmsg(
        '🚫 Raffle Canceled: ' . $title,
        "**$creator** canceled their raffle **{$title}**." .
        ($total > 0 ? " All **$total** ticket holder(s) have been refunded." : " No tickets had been purchased."),
        $img_url,
        'https://skulliance.io/staking/raffles.php',
        'raffles', '', 'ff6b00'
    );
} else {
    $conn->close();
}

echo json_encode($result);
