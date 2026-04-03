<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) { echo '<p style="opacity:0.5;">Not logged in.</p>'; exit; }

$auction_id = intval($_GET['id'] ?? 0);
if (!$auction_id) { echo '<p style="opacity:0.5;">Invalid auction.</p>'; exit; }

$auction = getAuction($conn, $auction_id);
if (!$auction) { echo '<p style="opacity:0.5;">Auction not found.</p>'; exit; }

$user_id    = intval($_SESSION['userData']['user_id']);
$is_creator = $user_id === intval($auction['user_id']);
$is_closed  = $auction['completed'] || $auction['canceled'];
$ended      = strtotime($auction['end_date']) < time();
$upcoming   = strtotime($auction['start_date']) > time();
$is_leader  = intval($auction['current_bidder_id']) === $user_id;

// Allowed project options for bid form
$allowed = $auction['allowed_projects'];
if (empty($allowed)) {
    $res = $conn->query("SELECT id, name, currency FROM projects ORDER BY id ASC");
    $allowed = [];
    if ($res) while ($r = $res->fetch_assoc()) $allowed[] = $r;
}

// Current user balances for each allowed project
$balances = [];
foreach ($allowed as $p) {
    $bal = getCurrentBalance($conn, $user_id, intval($p['project_id'] ?? $p['id']));
    $balances[intval($p['project_id'] ?? $p['id'])] = ($bal === 'false') ? 0 : floatval($bal);
}

$has_img = !empty($auction['image']) && file_exists(__DIR__ . '/../images/auctions/' . $auction['image']);
// Resolve current bid currency label from already-loaded allowed projects list
$cur_bid_label = '';
$cur_bid_currency_lc = '';
$cur_bid_pid = intval($auction['current_bid_project_id']);
foreach ($allowed as $ap) {
    $apid = intval($ap['project_id'] ?? $ap['id']);
    if ($apid === $cur_bid_pid) { $cur_bid_currency_lc = strtolower($ap['currency']); $cur_bid_label = strtoupper($ap['currency']); break; }
}

// Delivery tracking data (fetch before close)
$delivery_winner_name = '';
$delivery_winner_addr = '';
$is_winner            = false;
$delivery_days_left   = 0;
if ($auction['processing'] && $auction['winner_id']) {
    $dw_id  = intval($auction['winner_id']);
    $dw_res = $conn->query("SELECT username FROM users WHERE id='$dw_id' LIMIT 1");
    $delivery_winner_name = ($dw_res && $dw_res->num_rows) ? $dw_res->fetch_assoc()['username'] : 'Unknown';
    $delivery_winner_addr = getWinnerAddress($conn, $dw_id) ?? '';
    $is_winner            = $user_id === $dw_id;
    $delivery_days_left   = max(0, ceil(30 - (time() - strtotime($auction['end_date'])) / 86400));
}
$conn->close();
?>
<?php if ($has_img): ?>
<img class="auction-detail-img" src="images/auctions/<?php echo htmlspecialchars($auction['image']); ?>" alt="" />
<?php endif; ?>
<div style="font-size:1rem;font-weight:bold;color:#e8eef4;"><?php echo htmlspecialchars($auction['title']); ?></div>

