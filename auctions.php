<?php
include 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
include 'header.php';

$auctions = getActiveAuctions($conn);
$all_projects_res = $conn->query("SELECT id, name, currency FROM projects ORDER BY id ASC");
$all_projects = array();
if ($all_projects_res) { while ($r = $all_projects_res->fetch_assoc()) $all_projects[] = $r; }
?>
<style>
.auctions-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap:18px; margin-top:16px; }
.auction-card { background:#0d1f2d; border:1px solid rgba(0,200,160,0.15); border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.auction-card-img { width:100%; aspect-ratio:1/1; object-fit:cover; background:#111; }
.auction-card-img-placeholder { width:100%; aspect-ratio:1/1; background:#0a1a26; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,0.2); font-size:2.5rem; }
.auction-card-body { padding:14px; flex:1; display:flex; flex-direction:column; gap:8px; }
.auction-card-title { font-weight:bold; font-size:0.95rem; color:#e8eef4; }
.auction-card-desc { font-size:0.78rem; opacity:0.55; line-height:1.4; flex:1; }
.auction-bid-row { display:flex; align-items:center; justify-content:space-between; font-size:0.82rem; }
.auction-bid-label { opacity:0.5; }
.auction-bid-value { font-weight:bold; color:#00c8a0; }
.auction-timer { font-size:0.75rem; opacity:0.5; }
.auction-card-footer { padding:10px 14px; border-top:1px solid rgba(255,255,255,0.05); }
.auction-empty { opacity:0.5; text-align:center; padding:40px 0; }

/* Modal */
.auction-modal { display:none; position:fixed; inset:0; z-index:800; align-items:center; justify-content:center; }
.auction-modal.open { display:flex; }
.auction-modal-overlay { position:absolute; inset:0; background:rgba(0,0,0,0.75); }
.auction-modal-box { position:relative; z-index:1; background:#0d1f2d; border:1px solid rgba(0,200,160,0.2); border-radius:12px; width:min(560px,95vw); max-height:90vh; overflow-y:auto; padding:24px; display:flex; flex-direction:column; gap:16px; }
.auction-modal-title { font-size:1.1rem; font-weight:bold; color:#e8eef4; }
.auction-modal-close { position:absolute; top:14px; right:16px; background:none; border:none; color:rgba(255,255,255,0.5); font-size:1.4rem; cursor:pointer; }
.auction-modal-close:hover { color:#fff; }
.form-row { display:flex; flex-direction:column; gap:5px; }
.form-row label { font-size:0.78rem; opacity:0.6; }
.form-row input, .form-row textarea, .form-row select { background:#0a1520; border:1px solid rgba(255,255,255,0.1); border-radius:6px; color:#e8eef4; padding:8px 10px; font-size:0.85rem; width:100%; box-sizing:border-box; }
.form-row textarea { resize:vertical; min-height:70px; }
.form-row input:focus, .form-row textarea:focus, .form-row select:focus { outline:none; border-color:rgba(0,200,160,0.4); }
.form-section-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.05em; opacity:0.4; margin-top:4px; }
.projects-check-grid { display:flex; flex-wrap:wrap; gap:8px; margin-top:4px; }
.proj-check { display:flex; align-items:center; gap:5px; font-size:0.8rem; cursor:pointer; }
.proj-check input { width:auto; }

.bid-modal-box { width:min(420px,95vw); }
.bid-history { max-height:160px; overflow-y:auto; display:flex; flex-direction:column; gap:4px; }
.bid-row { display:flex; justify-content:space-between; font-size:0.78rem; padding:5px 8px; background:rgba(255,255,255,0.03); border-radius:4px; }

/* Detail modal image */
.auction-detail-img { width:100%; max-height:260px; object-fit:contain; border-radius:8px; background:#0a1520; }
</style>

<div class="row" id="row1">
  <div class="main">
    <h2>Auctions</h2>
    <div class="content">
      <p style="font-size:0.85rem;opacity:0.6;margin-bottom:4px;">Bid on rare items, NFTs, and prizes with your project points. Outbid others to claim the prize when the auction closes.</p>

      <?php if ($member): ?>
      <div style="margin-bottom:16px;">
        <button class="small-button" onclick="openCreateAuctionModal()">+ Create Auction</button>
      </div>
      <?php endif; ?>

      <?php if (empty($auctions)): ?>
      <div class="auction-empty"><p>No active auctions right now. Check back later or create one!</p></div>
      <?php else: ?>
      <div class="auctions-grid">
        <?php foreach ($auctions as $a):
          $has_img = !empty($a['image_path']) && file_exists($a['image_path']);
          $is_owner = (isset($_SESSION['userData']['user_id']) && intval($a['user_id']) === intval($_SESSION['userData']['user_id']));
        ?>
        <div class="auction-card">
          <?php if ($has_img): ?>
          <img class="auction-card-img" src="<?php echo htmlspecialchars($a['image_path']); ?>" alt="" />
          <?php else: ?>
          <div class="auction-card-img-placeholder">&#x1F3F7;</div>
          <?php endif; ?>
          <div class="auction-card-body">
            <div class="auction-card-title"><?php echo htmlspecialchars($a['title']); ?></div>
            <?php if ($a['nft_name']): ?><div style="font-size:0.75rem;opacity:0.45;margin-top:-4px;"><?php echo htmlspecialchars($a['nft_name']); ?></div><?php endif; ?>
            <?php if ($a['description']): ?><div class="auction-card-desc"><?php echo htmlspecialchars(mb_substr($a['description'],0,100)) . (mb_strlen($a['description'])>100?'…':''); ?></div><?php endif; ?>
            <div class="auction-bid-row">
              <span class="auction-bid-label">Current Bid</span>
              <span class="auction-bid-value">
                <?php
                  if ($a['current_bid'] > 0) {
                    $cur = $a['bid_currency'] ?: 'pts';
                    echo number_format($a['current_bid']) . ' ' . strtoupper($cur);
                  } else {
                    echo 'No bids yet';
                  }
                ?>
              </span>
            </div>
            <?php if ($a['bid_project_name']): ?>
            <div class="auction-bid-row" style="font-size:0.75rem;">
              <span style="opacity:0.45;">Accepted Currency</span>
              <span style="opacity:0.7;"><?php echo htmlspecialchars($a['bid_project_name']); ?></span>
            </div>
            <?php else: ?>
            <div class="auction-bid-row" style="font-size:0.75rem;">
              <span style="opacity:0.45;">Accepted Currency</span>
              <span style="opacity:0.7;">Any</span>
            </div>
            <?php endif; ?>
            <div class="auction-timer">
              Ends: <span class="countdown" data-deadline="<?php echo strtotime($a['end_date']); ?>"></span>
            </div>
            <div style="font-size:0.72rem;opacity:0.35;">By <?php echo htmlspecialchars($a['creator_name']); ?></div>
          </div>
          <div class="auction-card-footer">
            <button class="small-button" onclick="openBidModal(<?php echo $a['id']; ?>)" style="width:100%;">View &amp; Bid</button>
            <?php if ($is_owner && !$a['completed'] && !$a['canceled']): ?>
            <button class="small-button" onclick="cancelAuction(<?php echo $a['id']; ?>)" style="width:100%;margin-top:6px;background:rgba(200,50,50,0.15);border-color:rgba(200,50,50,0.3);">Cancel Auction</button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Create Auction Modal -->
<div class="auction-modal" id="create-auction-modal">
  <div class="auction-modal-overlay" onclick="closeCreateAuctionModal()"></div>
  <div class="auction-modal-box">
    <button class="auction-modal-close" onclick="closeCreateAuctionModal()">&times;</button>
    <div class="auction-modal-title">Create Auction</div>
    <div class="form-section-label">Item Details</div>
    <div class="form-row"><label>Title *</label><input type="text" id="a-title" maxlength="255" placeholder="What are you auctioning?" /></div>
    <div class="form-row"><label>Description</label><textarea id="a-desc" placeholder="Optional details about the item…"></textarea></div>
    <div class="form-row"><label>NFT Name (optional display name)</label><input type="text" id="a-nft-name" maxlength="255" placeholder="e.g. Skull #1234" /></div>
    <div class="form-row"><label>Cardano Asset ID (optional)</label><input type="text" id="a-asset-id" maxlength="120" placeholder="Policy ID + hex asset name" /></div>
    <div class="form-row">
      <label>Image Upload (optional — PNG/GIF, max 5MB)</label>
      <input type="file" id="a-image" accept="image/png,image/gif,image/jpeg,image/webp" />
    </div>
    <div class="form-section-label" style="margin-top:8px;">Bidding Settings</div>
    <div class="form-row"><label>Starting Bid *</label><input type="number" id="a-start-bid" min="1" step="1" placeholder="e.g. 100" /></div>
    <div class="form-row">
      <label>Accepted Currency (leave blank to accept any)</label>
      <select id="a-bid-project">
        <option value="">Any currency</option>
        <?php foreach ($all_projects as $p): ?>
        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> (<?php echo strtoupper($p['currency']); ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div id="a-multi-project-wrap">
      <div class="form-section-label">Or: accept specific currencies (check all that apply)</div>
      <div class="projects-check-grid" id="a-projects-check">
        <?php foreach ($all_projects as $p): ?>
        <label class="proj-check">
          <input type="checkbox" class="a-proj-cb" value="<?php echo $p['id']; ?>" />
          <?php echo htmlspecialchars($p['name']); ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="form-row"><label>End Date *</label><input type="datetime-local" id="a-end-date" /></div>
    <div id="a-error" style="color:#ff6b6b;font-size:0.82rem;display:none;"></div>
    <button class="small-button" onclick="submitCreateAuction()" style="margin-top:4px;">Create Auction</button>
  </div>
</div>

<!-- Bid / Detail Modal -->
<div class="auction-modal" id="bid-modal">
  <div class="auction-modal-overlay" onclick="closeBidModal()"></div>
  <div class="auction-modal-box bid-modal-box">
    <button class="auction-modal-close" onclick="closeBidModal()">&times;</button>
    <div id="bid-modal-inner" style="display:flex;flex-direction:column;gap:12px;">
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

// ── Create Auction Modal ──────────────────────────────────────────────────────
function openCreateAuctionModal() {
  document.getElementById('create-auction-modal').classList.add('open');
}
function closeCreateAuctionModal() {
  document.getElementById('create-auction-modal').classList.remove('open');
}

function submitCreateAuction() {
  var err = document.getElementById('a-error');
  err.style.display = 'none';
  var title    = document.getElementById('a-title').value.trim();
  var desc     = document.getElementById('a-desc').value.trim();
  var nftName  = document.getElementById('a-nft-name').value.trim();
  var assetId  = document.getElementById('a-asset-id').value.trim();
  var startBid = parseFloat(document.getElementById('a-start-bid').value);
  var bidPid   = document.getElementById('a-bid-project').value;
  var endDate  = document.getElementById('a-end-date').value;
  var imgFile  = document.getElementById('a-image').files[0];

  if (!title) { err.textContent = 'Title is required.'; err.style.display = 'block'; return; }
  if (!startBid || startBid < 1) { err.textContent = 'Starting bid must be at least 1.'; err.style.display = 'block'; return; }
  if (!endDate) { err.textContent = 'End date is required.'; err.style.display = 'block'; return; }
  if (assetId && !/^[0-9a-fA-F]{56,120}$/.test(assetId)) { err.textContent = 'Asset ID must be 56-120 hex characters.'; err.style.display = 'block'; return; }

  var checkedProjects = [];
  document.querySelectorAll('.a-proj-cb:checked').forEach(function(cb) { checkedProjects.push(cb.value); });

  var fd = new FormData();
  fd.append('title', title);
  fd.append('description', desc);
  fd.append('nft_name', nftName);
  fd.append('asset_id', assetId);
  fd.append('start_bid', startBid);
  fd.append('bid_project_id', bidPid);
  fd.append('allowed_projects', JSON.stringify(checkedProjects));
  fd.append('end_date', endDate);
  if (imgFile) fd.append('image', imgFile);

  $.ajax({
    url: 'ajax/auction-create.php',
    type: 'POST',
    data: fd,
    processData: false,
    contentType: false,
    success: function(res) {
      try { var r = JSON.parse(res); }
      catch(e) { err.textContent = 'Unexpected error.'; err.style.display = 'block'; return; }
      if (r.success) { location.reload(); }
      else { err.textContent = r.message || 'Error creating auction.'; err.style.display = 'block'; }
    },
    error: function() { err.textContent = 'Server error.'; err.style.display = 'block'; }
  });
}

// ── Bid Modal ─────────────────────────────────────────────────────────────────
function openBidModal(auction_id) {
  document.getElementById('bid-modal').classList.add('open');
  $.get('ajax/auction-detail.php', { id: auction_id }, function(html) {
    document.getElementById('bid-modal-inner').innerHTML = html;
    updateCountdowns();
  });
}
function closeBidModal() {
  document.getElementById('bid-modal').classList.remove('open');
}

function submitBid(auction_id) {
  var amt = parseFloat(document.getElementById('bid-amount').value);
  var pid = document.getElementById('bid-project-select').value;
  var err = document.getElementById('bid-error');
  err.style.display = 'none';
  if (!amt || amt < 1) { err.textContent = 'Enter a valid bid amount.'; err.style.display = 'block'; return; }
  $.post('ajax/auction-bid.php', { auction_id: auction_id, amount: amt, project_id: pid }, function(res) {
    try { var r = JSON.parse(res); }
    catch(e) { err.textContent = 'Unexpected error.'; err.style.display = 'block'; return; }
    if (r.success) { openBidModal(auction_id); }
    else { err.textContent = r.message || 'Bid failed.'; err.style.display = 'block'; }
  });
}

function cancelAuction(auction_id) {
  openConfirm('Cancel this auction? Any current bid will be refunded.', function() {
    $.post('ajax/auction-cancel.php', { auction_id: auction_id }, function(res) {
      try { var r = JSON.parse(res); } catch(e) { openNotify('Unexpected error.'); return; }
      if (r.success) { location.reload(); }
      else { openNotify(r.message || 'Could not cancel auction.'); }
    });
  });
}
</script>
</html>
