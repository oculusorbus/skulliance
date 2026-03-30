<?php
include 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
include 'header.php';

$auctions = getActiveAuctions($conn);
$all_projects_res = $conn->query("SELECT id, name, currency, divider FROM projects ORDER BY id ASC");
$all_projects = array();
if ($all_projects_res) { while ($r = $all_projects_res->fetch_assoc()) $all_projects[] = $r; }
$now_ts = time();
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
.auction-upcoming-badge { font-size:0.72rem; background:rgba(255,200,0,0.12); border:1px solid rgba(255,200,0,0.25); border-radius:4px; padding:2px 7px; color:#ffc800; display:inline-block; margin-bottom:4px; }
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
.currency-rows { display:flex; flex-direction:column; gap:6px; }
.currency-row { display:flex; gap:8px; align-items:center; }
.currency-row select { flex:2; }
.currency-row input { flex:1; }
.currency-row .rm-btn { flex-shrink:0; background:rgba(200,50,50,0.15); border:1px solid rgba(200,50,50,0.3); border-radius:5px; color:#ff6b6b; cursor:pointer; padding:6px 10px; font-size:0.78rem; white-space:nowrap; }

.bid-modal-box { width:min(420px,95vw); }
.bid-history { max-height:160px; overflow-y:auto; display:flex; flex-direction:column; gap:4px; }
.bid-row { display:flex; justify-content:space-between; font-size:0.78rem; padding:5px 8px; background:rgba(255,255,255,0.03); border-radius:4px; }

.auction-detail-img { width:100%; max-height:260px; object-fit:contain; border-radius:8px; background:#0a1520; }

/* ── Flatpickr overrides ── */
.flatpickr-calendar { background:#0e1e2e !important; border:1px solid rgba(255,255,255,0.12) !important; box-shadow:0 8px 32px rgba(0,0,0,0.5) !important; }
.flatpickr-day { color:#c8d8e4 !important; }
.flatpickr-day:hover, .flatpickr-day.prevMonthDay:hover, .flatpickr-day.nextMonthDay:hover { background:rgba(0,200,160,0.15) !important; border-color:transparent !important; }
.flatpickr-day.selected, .flatpickr-day.selected:hover { background:#00c8a0 !important; border-color:#00c8a0 !important; color:#0a1520 !important; }
.flatpickr-day.today { border-color:#00c8a0 !important; }
.flatpickr-months, .flatpickr-weekdays, .flatpickr-time { background:#0e1e2e !important; }
.flatpickr-current-month, .flatpickr-weekday, .numInputWrapper span { color:#c8d8e4 !important; }
.flatpickr-prev-month svg, .flatpickr-next-month svg { fill:#c8d8e4 !important; }
.flatpickr-time input, .flatpickr-time .flatpickr-am-pm { color:#c8d8e4 !important; background:#0a1520 !important; }
.flatpickr-time input:focus, .flatpickr-time .flatpickr-am-pm:focus { background:rgba(0,200,160,0.08) !important; }
.numInput { color:#c8d8e4 !important; background:#0a1520 !important; }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<div class="row" id="row1">
  <div class="main">
    <h2>Auctions</h2>
    <div class="content">
      <p style="font-size:0.85rem;opacity:0.6;margin-bottom:4px;">Bid on NFTs/FTs with your points. Outbid others to claim the prize when the auction closes.</p>

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
          $has_img   = !empty($a['image_path']) && file_exists($a['image_path']);
          $is_owner  = (isset($_SESSION['userData']['user_id']) && intval($a['user_id']) === intval($_SESSION['userData']['user_id']));
          $upcoming  = strtotime($a['start_date']) > $now_ts;
        ?>
        <div class="auction-card">
          <?php if ($has_img): ?>
          <img class="auction-card-img" src="<?php echo htmlspecialchars($a['image_path']); ?>" alt="" />
          <?php else: ?>
          <div class="auction-card-img-placeholder">&#x1F3F7;</div>
          <?php endif; ?>
          <div class="auction-card-body">
            <?php if ($upcoming): ?>
            <div class="auction-upcoming-badge">Upcoming</div>
            <?php endif; ?>
            <div class="auction-card-title"><?php echo htmlspecialchars($a['title']); ?></div>
            <div class="auction-bid-row">
              <span class="auction-bid-label">Current Bid</span>
              <span class="auction-bid-value">
                <?php
                  if ($a['current_bid'] > 0) {
                    $cur_lc = strtolower($a['current_bid_currency'] ?: 'pts');
                    echo '<img src="icons/' . $cur_lc . '.png" style="width:14px;height:14px;vertical-align:middle;margin-right:3px;">';
                    echo number_format($a['current_bid']) . ' ' . strtoupper($cur_lc);
                  } else {
                    echo 'No bids yet';
                  }
                ?>
              </span>
            </div>
            <div class="auction-timer">
              <?php if ($upcoming): ?>
              Launches: <span class="countdown" data-deadline="<?php echo strtotime($a['start_date']); ?>"></span>
              <?php else: ?>
              Ends: <span class="countdown" data-deadline="<?php echo strtotime($a['end_date']); ?>"></span>
              <?php endif; ?>
            </div>
            <div style="font-size:0.72rem;opacity:0.35;display:flex;align-items:center;gap:5px;">By
              <?php if (!empty($a['creator_discord']) && !empty($a['creator_avatar'])): ?>
              <img src="https://cdn.discordapp.com/avatars/<?php echo $a['creator_discord']; ?>/<?php echo $a['creator_avatar']; ?>.png" style="width:14px;height:14px;border-radius:50%;vertical-align:middle;">
              <?php endif; ?>
              <a href="/staking/profile.php?username=<?php echo htmlspecialchars($a['creator_name']); ?>" style="color:inherit;text-decoration:underline;"><?php echo htmlspecialchars($a['creator_name']); ?></a>
            </div>
          </div>
          <div class="auction-card-footer">
            <button class="small-button" onclick="openBidModal(<?php echo $a['id']; ?>)" style="width:100%;">View &amp; Bid</button>
            <?php if ($is_owner && !$a['completed'] && !$a['canceled']): ?>
            <button class="small-button small-button-danger" onclick="cancelAuction(<?php echo $a['id']; ?>)" style="width:100%;margin-top:6px;">Cancel Auction</button>
            <?php endif; ?>
            <?php if ($is_owner && !$a['completed'] && !$a['canceled']): ?>
            <button class="small-button" onclick="openEditAuctionModal(<?php echo $a['id']; ?>)" style="width:100%;margin-top:6px;">Edit Auction</button>
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
    <div class="form-row"><label>Cardano Asset ID *</label><input type="text" id="a-asset-id" maxlength="44" placeholder="asset1..." /></div>
    <div id="a-img-status" style="font-size:0.75rem;margin-top:4px;display:none;"></div>
    <div id="a-img-preview" style="display:none;margin-top:6px;"><img id="a-img-preview-img" style="max-width:80px;max-height:80px;border-radius:6px;border:1px solid rgba(255,255,255,0.12);object-fit:cover;" /></div>

    <div class="form-row" id="a-title-row" style="display:none;">
      <label>Title *</label>
      <input type="text" id="a-title" maxlength="255" placeholder="What are you auctioning?" />
    </div>

    <div class="form-row" id="a-image-row" style="display:none;">
      <label>Image Upload</label>
      <input type="file" id="a-image" accept="image/png,image/gif,image/jpeg,image/webp" />
      <input type="hidden" id="a-ipfs-url" />
    </div>

    <div class="form-row" id="a-qty-row" style="display:none;">
      <label>Quantity *</label>
      <input type="number" id="a-quantity" min="1" step="1" value="1" style="max-width:140px;" />
    </div>

    <div class="form-section-label" style="margin-top:8px;">Accepted Currencies &amp; Minimum Bids *</div>
    <div class="currency-rows" id="a-currency-rows"></div>
    <button type="button" onclick="addAuctionCurrencyRow()" style="background:rgba(0,200,160,0.08);border:1px solid rgba(0,200,160,0.2);border-radius:6px;color:#00c8a0;padding:6px 14px;font-size:0.8rem;cursor:pointer;align-self:flex-start;">+ Add Currency</button>

    <div class="form-section-label" style="margin-top:8px;">Schedule</div>
    <div class="form-row"><label>Start Date (optional — leave blank to list immediately)</label><input type="text" id="a-start-date" /></div>
    <div class="form-row"><label>End Date *</label><input type="text" id="a-end-date" /></div>

    <div id="a-error" style="color:#ff6b6b;font-size:0.82rem;display:none;"></div>
    <button class="small-button" onclick="submitCreateAuction()" style="margin-top:4px;">Create Auction</button>
  </div>
</div>

<!-- Edit Auction Modal -->
<div class="auction-modal" id="edit-auction-modal">
  <div class="auction-modal-overlay" onclick="closeEditAuctionModal()"></div>
  <div class="auction-modal-box">
    <button class="auction-modal-close" onclick="closeEditAuctionModal()">&times;</button>
    <div class="auction-modal-title">Edit Auction</div>
    <input type="hidden" id="ae-auction-id" />

    <div class="form-section-label">Item Details</div>
    <div class="form-row"><label>Cardano Asset ID *</label><input type="text" id="ae-asset-id" maxlength="44" placeholder="asset1..." /></div>
    <div id="ae-img-status" style="font-size:0.75rem;margin-top:4px;display:none;"></div>
    <div id="ae-img-preview" style="display:none;margin-top:6px;"><img id="ae-img-preview-img" style="max-width:80px;max-height:80px;border-radius:6px;border:1px solid rgba(255,255,255,0.12);object-fit:cover;" /></div>

    <div class="form-row" id="ae-title-row">
      <label>Title *</label>
      <input type="text" id="ae-title" maxlength="255" placeholder="What are you auctioning?" />
    </div>

    <div class="form-row" id="ae-image-row">
      <label>Image Upload</label>
      <input type="file" id="ae-image" accept="image/png,image/gif,image/jpeg,image/webp" />
      <input type="hidden" id="ae-ipfs-url" />
    </div>

    <div class="form-row" id="ae-qty-row" style="display:none;">
      <label>Quantity *</label>
      <input type="number" id="ae-quantity" min="1" step="1" value="1" style="max-width:140px;" />
    </div>

    <div class="form-section-label" style="margin-top:8px;">Accepted Currencies &amp; Minimum Bids *</div>
    <div class="currency-rows" id="ae-currency-rows"></div>
    <button type="button" onclick="addAuctionEditCurrencyRow()" style="background:rgba(0,200,160,0.08);border:1px solid rgba(0,200,160,0.2);border-radius:6px;color:#00c8a0;padding:6px 14px;font-size:0.8rem;cursor:pointer;align-self:flex-start;">+ Add Currency</button>

    <div class="form-section-label" style="margin-top:8px;">Schedule</div>
    <div class="form-row"><label>Start Date (optional — leave blank to list immediately)</label><input type="text" id="ae-start-date" /></div>
    <div class="form-row"><label>End Date *</label><input type="text" id="ae-end-date" /></div>

    <div id="ae-error" style="color:#ff6b6b;font-size:0.82rem;display:none;"></div>
    <button class="small-button" onclick="submitEditAuction()" style="margin-top:4px;">Save Changes</button>
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
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script type="text/javascript" src="skulliance.js"></script>
<script type="text/javascript">
// ── Project options (for dynamic currency rows) ───────────────────────────────
var allProjects = <?php echo json_encode(array_map(function($p){ return ['id'=>intval($p['id']),'name'=>$p['name'],'currency'=>strtoupper($p['currency']),'divider'=>(float)$p['divider']]; }, $all_projects)); ?>;
// Conversion rate to Diamond: Diamond(7)=1, Core(1-6)=6, Partner(8+,not 15)=12, CARBON(15)=10000
function getConversionRate(pid) {
  pid = parseInt(pid, 10);
  if (pid === 7)  return 1;
  if (pid === 15) return 100;
  if (pid >= 1 && pid <= 6) return 6;
  return 12;
}

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
  var rows = document.getElementById('a-currency-rows');
  if (rows.children.length === 0) addAuctionCurrencyRow();
  document.getElementById('create-auction-modal').classList.add('open');
}
function closeCreateAuctionModal() {
  document.getElementById('create-auction-modal').classList.remove('open');
}

function buildProjectOptions(selectedId, excludeIds) {
  excludeIds = excludeIds || [];
  return allProjects.filter(function(p) {
    return p.id == selectedId || excludeIds.indexOf(p.id) === -1;
  }).map(function(p) {
    var sel = (p.id == selectedId) ? ' selected' : '';
    return '<option value="' + p.id + '"' + sel + '>' + p.name + ' (' + p.currency + ')</option>';
  }).join('');
}

function getUsedProjectIds(containerSel, selectClass) {
  var ids = [];
  document.querySelectorAll(containerSel + ' ' + selectClass).forEach(function(s) {
    var v = parseInt(s.value, 10);
    if (v) ids.push(v);
  });
  return ids;
}

function refreshCurrencySelects(containerSel, selectClass, skipEl) {
  document.querySelectorAll(containerSel + ' ' + selectClass).forEach(function(sel) {
    if (skipEl && sel === skipEl) return;
    var currentVal = parseInt(sel.value, 10) || '';
    var otherUsed = getUsedProjectIds(containerSel, selectClass).filter(function(id) { return id !== currentVal; });
    sel.innerHTML = buildProjectOptions(currentVal, otherUsed);
  });
}

// ── Currency conversion sync ──────────────────────────────────────────────────
function syncBids(sourceRow, containerSel, bidClass, selectClass) {
  var sourcePid = parseInt(sourceRow.querySelector(selectClass).value, 10);
  var sourceVal = parseFloat(sourceRow.querySelector(bidClass).value);
  if (!sourcePid || !sourceVal || sourceVal <= 0) return;
  var baseDiamonds = sourceVal / getConversionRate(sourcePid);
  document.querySelectorAll(containerSel + ' .currency-row').forEach(function(row) {
    if (row === sourceRow) return;
    var targetPid = parseInt(row.querySelector(selectClass).value, 10);
    if (!targetPid) return;
    var computed = Math.ceil(baseDiamonds * getConversionRate(targetPid));
    row.querySelector(bidClass).value = computed > 0 ? computed : '';
  });
}

function attachBidSync(row, containerSel, bidClass, selectClass) {
  row.querySelector(bidClass).addEventListener('input', function() {
    syncBids(row, containerSel, bidClass, selectClass);
  });
  row.querySelector(selectClass).addEventListener('change', function() {
    refreshCurrencySelects(containerSel, selectClass);
    // When project changes, find first filled sibling row and re-sync from it
    var sourceRow = null;
    document.querySelectorAll(containerSel + ' .currency-row').forEach(function(r) {
      if (r === row) return;
      if (!sourceRow && parseFloat(r.querySelector(bidClass).value) > 0) sourceRow = r;
    });
    if (sourceRow) syncBids(sourceRow, containerSel, bidClass, selectClass);
  });
}

function addAuctionCurrencyRow(projectId, minBid) {
  var rows    = document.getElementById('a-currency-rows');
  var usedIds = getUsedProjectIds('#a-currency-rows', '.a-proj-select');
  var avail   = allProjects.filter(function(p) { return usedIds.indexOf(p.id) === -1; });
  if (!projectId && avail.length === 0) return;
  var selId = projectId || (avail[0] ? avail[0].id : '');
  var div   = document.createElement('div');
  div.className = 'currency-row';
  div.innerHTML =
    '<select class="a-proj-select">' + buildProjectOptions(selId, usedIds) + '</select>' +
    '<input type="number" class="a-min-bid" min="1" step="1" placeholder="Min bid" value="' + (minBid || '') + '" />' +
    '<button type="button" class="rm-btn" onclick="removeAuctionCurrencyRow(this)">Remove</button>';
  rows.appendChild(div);
  refreshCurrencySelects('#a-currency-rows', '.a-proj-select', div.querySelector('.a-proj-select'));
  attachBidSync(div, '#a-currency-rows', '.a-min-bid', '.a-proj-select');
}

function removeAuctionCurrencyRow(btn) {
  btn.parentNode.remove();
  refreshCurrencySelects('#a-currency-rows', '.a-proj-select');
}

// ── Asset IPFS auto-lookup ────────────────────────────────────────────────────
function setupAssetLookup(assetInputId, fileInputId, statusId, previewId, previewImgId, ipfsHiddenId, titleInputId, quantityWrapperId, titleWrapperId, imageWrapperId) {
  var timer      = null;
  var assetInput = document.getElementById(assetInputId);
  var fileInput  = document.getElementById(fileInputId);
  var status     = document.getElementById(statusId);
  var preview    = document.getElementById(previewId);
  var previewImg = document.getElementById(previewImgId);
  var ipfsHidden = document.getElementById(ipfsHiddenId);
  var titleInput = titleInputId ? document.getElementById(titleInputId) : null;
  var qtyRow     = quantityWrapperId ? document.getElementById(quantityWrapperId) : null;
  var qtyInput   = qtyRow ? qtyRow.querySelector('input[type=number]') : null;
  var titleWrap  = titleWrapperId ? document.getElementById(titleWrapperId) : null;
  var imageWrap  = imageWrapperId ? document.getElementById(imageWrapperId) : null;

  function setQtyVisible(visible) {
    if (!qtyRow) return;
    qtyRow.style.display = visible ? '' : 'none';
    if (!visible && qtyInput) qtyInput.value = 1;
  }
  function setTitleVisible(v) { if (titleWrap) titleWrap.style.display = v ? '' : 'none'; }
  function setImageVisible(v) { if (imageWrap) imageWrap.style.display = v ? '' : 'none'; }

  assetInput.addEventListener('input', function() {
    clearTimeout(timer);
    var val = this.value.trim();
    ipfsHidden.value      = '';
    preview.style.display = 'none';
    status.style.display  = 'none';
    if (!/^asset1[a-z0-9]{38}$/.test(val)) {
      setQtyVisible(false);
      setTitleVisible(true);
      setImageVisible(true);
      return;
    }
    status.textContent   = 'Looking up asset…';
    status.style.color   = '#8899aa';
    status.style.display = 'block';
    timer = setTimeout(function() {
      $.getJSON('ajax/asset-lookup.php', { asset_id: val }, function(r) {
        if (r.success) {
          if (r.name) {
            if (titleInput && titleInput.value.trim() === '') titleInput.value = r.name;
            setTitleVisible(false);
          } else {
            setTitleVisible(true);
          }
          setQtyVisible(r.is_fungible);
          if (r.ipfs_url) {
            ipfsHidden.value   = r.ipfs_raw || r.ipfs_url;
            previewImg.src     = r.ipfs_url;
            previewImg.onload  = function() { preview.style.display = 'block'; };
            previewImg.onerror = function() { preview.style.display = 'none'; };
            status.textContent = 'Asset found — image and name pre-filled.';
            status.style.color = '#00c8a0';
            setImageVisible(false);
          } else {
            status.textContent = 'Asset found — please upload an image manually.';
            status.style.color = '#8899aa';
            setImageVisible(true);
          }
        } else {
          setQtyVisible(true);
          setTitleVisible(true);
          setImageVisible(true);
          status.textContent = 'Asset not found — please fill in details manually.';
          status.style.color = '#8899aa';
        }
      }).fail(function() {
        setQtyVisible(true);
        setTitleVisible(true);
        setImageVisible(true);
        status.textContent = 'Lookup failed — please fill in details manually.';
        status.style.color = '#8899aa';
      });
    }, 600);
  });

  fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
      ipfsHidden.value      = '';
      preview.style.display = 'none';
      status.textContent    = 'Using uploaded file.';
      status.style.color    = '#8899aa';
      status.style.display  = 'block';
    }
  });
}

var _fpCfg = {
  enableTime:      true,
  altInput:        true,
  altFormat:       'M j, Y — h:i K',
  dateFormat:      'Y-m-d H:i',
  minuteIncrement: 15,
  minDate:         'today',
};

document.addEventListener('DOMContentLoaded', function() {
  setupAssetLookup('a-asset-id',  'a-image',  'a-img-status',  'a-img-preview',  'a-img-preview-img',  'a-ipfs-url',  'a-title',  'a-qty-row',  'a-title-row',  'a-image-row');
  setupAssetLookup('ae-asset-id', 'ae-image', 'ae-img-status', 'ae-img-preview', 'ae-img-preview-img', 'ae-ipfs-url', 'ae-title', 'ae-qty-row', null, null);

  flatpickr('#a-start-date',  Object.assign({}, _fpCfg, { minDate: null }));
  flatpickr('#a-end-date',    _fpCfg);
  flatpickr('#ae-start-date', Object.assign({}, _fpCfg, { minDate: null }));
  flatpickr('#ae-end-date',   _fpCfg);
});

function submitCreateAuction() {
  var err = document.getElementById('a-error');
  err.style.display = 'none';
  var title      = document.getElementById('a-title').value.trim();
  var assetId    = document.getElementById('a-asset-id').value.trim();
  var startDate  = document.getElementById('a-start-date').value;
  var endDate    = document.getElementById('a-end-date').value;
  var imgFile    = document.getElementById('a-image').files[0];

  if (!title) { err.textContent = 'Title is required.'; err.style.display = 'block'; return; }
  if (!endDate) { err.textContent = 'End date is required.'; err.style.display = 'block'; return; }
  if (!assetId) { err.textContent = 'Cardano Asset ID is required.'; err.style.display = 'block'; return; }
  if (!/^asset1[a-z0-9]{38}$/.test(assetId)) { err.textContent = 'Asset ID must be in asset1... fingerprint format (e.g. asset16jt7ekn7...).'; err.style.display = 'block'; return; }

  var projects = [];
  document.querySelectorAll('#a-currency-rows .currency-row').forEach(function(row) {
    var pid  = parseInt(row.querySelector('.a-proj-select').value, 10);
    var mmin = parseInt(row.querySelector('.a-min-bid').value, 10);
    if (pid > 0 && mmin > 0) projects.push({ project_id: pid, minimum_bid: mmin });
  });
  if (projects.length === 0) { err.textContent = 'Add at least one currency with a minimum bid.'; err.style.display = 'block'; return; }

  var fd = new FormData();
  fd.append('title', title);
  fd.append('asset_id', assetId);
  fd.append('projects', JSON.stringify(projects));
  fd.append('start_date', startDate);
  fd.append('end_date', endDate);
  var aQtyRow = document.getElementById('a-qty-row');
  fd.append('quantity', (aQtyRow && aQtyRow.style.display !== 'none') ? (parseInt(document.getElementById('a-quantity').value) || 1) : 1);
  if (imgFile) fd.append('image', imgFile);
  else { var ipfsUrl = document.getElementById('a-ipfs-url').value; if (ipfsUrl) fd.append('ipfs_url', ipfsUrl); }

  $.ajax({
    url: 'ajax/auction-create.php',
    type: 'POST',
    data: fd,
    processData: false,
    contentType: false,
    dataType: 'text',
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
function setMinBidFromSelect() {
  var sel = document.getElementById('bid-project-select');
  if (!sel) return;
  var opt = sel.options[sel.selectedIndex];
  var min = parseInt(opt.dataset.minbid, 10) || 1;
  var inp = document.getElementById('bid-amount');
  if (inp) { inp.value = min; inp.min = min; }
}

function openBidModal(auction_id) {
  document.getElementById('bid-modal').classList.add('open');
  $.get('ajax/auction-detail.php', { id: auction_id }, function(html) {
    document.getElementById('bid-modal-inner').innerHTML = html;
    updateCountdowns();
    var sel = document.getElementById('bid-project-select');
    if (sel) sel.addEventListener('change', setMinBidFromSelect);
    setMinBidFromSelect();
  });
}
function closeBidModal() {
  document.getElementById('bid-modal').classList.remove('open');
}

function submitBid(auction_id) {
  var amt = parseInt(document.getElementById('bid-amount').value, 10);
  var pid = document.getElementById('bid-project-select').value;
  var err = document.getElementById('bid-error');
  err.style.display = 'none';
  if (!amt || amt < 1) { err.textContent = 'Enter a valid bid amount.'; err.style.display = 'block'; return; }
  $.post('ajax/auction-bid.php', { auction_id: auction_id, amount: amt, project_id: pid }, function(res) {
    var r; try { r = JSON.parse(res); } catch(e) { err.textContent = 'Server error: ' + String(res).replace(/<[^>]+>/g,'').trim().slice(0,200); err.style.display = 'block'; return; }
    if (r.success) { openBidModal(auction_id); }
    else { err.textContent = r.message || 'Bid failed.'; err.style.display = 'block'; }
  }, 'text');
}

// ── Edit Auction Modal ────────────────────────────────────────────────────────
function openEditAuctionModal(id) {
  $.getJSON('ajax/auction-edit-load.php', { id: id }, function(r) {
    if (!r.success) { openNotify(r.message || 'Could not load auction data.'); return; }
    document.getElementById('ae-auction-id').value  = id;
    document.getElementById('ae-title').value        = r.title || '';
    document.getElementById('ae-asset-id').value     = r.asset_id || '';
    var fpStart = document.getElementById('ae-start-date')._flatpickr;
    var fpEnd   = document.getElementById('ae-end-date')._flatpickr;
    if (r.start_date) fpStart.setDate(r.start_date, false); else fpStart.clear();
    if (r.end_date)   fpEnd.setDate(r.end_date, false);     else fpEnd.clear();
    document.getElementById('ae-image').value        = '';
    var rows = document.getElementById('ae-currency-rows');
    rows.innerHTML = '';
    (r.projects || []).forEach(function(p) { addAuctionEditCurrencyRow(p.project_id, p.minimum_bid); });
    if (rows.children.length === 0) addAuctionEditCurrencyRow();
    var aeQr = document.getElementById('ae-qty-row');
    var qty  = parseInt(r.quantity) || 1;
    if (aeQr) { aeQr.style.display = qty > 1 ? '' : 'none'; document.getElementById('ae-quantity').value = qty > 1 ? qty : 1; }
    document.getElementById('ae-error').style.display = 'none';
    document.getElementById('edit-auction-modal').classList.add('open');
  });
}
function closeEditAuctionModal() {
  document.getElementById('edit-auction-modal').classList.remove('open');
}

function addAuctionEditCurrencyRow(projectId, minBid) {
  var rows    = document.getElementById('ae-currency-rows');
  var usedIds = getUsedProjectIds('#ae-currency-rows', '.ae-proj-select');
  var avail   = allProjects.filter(function(p) { return usedIds.indexOf(p.id) === -1; });
  if (!projectId && avail.length === 0) return;
  var selId = projectId || (avail[0] ? avail[0].id : '');
  var div   = document.createElement('div');
  div.className = 'currency-row';
  div.innerHTML =
    '<select class="ae-proj-select">' + buildProjectOptions(selId, usedIds) + '</select>' +
    '<input type="number" class="ae-min-bid" min="1" step="1" placeholder="Min bid" value="' + (minBid || '') + '" />' +
    '<button type="button" class="rm-btn" onclick="removeAuctionEditCurrencyRow(this)">Remove</button>';
  rows.appendChild(div);
  refreshCurrencySelects('#ae-currency-rows', '.ae-proj-select', div.querySelector('.ae-proj-select'));
  attachBidSync(div, '#ae-currency-rows', '.ae-min-bid', '.ae-proj-select');
}

function removeAuctionEditCurrencyRow(btn) {
  btn.parentNode.remove();
  refreshCurrencySelects('#ae-currency-rows', '.ae-proj-select');
}

function submitEditAuction() {
  var err = document.getElementById('ae-error');
  err.style.display = 'none';
  var auctionId  = document.getElementById('ae-auction-id').value;
  var title      = document.getElementById('ae-title').value.trim();
  var assetId    = document.getElementById('ae-asset-id').value.trim();
  var startDate  = document.getElementById('ae-start-date').value;
  var endDate    = document.getElementById('ae-end-date').value;
  var imgFile    = document.getElementById('ae-image').files[0];

  if (!title)   { err.textContent = 'Title is required.'; err.style.display = 'block'; return; }
  if (!endDate) { err.textContent = 'End date is required.'; err.style.display = 'block'; return; }
  if (!assetId) { err.textContent = 'Cardano Asset ID is required.'; err.style.display = 'block'; return; }
  if (!/^asset1[a-z0-9]{38}$/.test(assetId)) { err.textContent = 'Asset ID must be in asset1... fingerprint format.'; err.style.display = 'block'; return; }

  var projects = [];
  document.querySelectorAll('#ae-currency-rows .currency-row').forEach(function(row) {
    var pid  = parseInt(row.querySelector('.ae-proj-select').value, 10);
    var mmin = parseInt(row.querySelector('.ae-min-bid').value, 10);
    if (pid > 0 && mmin > 0) projects.push({ project_id: pid, minimum_bid: mmin });
  });
  if (projects.length === 0) { err.textContent = 'Add at least one currency with a minimum bid.'; err.style.display = 'block'; return; }

  var fd = new FormData();
  fd.append('auction_id', auctionId);
  fd.append('title', title);
  fd.append('asset_id', assetId);
  fd.append('projects', JSON.stringify(projects));
  fd.append('start_date', startDate);
  fd.append('end_date', endDate);
  var aeQtyRow = document.getElementById('ae-qty-row');
  fd.append('quantity', (aeQtyRow && aeQtyRow.style.display !== 'none') ? (parseInt(document.getElementById('ae-quantity').value) || 1) : 1);
  if (imgFile) fd.append('image', imgFile);
  else { var ipfsUrl = document.getElementById('ae-ipfs-url').value; if (ipfsUrl) fd.append('ipfs_url', ipfsUrl); }

  $.ajax({
    url: 'ajax/auction-edit.php',
    type: 'POST',
    data: fd,
    processData: false,
    contentType: false,
    dataType: 'text',
    success: function(res) {
      try { var r = JSON.parse(res); } catch(e) { err.textContent = 'Unexpected error.'; err.style.display = 'block'; return; }
      if (r.success) { location.reload(); }
      else { err.textContent = r.message || 'Error saving auction.'; err.style.display = 'block'; }
    },
    error: function() { err.textContent = 'Server error.'; err.style.display = 'block'; }
  });
}

function cancelAuction(auction_id) {
  openConfirm('Cancel this auction? Any current bid will be refunded.', function() {
    $.post('ajax/auction-cancel.php', { auction_id: auction_id }, function(res) {
      var r; try { r = JSON.parse(res); } catch(e) { openNotify('Server error: ' + String(res).replace(/<[^>]+>/g,'').trim().slice(0,200)); return; }
      if (r.success) { location.reload(); }
      else { openNotify(r.message || 'Could not cancel auction.'); }
    }, 'text');
  });
}
</script>
</html>