<div style="display:flex;flex-direction:column;gap:6px;background:rgba(0,200,160,0.06);border:1px solid rgba(0,200,160,0.15);border-radius:8px;padding:12px;">
  <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
    <span style="opacity:0.5;">Current Bid</span>
    <span style="font-weight:bold;color:#00c8a0;">
      <?php
        if ($auction['current_bid'] > 0) {
          if ($cur_bid_currency_lc) echo '<img src="icons/' . $cur_bid_currency_lc . '.png" style="width:14px;height:14px;vertical-align:middle;margin-right:3px;">';
          echo number_format($auction['current_bid']) . ($cur_bid_label ? ' ' . $cur_bid_label : '');
        } else {
          // Show lowest minimum bid from allowed projects
          $min_bids = array_column($allowed, 'minimum_bid');
          $min_val  = !empty($min_bids) ? min(array_filter($min_bids)) : 0;
          echo 'No bids yet';
          if ($min_val) echo ' — min ' . number_format($min_val);
        }
      ?>
    </span>
  </div>
  <?php if ($is_leader): ?><div style="font-size:0.75rem;color:#00c8a0;opacity:0.8;">&#x2713; You are the current leader</div><?php endif; ?>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;"><?php echo $upcoming ? 'Launches' : 'Ends'; ?></span>
    <span><span class="countdown" data-deadline="<?php echo strtotime($upcoming ? $auction['start_date'] : $auction['end_date']); ?>"></span></span>
  </div>
  <div style="font-size:0.75rem;opacity:0.4;display:flex;align-items:center;gap:5px;">By
    <?php
      $cav = $auction['creator_avatar'] ?? '';
      $cdis = $auction['creator_discord'] ?? '';
      $cname = htmlspecialchars($auction['creator_name']);
      $cuser = htmlspecialchars($auction['creator_name']);
      if ($cdis && $cav) echo '<img src="https://cdn.discordapp.com/avatars/' . $cdis . '/' . $cav . '.png" style="width:16px;height:16px;border-radius:50%;vertical-align:middle;">';
      echo '<a href="/staking/profile.php?username=' . $cuser . '" style="color:inherit;text-decoration:underline;">' . $cname . '</a>';
    ?>
  </div>
</div>

<?php if ($auction['processing'] && $auction['winner_id']): ?>
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
  <?php if ($auction['asset_id']): ?>
  <div style="display:flex;flex-direction:column;gap:3px;">
    <span style="opacity:0.5;font-size:0.78rem;">NFT Asset ID</span>
    <div style="background:rgba(0,0,0,0.25);border-radius:5px;padding:6px 9px;font-family:monospace;font-size:0.72rem;word-break:break-all;line-height:1.5;"><?php echo htmlspecialchars($auction['asset_id']); ?></div>
  </div>
  <?php endif; ?>
  <?php if ($delivery_winner_addr): ?>
  <div style="display:flex;flex-direction:column;gap:4px;">
    <span style="opacity:0.5;font-size:0.78rem;">Send NFT to this address</span>
    <div style="background:rgba(0,0,0,0.25);border-radius:5px;padding:7px 9px;font-family:monospace;font-size:0.7rem;word-break:break-all;line-height:1.6;"><?php echo htmlspecialchars($delivery_winner_addr); ?></div>
    <button onclick="navigator.clipboard.writeText(<?php echo json_encode($delivery_winner_addr); ?>).then(function(){var b=document.getElementById('copy-addr-btn');b.textContent='Copied!';setTimeout(function(){b.textContent='Copy Address'},1500)})" id="copy-addr-btn" style="background:rgba(0,200,160,0.12);border:1px solid rgba(0,200,160,0.3);border-radius:5px;color:#00c8a0;font-size:0.75rem;padding:5px 12px;cursor:pointer;align-self:flex-start;">Copy Address</button>
  </div>
  <?php else: ?>
  <div style="background:rgba(255,200,0,0.08);border:1px solid rgba(255,200,0,0.2);border-radius:6px;padding:8px 10px;font-size:0.78rem;color:#ffc800;">&#x26A0;&#xFE0F; <strong><?php echo htmlspecialchars($delivery_winner_name); ?></strong> has no linked Cardano wallet. Contact them directly in Discord to obtain their address.</div>
  <?php endif; ?>
