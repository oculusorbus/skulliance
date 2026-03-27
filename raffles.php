<?php
include 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
include 'header.php';

$raffles = getActiveRaffles($conn);
$all_projects_res = $conn->query("SELECT id, name, currency FROM projects ORDER BY id ASC");
$all_projects = array();
if ($all_projects_res) { while ($r = $all_projects_res->fetch_assoc()) $all_projects[] = $r; }
?>
<style>
.raffles-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap:18px; margin-top:16px; }
.raffle-card { background:#0d1f2d; border:1px solid rgba(0,200,160,0.15); border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.raffle-card-img { width:100%; aspect-ratio:1/1; object-fit:cover; background:#111; }
.raffle-card-img-placeholder { width:100%; aspect-ratio:1/1; background:#0a1a26; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,0.2); font-size:2.5rem; }
.raffle-card-body { padding:14px; flex:1; display:flex; flex-direction:column; gap:8px; }
.raffle-card-title { font-weight:bold; font-size:0.95rem; color:#e8eef4; }
.raffle-card-desc { font-size:0.78rem; opacity:0.55; line-height:1.4; flex:1; }
.raffle-stat-row { display:flex; align-items:center; justify-content:space-between; font-size:0.82rem; }
.raffle-stat-label { opacity:0.5; }
.raffle-stat-value { font-weight:bold; color:#00c8a0; }
.raffle-timer { font-size:0.75rem; opacity:0.5; }
.raffle-card-footer { padding:10px 14px; border-top:1px solid rgba(255,255,255,0.05); }
.raffle-empty { opacity:0.5; text-align:center; padding:40px 0; }
.raffle-progress-bar { height:6px; background:rgba(255,255,255,0.08); border-radius:3px; overflow:hidden; margin-top:4px; }
.raffle-progress-fill { height:100%; background:#00c8a0; border-radius:3px; }

/* Modals reuse auction-modal styles from auctions.php; include inline */
.raffle-modal { display:none; position:fixed; inset:0; z-index:800; align-items:center; justify-content:center; }
.raffle-modal.open { display:flex; }
.raffle-modal-overlay { position:absolute; inset:0; background:rgba(0,0,0,0.75); }
.raffle-modal-box { position:relative; z-index:1; background:#0d1f2d; border:1px solid rgba(0,200,160,0.2); border-radius:12px; width:min(520px,95vw); max-height:90vh; overflow-y:auto; padding:24px; display:flex; flex-direction:column; gap:16px; }
.raffle-modal-title { font-size:1.1rem; font-weight:bold; color:#e8eef4; }
.raffle-modal-close { position:absolute; top:14px; right:16px; background:none; border:none; color:rgba(255,255,255,0.5); font-size:1.4rem; cursor:pointer; }
.raffle-modal-close:hover { color:#fff; }
.form-row { display:flex; flex-direction:column; gap:5px; }
.form-row label { font-size:0.78rem; opacity:0.6; }
.form-row input, .form-row textarea, .form-row select { background:#0a1520; border:1px solid rgba(255,255,255,0.1); border-radius:6px; color:#e8eef4; padding:8px 10px; font-size:0.85rem; width:100%; box-sizing:border-box; }
.form-row textarea { resize:vertical; min-height:70px; }
.form-row input:focus, .form-row textarea:focus, .form-row select:focus { outline:none; border-color:rgba(0,200,160,0.4); }
.form-section-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.05em; opacity:0.4; margin-top:4px; }
.ticket-modal-box { width:min(420px,95vw); }
</style>

<div class="row" id="row1">
  <div class="main">
    <h2>Raffles</h2>
    <div class="content">
      <p style="font-size:0.85rem;opacity:0.6;margin-bottom:4px;">Buy tickets for a chance to win NFTs and prizes. The more tickets you hold, the better your odds.</p>

      <?php if ($member): ?>
      <div style="margin-bottom:16px;">
        <button class="small-button" onclick="openCreateRaffleModal()">+ Create Raffle</button>
      </div>
      <?php endif; ?>

      <?php if (empty($raffles)): ?>
      <div class="raffle-empty"><p>No active raffles right now. Check back later or create one!</p></div>
      <?php else: ?>
      <div class="raffles-grid">
        <?php foreach ($raffles as $r):
          $has_img = !empty($r['image_path']) && file_exists($r['image_path']);
          $is_owner = (isset($_SESSION['userData']['user_id']) && intval($r['user_id']) === intval($_SESSION['userData']['user_id']));
          $sold = intval($r['total_tickets_sold']);
          $max  = $r['max_tickets'] ? intval($r['max_tickets']) : null;
          $pct  = ($max && $max > 0) ? min(100, round($sold / $max * 100)) : null;
        ?>
        <div class="raffle-card">
          <?php if ($has_img): ?>
          <img class="raffle-card-img" src="<?php echo htmlspecialchars($r['image_path']); ?>" alt="" />
          <?php else: ?>
          <div class="raffle-card-img-placeholder">&#x1F3AB;</div>
          <?php endif; ?>
          <div class="raffle-card-body">
            <div class="raffle-card-title"><?php echo htmlspecialchars($r['title']); ?></div>
            <?php if ($r['nft_name']): ?><div style="font-size:0.75rem;opacity:0.45;margin-top:-4px;"><?php echo htmlspecialchars($r['nft_name']); ?></div><?php endif; ?>
            <?php if ($r['description']): ?><div class="raffle-card-desc"><?php echo htmlspecialchars(mb_substr($r['description'],0,100)) . (mb_strlen($r['description'])>100?'…':''); ?></div><?php endif; ?>
            <div class="raffle-stat-row">
              <span class="raffle-stat-label">Ticket Price</span>
              <span class="raffle-stat-value"><?php echo number_format($r['ticket_price']); ?> <?php echo strtoupper($r['ticket_currency']); ?></span>
            </div>
            <div class="raffle-stat-row">
              <span class="raffle-stat-label">Tickets Sold</span>
              <span class="raffle-stat-value"><?php echo $sold; ?><?php echo $max ? ' / '.$max : ''; ?></span>
            </div>
            <?php if ($pct !== null): ?>
            <div class="raffle-progress-bar"><div class="raffle-progress-fill" style="width:<?php echo $pct; ?>%"></div></div>
            <?php endif; ?>
            <?php if ($r['tickets_per_user']): ?><div style="font-size:0.75rem;opacity:0.45;">Max <?php echo $r['tickets_per_user']; ?> tickets per person</div><?php endif; ?>
            <div class="raffle-timer">Ends: <span class="countdown" data-deadline="<?php echo strtotime($r['end_date']); ?>"></span></div>
            <div style="font-size:0.72rem;opacity:0.35;">By <?php echo htmlspecialchars($r['creator_name']); ?></div>
          </div>
          <div class="raffle-card-footer">
            <button class="small-button" onclick="openTicketModal(<?php echo $r['id']; ?>)" style="width:100%;">Buy Tickets</button>
            <?php if ($is_owner && !$r['completed'] && !$r['canceled']): ?>
            <button class="small-button" onclick="cancelRaffle(<?php echo $r['id']; ?>)" style="width:100%;margin-top:6px;background:rgba(200,50,50,0.15);border-color:rgba(200,50,50,0.3);">Cancel Raffle</button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Create Raffle Modal -->
<div class="raffle-modal" id="create-raffle-modal">
  <div class="raffle-modal-overlay" onclick="closeCreateRaffleModal()"></div>
  <div class="raffle-modal-box">
    <button class="raffle-modal-close" onclick="closeCreateRaffleModal()">&times;</button>
    <div class="raffle-modal-title">Create Raffle</div>
    <div class="form-section-label">Prize Details</div>
    <div class="form-row"><label>Title *</label><input type="text" id="r-title" maxlength="255" placeholder="What are you raffling off?" /></div>
    <div class="form-row"><label>Description</label><textarea id="r-desc" placeholder="Optional prize details…"></textarea></div>
    <div class="form-row"><label>NFT Name (optional display name)</label><input type="text" id="r-nft-name" maxlength="255" placeholder="e.g. Skull #1234" /></div>
    <div class="form-row"><label>Cardano Asset ID (optional)</label><input type="text" id="r-asset-id" maxlength="120" placeholder="Policy ID + hex asset name" /></div>
    <div class="form-row">
      <label>Image Upload (optional — PNG/GIF, max 5MB)</label>
      <input type="file" id="r-image" accept="image/png,image/gif,image/jpeg,image/webp" />
    </div>
    <div class="form-section-label" style="margin-top:8px;">Ticket Settings</div>
    <div class="form-row">
      <label>Ticket Currency *</label>
      <select id="r-ticket-project">
        <option value="">Select currency…</option>
        <?php foreach ($all_projects as $p): ?>
        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> (<?php echo strtoupper($p['currency']); ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row"><label>Ticket Price *</label><input type="number" id="r-ticket-price" min="1" step="1" placeholder="e.g. 50" /></div>
    <div class="form-row"><label>Max Tickets (leave blank for unlimited)</label><input type="number" id="r-max-tickets" min="1" step="1" placeholder="e.g. 100" /></div>
    <div class="form-row"><label>Max Tickets Per User (leave blank for unlimited)</label><input type="number" id="r-per-user" min="1" step="1" placeholder="e.g. 5" /></div>
    <div class="form-row"><label>End Date *</label><input type="datetime-local" id="r-end-date" /></div>
    <div id="r-error" style="color:#ff6b6b;font-size:0.82rem;display:none;"></div>
    <button class="small-button" onclick="submitCreateRaffle()" style="margin-top:4px;">Create Raffle</button>
  </div>
</div>

<!-- Ticket Purchase Modal -->
<div class="raffle-modal" id="ticket-modal">
  <div class="raffle-modal-overlay" onclick="closeTicketModal()"></div>
  <div class="raffle-modal-box ticket-modal-box">
    <button class="raffle-modal-close" onclick="closeTicketModal()">&times;</button>
    <div id="ticket-modal-inner" style="display:flex;flex-direction:column;gap:12px;">
      <div style="opacity:0.5;font-size:0.85rem;">Loading…</div>
    </div>
  </div>
</div>

<!-- Footer -->
<div class="footer"><p>Skulliance<br>Copyright © <span id="year"></span></p></div>
</div></div>
<?php $conn->close(); ?>
<script type="text/javascript" src="skulliance.js"></script>
<script type="text/javascript">
// ── Countdown timers ──────────────────────────────────────────────────────────
function updateCountdowns() {
  var now = Math.floor(Date.now() / 1000);
  document.querySelectorAll('.countdown[data-deadline]').forEach(function(el) {
    var deadline = parseInt(el.dataset.deadline, 10);
    var diff = deadline - now;
    if (diff <= 0) { el.textContent = 'Ended'; return; }
    var d = Math.floor(diff / 86400);
    var h = Math.floor((diff % 86400) / 3600);
    var m = Math.floor((diff % 3600) / 60);
    var s = diff % 60;
    el.textContent = (d > 0 ? d + 'd ' : '') + (h > 0 ? h + 'h ' : '') + (m > 0 ? m + 'm ' : '') + s + 's';
  });
}
setInterval(updateCountdowns, 1000);
updateCountdowns();

// ── Create Raffle ─────────────────────────────────────────────────────────────
function openCreateRaffleModal() {
  document.getElementById('create-raffle-modal').classList.add('open');
}
function closeCreateRaffleModal() {
  document.getElementById('create-raffle-modal').classList.remove('open');
}

function submitCreateRaffle() {
  var err = document.getElementById('r-error');
  err.style.display = 'none';
  var title    = document.getElementById('r-title').value.trim();
  var desc     = document.getElementById('r-desc').value.trim();
  var nftName  = document.getElementById('r-nft-name').value.trim();
  var assetId  = document.getElementById('r-asset-id').value.trim();
  var tpid     = document.getElementById('r-ticket-project').value;
  var tprice   = parseFloat(document.getElementById('r-ticket-price').value);
  var maxT     = document.getElementById('r-max-tickets').value;
  var perU     = document.getElementById('r-per-user').value;
  var endDate  = document.getElementById('r-end-date').value;
  var imgFile  = document.getElementById('r-image').files[0];

  if (!title) { err.textContent = 'Title is required.'; err.style.display = 'block'; return; }
  if (!tpid) { err.textContent = 'Select a ticket currency.'; err.style.display = 'block'; return; }
  if (!tprice || tprice < 1) { err.textContent = 'Ticket price must be at least 1.'; err.style.display = 'block'; return; }
  if (!endDate) { err.textContent = 'End date is required.'; err.style.display = 'block'; return; }
  if (assetId && !/^[0-9a-fA-F]{56,120}$/.test(assetId)) { err.textContent = 'Asset ID must be 56-120 hex characters.'; err.style.display = 'block'; return; }

  var fd = new FormData();
  fd.append('title', title);
  fd.append('description', desc);
  fd.append('nft_name', nftName);
  fd.append('asset_id', assetId);
  fd.append('ticket_project_id', tpid);
  fd.append('ticket_price', tprice);
  fd.append('max_tickets', maxT);
  fd.append('tickets_per_user', perU);
  fd.append('end_date', endDate);
  if (imgFile) fd.append('image', imgFile);

  $.ajax({
    url: 'ajax/raffle-create.php',
    type: 'POST',
    data: fd,
    processData: false,
    contentType: false,
    success: function(res) {
      try { var r = JSON.parse(res); } catch(e) { err.textContent = 'Unexpected error.'; err.style.display = 'block'; return; }
      if (r.success) { location.reload(); }
      else { err.textContent = r.message || 'Error creating raffle.'; err.style.display = 'block'; }
    },
    error: function() { err.textContent = 'Server error.'; err.style.display = 'block'; }
  });
}

// ── Ticket Modal ──────────────────────────────────────────────────────────────
function openTicketModal(raffle_id) {
  document.getElementById('ticket-modal').classList.add('open');
  $.get('ajax/raffle-detail.php', { id: raffle_id }, function(html) {
    document.getElementById('ticket-modal-inner').innerHTML = html;
    updateCountdowns();
  });
}
function closeTicketModal() {
  document.getElementById('ticket-modal').classList.remove('open');
}

function submitBuyTickets(raffle_id) {
  var qty = parseInt(document.getElementById('ticket-qty').value, 10);
  var err = document.getElementById('ticket-error');
  err.style.display = 'none';
  if (!qty || qty < 1) { err.textContent = 'Enter a valid quantity.'; err.style.display = 'block'; return; }
  $.post('ajax/raffle-buy.php', { raffle_id: raffle_id, quantity: qty }, function(res) {
    try { var r = JSON.parse(res); } catch(e) { err.textContent = 'Unexpected error.'; err.style.display = 'block'; return; }
    if (r.success) { openNotify(r.message); setTimeout(function(){ openTicketModal(raffle_id); }, 800); }
    else { err.textContent = r.message || 'Purchase failed.'; err.style.display = 'block'; }
  });
}

function cancelRaffle(raffle_id) {
  openConfirm('Cancel this raffle? All ticket buyers will be fully refunded.', function() {
    $.post('ajax/raffle-cancel.php', { raffle_id: raffle_id }, function(res) {
      try { var r = JSON.parse(res); } catch(e) { openNotify('Unexpected error.'); return; }
      if (r.success) { location.reload(); }
      else { openNotify(r.message || 'Could not cancel raffle.'); }
    });
  });
}
</script>
</html>
