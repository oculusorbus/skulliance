<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) { echo '<p style="opacity:0.5;">Not logged in.</p>'; exit; }

$raffle_id = intval($_GET['id'] ?? 0);
if (!$raffle_id) { echo '<p style="opacity:0.5;">Invalid raffle.</p>'; exit; }

$raffle = getRaffle($conn, $raffle_id);
if (!$raffle) { echo '<p style="opacity:0.5;">Raffle not found.</p>'; exit; }

$user_id    = intval($_SESSION['userData']['user_id']);
$is_creator = $user_id === intval($raffle['user_id']);
$is_closed  = $raffle['completed'] || $raffle['canceled'];
$ended      = strtotime($raffle['end_date']) < time();
$upcoming   = strtotime($raffle['start_date']) > time();
$sold           = intval($raffle['total_tickets_sold']);
$user_tickets   = intval($raffle['user_tickets']);
$ticket_minimum = max(1, intval($raffle['ticket_minimum'] ?? 1));

// Load balances for all ticket options
$balances = [];
foreach ($raffle['ticket_options'] as $opt) {
    $pid = intval($opt['project_id']);
    $bal = getCurrentBalance($conn, $user_id, $pid);
    $balances[$pid] = ($bal === 'false') ? 0 : floatval($bal);
}

$has_img = !empty($raffle['image']) && file_exists(__DIR__ . '/../images/raffles/' . $raffle['image']);

