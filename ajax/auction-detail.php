<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) { echo '<p style="opacity:0.5;">Not logged in.</p>'; exit; }

$auction_id = intval($_GET['id'] ?? 0);
if (!$auction_id) { echo '<p style="opacity:0.5;">Invalid auction.</p>'; exit; }

$auction = getAuction($conn, $auction_id);
if (!$auction) { echo '<p style="opacity:0.5;">Auction not found.</p>'; exit; }

$user_id   = intval($_SESSION['userData']['user_id']);
$is_closed = $auction['completed'] || $auction['canceled'];
$ended     = strtotime($auction['end_date']) < time();
$upcoming  = strtotime($auction['start_date']) > time();
$is_leader = intval($auction['current_bidder_id']) === $user_id;

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

$has_img = !empty($auction['image_path']) && file_exists($auction['image_path']);
// Resolve current bid currency label from already-loaded allowed projects list
$cur_bid_label = '';
$cur_bid_pid = intval($auction['current_bid_project_id']);
foreach ($allowed as $ap) {
    $apid = intval($ap['project_id'] ?? $ap['id']);
    if ($apid === $cur_bid_pid) { $cur_bid_label = strtoupper($ap['currency']); break; }
}
$conn->close();
?>
<?php if ($has_img): ?>
<img class="auction-detail-img" src="<?php echo htmlspecialchars($auction['image_path']); ?>" alt="" />
<?php endif; ?>
<div style="font-size:1rem;font-weight:bold;color:#e8eef4;"><?php echo htmlspecialchars($auction['title']); ?></div>
<?php if ($auction['description']): ?><div style="font-size:0.82rem;opacity:0.6;line-height:1.5;"><?php echo nl2br(htmlspecialchars($auction['description'])); ?></div><?php endif; ?>

<div style="display:flex;flex-direction:column;gap:6px;background:rgba(0,200,160,0.06);border:1px solid rgba(0,200,160,0.15);border-radius:8px;padding:12px;">
  <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
    <span style="opacity:0.5;">Current Bid</span>
    <span style="font-weight:bold;color:#00c8a0;">
      <?php
        if ($auction['current_bid'] > 0) {
          echo number_format($auction['current_bid']) . ($cur_bid_label ? ' ' . $cur_bid_label : '');
        } else {
          echo 'No bids — starting at ' . number_format($auction['start_bid']);
        }
      ?>
    </span>
  </div>
  <?php if ($is_leader): ?><div style="font-size:0.75rem;color:#00c8a0;opacity:0.8;">&#x2713; You are the current leader</div><?php endif; ?>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;"><?php echo $upcoming ? 'Launches' : 'Ends'; ?></span>
    <span><span class="countdown" data-deadline="<?php echo strtotime($upcoming ? $auction['start_date'] : $auction['end_date']); ?>"></span></span>
  </div>
  <div style="font-size:0.75rem;opacity:0.4;">By <?php echo htmlspecialchars($auction['creator_name']); ?></div>
</div>

<?php if (!$is_closed && !$ended && !$upcoming): ?>
<div style="display:flex;flex-direction:column;gap:8px;">
  <div style="font-size:0.82rem;font-weight:bold;">Place a Bid</div>
  <div style="display:flex;gap:8px;">
    <select id="bid-project-select" style="background:#0a1520;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#e8eef4;padding:7px 9px;font-size:0.82rem;flex:1;">
      <?php foreach ($allowed as $p):
        $apid = intval($p['project_id'] ?? $p['id']);
        $bal  = $balances[$apid] ?? 0;
      ?>
      <option value="<?php echo $apid; ?>"><?php echo htmlspecialchars($p['name']); ?> (<?php echo strtoupper($p['currency']); ?>) — Bal: <?php echo number_format($bal); ?></option>
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
      <span><?php echo htmlspecialchars($b['bidder_name']); ?></span>
      <span><?php echo number_format($b['amount']); ?> <?php echo strtoupper($b['currency']); ?></span>
      <span style="opacity:0.4;"><?php echo date('M j g:ia', strtotime($b['created_date'])); ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