</div>
<?php elseif ($is_winner): ?>
<div style="background:rgba(0,200,160,0.07);border:1px solid rgba(0,200,160,0.22);border-radius:8px;padding:13px 14px;display:flex;flex-direction:column;gap:8px;">
  <div style="font-size:0.85rem;font-weight:bold;color:#00c8a0;">&#x1F3C6; You won this auction!</div>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;">Delivery window</span>
    <span><?php echo $delivery_days_left; ?> of 30 days remaining</span>
  </div>
  <?php if ($auction['asset_id']): ?>
  <div style="display:flex;flex-direction:column;gap:3px;">
    <span style="opacity:0.5;font-size:0.78rem;">NFT Asset ID</span>
    <div style="background:rgba(0,0,0,0.25);border-radius:5px;padding:6px 9px;font-family:monospace;font-size:0.72rem;word-break:break-all;line-height:1.5;"><?php echo htmlspecialchars($auction['asset_id']); ?></div>
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
<div style="font-size:0.82rem;opacity:0.5;">You created this auction and cannot place bids.</div>
<?php elseif (!$is_closed && !$ended && !$upcoming): ?>
<div style="display:flex;flex-direction:column;gap:8px;">
  <div style="font-size:0.82rem;font-weight:bold;">Place a Bid</div>
  <div style="display:flex;gap:8px;">
    <select id="bid-project-select" style="background:#0a1520;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#e8eef4;padding:7px 9px;font-size:0.82rem;flex:1;">
      <?php
        $has_current_bid = $auction['current_bid'] > 0;
        $current_norm    = $has_current_bid
            ? floatval($auction['current_bid']) * getProjectNormalizedValue(intval($auction['current_bid_project_id']))
            : 0;
        foreach ($allowed as $p):
          $apid    = intval($p['project_id'] ?? $p['id']);
          $bal     = $balances[$apid] ?? 0;
          $min_bid = $has_current_bid
              ? (int) ceil($current_norm * 1.05 / getProjectNormalizedValue($apid))
              : max(1, intval($p['minimum_bid'] ?? 1));
      ?>
      <option value="<?php echo $apid; ?>" data-minbid="<?php echo $min_bid; ?>"><?php echo htmlspecialchars($p['name']); ?> (<?php echo strtoupper($p['currency']); ?>) — Min: <?php echo number_format($min_bid); ?> — Bal: <?php echo number_format($bal); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <input type="number" id="bid-amount" min="1" step="1" placeholder="Bid amount…" style="background:#0a1520;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#e8eef4;padding:7px 9px;font-size:0.82rem;width:100%;box-sizing:border-box;" />
  <div id="bid-error" style="color:#ff6b6b;font-size:0.78rem;display:none;"></div>
  <button class="small-button" onclick="submitBid(<?php echo $auction_id; ?>)">Submit Bid</button>
  <p style="font-size:0.72rem;opacity:0.4;margin:0;">Bids must be at least 5% above current bid (normalized). Outbid bids are refunded automatically.</p>
</div>
<?php elseif ($upcoming): ?>
<div style="font-size:0.82rem;opacity:0.5;">This auction hasn't started yet.</div>
<?php elseif ($is_closed): ?>
<div style="font-size:0.82rem;color:#ff6b6b;opacity:0.7;">This auction is closed.</div>
<?php else: ?>
<div style="font-size:0.82rem;opacity:0.5;">Auction has ended. Awaiting processing.</div>
<?php endif; ?>

<?php if (!empty($auction['bids'])): ?>
<div style="display:flex;flex-direction:column;gap:6px;">
  <div style="font-size:0.78rem;font-weight:bold;opacity:0.7;">Recent Bids</div>
  <div class="bid-history">
    <?php foreach ($auction['bids'] as $b): ?>
    <div class="bid-row">
      <span style="flex:1;min-width:0;display:flex;align-items:center;gap:5px;">
        <?php if (!empty($b['bidder_discord']) && !empty($b['bidder_avatar'])): ?>
        <img src="https://cdn.discordapp.com/avatars/<?php echo $b['bidder_discord']; ?>/<?php echo $b['bidder_avatar']; ?>.png" style="width:16px;height:16px;border-radius:50%;flex-shrink:0;">
        <?php endif; ?>
        <a href="/staking/profile.php?username=<?php echo htmlspecialchars($b['bidder_name']); ?>" style="color:inherit;text-decoration:underline;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($b['bidder_name']); ?></a>
      </span>
      <span style="flex:0 0 130px;text-align:right;display:flex;align-items:center;justify-content:flex-end;gap:3px;">
        <img src="icons/<?php echo strtolower($b['currency']); ?>.png" style="width:14px;height:14px;">
        <?php echo number_format($b['amount']); ?> <?php echo strtoupper($b['currency']); ?>
      </span>
      <span style="flex:0 0 75px;text-align:right;opacity:0.4;"><?php echo date('M j g:ia', strtotime($b['created_date'])); ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