// Ticket holders leaderboard: aggregated by user, ordered by ticket count desc
$ticket_holders = [];
$th = $conn->query("SELECT u.username, u.discord_id, u.avatar, SUM(t.quantity) AS tickets
                    FROM tickets t
                    INNER JOIN users u ON u.id = t.user_id
                    WHERE t.raffle_id='$raffle_id' AND t.status=1
                    GROUP BY t.user_id
                    ORDER BY tickets DESC");
if ($th) { while ($row = $th->fetch_assoc()) $ticket_holders[] = $row; }

// Delivery tracking data (fetch before close)
$delivery_winner_name = '';
$delivery_winner_addr = '';
$is_winner            = false;
$delivery_days_left   = 0;
if ($raffle['processing'] && $raffle['winner_id']) {
    $dw_id  = intval($raffle['winner_id']);
    $dw_res = $conn->query("SELECT username FROM users WHERE id='$dw_id' LIMIT 1");
    $delivery_winner_name = ($dw_res && $dw_res->num_rows) ? $dw_res->fetch_assoc()['username'] : 'Unknown';
    $delivery_winner_addr = getWinnerAddress($conn, $dw_id) ?? '';
    $is_winner            = $user_id === $dw_id;
    $delivery_days_left   = max(0, ceil(30 - (time() - strtotime($raffle['end_date'])) / 86400));
}
$conn->close();
?>
<?php if ($has_img): ?>
<img style="width:100%;max-height:220px;object-fit:contain;border-radius:8px;background:#0a1520;" src="images/raffles/<?php echo htmlspecialchars($raffle['image']); ?>" alt="" />
<?php endif; ?>
<div style="font-size:1rem;font-weight:bold;color:#e8eef4;"><?php echo htmlspecialchars($raffle['title']); ?></div>

<?php if ($upcoming): ?>
<div style="background:rgba(255,200,0,0.1);border:1px solid rgba(255,200,0,0.25);border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#ffc800;">
  Launches in: <span class="countdown" data-deadline="<?php echo strtotime($raffle['start_date']); ?>"></span>
</div>
<?php endif; ?>

<div style="display:flex;flex-direction:column;gap:6px;background:rgba(0,200,160,0.06);border:1px solid rgba(0,200,160,0.15);border-radius:8px;padding:12px;">
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;">Tickets Sold</span>
    <span><?php echo number_format($sold); ?></span>
  </div>
  <?php if ($ticket_minimum > 1): ?>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;">Min. Required</span>
    <span style="color:<?php echo $sold >= $ticket_minimum ? '#00c8a0' : '#ffc800'; ?>;">
      <?php echo number_format($sold); ?> / <?php echo number_format($ticket_minimum); ?>
    </span>
  </div>
  <?php endif; ?>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;">Your Tickets</span>
    <span><?php echo $user_tickets; ?></span>
  </div>
  <?php if ($sold > 0 && $user_tickets > 0): ?>
  <div style="font-size:0.75rem;opacity:0.5;">Your odds: <?php echo round($user_tickets / $sold * 100, 1); ?>% (<?php echo $user_tickets; ?> of <?php echo $sold; ?> tickets)</div>
  <?php endif; ?>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;"><?php echo $upcoming ? 'Starts' : 'Ends'; ?></span>
    <span><span class="countdown" data-deadline="<?php echo strtotime($upcoming ? $raffle['start_date'] : $raffle['end_date']); ?>"></span></span>
  </div>
  <div style="font-size:0.72rem;opacity:0.35;display:flex;align-items:center;gap:5px;">By
    <?php
      $cav  = $raffle['creator_avatar'] ?? '';
      $cdis = $raffle['creator_discord'] ?? '';
      $cname = htmlspecialchars($raffle['creator_name']);
      if ($cdis && $cav) echo '<img src="https://cdn.discordapp.com/avatars/' . $cdis . '/' . $cav . '.png" style="width:16px;height:16px;border-radius:50%;vertical-align:middle;">';
      echo '<a href="/staking/profile.php?username=' . $cname . '" style="color:inherit;text-decoration:underline;">' . $cname . '</a>';
    ?>
  </div>
</div>

<?php if ($raffle['processing'] && $raffle['winner_id']): ?>
<?php if ($is_creator): ?>
<div style="background:rgba(0,200,160,0.07);border:1px solid rgba(0,200,160,0.22);border-radius:8px;padding:13px 14px;display:flex;flex-direction:column;gap:8px;">
  <div style="font-size:0.85rem;font-weight:bold;color:#00c8a0;">&#x1F4E6; Pending NFT Delivery</div>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;">Winner</span>
    <a href="/staking/profile.php?username=<?php echo htmlspecialchars($delivery_winner_name); ?>" style="color:#e8eef4;text-decoration:underline;"><?php echo htmlspecialchars($delivery_winner_name); ?></a>
  </div>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;">Deadline</span>
    <span style="color:<?php echo $delivery_days_left <= 7 ? '#ff6b6b' : ($delivery_days_left <= 14 ? '#ffc800' : '#e8eef4'); ?>;"><?php echo $delivery_days_left; ?> of 30 days remaining</span>
  </div>
  <?php if ($raffle['asset_id']): ?>
  <div style="display:flex;flex-direction:column;gap:3px;">
    <span style="opacity:0.5;font-size:0.78rem;">NFT Asset ID</span>
    <div style="background:rgba(0,0,0,0.25);border-radius:5px;padding:6px 9px;font-family:monospace;font-size:0.72rem;word-break:break-all;line-height:1.5;"><?php echo htmlspecialchars($raffle['asset_id']); ?></div>
  </div>
  <?php endif; ?>
  <?php if ($delivery_winner_addr): ?>
  <div style="display:flex;flex-direction:column;gap:4px;">
    <span style="opacity:0.5;font-size:0.78rem;">Send NFT to this address</span>
    <div style="background:rgba(0,0,0,0.25);border-radius:5px;padding:7px 9px;font-family:monospace;font-size:0.7rem;word-break:break-all;line-height:1.6;"><?php echo htmlspecialchars($delivery_winner_addr); ?></div>
    <button onclick="navigator.clipboard.writeText(<?php echo json_encode($delivery_winner_addr); ?>).then(function(){var b=document.getElementById('copy-raddr-btn');b.textContent='Copied!';setTimeout(function(){b.textContent='Copy Address'},1500)})" id="copy-raddr-btn" style="background:rgba(0,200,160,0.12);border:1px solid rgba(0,200,160,0.3);border-radius:5px;color:#00c8a0;font-size:0.75rem;padding:5px 12px;cursor:pointer;align-self:flex-start;">Copy Address</button>
  </div>
  <?php else: ?>
  <div style="background:rgba(255,200,0,0.08);border:1px solid rgba(255,200,0,0.2);border-radius:6px;padding:8px 10px;font-size:0.78rem;color:#ffc800;">&#x26A0;&#xFE0F; <strong><?php echo htmlspecialchars($delivery_winner_name); ?></strong> has no linked Cardano wallet. Contact them directly in Discord to obtain their address.</div>
  <?php endif; ?>
</div>
<?php elseif ($is_winner): ?>
<div style="background:rgba(0,200,160,0.07);border:1px solid rgba(0,200,160,0.22);border-radius:8px;padding:13px 14px;display:flex;flex-direction:column;gap:8px;">
  <div style="font-size:0.85rem;font-weight:bold;color:#00c8a0;">&#x1F3C6; You won this raffle!</div>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;">Delivery window</span>
    <span><?php echo $delivery_days_left; ?> of 30 days remaining</span>
  </div>
  <?php if ($raffle['asset_id']): ?>
  <div style="display:flex;flex-direction:column;gap:3px;">
    <span style="opacity:0.5;font-size:0.78rem;">NFT Asset ID</span>
    <div style="background:rgba(0,0,0,0.25);border-radius:5px;padding:6px 9px;font-family:monospace;font-size:0.72rem;word-break:break-all;line-height:1.5;"><?php echo htmlspecialchars($raffle['asset_id']); ?></div>
  </div>
  <?php endif; ?>
  <div style="font-size:0.75rem;opacity:0.55;">The creator has <?php echo $delivery_days_left; ?> days to send the NFT to your linked Cardano wallet. Delivery is verified automatically on-chain — you'll be notified once confirmed.</div>
</div>
<?php else: ?>
<div style="background:rgba(0,200,160,0.05);border:1px solid rgba(0,200,160,0.12);border-radius:8px;padding:10px 13px;font-size:0.82rem;">
  &#x1F3C6; Won by <a href="/staking/profile.php?username=<?php echo htmlspecialchars($delivery_winner_name); ?>" style="color:#00c8a0;text-decoration:underline;"><?php echo htmlspecialchars($delivery_winner_name); ?></a> — NFT delivery in progress (<?php echo $delivery_days_left; ?> days remaining).
</div>
<?php endif; ?>
<?php elseif ($is_creator): ?>
<div style="font-size:0.82rem;opacity:0.5;">You created this raffle and cannot buy tickets.</div>
<?php elseif (!$is_closed && !$ended && !$upcoming && !empty($raffle['ticket_options'])): ?>
<div style="display:flex;flex-direction:column;gap:8px;">
  <div style="font-size:0.82rem;font-weight:bold;">Buy Tickets</div>
  <select id="raffle-project-select" style="background:#0a1520;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#e8eef4;padding:7px 9px;font-size:0.82rem;width:100%;">
    <?php foreach ($raffle['ticket_options'] as $opt):
      $pid  = intval($opt['project_id']);
      $bal  = $balances[$pid] ?? 0;
    ?>
    <option value="<?php echo $pid; ?>" data-cost="<?php echo intval($opt['cost']); ?>" data-bal="<?php echo $bal; ?>" data-currency="<?php echo strtolower($opt['currency']); ?>">
      <?php echo htmlspecialchars($opt['project_name']); ?> (<?php echo strtoupper($opt['currency']); ?>) — <?php echo number_format(intval($opt['cost'])); ?>/ticket — Bal: <?php echo number_format($bal); ?>
    </option>
    <?php endforeach; ?>
  </select>
  <div style="display:flex;gap:8px;align-items:center;">
    <input type="number" id="ticket-qty" min="1" step="1" value="1"
      style="background:#0a1520;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#e8eef4;padding:7px 9px;font-size:0.82rem;width:100px;box-sizing:border-box;" />
    <span style="font-size:0.82rem;opacity:0.6;">× <span id="raffle-cost-per">0</span> = <strong id="ticket-total">0</strong> <span id="raffle-cur-label"></span></span>
  </div>
  <div id="ticket-error" style="color:#ff6b6b;font-size:0.78rem;display:none;"></div>
  <button class="small-button" onclick="submitBuyTickets(<?php echo $raffle_id; ?>)">Buy Tickets</button>
</div>
<?php elseif ($upcoming): ?>
<div style="font-size:0.82rem;opacity:0.5;">This raffle hasn't started yet.</div>
<?php elseif ($is_closed): ?>
<div style="font-size:0.82rem;color:#ff6b6b;opacity:0.7;">This raffle is closed.</div>
<?php else: ?>
<div style="font-size:0.82rem;opacity:0.5;">Raffle has ended. Awaiting draw.</div>
<?php endif; ?>

<?php if (!empty($ticket_holders)): ?>
<div style="display:flex;flex-direction:column;gap:6px;">
  <div style="font-size:0.78rem;font-weight:bold;opacity:0.7;">Ticket Holders</div>
  <div class="bid-history">
    <?php foreach ($ticket_holders as $i => $h):
      $h_tickets = intval($h['tickets']);
      $odds      = $sold > 0 ? round($h_tickets / $sold * 100, 1) : 0;
      $is_you    = ($h['username'] === ($_SESSION['userData']['username'] ?? ''));
      $rank_colors = ['#ffd700','#c0c0c0','#cd7f32'];
      $rank_color  = $rank_colors[$i] ?? null;
    ?>
    <div class="bid-row" style="<?php echo $is_you ? 'background:rgba(0,200,160,0.12);' : ''; ?>">
      <span style="display:flex;align-items:center;gap:5px;flex:1;min-width:0;">
        <?php if (!empty($h['discord_id']) && !empty($h['avatar'])): ?>
        <img src="https://cdn.discordapp.com/avatars/<?php echo htmlspecialchars($h['discord_id']); ?>/<?php echo htmlspecialchars($h['avatar']); ?>.png" style="width:16px;height:16px;border-radius:50%;vertical-align:middle;flex-shrink:0;">
        <?php endif; ?>
        <a href="/staking/profile.php?username=<?php echo htmlspecialchars($h['username']); ?>" style="color:inherit;text-decoration:underline;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($h['username']); ?></a>
      </span>
      <span style="flex:0 0 90px;text-align:right;"><?php echo number_format($h_tickets); ?> tickets</span>
      <span style="flex:0 0 44px;text-align:right;opacity:0.4;"><?php echo $odds; ?>%</span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
