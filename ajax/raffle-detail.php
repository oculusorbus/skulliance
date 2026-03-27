<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) { echo '<p style="opacity:0.5;">Not logged in.</p>'; exit; }

$raffle_id = intval($_GET['id'] ?? 0);
if (!$raffle_id) { echo '<p style="opacity:0.5;">Invalid raffle.</p>'; exit; }

$raffle    = getRaffle($conn, $raffle_id);
if (!$raffle) { echo '<p style="opacity:0.5;">Raffle not found.</p>'; exit; }

$user_id   = intval($_SESSION['userData']['user_id']);
$is_closed = $raffle['completed'] || $raffle['canceled'];
$ended     = strtotime($raffle['end_date']) < time();
$sold      = intval($raffle['total_tickets_sold']);
$max       = $raffle['max_tickets'] ? intval($raffle['max_tickets']) : null;
$pct       = ($max && $max > 0) ? min(100, round($sold / $max * 100)) : null;
$user_tickets = intval($raffle['user_tickets']);
$per_user  = $raffle['tickets_per_user'] ? intval($raffle['tickets_per_user']) : null;

$pid     = intval($raffle['ticket_project_id']);
$balance = getCurrentBalance($conn, $user_id, $pid);
$balance = ($balance === 'false') ? 0 : floatval($balance);

$has_img = !empty($raffle['image_path']) && file_exists($raffle['image_path']);
$conn->close();
?>
<?php if ($has_img): ?>
<img style="width:100%;max-height:220px;object-fit:contain;border-radius:8px;background:#0a1520;" src="<?php echo htmlspecialchars($raffle['image_path']); ?>" alt="" />
<?php endif; ?>
<div style="font-size:1rem;font-weight:bold;color:#e8eef4;"><?php echo htmlspecialchars($raffle['title']); ?></div>
<?php if ($raffle['nft_name']): ?><div style="font-size:0.78rem;opacity:0.45;"><?php echo htmlspecialchars($raffle['nft_name']); ?></div><?php endif; ?>
<?php if ($raffle['description']): ?><div style="font-size:0.82rem;opacity:0.6;line-height:1.5;"><?php echo nl2br(htmlspecialchars($raffle['description'])); ?></div><?php endif; ?>

<div style="display:flex;flex-direction:column;gap:6px;background:rgba(160,64,255,0.08);border:1px solid rgba(160,64,255,0.2);border-radius:8px;padding:12px;">
  <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
    <span style="opacity:0.5;">Ticket Price</span>
    <span style="font-weight:bold;color:#a040ff;"><?php echo number_format($raffle['ticket_price']); ?> <?php echo strtoupper($raffle['ticket_currency']); ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;">Tickets Sold</span>
    <span><?php echo $sold; ?><?php echo $max ? ' / '.$max : ''; ?></span>
  </div>
  <?php if ($pct !== null): ?>
  <div style="height:5px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden;"><div style="height:100%;width:<?php echo $pct; ?>%;background:#a040ff;border-radius:3px;"></div></div>
  <?php endif; ?>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;">Your Tickets</span>
    <span><?php echo $user_tickets; ?><?php echo $per_user ? ' / '.$per_user.' max' : ''; ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;">Your Balance</span>
    <span><?php echo number_format($balance); ?> <?php echo strtoupper($raffle['ticket_currency']); ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
    <span style="opacity:0.5;">Ends</span>
    <span><span class="countdown" data-deadline="<?php echo strtotime($raffle['end_date']); ?>"></span></span>
  </div>
  <div style="font-size:0.72rem;opacity:0.35;">By <?php echo htmlspecialchars($raffle['creator_name']); ?></div>
  <?php if ($sold > 0): ?>
  <div style="font-size:0.75rem;opacity:0.5;margin-top:4px;">
    Your odds: <?php echo $sold > 0 ? round($user_tickets / $sold * 100, 1) : 0; ?>% (<?php echo $user_tickets; ?> of <?php echo $sold; ?> tickets)
  </div>
  <?php endif; ?>
</div>

<?php if (!$is_closed && !$ended): ?>
<div style="display:flex;flex-direction:column;gap:8px;">
  <div style="font-size:0.82rem;font-weight:bold;">Buy Tickets</div>
  <div style="display:flex;gap:8px;align-items:center;">
    <input type="number" id="ticket-qty" min="1" step="1" value="1"
      <?php if ($per_user): ?>max="<?php echo max(0, $per_user - $user_tickets); ?>"<?php endif; ?>
      style="background:#0a1520;border:1px solid rgba(255,255,255,0.1);border-radius:6px;color:#e8eef4;padding:7px 9px;font-size:0.82rem;width:100px;box-sizing:border-box;"
      oninput="updateTicketCost(<?php echo $raffle['ticket_price']; ?>)" />
    <span style="font-size:0.82rem;opacity:0.6;">× <?php echo number_format($raffle['ticket_price']); ?> <?php echo strtoupper($raffle['ticket_currency']); ?> = <strong id="ticket-total"><?php echo number_format($raffle['ticket_price']); ?></strong> <?php echo strtoupper($raffle['ticket_currency']); ?></span>
  </div>
  <?php if ($per_user): ?><div style="font-size:0.72rem;opacity:0.4;">Max <?php echo max(0, $per_user - $user_tickets); ?> more ticket(s) available to you.</div><?php endif; ?>
  <?php if ($max): ?><div style="font-size:0.72rem;opacity:0.4;"><?php echo max(0, $max - $sold); ?> ticket(s) remaining overall.</div><?php endif; ?>
  <div id="ticket-error" style="color:#ff6b6b;font-size:0.78rem;display:none;"></div>
  <button class="small-button" onclick="submitBuyTickets(<?php echo $raffle_id; ?>)">Buy Tickets</button>
</div>
<script>
function updateTicketCost(price) {
  var qty = parseInt(document.getElementById('ticket-qty').value, 10) || 1;
  document.getElementById('ticket-total').textContent = (qty * price).toLocaleString();
}
</script>
<?php elseif ($is_closed): ?>
<div style="font-size:0.82rem;color:#ff6b6b;opacity:0.7;">This raffle is closed.</div>
<?php else: ?>
<div style="font-size:0.82rem;opacity:0.5;">Raffle has ended. Awaiting draw.</div>
<?php endif; ?>
